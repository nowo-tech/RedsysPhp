# Feature Specification: Redsys PHP SDK baseline

**Feature Branch**: `001-baseline`  
**Created**: 2026-08-21  
**Updated**: 2026-08-21  
**Status**: Shipped (`1.0.0`, tag `v1.0.0`)  
**Input**: Clean-room Nowo Redsys TPV SDK (`nowo-tech/redsys-php`) covering 100% of production units under `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md), [`docs/COVERAGE.md`](../../docs/COVERAGE.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md), [`docs/CHANGELOG.md`](../../docs/CHANGELOG.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Independent MIT SDK for the public Redsys TPV Virtual protocol: HMAC signing (`HMAC_SHA512_V2` default), redirect HTML form, online notification verification, and REST SIS (`inicia` / `trata`). Namespace `Nowo\Redsys\`. FrankenPHP-safe (no echo/exit). GitHub: `nowo-tech/RedsysPhp`.

## User Scenarios & Testing

### US-001 — Sign and redirect a payment (Priority: P1)

As a merchant app, I build merchant parameters, sign them, and render an auto-submit HTML form to the SIS redirect URL.

**Independent Test**: `RedirectAndNotificationTest`, `SignerOfficialVectorTest`.

**Acceptance Scenarios**:

1. **Given** sandbox merchant + amount/order/currency/type, **When** `RedirectForm::forMerchant()` runs, **Then** HTML contains `Ds_MerchantParameters`, `Ds_Signature`, `Ds_SignatureVersion`, and the test SIS URL.
2. **Given** the official Redsys HMAC_SHA512_V2 vector, **When** `Signer::sign()` runs, **Then** the signature matches the published example.

### US-002 — Verify online notification (Priority: P1)

As a merchant server, I verify `Ds_Signature` before fulfilling an order.

**Independent Test**: `RedirectAndNotificationTest`, `ProtocolExtrasTest`.

**Acceptance Scenarios**:

1. **Given** a correctly signed notify trio, **When** `Notification::fromRequest()` runs, **Then** `isAuthorized()` reflects `Ds_Response` 0000–0099.
2. **Given** a bad signature, **When** `fromRequest()` runs, **Then** `RedsysException` is thrown.

### US-003 — REST SIS with injectable HTTP (Priority: P2)

As a tester / integrator, I call `RestClient::init|treat` without hitting live Redsys in unit tests.

**Independent Test**: `RestClientTest`, integration curl smoke.

**Acceptance Scenarios**:

1. **Given** a fake `HttpClient`, **When** `treat()` runs, **Then** the body is signed JSON and the response signature is verified.
2. **Given** `CurlHttpClient`, **When** posting JSON, **Then** connect/timeout options are applied.

### US-004 — Release quality gate (Priority: P2)

As a maintainer, I run `make release-check` and GitHub Actions before tagging `v*`.

**Acceptance Scenarios**:

1. **Given** local changes, **When** `make release-check` runs, **Then** CS + PHPStan + coverage gate pass.
2. **Given** a commit, **When** hooks run, **Then** Cursor co-author trailers are rejected (REQ-GIT-001).

## Functional Requirements

- **FR-001**: Support `HMAC_SHA512_V2`, `HMAC_SHA512_V1`, `HMAC_SHA256_V1` per public docs.
- **FR-002**: Encode/decode Base64URL merchant parameters JSON.
- **FR-003**: Redirect form returns string only (worker-safe).
- **FR-004**: Notification verifies signature with `hash_equals` normalization.
- **FR-005**: REST client posts signed JSON to inicia/trata URLs.
- **FR-006**: cURL client enforces connect and total timeouts.
- **FR-007**: No proprietary PHPL_* sources in the repository.

## Success Criteria

- **SC-001**: Official V2 vector green in CI.
- **SC-002**: PHPStan level 8, zero ignoreErrors.
- **SC-003**: Lines coverage ≥ 99% on `src/`.
- **SC-004**: MIT license; Packagist-ready metadata.
