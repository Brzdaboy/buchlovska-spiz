<?php

declare(strict_types=1);

/*
 * Spuštění databázových migrací (jen z příkazové řádky).
 *
 *   php bin/migrate.php           provede chybějící migrace
 *   php bin/migrate.php --status  vypíše stav
 *
 * Na hostingu bez SSH viz docs/DEPLOY-WEDOS.md (připravíme v kroku 13).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/autoload.php';

use App\Support\Config;
use App\Support\Database;
use App\Support\Migrator;

try {
    $config = Config::fromFile(Config::locate(dirname(__DIR__)));
    $migrator = new Migrator((new Database($config))->pdo(), dirname(__DIR__) . '/migrations');

    if (in_array('--status', $argv, true)) {
        foreach ($migrator->status() as $version => $info) {
            echo ($info['applied'] ? '[x] ' : '[ ] ') . $version . PHP_EOL;
        }
        exit(0);
    }

    $done = $migrator->migrate();
    echo $done === [] ? "Databáze je aktuální.\n" : 'Provedeno: ' . implode(', ', $done) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Chyba migrace: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
