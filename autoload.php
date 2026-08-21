<?php

declare(strict_types=1);

/*
 * Non-Composer autoload for Nowo\Redsys\.
 * Prefer `vendor/autoload.php` when using Composer (primary).
 */
if (defined('NOWO_REDSYS_LOADED')) {
    return;
}

define('NOWO_REDSYS_LOADED', true);

$composerAutoload = __DIR__.'/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    if (!defined('NOWO_REDSYS_PATH')) {
        define('NOWO_REDSYS_PATH', __DIR__);
    }

    spl_autoload_register(static function (string $class): void {
        $prefix = 'Nowo\\Redsys\\';
        $baseDir = NOWO_REDSYS_PATH.'/src/';
        $len = strlen($prefix);

        if (0 !== strncmp($prefix, $class, $len)) {
            return;
        }

        $relative = substr($class, $len);
        $file = $baseDir.str_replace('\\', '/', $relative).'.php';
        if (is_file($file)) {
            require $file;
        }
    });
}
