# Usage — Nowo RedsysPhp 1.x

Clean-room SDK. Namespace `Nowo\Redsys\`. Default signature: `HMAC_SHA512_V2`.

Sandbox FUC / key / test cards: [SANDBOX.md](SANDBOX.md).

## Table of contents

- [Merchant](#merchant)
- [Redirect payment](#redirect-payment)
- [Notification](#notification)
- [REST SIS](#rest-sis)
- [Stored card (COF) and merchant-initiated (MIT) payments](#stored-card-cof-and-merchant-initiated-mit-payments)
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

`Notification::fromRequest($request->request->all(), $merchant)` (or query fields on browser return) verifies the signature and exposes `isAuthorized()` (`Ds_Response` in `0000`–`0099`, payments and pre-authorizations). For refunds, confirmations and cancellations use `isSuccessfulForTransactionType()`: it accepts `0900` for refund (3) / confirmation (2), `0400` for cancellation (9), and falls back to `0000`–`0099` for other types; pass the `TransactionType` you sent (recommended: otherwise a signed refund or cancellation notification of a payment order reads as successful) to check against the operation you requested instead of the notification's `Ds_TransactionType` (exposed by `transactionType()`). Prefer the Symfony `Request` over `$_POST` / `$_GET` under FrankenPHP worker mode. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## REST SIS

`RestClient` + `CurlHttpClient` (connect/timeout defaults 5s/30s; TLS verification, HTTPS only and no redirects set explicitly) call `iniciaPeticionREST` / `trataPeticionREST`. Inject a fake `HttpClient` in tests.

A `RestResponse` is signature-checked when Redsys returns the signed trio. **Only the verified view is trusted**:

- `isSignatureValid()` / `assertSignatureValid()` (throws `SignatureInvalidException`);
- `verifiedParameters()`: the decoded fields, or null when the signature is missing or does not verify;
- `responseCode()`, `transactionType()`, `isAuthorized()`, `isSuccessfulForTransactionType()`: the same rules as `Notification`, on verified fields only (null / false otherwise).

The public `$decodedParameters` is kept for diagnostics and backward compatibility: it is filled even when the signature fails (raw JSON or unverified decoded fields), so never fulfil an order from it. `$errorCode` (`SISxxxx`) comes from unsigned error bodies.

## Stored card (COF) and merchant-initiated (MIT) payments

Since 1.3.0 `MerchantParameters` has typed helpers for credential-on-file payments. Each one produces exactly the same encoded parameters as the raw `with()` call shown next to it:

| Helper | Raw equivalent |
| --- | --- |
| `identifier('REQUIRED')` / `identifier($token)` | `with('DS_MERCHANT_IDENTIFIER', 'REQUIRED' / $token)` |
| `cofInit()` / `cofInit(false)` | `with('DS_MERCHANT_COF_INI', 'S' / 'N')` |
| `cofType(CofType::Recurring)` | `with('DS_MERCHANT_COF_TYPE', 'R')` |
| `excepSca(ScaExemption::MerchantInitiated)` | `with('DS_MERCHANT_EXCEP_SCA', 'MIT')` |
| `directPayment()` / `directPayment(DirectPayment::Moto)` | `with('DS_MERCHANT_DIRECTPAYMENT', 'true' / 'moto')` |
| `cofTxnId($txnId)` | `with('DS_MERCHANT_COF_TXNID', $txnId)` |

`CofType`: `I` installments, `R` recurring, `H` reauthorization, `E` resubmission, `D` delayed, `M` incremental, `N` no show, `C` other (Redsys's default). `ScaExemption`: `MIT`, `LWV`, `TRA`, `COR`, `ATD` (all but MIT need the acquirer's activation). Invalid values throw `RedsysException`.

**1. Initial customer-initiated payment that stores the card.** The customer authenticates (3-D Secure) as usual; Redsys returns a card reference and the COF transaction id in the signed notification (redirect) or REST response.

```php
use Nowo\Redsys\CofType;
use Nowo\Redsys\Currency;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\RedirectForm;
use Nowo\Redsys\TransactionType;

$params = MerchantParameters::create()
    ->forMerchant($merchant)
    ->amount(999)                      // 9.99 EUR, minor units
    ->currency(Currency::Eur)
    ->order('2611120001')
    ->transactionType(TransactionType::Authorization)
    ->merchantUrl('https://shop.example/redsys/notify')
    ->identifier(MerchantParameters::IDENTIFIER_REQUIRED) // ask Redsys for a card reference
    ->cofInit()                                           // first COF operation: store the credentials
    ->cofType(CofType::Recurring);

