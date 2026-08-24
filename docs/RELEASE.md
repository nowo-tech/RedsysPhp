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

```sh
git tag -a v1.0.1 -m "RedsysPhp v1.0.1"
git push origin v1.0.1
```
