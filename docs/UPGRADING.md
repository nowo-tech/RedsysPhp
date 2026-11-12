# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.3.0](#to-130)
- [To 1.2.0](#to-120)
- [To 1.1.0](#to-110)
- [To 1.0.10](#to-1010)
- [To 1.0.9](#to-109)
- [To 1.0.8](#to-108)
- [To 1.0.7](#to-107)
- [To 1.0.6](#to-106)
- [To 1.0.5](#to-105)
- [To 1.0.4](#to-104)
- [From 1.0.2 to 1.0.3](#from-102-to-103)
- [From 1.0.1 to 1.0.2](#from-101-to-102)
- [To 1.0.0 (clean-room)](#to-100-clean-room)

## Unreleased

## To 1.3.0

From **1.2.x**:

```bash
composer update nowo-tech/redsys-php
```

No breaking change: new methods and enums only.

- **COF / MIT helpers.** Code that sets `DS_MERCHANT_IDENTIFIER`, `DS_MERCHANT_COF_INI`, `DS_MERCHANT_COF_TYPE`, `DS_MERCHANT_EXCEP_SCA`, `DS_MERCHANT_DIRECTPAYMENT` or `DS_MERCHANT_COF_TXNID` with `with()` may switch to `identifier()`, `cofInit()`, `cofType()`, `excepSca()`, `directPayment()` and `cofTxnId()`: the encoded parameters (and so the signature) are identical. The helpers validate their input (throw `RedsysException`); `with()` still accepts anything. `directPayment('MOTO')` sends the documented lower-case `moto`.
- **Reading the token.** Replace reads of `$response->decodedParameters['Ds_Merchant_Identifier']` / `['Ds_Merchant_Cof_Txnid']` (or of `Notification::parameters()->toArray()`) with `merchantIdentifier()` / `cofTxnId()` on `Notification` or `RestResponse`; on `RestResponse` they return null unless the signature verified.
- **Debug output.** `var_dump()` / `print_r()` of `MerchantParameters`, `Notification` and `RestResponse` now redact the token and the COF txn id and shorten Base64 payloads / raw bodies to their length. Code that parsed that output (it should not) must use `toArray()` / `verifiedParameters()` instead. The redaction is `__debugInfo()` only: `json_encode()` / `jsonSerialize()`, `var_export()`, `serialize()`, `(array)` casts and the public `RestResponse::$rawBody` / `$decodedParameters` still carry the raw token and txn id, so do not log those.
- **Case variants.** The new helpers replace any key that differs from theirs only in case (for example a `Ds_Merchant_Cof_Txnid` set with `with()`), and the readers match the response key ignoring case, returning null when two spellings disagree.

## To 1.2.0

From **1.1.x**:

```bash
composer update nowo-tech/redsys-php
```

No breaking change for code that catches `RedsysException` or reads `RestResponse::$signatureValid`.

- **Exception subtypes.** `RedsysException` is no longer `final`; the library throws `SignatureInvalidException` (bad signature, signature version other than the merchant's), `InvalidResponseException` (missing field, unknown version, invalid Base64 / JSON) and `TransportException` (HTTP failure) — all subclasses of `RedsysException`. Messages are unchanged. Code that compares `$e::class === RedsysException::class` (or logs it and alerts on it) sees the subclass name now; `RestClient`'s error log records the subclass in `exception`.
- **REST outcome.** Replace reads of `$response->decodedParameters['Ds_Response']` with `$response->assertSignatureValid()` + `$response->isAuthorized()` / `isSuccessfulForTransactionType()` / `verifiedParameters()`. `$decodedParameters` still holds unverified data on a failed signature: treat it as diagnostics only.
- **cURL transport.** `CurlHttpClient` now refuses non-HTTPS URLs (`TransportException`), never follows redirects (a 3xx answer comes back as an unsigned `RestResponse`, `isSignatureValid()` false) and always verifies TLS. The Redsys `Environment` URLs are HTTPS and do not redirect; a host that called `CurlHttpClient::postJson()` with a plain-HTTP **target** URL (a local mock, for example) or relied on redirects being followed must use `Psr18HttpClient` with its own client. Proxies are unaffected: an `http://` proxy in front of the HTTPS Redsys URL still works. Header values with CR / LF / NUL throw `RedsysException`.

## To 1.1.0

From **1.0.10**:

```bash
composer update nowo-tech/redsys-php
```

## To 1.0.10

From **1.0.9** — version metadata matches the release

```bash
composer update nowo-tech/redsys-php
```

- No breaking changes. No application upgrade steps.

## To 1.0.9

From **1.0.8** — success check per transaction type (refunds, cancellations); doc fixes.

```bash
composer update nowo-tech/redsys-php
```

- New `Notification::isSuccessfulForTransactionType()` and `Notification::transactionType()`. `isAuthorized()` is unchanged (0000–0099). If you process refund (3), confirmation (2) or cancellation (9) notifications, switch to `isSuccessfulForTransactionType()`: Redsys reports their success as `0900` / `0400`, which `isAuthorized()` treats as failed.

## To 1.0.8

From **1.0.7** — security: signatures verified only with the merchant's configured version.

```bash
composer update nowo-tech/redsys-php
```

- **Behaviour change:** notifications whose `Ds_SignatureVersion` differs from the `Merchant` signature version now throw `RedsysException`, and REST responses report `signatureValid=false`. Configure the version your terminal really uses (the default is `HmacSha512V2`).

## To 1.0.7

From **1.0.6** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/redsys-php
```

- No breaking changes. No application upgrade steps.

## To 1.0.6

From **1.0.5** — dependency updates.

```bash
composer update nowo-tech/redsys-php
```

No breaking changes. No application upgrade steps.

## To 1.0.5

From **1.0.4** — CI/test and maintainers tooling. **No application upgrade steps.**

```bash
composer update nowo-tech/redsys-php
```

## To 1.0.4

From **1.0.3** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/redsys-php
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.0.2 to 1.0.3

No breaking changes. **No application upgrade steps.** FrankenPHP worker audit documented; core types are `final readonly class` (same immutability contract as before); PHPStan worker-strict enabled for maintainers. Package identity constants now report `1.0.3`.

```bash
composer update nowo-tech/redsys-php
```

If you run under FrankenPHP worker with the kernel **not** reset: keep a shared `Merchant` only when credentials are fixed per deployment; pass notification fields from the current Symfony `Request` (not `$_POST` / `$_GET`). See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## From 1.0.1 to 1.0.2

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/redsys-php
```

## To 1.0.0 (clean-room)

There is no supported upgrade path from any pre-1.0 tree that redistributed proprietary PHPL_* sources. Treat **1.0.0** as a new package:

```json
{
  "require": {
    "nowo-tech/redsys-php": "^1.0"
  }
}
```

| Old concept | 1.0.0 |
|-------------|-------|
| `Redsys\` namespace | `Nowo\Redsys\` |
| Composer `replace` of `redsys/redsys-lib` | Removed |
| Proprietary LICENSE | MIT |
| `Redirect::authorisation()` | `RedirectForm::forMerchant()` |
| `Parameters::digest()` | `Notification::fromRequest()` |

Rewrite call sites; do not expect drop-in BC.
