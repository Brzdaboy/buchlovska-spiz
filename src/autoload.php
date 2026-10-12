<?php

declare(strict_types=1);

/*
 * Vlastní PSR-4 autoloader: aplikace na serveru nepotřebuje Composer.
 * Jmenný prostor App\ odpovídá složce src/.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Support/helpers.php';