$html = RedirectForm::forMerchant($merchant, $params);

// Notification endpoint:
$notification = Notification::fromRequest($request->request->all(), $merchant); // throws unless the signature verifies
if ($notification->isSuccessfulForTransactionType(TransactionType::Authorization)) {
    $token = $notification->merchantIdentifier(); // Ds_Merchant_Identifier
    $txnId = $notification->cofTxnId();           // Ds_Merchant_Cof_Txnid
    // store both like a credential (encrypted at rest), next to the subscription; never log them
}
```

**2. Later merchant-initiated recurring charge (MIT), over REST, without the customer.**

```php
use Nowo\Redsys\ScaExemption;

$params = MerchantParameters::create()
    ->forMerchant($merchant)
    ->amount(999)
    ->currency(Currency::Eur)
    ->order('2612120001')               // a new order number per charge
    ->transactionType(TransactionType::Authorization)
    ->identifier($token)                // the stored reference
    ->cofTxnId($txnId)                  // the id of the initial COF operation
    ->cofType(CofType::Recurring)
    ->excepSca(ScaExemption::MerchantInitiated)
    ->directPayment();                  // DS_MERCHANT_DIRECTPAYMENT=true: no customer authentication

$response = $restClient->treat($params);
$response->assertSignatureValid();
if ($response->isSuccessfulForTransactionType(TransactionType::Authorization)) {
    // charged; $response->cofTxnId() / merchantIdentifier() are read from verified fields only (null otherwise)
}
```

`Notification::merchantIdentifier()` / `cofTxnId()` and `RestResponse::merchantIdentifier()` / `cofTxnId()` read `Ds_Merchant_Identifier` / `Ds_Merchant_Cof_Txnid` (matched ignoring case) from verified fields only, and return null when two spellings carry different values; never take them from `RestResponse::$decodedParameters`. Each helper first removes any key that differs from its own only in case (a `with('Ds_Merchant_Cof_Txnid', …)` set earlier is replaced, not sent next to it); `with()` itself is unchanged.

**Redaction covers `var_dump()` / `print_r()` only** (`__debugInfo()`): those show the token and the txn id as `[redacted]`. `json_encode()` / `jsonSerialize()`, `var_export()`, `serialize()`, `(array)` casts, `toArray()`, `encode()` and the public `RestResponse::$rawBody` / `$decodedParameters` still carry the raw values: never log or dump those. `RestClient` never logs them. Redsys reference: [tokenización MIT](https://pagosonline.redsys.es/desarrolladores-inicio/documentacion-funcionalidades-avanzadas/tokenizacion/tokenizacion-mit/) and [parámetros de entrada y salida](https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/parametros-de-entrada-y-salida/).

## Extra DS_MERCHANT_* fields

Use `MerchantParameters::with('DS_MERCHANT_…', $value)` for advanced options (Bizum `DS_MERCHANT_PAYMETHODS=z`, EMV3DS, etc.) as documented by Redsys.

## Errors

Every exception extends `Nowo\Redsys\Exception\RedsysException`, so `catch (RedsysException)` catches them all. Since 1.2.0 the cause has its own type:

| Exception | Thrown when | What to do |
| --- | --- | --- |
| `SignatureInvalidException` | the notification signature does not verify, or `Ds_SignatureVersion` is not the merchant's (downgrade attempt); `RestResponse::assertSignatureValid()` | forged or tampered: log a security event, answer 4xx, never retry |
| `InvalidResponseException` | a notification / REST payload misses `Ds_MerchantParameters`, `Ds_Signature`, `Ds_SignatureVersion` or `Ds_Order`, has an unknown version, or is not valid Base64 / JSON | malformed: answer 4xx, nothing in it is trusted |
| `TransportException` | the HTTP call failed (`CurlHttpClient`: connection, DNS, TLS, timeout, non-HTTPS URL; `Psr18HttpClient`: any PSR-18 client exception) | network failures and timeouts are retryable (for `treat` check the order's state first); a non-HTTPS URL or a TLS verification failure is a configuration error. A redirect is not followed and comes back as an unsigned `RestResponse` (`isSignatureValid()` false), not as an exception |
| `RedsysException` (base) | the caller's own input: empty merchant code / key, missing `DS_MERCHANT_ORDER`, header values with CR / LF | fix the calling code |

## Using it from a DDD / hexagonal application

RedsysPhp is an **infrastructure client**: no framework, no persistence, no global state. Signing and notification verification are pure functions of `Merchant` + `MerchantParameters`; REST calls go through the `HttpClient` port.

- Keep your own port in the application layer (for example `CardPaymentGateway` and `PaymentNotificationVerifier`) and your own types (order id, money in minor units). Implement them in infrastructure with this library.
- Redirect flow: build `MerchantParameters` in the adapter and either return `RedirectForm::forMerchant()` HTML or, with a strict CSP, render `SignedPayload::from($merchant, $params)->toArray()` as hidden fields in your own template.
- Notification: call `Notification::fromRequest($fields, $merchant)` in the controller/adapter, then map `parameters()`, `responseCode()` and `isSuccessfulForTransactionType($typeYouSent)` to your own domain event (for example `PaymentAuthorized(orderId)`). **Always pass the transaction type you sent**: without it the type comes from the signed payload, so a genuine refund (3 / `0900`) or cancellation (9 / `0400`) notification of the same order would read as a successful payment. Check that the order, amount, currency and merchant code match what you sent. Map `SignatureInvalidException` to a security event and `InvalidResponseException` to a rejected request (see [Errors](#errors)).
- REST: `RestClient::init()` / `treat()`; call `assertSignatureValid()` (or check `isSignatureValid()`) and read `isSuccessfulForTransactionType($typeYouSent)` / `verifiedParameters()`, never the raw `$decodedParameters`. Map `TransportException` to your own "gateway unavailable" error (retry network failures and timeouts; for `treat` check the order's state first).
- Tests: implement `Nowo\Redsys\Http\HttpClient` with a fake, or inject `Psr18HttpClient` with your PSR-18 client. Type your adapters against `Nowo\Redsys\Rest\RestClientInterface` (since 1.4.0) to fake the REST calls themselves.
- Ports, extension points and the Symfony / DDD wiring: [ARCHITECTURE.md](ARCHITECTURE.md).

```php
namespace App\Infrastructure\Payment;

