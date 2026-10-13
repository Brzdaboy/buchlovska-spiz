<?php

declare(strict_types=1);

/*
 * Denní úklid z příkazové řádky (plánovaná úloha s PHP):
 *
 *   php bin/daily.php
 *
 * Na WEDOS bez PHP v cronu použijte adresu /cron/denni?klic=… (viz docs/UKLID.md).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/autoload.php';

use App\App;
use App\Support\Config;
use App\Support\Logger;

try {
    $root = dirname(__DIR__);
    $config = Config::fromFile(Config::locate($root));
    date_default_timezone_set((string) $config->get('app.timezone', 'Europe/Prague'));
    $app = new App($config, new Logger($root . '/var/log'), static fn () => throw new LogicException('Bez session.'), $root);
    $report = $app->maintenance()->runExclusive(new DateTimeImmutable('now', new DateTimeZone('UTC')), today());
    if ($report === null) {
        echo "Úklid už běží.\n";
        exit(0);
    }
    foreach ($report as $name => $count) {
        echo "{$name}: {$count}\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Chyba úklidu: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
