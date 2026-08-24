# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `docs/SANDBOX.md` — public Redsys test merchant credentials and sandbox test cards
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
