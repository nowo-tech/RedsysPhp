# Upgrading

## Table of contents

- [From 1.0.2 to 1.0.3](#from-102-to-103)
- [From 1.0.1 to 1.0.2](#from-101-to-102)
- [To 1.0.0 (clean-room)](#to-100-clean-room)

## From 1.0.2 to 1.0.3

No breaking changes. **No application upgrade steps.** FrankenPHP worker audit documented; core types are `final readonly class` (same immutability contract as before); PHPStan worker-strict enabled for maintainers. Package identity constants now report `1.0.3`.

```bash
composer update nowo-tech/redsys-php
```

If you run under FrankenPHP worker with the kernel **not** reset: keep a shared `Merchant` only when credentials are fixed per deployment; pass notification fields from the current Symfony `Request` (not `$_POST` / `$_GET`). See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## From 1.0.1 to 1.0.2

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/redsys-php
```

## To 1.0.0 (clean-room)

There is no supported upgrade path from any pre-1.0 tree that redistributed proprietary PHPL_* sources. Treat **1.0.0** as a new package:

```json
{
  "require": {
    "nowo-tech/redsys-php": "^1.0"
  }
}
```

| Old concept | 1.0.0 |
|-------------|-------|
| `Redsys\` namespace | `Nowo\Redsys\` |
| Composer `replace` of `redsys/redsys-lib` | Removed |
| Proprietary LICENSE | MIT |
| `Redirect::authorisation()` | `RedirectForm::forMerchant()` |
| `Parameters::digest()` | `Notification::fromRequest()` |

Rewrite call sites; do not expect drop-in BC.
