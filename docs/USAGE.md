# Usage — Nowo RedsysPhp 1.x

Clean-room SDK. Namespace `Nowo\Redsys\`. Default signature: `HMAC_SHA512_V2`.

Sandbox FUC / key / test cards: [SANDBOX.md](SANDBOX.md).

## Table of contents

- [Merchant](#merchant)
- [Redirect payment](#redirect-payment)
- [Notification](#notification)
- [REST SIS](#rest-sis)
- [Extra DS_MERCHANT_* fields](#extra-ds_merchant_-fields)
- [Errors](#errors)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)

## Merchant

```php
use Nowo\Redsys\Environment;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\SignatureVersion;

// Public sandbox merchant — see SANDBOX.md. Use Environment::Live + bank credentials in production.
$merchant = new Merchant(
    '999008881',
    '001',
    getenv('REDSYS_SECRET_KEY') ?: 'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
    Environment::Test,
    SignatureVersion::HmacSha512V2,
);
```

## Redirect payment

Build `MerchantParameters`, then `RedirectForm::forMerchant()` → HTML string. Emit via your HTTP layer; do not `echo`/`exit` inside workers.

## Notification

`Notification::fromRequest($request->request->all(), $merchant)` (or query fields on browser return) verifies the signature and exposes `isAuthorized()` (`Ds_Response` in `0000`–`0099`, payments and pre-authorizations). For refunds, confirmations and cancellations use `isSuccessfulForTransactionType()`: it accepts `0900` for refund (3) / confirmation (2), `0400` for cancellation (9), and falls back to `0000`–`0099` for other types; pass a `TransactionType` to check against the operation you requested instead of the notification's `Ds_TransactionType` (exposed by `transactionType()`). Prefer the Symfony `Request` over `$_POST` / `$_GET` under FrankenPHP worker mode. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## REST SIS

`RestClient` + `CurlHttpClient` (connect/timeout defaults 5s/30s; TLS verification, HTTPS only and no redirects set explicitly) call `iniciaPeticionREST` / `trataPeticionREST`. Inject a fake `HttpClient` in tests.

A `RestResponse` is signature-checked when Redsys returns the signed trio. **Only the verified view is trusted**:

- `isSignatureValid()` / `assertSignatureValid()` (throws `SignatureInvalidException`);
- `verifiedParameters()`: the decoded fields, or null when the signature is missing or does not verify;
- `responseCode()`, `transactionType()`, `isAuthorized()`, `isSuccessfulForTransactionType()`: the same rules as `Notification`, on verified fields only (null / false otherwise).

The public `$decodedParameters` is kept for diagnostics and backward compatibility: it is filled even when the signature fails (raw JSON or unverified decoded fields), so never fulfil an order from it. `$errorCode` (`SISxxxx`) comes from unsigned error bodies.

## Extra DS_MERCHANT_* fields

Use `MerchantParameters::with('DS_MERCHANT_…', $value)` for advanced options (Bizum `DS_MERCHANT_PAYMETHODS=z`, EMV3DS, etc.) as documented by Redsys.

## Errors

Every exception extends `Nowo\Redsys\Exception\RedsysException`, so `catch (RedsysException)` catches them all. Since 1.2.0 the cause has its own type:

| Exception | Thrown when | What to do |
| --- | --- | --- |
| `SignatureInvalidException` | the notification signature does not verify, or `Ds_SignatureVersion` is not the merchant's (downgrade attempt); `RestResponse::assertSignatureValid()` | forged or tampered: log a security event, answer 4xx, never retry |
| `InvalidResponseException` | a notification / REST payload misses `Ds_MerchantParameters`, `Ds_Signature`, `Ds_SignatureVersion` or `Ds_Order`, has an unknown version, or is not valid Base64 / JSON | malformed: answer 4xx, nothing in it is trusted |
| `TransportException` | the HTTP call failed (`CurlHttpClient`: connection, DNS, TLS, timeout, non-HTTPS URL; `Psr18HttpClient`: any PSR-18 client exception) | retryable; for `treat` check the order's state first |
| `RedsysException` (base) | the caller's own input: empty merchant code / key, missing `DS_MERCHANT_ORDER`, header values with CR / LF | fix the calling code |

## Using it from a DDD / hexagonal application

RedsysPhp is an **infrastructure client**: no framework, no persistence, no global state. Signing and notification verification are pure functions of `Merchant` + `MerchantParameters`; REST calls go through the `HttpClient` port.

- Keep your own port in the application layer (for example `CardPaymentGateway` and `PaymentNotificationVerifier`) and your own types (order id, money in minor units). Implement them in infrastructure with this library.
- Redirect flow: build `MerchantParameters` in the adapter and either return `RedirectForm::forMerchant()` HTML or, with a strict CSP, render `SignedPayload::from($merchant, $params)->toArray()` as hidden fields in your own template.
- Notification: call `Notification::fromRequest($fields, $merchant)` in the controller/adapter, then map `parameters()`, `responseCode()` and `isSuccessfulForTransactionType()` to your own domain event (for example `PaymentAuthorized(orderId)`). Check that the order and amount match what you sent. Map `SignatureInvalidException` to a security event and `InvalidResponseException` to a rejected request (see [Errors](#errors)).
- REST: `RestClient::init()` / `treat()`; call `assertSignatureValid()` (or check `isSignatureValid()`) and read `isAuthorized()` / `verifiedParameters()`, never the raw `$decodedParameters`. Map `TransportException` to your own "gateway unavailable" (retryable) error.
- Tests: implement `Nowo\Redsys\Http\HttpClient` with a fake, or inject `Psr18HttpClient` with your PSR-18 client.

```php
namespace App\Infrastructure\Payment;

use App\Application\Payment\PaymentNotificationVerifier;   // your port
use App\Application\Payment\VerifiedPaymentNotification;   // your DTO
use App\Application\Payment\ForgedPaymentNotification;
use App\Application\Payment\InvalidPaymentNotification;
use Nowo\Redsys\Exception\InvalidResponseException;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\Notification;

final readonly class RedsysNotificationVerifier implements PaymentNotificationVerifier
{
    public function __construct(private Merchant $merchant) {}

    /** @param array<string, mixed> $fields POST (or query) fields of the Redsys call */
    public function verify(array $fields): VerifiedPaymentNotification
    {
        try {
            $notification = Notification::fromRequest($fields, $this->merchant);
        } catch (SignatureInvalidException $e) {
            throw new ForgedPaymentNotification($e->getMessage(), previous: $e);
        } catch (InvalidResponseException $e) {
            throw new InvalidPaymentNotification($e->getMessage(), previous: $e);
        }
        $p = $notification->parameters()->toArray();

        return new VerifiedPaymentNotification(
            orderId: (string) ($p['Ds_Order'] ?? ''),
            amountMinor: (int) ($p['Ds_Amount'] ?? 0),
            successful: $notification->isSuccessfulForTransactionType(),
            responseCode: $notification->responseCode(),
        );
    }
}
```

Known limitations:

- `RestResponse::$decodedParameters` stays public and is filled for unverified responses (backward compatibility); use `verifiedParameters()`.
- `RedirectForm` uses an inline script; with a strict CSP render the hidden fields yourself.
- `RestClient`, `Signer` and `RedirectForm` are final without interfaces; the `HttpClient` port is the test seam.
- `MerchantParameters::amount()` accepts any string; pass minor units (cents) from your own money type.
