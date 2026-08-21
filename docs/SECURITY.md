# Security

## Reporting

See [.github/SECURITY.md](../.github/SECURITY.md) for private disclosure.

## Checklist (REQ-SEC-002)

1. Store the terminal signature key outside VCS.
2. Always verify notification / REST signatures via `Notification::fromRequest` / `RestResponse::$signatureValid` before fulfilling orders.
3. Never log PAN, CVV, full notify bodies with card data, or the raw secret key.
4. Use HTTPS endpoints only (`Environment` URLs are HTTPS).
5. Keep cURL TLS peer verification enabled (default OpenSSL trust store).
6. Use connect/request timeouts on REST (`CurlHttpClient` defaults 5s / 30s).
7. Prefer `HMAC_SHA512_V2` unless the terminal requires another published version.
8. Treat `Ds_Response` `0000`–`0099` as authorized only after signature OK.
9. Do not reintroduce proprietary PHPL_* sources into this repository.
10. Pin Composer releases (`^1.0`) in production apps.
11. Rotate the terminal key if compromise is suspected (Redsys / bank process).
12. Run `make release-check` before tagging.

## Threat notes

- Signature bypass → financial fraud: always `hash_equals` via `Signer::verify`.
- Key leak across workers: this SDK is instance-based (`Merchant` readonly); no process-wide static credentials.
