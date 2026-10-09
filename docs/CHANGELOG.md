# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
  - [Changed](#changed)
- [[1.0.6] - 2026-10-09](#106---2026-10-09)
- [[1.0.5] - 2026-09-28](#105---2026-09-28)
- [[1.0.4] - 2026-09-27](#104---2026-09-27)
- [[1.0.3] - 2026-09-24](#103---2026-09-24)
- [[1.0.2] - 2026-08-24](#102---2026-08-24)
- [[1.0.1] - 2026-08-21](#101---2026-08-21)
- [[1.0.0] - 2026-08-21](#100---2026-08-21)

## [Unreleased]

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.3.0 so the committed lock stays installable on the minimum PHP.

## [1.0.6] - 2026-10-09

### Fixed

- CI: `composer.lock` content-hash re-synced after the package identity bump.

### Changed

- **Identity:** `Version::VERSION` and `extra.nowo-package-version` report `1.0.6`.

### Dependencies

- Dev tooling: `igor-php/igor-php` ^0.10 (v0.10.1, Dependabot #12), `nowo-tech/phpstan-frankenphp` v1.2.3 (#13), `phpstan/phpstan` 2.3.1, `rector/rector` 2.7.0, `phpunit/phpunit` 11.5.57.
- Demo (Symfony 8): `twig/twig` v3.30.0, `twig/extra-bundle` v3.29.0, `nowo-tech/hot-reload-bundle` v1.5.5, `nowo-tech/twig-inspector-bundle` v1.1.7.

[1.0.6]: https://github.com/nowo-tech/RedsysPhp/releases/tag/v1.0.6

## [1.0.5] - 2026-09-28

### Fixed

- Integration test: skip `CurlHttpClient` HTTPS echo assertion when httpbin returns a non-200 (transient upstream), and apply PHP CS Fixer.
- Makefile: point `strip-cursor-coauthor` target at `main`.

### Changed

- Dev dependency: `nowo-tech/phpstan-frankenphp` 1.2.0.
- **Identity:** `Version::VERSION` and `extra.nowo-package-version` report `1.0.5`.

[1.0.5]: https://github.com/nowo-tech/RedsysPhp/releases/tag/v1.0.5

## [1.0.4] - 2026-09-27


### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

[1.0.4]: https://github.com/nowo-tech/RedsysPhp/releases/tag/v1.0.4

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
