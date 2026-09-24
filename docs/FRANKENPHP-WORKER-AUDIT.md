# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/redsys-php` (`library`, framework-agnostic, no Symfony bundle) |
| Audited revision | `v1.0.3` |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` plus the non-Composer `autoload.php` + PHPStan classic / **worker-strict** rulesets |
| **Verdict** | ✅ **Viable** — immutable objects (`final readonly class`), no globals, no output; only a Low note on the default HTTP timeout |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests (`reset_kernel` false / equivalent), so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A library that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

This package ships no DI configuration. The "services" below are the classes an integrator is expected to register as shared services (typically `Merchant`, `CurlHttpClient`, `RestClient`).

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `Merchant`, `Signer`, `CurlHttpClient`, `RestClient`, `Notification`, `SignedPayload`, `HttpResponse`, `RestResponse`, `MerchantParameters` are `final readonly class`; `MerchantParameters::with()` returns a new instance |
| Static properties / `static` locals | ✅ | Only pure static helpers (`Base64Url`, `RedirectForm`, factory methods); no static properties |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset |
| Request / user / locale captured in services | ✅ | Notification input is passed as an array argument to `Notification::fromRequest()` |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None read in `src/` (worker-strict clean) |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | `RedirectForm::render()` returns a string and never echoes or exits (`src/RedirectForm.php`) |
| Resources (files, sockets, cURL) held open | ✅ | One cURL handle per call, local variable, freed when the method returns (`src/Http/CurlHttpClient.php`) |
| Memory growth across requests | ✅ | No caches or accumulating arrays |
| Blocking I/O and timeouts | ⚠️ Low | Explicit and configurable cURL timeouts, but the default total timeout is 30 s |
| Third-party static state | ✅ | Only PHP extensions (`curl`, `openssl`, `json`) |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker-strict.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\Redsys\Merchant` | integrator's choice (usually yes) | none (`readonly` credentials and enums) | ✅ | ✅ |
| `Nowo\Redsys\Signature\Signer` | created per call by `Merchant::signer()`, `Notification`, `RestResponse` | none | ✅ | ✅ |
| `Nowo\Redsys\Http\CurlHttpClient` | integrator's choice (usually yes) | none (`readonly` timeouts; handle is a local variable) | ✅ | ✅ |
| `Nowo\Redsys\Rest\RestClient` | integrator's choice (usually yes) | none (`readonly` merchant and transport) | ✅ | ✅ |
| `Nowo\Redsys\MerchantParameters` | per payment (do not share across requests) | none (`readonly`; copy-on-write via `with()`) | ✅ | ✅ |
| `Base64Url`, `RedirectForm` | static helpers | none | ✅ | ✅ |

Value objects and enums are created per call. A shared "template" `MerchantParameters` is never mutated in place.

## Findings

### W-01 — Default cURL total timeout is 30 seconds (Low)

- **Where:** `src/Http/CurlHttpClient.php` (defaults `connectTimeoutSeconds = 5`, `timeoutSeconds = 30`).
- **Worker impact:** a slow Redsys REST endpoint can hold one worker thread for up to 30 s per call. It does not leak state, but with a small worker pool a few slow payments can saturate the pool.
- **Recommendation:** construct `CurlHttpClient` with tighter values if the payment flow allows it (for example `new CurlHttpClient(3, 15)`), and cap queued requests with FrankenPHP `max_wait_time`.

### W-02 — Notification input is an array, not superglobals (Info)

- **Where:** `src/Notification.php` (`fromRequest(array $input, Merchant $merchant)`).
- **Worker impact:** the library never reads `$_POST` / `$_GET` itself, which is correct for worker mode. Pass `$request->request->all()` / `$request->query->all()` so the data always comes from the current Symfony `Request`.
- **Recommendation:** pass data from the Symfony `Request`, not from superglobals.

### W-03 — Non-Composer autoloader is idempotent (Info)

- **Where:** `autoload.php` (guarded by the `NOWO_REDSYS_LOADED` constant) and `spl_autoload_register`.
- **Worker impact:** the autoloader is registered once per process; a second `require` returns early, so it does not stack autoloaders per request.
- **Recommendation:** none. Composer autoloading remains the preferred path.

No other findings. The signature check uses `hash_equals()`, and no signing key or diversified key is cached between calls. **Scenario B (kernel never reset) is safe** when integrators follow the usage recommendations below.

## Usage recommendations in worker mode

- Register `Merchant`, `CurlHttpClient` and `RestClient` as shared services only when credentials are fixed per deployment. In multi-tenant apps, build the `Merchant` per request from the tenant's credentials and do **not** store it in a shared service property, or tenant A's key could sign tenant B's payment.
- Do not keep a `MerchantParameters` / `Notification` instance in a service property across requests; they carry per-order data.
- Return `RedirectForm::render()` output inside a Symfony `Response`; do not `echo` it.
- Prefer `Notification::fromRequest($request->request->all(), $merchant)` over `$_POST`.
- Custom `HttpClient` implementations must stay stateless (no response buffers, no persistent handles without explicit reset) and should set explicit timeouts.
- The demo (`demo/symfony8/docker/frankenphp/Caddyfile`) runs FrankenPHP in `worker` mode.

## Re-audit triggers

Re-run this audit when a change adds: a static property or a signer/key cache, a persistent cURL handle or connection pool in `CurlHttpClient`, any direct read of `$_POST` / `$_GET` / `$_SERVER` / `$_ENV`, output functions (`echo`, `header()`, `exit`), or a Symfony bundle / DI integration.
