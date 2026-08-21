# Demo notes (FrankenPHP)

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`).

This package includes `demo/symfony8` — a sample Symfony **8** application under **FrankenPHP**.

The demo has its own `docker-compose.yml`, `Dockerfile`, and `docker/frankenphp/` Caddyfile variants.

The **repository root** `docker-compose.yml` is for **library** development (PHPUnit, PHPStan, CS). It is not the same as launching the demo as a hosted app.

```bash
make -C demo/symfony8 up
# Open http://localhost:8020/  (PORT from demo/symfony8/.env.example)
```

This library is **FrankenPHP worker mode friendly**. Demos default to `FRANKENPHP_MODE=worker`.

## Switching classic vs worker (`FRANKENPHP_MODE`)

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile |
| **`classic`** | Plain `php_server`, hot-reload friendly |

After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated**.

## What the demo shows

- Redirect payment via `Nowo\Redsys\RedirectForm`
- Notify verification via `Nowo\Redsys\Notification`
- OK/KO return pages
