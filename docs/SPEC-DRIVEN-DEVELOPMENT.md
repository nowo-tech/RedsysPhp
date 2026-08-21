# Spec-driven development

RedsysPhp follows Spec Kit for the baseline feature `specs/001-baseline/`.

## Product facts

- Clean-room MIT SDK (`nowo-tech/redsys-php`)
- Namespace `Nowo\Redsys\`
- HMAC signing, redirect form, notification verify, REST SIS
- FrankenPHP-friendly (`RedirectForm` has no side effects)

## Workflow

1. Update `specs/001-baseline/spec.md` + `code-inventory.md` when the public API changes.
2. Implement with tests (official HMAC_SHA512_V2 vector must stay green).
3. Run `make release-check` before tagging `v*`.

See also [SPEC-KIT.md](SPEC-KIT.md) and [CONFIGURATION.md](CONFIGURATION.md).
