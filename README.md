# Redsys PHP SDK (Nowo)

[![CI](https://github.com/nowo-tech/RedsysPhp/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/RedsysPhp/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/redsys-php.svg?style=flat)](https://packagist.org/packages/nowo-tech/redsys-php) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/redsys-php.svg)](https://packagist.org/packages/nowo-tech/redsys-php) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)](https://php.net) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/RedsysPhp.svg?style=social&label=Star)](https://github.com/nowo-tech/RedsysPhp)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/redsys-php) · Give it a **star** on [GitHub](https://github.com/nowo-tech/RedsysPhp) so more developers can find it.

Clean-room PHP SDK for the **public Redsys TPV Virtual protocol** (HMAC signing, redirect form, REST SIS). Namespace `Nowo\Redsys\`. License **MIT**. Release **1.0.0**.

This is an **independent implementation** written from publicly documented algorithms and endpoints. It does **not** redistribute Redsys proprietary PHPL_* library source. “Redsys” is a trademark of its respective owners.

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This library is **FrankenPHP worker mode friendly**: `RedirectForm` returns an HTML string and never calls `echo` or `exit`.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Sandbox: credenciales y tarjetas de prueba](docs/SANDBOX.md)
- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [Spec Kit](docs/SPEC-KIT.md)
- [Coverage](docs/COVERAGE.md)
- [GitHub CI](docs/GITHUB_CI.md)
- [Branching](docs/BRANCHING.md)
- [FrankenPHP demo](docs/DEMO-FRANKENPHP.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)

## Quick start

```php
use Nowo\Redsys\Currency;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\RedirectForm;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\TransactionType;

$merchant = new Merchant(
    merchantCode: '999008881',
    terminal: '1',
    secretKey: getenv('REDSYS_SECRET_KEY') ?: '',
    environment: Environment::Test,
    signatureVersion: SignatureVersion::HmacSha512V2,
);

$params = MerchantParameters::create()
    ->forMerchant($merchant)
    ->amount(145)
    ->order('1444904795')
    ->currency(Currency::Eur)
    ->transactionType(TransactionType::Authorization)
    ->merchantUrl('https://example.test/redsys/notify')
    ->urlOk('https://example.test/ok')
    ->urlKo('https://example.test/ko');

$html = RedirectForm::forMerchant($merchant, $params);
```

## Compatibility

PHP **8.3+** with extensions `curl`, `json`, `openssl`.

## Installation

```sh
composer require nowo-tech/redsys-php:^1.0
```

```php
require_once 'vendor/autoload.php';
```

## Tests and coverage

```sh
composer install
composer test
composer test-coverage
```

Clover gate: **≥ 99% Lines** on `src/` (see [docs/COVERAGE.md](docs/COVERAGE.md)).

```sh
make qa
make release-check
```

## Develop

```sh
make up && make install && make qa
make setup-hooks
```

## License

**MIT** — see [LICENSE](LICENSE).

Independent clean-room implementation of the public Redsys TPV Virtual protocol. Does not redistribute Redsys proprietary PHPL_* source. “Redsys” is a trademark of its respective owners.
