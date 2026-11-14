#!/usr/bin/env bash
# Release gate: src/Version.php (User-Agent) and composer.json extra.nowo-package-version must agree,
# and match the release tag.
#   check-version.sh            -> equal to each other; equal to the tag on HEAD (if any), else >= latest v* tag
#   check-version.sh vX.Y.Z     -> equal to the given tag (used by release.yml)
set -euo pipefail
cd "$(dirname "$0")/.."

code=$(sed -n "s/.*const string VERSION = '\([^']*\)'.*/\1/p" src/Version.php)
extra=$(sed -n 's/.*"nowo-package-version": "\([^"]*\)".*/\1/p' composer.json)

if [ -z "$code" ] || [ "$code" != "$extra" ]; then
  echo "Version mismatch: src/Version.php='$code' composer.json extra.nowo-package-version='$extra' (run: make bump-version VERSION=X.Y.Z)" >&2
  exit 1
fi

tag="${1:-}"
if [ -z "$tag" ]; then
  tag=$(git tag --points-at HEAD --list 'v*' 2>/dev/null | sort -V | tail -1 || true)
fi
if [ -n "$tag" ]; then
  if [ "${tag#v}" != "$code" ]; then
    echo "Version $code does not match tag $tag (run: make bump-version VERSION=${tag#v})" >&2
    exit 1
  fi
  echo "OK: version $code matches tag $tag"
  exit 0
fi

latest=$(git describe --tags --abbrev=0 --match 'v*' 2>/dev/null || true)
if [ -n "$latest" ]; then
  lowest=$(printf '%s\n%s\n' "${latest#v}" "$code" | sort -V | head -1)
  if [ "$lowest" != "${latest#v}" ]; then
    echo "Version $code is older than the latest tag $latest (stale; run: make bump-version VERSION=X.Y.Z)" >&2
    exit 1
  fi
fi
echo "OK: version $code (latest tag: ${latest:-none})"
