# Demo notes (FrankenPHP)

**REQ-DEMO-001:** FrankenPHP demos install **Nowo Twig Inspector** and **Nowo Hot Reload** (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`).

This package includes `demo/symfony8` — Symfony **8** + **FrankenPHP**.

```bash
make -C demo/symfony8 up
# Open http://localhost:8020/  (PORT from demo/symfony8/.env.example)
```

This library is **FrankenPHP worker mode friendly**. Demos default to `FRANKENPHP_MODE=worker`. `RedirectForm` returns HTML only (no `echo`/`exit`).

## What the demo shows

- Signed redirect payment form (`RedirectForm::forMerchant`)
- Online notification endpoint (`Notification::fromRequest`)
- OK / KO browser return pages
- Web Profiler + Twig Inspector + Hot Reload in `dev`

## Credentials

Sandbox defaults come from Redsys public docs (`REDSYS_*` in `.env.example`). Override with your terminal key for real SIS tests.
