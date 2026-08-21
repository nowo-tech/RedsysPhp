# Installation

## Composer (recommended)

```sh
composer require nowo-tech/redsys-php:^1.0
```

```php
require_once 'vendor/autoload.php';

use Nowo\Redsys\Merchant;
```

## Manual

Clone the repository and require the root bootstrap (Composer is preferred):

```php
require __DIR__ . '/RedsysPhp/autoload.php';
```

## Requirements

- PHP **8.3+**
- Extensions: `curl`, `json`, `openssl`