use App\Application\Payment\ExpectedPayment;               // your DTO: what you sent for this order
use App\Application\Payment\PaymentNotificationVerifier;   // your port
use App\Application\Payment\VerifiedPaymentNotification;   // your DTO
use App\Application\Payment\ForgedPaymentNotification;
use App\Application\Payment\InvalidPaymentNotification;
use Nowo\Redsys\Exception\InvalidResponseException;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\Notification;
use Nowo\Redsys\TransactionType;

final readonly class RedsysNotificationVerifier implements PaymentNotificationVerifier
{
    public function __construct(private Merchant $merchant) {}

    /**
     * @param array<string, mixed> $fields   POST (or query) fields of the Redsys call
     * @param ExpectedPayment      $expected order, amount (minor units), currency, merchant code and type you sent
     */
    public function verify(array $fields, ExpectedPayment $expected): VerifiedPaymentNotification
    {
        try {
            $notification = Notification::fromRequest($fields, $this->merchant);
        } catch (SignatureInvalidException $e) {
            throw new ForgedPaymentNotification($e->getMessage(), previous: $e);
        } catch (InvalidResponseException $e) {
            throw new InvalidPaymentNotification($e->getMessage(), previous: $e);
        }
        $p = $notification->parameters()->toArray();

        // Signed, but is it the operation you asked for? Compare every field you rely on.
        if ((string) ($p['Ds_Order'] ?? '') !== $expected->orderId
            || (string) ($p['Ds_Amount'] ?? '') !== (string) $expected->amountMinor
            || (string) ($p['Ds_Currency'] ?? '') !== $expected->currencyNumeric        // e.g. "978"
            || (string) ($p['Ds_MerchantCode'] ?? '') !== $this->merchant->merchantCode()) {
            throw new InvalidPaymentNotification('Notification does not match the expected payment.');
        }

        return new VerifiedPaymentNotification(
            orderId: $expected->orderId,
            amountMinor: $expected->amountMinor,
            // The type you sent (here a payment), never the notification's own Ds_TransactionType:
            // a refund (0900) or cancellation (0400) of the same order is not a successful payment.
            successful: $notification->isSuccessfulForTransactionType(TransactionType::Authorization),
            responseCode: $notification->responseCode(),
        );
    }
}
```

Known limitations:

- `RestResponse::$decodedParameters` stays public and is filled for unverified responses (backward compatibility); use `verifiedParameters()`.
- `RedirectForm` uses an inline script; with a strict CSP render the hidden fields yourself.
- `RedirectForm` is a static helper without an interface. `RestClient` and `Signer` implement `Rest\RestClientInterface` and `Signature\SignerInterface` since 1.4.0 (a custom signer goes into the `Merchant` constructor); see [ARCHITECTURE.md](ARCHITECTURE.md#ports-and-extension-points-spi).
- `MerchantParameters::amount()` accepts any string; pass minor units (cents) from your own money type.
