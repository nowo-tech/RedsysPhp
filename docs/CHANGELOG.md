# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.3] - 2026-09-24](#103---2026-09-24)
- [[1.0.2] - 2026-08-24](#102---2026-08-24)
- [[1.0.1] - 2026-08-21](#101---2026-08-21)
- [[1.0.0] - 2026-08-21](#100---2026-08-21)

## [Unreleased]

## [1.0.3] - 2026-09-24

### Added

- **Docs:** `docs/FRANKENPHP-WORKER-AUDIT.md` — full audit for FrankenPHP worker with kernel **not** reset (scenario B); verdict **Viable**.

### Changed

- **PHPStan:** include `ruleset-worker-strict.neon` (covers worker rules; no request-superglobal usage in `src/`).
- **Immutability:** core types promoted to `final readonly class` (`Merchant`, `MerchantParameters`, `Notification`, `Signer`, `CurlHttpClient`, `HttpResponse`, `RestClient`, `RestResponse`, `SignedPayload`). `MerchantParameters::with()` returns a new instance (no in-place mutation).
- **Identity:** `Version::VERSION` and `extra.nowo-package-version` report `1.0.3` (were stuck at `1.0.1` after the `v1.0.2` tag).

### Documentation

- **USAGE.md** / **CONFIGURATION.md** / **SECURITY.md** / **README.md**: worker guidance and link to the audit.
- **specs/001-baseline:** status `1.0.3`; US-005 + FR-008 for worker scenario B.

### Notes

- **No public API changes.** Integrators on `^1.0` only need `composer update`.
- Residual **Low** finding: default cURL timeouts (5 s / 30 s) can occupy a worker thread; tune via `CurlHttpClient` if needed.

[1.0.3]: https://github.com/nowo-tech/RedsysPhp/releases/tag/v1.0.3

## [1.0.2] - 2026-08-24

### Changed

- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **Docs:** English sandbox credentials guide (`docs/SANDBOX.md`).
- **CI:** git hooks and release hygiene (REQ-GIT-001).
- **Docs:** Spec Kit baseline refresh.

### Notes

- **No API or configuration changes** for integrators unless noted above.

### Added

- `docs/SANDBOX.md` — public Redsys test merchant credentials and sandbox test cards

[1.0.2]: https://github.com/nowo-tech/RedsysPhp/releases/tag/v1.0.2

## [1.0.1] - 2026-08-21

### Added

- Symfony 8 + FrankenPHP demo (`demo/symfony8`) with redirect / notify / OK / KO flows
- `docs/DEMO-FRANKENPHP.md`

## [1.0.0] - 2026-08-21

### Added

- Initial public release of the **Nowo clean-room** Redsys TPV SDK.
- Namespace `Nowo\Redsys\`
- License **MIT** (independent protocol implementation; not PHPL_*)
- `Signature\Signer`: `HMAC_SHA512_V2` (default), `HMAC_SHA512_V1`, `HMAC_SHA256_V1`
- Official HMAC_SHA512_V2 vector from Redsys public docs covered by PHPUnit
- `RedirectForm` (FrankenPHP-safe), `Notification`, `RestClient` + cURL timeouts
- PHPStan level 8 + FrankenPHP rulesets
- Spec Kit baseline + Nowo bundle scaffold

### Changed

- GitHub Actions: `actions/checkout@v7`, `actions/github-script@v9`, `actions/stale@v11`
