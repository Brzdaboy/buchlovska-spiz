<?php

declare(strict_types=1);

// Vestavěný server PHP (jen vývoj): statické soubory obslouží sám.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($path) && $path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/autoload.php';

App\App::boot(dirname(__DIR__));
