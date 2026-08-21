# Openpay PHP SDK Constitution

## Core Principles

### I. Drop-in Openpay contract
Public namespaces stay `Openpay\\`. Composer `replace`s `openpay/sdk` 3.1.1.
Nowo additions (`configure` / `reset` / `OpenpaySession` / HTTP transport) must
not break the upstream API used by merchant apps.

### II. Request isolation
Merchant credentials are process statics. Callers on php-fpm and FrankenPHP
workers MUST `reset()` (or use `OpenpaySession`) at the end of each request.

### III. Spec-first, test-proven
PHPUnit and PHPStan are the mechanical proof. Behavioral changes require tests.

### IV. 100% code inventory traceability
Every production file under `Openpay/` and root `Openpay.php` must appear in
`specs/001-baseline/code-inventory.md`. New files require spec updates in the same PR.

### V. Cursor + Spec Kit
GitHub Spec Kit is initialized with **Cursor Agent**. Do not add Cursor
co-author trailers (REQ-GIT-001).

### VI. Scoped static analysis
PHPStan and Rector MUST stay on the Nowo HTTP/session surface (plus Rector
`tests/`). Do not require a full-tree Rector rewrite of upstream `Openpay/Resources`
as a release gate.

## Governance
Amendments update this file, baseline spec when principles affect behavior, and
`docs/CHANGELOG.md` when consumer-visible.

**Version**: 1.1.0 | **Ratified**: 2026-08-21 | **Last Amended**: 2026-08-21
