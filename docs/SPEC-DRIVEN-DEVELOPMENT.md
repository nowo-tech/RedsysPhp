# Spec-driven development

This repository follows Nowo **REQ-*** bundle standards (see the local
`BUNDLES_FULL_SPECS_DETAILS.md` in `developer.local.server/repositories/bundles`)
plus a GitHub Spec Kit baseline under `specs/001-baseline/`.

## Product behaviour

- Drop-in Composer replacement for `redsys/redsys-lib` 3.1.1 (`Nowo\Redsys\\` namespaces).
- Request-safe merchant credentials on php-fpm and FrankenPHP workers.
- Injectable HTTP transport with explicit timeouts and OS-store TLS.
- PHP 8.3+, PHPUnit (Clover Lines ≥ 99%), PHP-CS-Fixer, scoped Rector/PHPStan.
- Current release **1.0.0** (tag `v1.0.0`); GitHub `nowo-tech/RedsysPhp`.

## User stories

- **As** a merchant app **I want** `composer require nowo-tech/redsys-php` **so that**
  I keep the Openpay API without the official SDK leaking credentials across requests.
- **As** a FrankenPHP worker **I want** `OpenpaySession` / `reset()` **so that**
  the next tenant does not inherit the previous API key.
- **As** a contributor **I want** `make release-check` **so that** style, analysis,
  and tests run the same way as other Nowo packages.

## REQ-* traceability

| Area | Where |
| ---- | ----- |
| Docker | `Dockerfile`, `docker-compose.yml` (`name: redsys-php`) |
| Makefile | `ensure-up`, `release-check`, `setup-hooks`, `update-deps` |
| QA | `composer cs-check` / `phpstan` / `test` / `test-coverage` |
| Docs | `docs/*` linked from README (REQ-DOCS-002) |
| CI | `.github/workflows/ci.yml` |
| Git | `.githooks/commit-msg`, `.scripts/check-no-cursor-coauthor.sh` |

## Layers

1. **Constitution** — `.specify/memory/constitution.md` (v1.1.0)
2. **Baseline spec** — `specs/001-baseline/` (shipped 1.0.0)
3. **Implementation** — `src/`, `Openpay.php`, `tests/`

Validation: `make release-check` (PHPUnit, PHPStan, PHP-CS-Fixer, Rector dry-run, coverage gate).
