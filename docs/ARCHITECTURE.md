# Architecture — Nowo RedsysPhp

RedsysPhp is a framework-free **infrastructure library** (REQ-ARCH-001, class *Infrastructure*). It has no entities,
no persistence, no events and no configuration of its own: everything is a plain PHP object built by the caller. It
depends only on `psr/log` and the PHP `curl`, `json` and `openssl` extensions; there is no Symfony, Doctrine or HTTP
foundation type in its public API.

## Table of contents

- [Public surface](#public-surface)
- [Ports and extension points (SPI)](#ports-and-extension-points-spi)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)
- [Worker mode](#worker-mode)

## Public surface

| Kind | Types |
| --- | --- |
| Ports (interfaces) | `Rest\RestClientInterface`, `Signature\SignerInterface`, `Http\HttpClient` |
| Default adapters | `Rest\RestClient`, `Signature\Signer`, `Http\CurlHttpClient`, `Http\Psr18HttpClient` |
| Value objects | `Merchant`, `MerchantParameters`, `SignedPayload`, `Notification`, `ResponseOutcome`, `Rest\RestResponse`, `Http\HttpResponse` |
| Enums | `Environment`, `SignatureVersion`, `Currency`, `TransactionType`, `CofType`, `ScaExemption`, `DirectPayment` |
| Helpers | `RedirectForm` (static, returns an HTML string), `Redaction`, `Encoding\Base64Url`, `Signature\SignatureCheck` (internal, since 1.4.0) |
| Exceptions | `Exception\RedsysException` and its subclasses `SignatureInvalidException`, `InvalidResponseException`, `TransportException` |

## Ports and extension points (SPI)

Every replaceable piece is an interface with a default implementation. Type your own code against the interface.

| Port | Default | Replace it to… | How |
| --- | --- | --- | --- |
| `Http\HttpClient` (`postJson()`) | `CurlHttpClient`; `Psr18HttpClient` wraps any PSR-18 client | use your HTTP stack, add timeouts / proxies, fake the network in tests | pass it to `new RestClient($merchant, $http)` |
| `Rest\RestClientInterface` (`init()`, `treat()`) (since 1.4.0) | `RestClient` | decorate the REST calls (retry, metrics, circuit breaker) or fake them in use-case tests | type your adapter against the interface and wire the implementation you want |
| `Signature\SignerInterface` (`version()`, `sign()`, `verify()`) (since 1.4.0) | `Signer` (HMAC_SHA512_V2 / V1, HMAC_SHA256_V1) | sign with a key kept in an HSM or a KMS | `new Merchant(…, $signatureVersion, $customSigner)`; its `version()` must equal the merchant's version |

`Merchant::signatureSigner()` returns the signer the library uses everywhere it signs or verifies
(`SignedPayload::from()`, `Notification::fromRequest()`, `RestResponse::fromHttp()`): the custom signer when one was
passed, otherwise the built-in `Signer`. `Merchant::signer()` keeps returning the built-in `Signer` (unchanged since
1.0). The merchant's configured `SignatureVersion` stays the only accepted algorithm: a received
`Ds_SignatureVersion` that differs is rejected before the signer is asked (no downgrade).

The library only calls `sign()` on that signer. To check a received signature it recomputes it with `sign()` and
compares both with `hash_equals()` itself (standard or URL-safe Base64, padding optional), so a custom signer whose
`verify()` is wrong cannot make `Notification` or `RestResponse` accept a forged payload. `verify()` stays on the
interface for hosts that check signatures themselves.

`RedirectForm` stays a static helper: with a strict CSP render `SignedPayload::toArray()` as hidden fields in your
own template instead.

## Standard Symfony and DDD hosts

**Standard Symfony host.** Register the library classes as services and inject them where you need them:

```yaml
# config/services.yaml
services:
  Nowo\Redsys\Merchant:
    arguments:
      $merchantCode: '%env(REDSYS_MERCHANT_CODE)%'
      $terminal: '%env(REDSYS_TERMINAL)%'
      $secretKey: '%env(REDSYS_SECRET_KEY)%'
      $environment: !php/enum Nowo\Redsys\Environment::Live

  Nowo\Redsys\Http\HttpClient:
    class: Nowo\Redsys\Http\Psr18HttpClient   # or Nowo\Redsys\Http\CurlHttpClient

  Nowo\Redsys\Rest\RestClient: ~
  Nowo\Redsys\Rest\RestClientInterface: '@Nowo\Redsys\Rest\RestClient'
```

**DDD / hexagonal host.** Keep your own ports in the application layer (for example `CardPaymentGateway` and
`PaymentNotificationVerifier`) with your own types (order id, money in minor units). Implement them in the
infrastructure layer with this library, typed against `RestClientInterface` and `Merchant`, and map the library's
exceptions to your own errors. Your domain never sees a `Nowo\Redsys` type:

```php
namespace App\Infrastructure\Payment;

use App\Application\Payment\CardPaymentGateway;     // your port
use App\Application\Payment\GatewayUnavailable;     // your error
use App\Domain\Payment\OrderId;
use Nowo\Redsys\Exception\TransportException;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Rest\RestClientInterface;
use Nowo\Redsys\TransactionType;

final readonly class RedsysCardPaymentGateway implements CardPaymentGateway
{
    public function __construct(private Merchant $merchant, private RestClientInterface $rest) {}

    public function refund(OrderId $order, int $amountMinor): bool
    {
        $params = MerchantParameters::create()
            ->forMerchant($this->merchant)
            ->order($order->toString())
            ->amount($amountMinor)
            ->transactionType(TransactionType::Refund);

        try {
            $response = $this->rest->treat($params);
        } catch (TransportException $e) {
            throw new GatewayUnavailable($e->getMessage(), previous: $e);
        }

        return $response->isSignatureValid() && $response->isSuccessfulForTransactionType(TransactionType::Refund);
    }
}
```

In tests of that adapter, implement `RestClientInterface` (or `Http\HttpClient`) with a fake. The notification
side is in [USAGE — Using it from a DDD / hexagonal application](USAGE.md#using-it-from-a-ddd--hexagonal-application).

## Worker mode

All objects are immutable or stateless; nothing is cached in static properties. One `Merchant` and one
`RestClient` can be shared by every request of a FrankenPHP worker (kernel reset off). See
[FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
