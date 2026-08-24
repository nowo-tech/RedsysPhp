# Code inventory — RedsysPhp 1.0.0

Traceability for REQ-SPECKIT-003. All production units under `src/`.

**Last audited:** 2026-08-24 (baseline aligned with `spec.md` user scenarios and FR-* requirements).

## Core

| Unit | Path | Spec refs |
|------|------|-----------|
| Merchant | `src/Merchant.php` | US-001 |
| MerchantParameters | `src/MerchantParameters.php` | US-001, FR-002 |
| SignedPayload | `src/SignedPayload.php` | US-001, FR-001 |
| RedirectForm | `src/RedirectForm.php` | US-001, FR-003 |
| Notification | `src/Notification.php` | US-002, FR-004 |
| Version | `src/Version.php` | SC-004 |
| Environment | `src/Environment.php` | FR-005 |
| SignatureVersion | `src/SignatureVersion.php` | FR-001 |
| TransactionType | `src/TransactionType.php` | US-001 |
| Currency | `src/Currency.php` | US-001 |

## Signature / encoding

| Unit | Path | Spec refs |
|------|------|-----------|
| Signer | `src/Signature/Signer.php` | US-001, FR-001 |
| Base64Url | `src/Encoding/Base64Url.php` | FR-002 |
| RedsysException | `src/Exception/RedsysException.php` | US-002 |

## HTTP / REST

| Unit | Path | Spec refs |
|------|------|-----------|
| HttpClient | `src/Http/HttpClient.php` | US-003 |
| HttpResponse | `src/Http/HttpResponse.php` | US-003 |
| CurlHttpClient | `src/Http/CurlHttpClient.php` | US-003, FR-006 |
| RestClient | `src/Rest/RestClient.php` | US-003, FR-005 |
| RestResponse | `src/Rest/RestResponse.php` | US-003, FR-004 |

**Total production PHP files**: 18
