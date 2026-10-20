# Spec-driven development

## Table of contents

- [Layers in sync](#layers-in-sync)
- [User stories](#user-stories)
- [Functional scope](#functional-scope)
  - [In scope](#in-scope)
  - [Explicit non-goals](#explicit-non-goals)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Contributor workflow](#contributor-workflow)
- [Relationship to Engram](#relationship-to-engram)
- [GitHub Spec Kit](#github-spec-kit)
- [See also](#see-also)

## Layers in sync

This repository uses **spec-driven development** with three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with **Cursor Agent** skills (`.cursor/skills/speckit-*`). The inventory maps **100%** of production code under `src/` (18/18). **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what consuming applications may rely on: signing, redirect form, notification verification and REST SIS ([`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`SANDBOX.md`](SANDBOX.md)).
3. **Traceability anchors** — stable `REQ-*` identifiers in the `Makefile` comments so PRs and issues can cite tooling behavior.

PHPUnit and PHPStan are the mechanical proof (including the official Redsys `HMAC_SHA512_V2` vector). There is no separate executable spec language (no Gherkin).

## User stories

| ID | Story | Scope | Integrator docs |
| --- | --- | --- | --- |
| US-001 | As a merchant app, I build merchant parameters, sign them and render an auto-submit HTML form to the SIS redirect URL. | `MerchantParameters`, `Signer`, `RedirectForm` | [USAGE](USAGE.md) |
| US-002 | As a merchant server, I verify `Ds_Signature` of the online notification before fulfilling an order. | `Notification` | [USAGE](USAGE.md) |
| US-003 | As an integrator, I call REST SIS (`inicia` / `trata`) with an injectable HTTP client. | `RestClient`, `HttpClient`, `CurlHttpClient` | [CONFIGURATION](CONFIGURATION.md) |
| US-004 | As a maintainer, I run `make release-check` and GitHub Actions before tagging `v*`. | QA tooling | [RELEASE](RELEASE.md) |
| US-005 | As an app on FrankenPHP worker mode (kernel not reset), I share `Merchant` / `RestClient` without cross-request leakage. | Whole `src/` | [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md) |

Acceptance criteria (Given/When/Then) live in [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md).

## Functional scope

**Goal:** a clean-room MIT PHP SDK for the **public** Redsys TPV Virtual protocol (`nowo-tech/redsys-php`, namespace `Nowo\Redsys\`).

### In scope

- HMAC signing: `HMAC_SHA512_V2` (default), `HMAC_SHA512_V1`, `HMAC_SHA256_V1`.
- Base64URL merchant-parameter encoding/decoding.
- Redirect HTML form returned as a string (no `echo` / `exit`; worker-safe).
- Online notification verification (`hash_equals`, response-code classification).
- REST SIS client posting signed JSON, with connect / total timeouts on the cURL client.

### Explicit non-goals

- Redistributing or wrapping the proprietary Redsys `PHPL_*` library.
- Framework integration (Symfony bundle, routes, controllers): the package is a plain PHP library.
- Order storage, refunds UI or any merchant back office.
- `demo/` trees are illustrative only and not part of the Packagist API.

## Validating the functional spec

- `make qa` (Docker): PHP-CS-Fixer, PHPStan level 8 (FrankenPHP classic + worker-strict rulesets), PHPUnit.
- `make release-check` before every tag; `make check-no-cursor-coauthor` again after the release commit.
- Coverage: Clover gate on `src/` lines (see [COVERAGE.md](COVERAGE.md); currently 100%).
- Rule: any behavior change requires tests under `tests/` (the official HMAC_SHA512_V2 vector must stay green).

## Requirement identifiers (`REQ-*`)

| ID | Location | Meaning |
| --- | --- | --- |
| `REQ-MAKE-001` / `REQ-MAKE-002` | Root `Makefile` header | Docker-driven workflow; `release-check` gate |
| `REQ-MAKE-006` | Root `Makefile` (`setup-hooks`) | Installs `.githooks` (commit-msg / pre-commit) |
| `REQ-MAKE-008` | Root `Makefile` (`update-deps`) | Dependency update covers library and demos |
| `REQ-MAKE-009` / `REQ-MAKE-010` | Root `Makefile` | Optional monorepo helper; Compose V2 with absolute docker path |
| `REQ-GIT-001` | Root `Makefile` (`check-no-cursor-coauthor`, `setup-hooks`) | No AI co-author trailers in git history |
| `REQ-CS-008` | Root `Makefile` (`igor`) | Igor FrankenPHP worker-state audit |

When scripted behavior changes, update the comment next to the existing `REQ-*` or add a new ID and cite it in the PR.

## Contributor workflow

1. **Clarify** the behavior in an issue or draft PR (spec + Makefile/demo impact).
2. **Implement** with tests and static analysis.
3. **Update** `specs/001-baseline/spec.md` and `code-inventory.md` when the public API or `src/` files change (see the [Spec Kit maintainer checklist](SPEC-KIT.md#maintainer-checklist)).
4. **Anchor** scripts and demos with `REQ-*` comments when tooling changes.
5. **Ship** integrator docs (`USAGE`, `CONFIGURATION`, `CHANGELOG`, `UPGRADING`) when behavior or configuration changes.

## Relationship to Engram

[`ENGRAM.md`](ENGRAM.md) covers the org-wide checklist and assistant memory (MCP). This document is the functional contract and local `REQ-*` traceability; it links to Engram instead of duplicating checklist prose.

## GitHub Spec Kit

- Integration: **Cursor Agent** (`cursor-agent`), skills under `.cursor/skills/speckit-*`.
- Baseline: [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md) (18/18 production files).
- Re-init: `specify init --here --force --integration cursor-agent --script sh`.
- Full manual: [`SPEC-KIT.md`](SPEC-KIT.md).

## See also

- [SPEC-KIT.md](SPEC-KIT.md)
- [specs/001-baseline/spec.md](../specs/001-baseline/spec.md)
- [USAGE.md](USAGE.md)
- [CONFIGURATION.md](CONFIGURATION.md)
- [SANDBOX.md](SANDBOX.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [RELEASE.md](RELEASE.md)
- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md)
