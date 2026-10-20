# Demo notes (FrankenPHP)

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`).

This package includes `demo/symfony8` — a sample Symfony **8** application under **FrankenPHP**.

The demo has its own `docker-compose.yml`, `Dockerfile`, and `docker/frankenphp/` Caddyfile variants.

The **repository root** `docker-compose.yml` is for **library** development (PHPUnit, PHPStan, CS). It is not the same as launching the demo as a hosted app.

```bash
make -C demo/symfony8 up
# Open http://localhost:8020/  (PORT from demo/symfony8/.env.example)
```

This library is **FrankenPHP worker mode friendly**. Demos default to `FRANKENPHP_MODE=worker`. Full audit for worker with kernel **not** reset: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Table of contents

- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Start the demo in development](#start-the-demo-in-development)
- [Start the demo in production mode](#start-the-demo-in-production-mode)
- [Troubleshooting](#troubleshooting)
- [What the demo shows](#what-the-demo-shows)
- [Sandbox credentials and test cards](#sandbox-credentials-and-test-cards)

## Switching classic vs worker (`FRANKENPHP_MODE`)

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile |
| **`classic`** | Plain `php_server`, hot-reload friendly |

After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated**.

## Start the demo in development

Development is the default: the demo compose file runs with `APP_ENV=dev` / `APP_DEBUG=1`, mounts `docker/php-dev.ini` (`opcache.revalidate_freq=0`) and the library at `/var/redsys-php`.

```bash
make -C demo/symfony8 up              # build + start, composer install, prints the URL
make -C demo/symfony8 logs            # follow FrankenPHP logs
make -C demo/symfony8 update-bundle   # re-sync the mounted library after editing src/
```

For edit-and-refresh without worker restarts set `FRANKENPHP_MODE=classic` in `demo/symfony8/.env` and recreate the container (`docker compose up -d`): the entrypoint then uses `Caddyfile.dev` (plain `php_server` with `hot_reload`).

## Start the demo in production mode

The demo is not a production deployment, but the production runtime can be reproduced locally:

1. Keep `FRANKENPHP_MODE=worker` (default): `Caddyfile` runs `php_server { worker { file /app/public/index.php; watch } }`.
2. Run with `APP_ENV=prod` and `APP_DEBUG=0` (compose override), then install without dev dependencies and warm the cache inside the container:

```bash
docker compose -f demo/symfony8/docker-compose.yml exec php composer install --no-dev --optimize-autoloader
docker compose -f demo/symfony8/docker-compose.yml exec php php bin/console cache:clear --env=prod
```

3. Use real terminal credentials (`REDSYS_ENV=live`, merchant code, terminal, secret key) only through real secrets, never through the committed `.env.example`.

The demo pages do not call REST SIS; when an application uses `RestClient`, keep `CurlHttpClient` connect / total timeouts below the FrankenPHP / PHP request limits so a slow SIS never pins a worker.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| Twig or PHP changes do not appear | In worker mode the kernel stays in memory: switch to `FRANKENPHP_MODE=classic`, or restart the container (`make -C demo/symfony8 restart`). |
| `FRANKENPHP_MODE` change has no effect | Env is read at container creation: run `docker compose up -d` (recreate), not `docker compose restart`. |
| Port already in use | Change `PORT` in `demo/symfony8/.env` (default **8020**) and run `make -C demo/symfony8 up` again. |
| Redsys sandbox rejects the payment (`SIS0042`, signature error) | Check `REDSYS_SECRET_KEY` / merchant code / terminal against [SANDBOX.md](SANDBOX.md); the signature depends on the order number. |
| Notification never arrives | The sandbox must reach `merchantUrl`: expose the demo with a public tunnel or test the notify page manually. |
| Library changes not picked up | Run `make -C demo/symfony8 update-bundle` (re-syncs the mounted library, autoload and cache). |

## What the demo shows

- Redirect payment via `Nowo\Redsys\RedirectForm`
- Notify verification via `Nowo\Redsys\Notification`
- OK/KO return pages

## Sandbox credentials and test cards

Defaults in `demo/symfony8/.env.example` use the public Redsys test merchant (`999008881`). Full table of credentials, PANs, CVV denial codes, and official links: [SANDBOX.md](SANDBOX.md).
