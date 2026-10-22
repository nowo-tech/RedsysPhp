# PSR evaluation (REQ-CS-007)

| PSR | Status | Notes |
|-----|--------|-------|
| PSR-4 | Adopted | `Nowo\Redsys\` → `src/` |
| PSR-12 | Adopted | via PHP-CS-Fixer `@PhpCsFixer` / project rules |
| PSR-3 | Adopted | `RestClient` takes an optional `Psr\Log\LoggerInterface` (`NullLogger` default) and logs the REST outcome without card, merchant key, signature or body data (REQ-OBS-001) |
| PSR-7 / 17 / 18 | Adopted (optional) | `Http\Psr18HttpClient` adapts any PSR-18 client + PSR-17 factories to the package `HttpClient` port; the zero-dependency `CurlHttpClient` stays the default so the library installs without an HTTP stack |
| PSR-11 | N/A | Not a container |
| PSR-6 / 16 | N/A | No cache |
