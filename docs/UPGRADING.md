# Upgrading

## Table of contents

- [From 1.0.1 to 1.0.2](#from-101-to-102)

## From 1.0.1 to 1.0.2

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/redsys-php
```

## From 1.0.1 to 1.0.2

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/redsys-php
```

# Upgrading

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
