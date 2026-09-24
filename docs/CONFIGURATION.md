# Configuration

## Merchant credentials

Provide FUC (`merchantCode`), terminal, and the **terminal signature key** from the Redsys admin portal. Prefer environment / secret store — never commit keys.

For **sandbox** FUC, terminal, signature key, URLs, and **test cards**, see [SANDBOX.md](SANDBOX.md).

| Setting | Class / API |
|---------|-------------|
| Environment | `Environment::Test` / `Environment::Live` |
| Signature version | `SignatureVersion::HmacSha512V2` (default), `HmacSha512V1`, `HmacSha256V1` |
| HTTP timeouts | `CurlHttpClient(connectTimeoutSeconds: 5, timeoutSeconds: 30)` |

## HTTP (REQ-RUNTIME-001)

`RestClient` uses an injectable `HttpClient`. Production default:

```php
use Nowo\Redsys\Http\CurlHttpClient;
use Nowo\Redsys\Rest\RestClient;

$client = new RestClient($merchant, new CurlHttpClient(5, 30));
```

## FrankenPHP / workers

`RedirectForm` only returns HTML. Build a Symfony/`Response` (or equivalent) in the host app. Do not `echo`/`exit` inside the worker.

Safe under FrankenPHP worker with kernel **not** reset between requests when you:

- share `Merchant` / `CurlHttpClient` / `RestClient` only for fixed deployment credentials;
- pass notification fields from the current `Request` (`$request->request->all()`), not `$_POST` / `$_GET`;
- do not store `MerchantParameters` / `Notification` on shared services across requests.

Full audit: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## PSR (REQ-CS-007)

See [PSR.md](PSR.md).
