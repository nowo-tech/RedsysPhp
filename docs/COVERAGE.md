# Coverage

PHPUnit Clover gate: **100% Lines** on `src/` (REQ-TEST-003).

## Include

`phpunit.xml.dist` `<source>` includes the entire `src/` tree (`Nowo\Redsys\`).

## Gate

`.scripts/php-coverage-percent.sh` fails the build when PHPUnit **Lines** coverage is below **99%** (REQ-TEST-003).

Current measured Lines coverage: **100%** (244/244 statements).

## Justified ignores

- `CurlHttpClient`: `curl_init()` false and non-string `curl_exec` — `@codeCoverageIgnore` (PHP/runtime failure modes).
- `Signer`: `openssl_encrypt` false branches — `@codeCoverageIgnore`.
- `MerchantParameters::encode` / `SignedPayload::toJson`: `json_encode` false — `@codeCoverageIgnore`.

No silent ignores on protocol happy-path code.
