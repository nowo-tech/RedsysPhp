# Release

## Checklist

- [ ] `make release-check` green (includes `check-no-cursor-coauthor`, CS, PHPStan, tests, coverage ≥ 99%)
- [ ] `docs/CHANGELOG.md` updated for the version
- [ ] `src/Version.php` and `composer.json` `extra.nowo-package-version` match
- [ ] No proprietary PHPL_* sources or Cursor co-author trailers (`make check-no-cursor-coauthor` before push — REQ-GIT-001)
- [ ] Tag `vX.Y.Z` (prefix `v` so `release.yml` runs)
- [ ] GitHub Release notes published
- [ ] Packagist synced (if publishing)

## Tagging

- Run `make bump-version VERSION=X.Y.Z` in the release commit: it sets `Nowo\Redsys\Version::VERSION` (sent in the User-Agent) and `extra.nowo-package-version` to the tag being released. `tests/Unit/VersionTest.php` fails if they differ, `make check-version` (part of `release-check`) fails if they are older than the latest tag, and `release.yml` fails if they differ from the pushed tag.

```sh
# X.Y.Z = the version in src/Version.php (e.g. 1.0.9)
git tag -a vX.Y.Z -m "RedsysPhp vX.Y.Z"
git push origin vX.Y.Z
```
