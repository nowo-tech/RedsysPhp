# Spec Kit

GitHub Spec Kit layout for this library (Spec Kit **0.12.5**, Cursor Agent):

- `.specify/` — constitution, templates, `feature.json`
- `specs/001-baseline/` — shipped product spec (`1.0.0`), `src/` inventory, quality checklist

Active feature directory (`.specify/feature.json`): `specs/001-baseline`.

Workflow:

1. Change behaviour → update `specs/001-baseline/spec.md` (or add `specs/NNN-*` and point `feature.json` at it).
2. Keep `code-inventory.md` in the same change (constitution IV).
3. Implement in `src/` / tests.
4. Run `make release-check`.
5. Document in `docs/CHANGELOG.md` and README if the public API changed.

See [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md).
