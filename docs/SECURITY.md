# Security

## Table of contents

- [Reporting](#reporting)
- [Attack surface](#attack-surface)
- [Checklist (REQ-SEC-002)](#checklist-req-sec-002)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [Threat notes](#threat-notes)
- [Logging](#logging)
- [Dependencies](#dependencies)

## Reporting

See [.github/SECURITY.md](../.github/SECURITY.md) for private disclosure.

## Attack surface

| Surface | Input trust | Protection |
|---------|-------------|------------|
| Redirect form (`RedirectForm`) | Host-built parameters | Signed with the terminal key; values HTML-escaped in the generated form |
| Notification endpoint (`Notification::fromRequest`) | Untrusted POST from the internet | Signature verified with the merchant's configured version (`hash_equals`), downgrade rejected |
| REST calls (`RestClient`) | Outbound HTTPS to Redsys | TLS peer verification, connect / total timeouts, response signature verified |
| Terminal secret key (`Merchant`) | Host configuration | `#[\SensitiveParameter]`, never logged, instance-scoped |

The library has no HTTP routes, no storage and no CLI of its own; the host owns the endpoints that call it.

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

## Release security checklist (12.4.1)

| Item | Notes |
|------|-------|
| **SECURITY.md** | This document and `.github/SECURITY.md` are current. |
| **`.gitignore` / `.env`** | No `.env` with secrets is tracked (demo uses `.env.example`). |
| **No secrets in repo** | Only Redsys public sandbox keys appear (docs / tests / demo). |
| **Recipe / installer** | None (plain library). |
| **Input / output** | Notifications and REST responses are signature-verified before use. |
| **Dependencies** | `composer audit` clean (runtime deps: PHP extensions + `psr/log`). |
| **Logging** | `RestClient` logs outcome codes only (see [Logging](#logging)). |
| **Cryptography** | HMAC-SHA256/512 + 3DES/AES key diversification per Redsys spec; keys from host config. |
| **Permissions / exposure** | No routes; host endpoints must stay HTTPS-only. |
| **Limits / DoS** | REST timeouts (cURL 5 s connect / 30 s total, or the wrapped PSR-18 client's). |
| **AI security audit (REQ-SEC-004)** | Re-run when signing, notification parsing or the HTTP layer changes; grade recorded in the monorepo `BUNDLES_SECURITY_ANALYSIS.md`. |

## Threat notes

- Signature bypass → financial fraud: always `hash_equals` via `Signer::verify`.
- Algorithm downgrade: `Ds_SignatureVersion` comes from the (untrusted) request. `Notification::fromRequest` and `RestResponse::fromHttp` always verify with the `Merchant`'s configured `SignatureVersion`; a different value is rejected (`RedsysException` / `signatureValid = false`).
- Secret key in stack traces: `Merchant` and `Signer` constructors mark the key `#[\SensitiveParameter]`.
- Key leak across workers: this SDK is instance-based (`Merchant` is `final readonly class`); no process-wide static credentials. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Logging

REQ-OBS-001. `RestClient` accepts an optional PSR-3 logger and records, per call, `action` (`rest_init` / `rest_treat`),
`http_status`, `signature_valid` and Redsys `error_code` (info), or the exception class on transport failure (error).
Never logged: merchant key, card data, order data, signatures, request or response bodies.

## Dependencies

Run `composer audit` before releases and in host apps. Runtime requirements are PHP extensions (`curl`, `json`,
`openssl`) and the `psr/log` interfaces; the PSR-18 adapter's interfaces are optional (`suggest`).

