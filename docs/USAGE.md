# Usage — Nowo RedsysPhp 1.x

Clean-room SDK. Namespace `Nowo\Redsys\`. Default signature: `HMAC_SHA512_V2`.

## Merchant

```php
use Nowo\Redsys\Environment;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\SignatureVersion;

$merchant = new Merchant(
    '999008881',
    '001',
    getenv('REDSYS_SECRET_KEY') ?: '',
    Environment::Live,
    SignatureVersion::HmacSha512V2,
);
```

## Redirect payment

Build `MerchantParameters`, then `RedirectForm::forMerchant()` → HTML string. Emit via your HTTP layer; do not `echo`/`exit` inside workers.

## Notification

`Notification::fromRequest($_POST, $merchant)` verifies the signature and exposes `isAuthorized()` (`Ds_Response` in `0000`–`0099`).

## REST SIS

`RestClient` + `CurlHttpClient` (connect/timeout defaults 5s/30s) call `iniciaPeticionREST` / `trataPeticionREST`. Inject a fake `HttpClient` in tests.

## Extra DS_MERCHANT_* fields

Use `MerchantParameters::with('DS_MERCHANT_…', $value)` for advanced options (Bizum `DS_MERCHANT_PAYMETHODS=z`, EMV3DS, etc.) as documented by Redsys.
