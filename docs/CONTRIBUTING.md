# Contributing

Thank you for considering contributing to the Nowo Redsys PHP SDK fork.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md).
By participating, you are expected to uphold it. Please report unacceptable
behavior to **hectorfranco@nowo.tech**.

## Maintainer

This project is maintained by [Héctor Franco Aceituno](https://github.com/HecFranco)
at [Nowo.tech](https://nowo.tech).

## Development setup

```bash
git clone git@github.com:nowo-tech/RedsysPhp.git
cd RedsysPhp
make setup-hooks
make up
make test
```

Without Docker: PHP 8.3+, Composer, `composer install`, `composer test`.

Install git hooks with `make setup-hooks` before committing. Run
`make check-no-cursor-coauthor` before every push. If CI `git-hygiene`
fails on historical trailers, run `make strip-cursor-coauthor-from-history`
(then force-push only with maintainer approval).

## Pull requests

1. Fork the repository.
2. Create a feature branch from `master`.
3. Run `make release-check` (or at least `composer test` and `composer cs-check`).
4. Open a PR using the template.

## Coding standards

- PSR-12 via PHP-CS-Fixer (`make cs-fix`)
- `declare(strict_types=1);` on every PHP file
- PHPDoc and comments in **English**
- PHPStan (FrankenPHP classic + worker rulesets)

## Cursor Agent

Submit prompts with **Ctrl+Enter**. Queue follow-ups (`queueMessageDefaultBehavior: queue`)
so the agent finishes the current turn before the next message.

Do **not** add `Co-authored-by: Cursor <cursoragent@cursor.com>` trailers
(REQ-GIT-001). Install hooks with `make setup-hooks`.
