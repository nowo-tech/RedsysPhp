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
