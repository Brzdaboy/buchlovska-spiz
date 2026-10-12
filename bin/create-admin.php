<?php

declare(strict_types=1);

/*
 * Založí účet administrátora (jen z příkazové řádky).
 *
 *   php bin/create-admin.php vas@email.cz
 *
 * Účet vznikne ve stavu "invited". Při prvním přihlášení přes Logto se stejným
 * OVĚŘENÝM e-mailem se propojí s identitou v Logto a aktivuje.
 * Admin se nikdy nezakládá přes web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/autoload.php';

use App\Repository\AuditLog;
use App\Repository\UserRepository;
use App\Support\Config;
use App\Support\Database;

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Použití: php bin/create-admin.php vas@email.cz\n");
    exit(2);
}

try {
    $config = Config::fromFile(Config::locate(dirname(__DIR__)));
    $pdo = (new Database($config))->pdo();
    $users = new UserRepository($pdo);

    $existing = $users->findByEmail($email);
    if ($existing !== null) {
        fwrite(STDERR, "Uživatel s tímto e-mailem už existuje (role {$existing['role']}, stav {$existing['status']}).\n");
        exit(1);
    }
    $id = $users->createAdmin($email);
    (new AuditLog($pdo))->record(null, 'user.admin_created_cli', 'user', $id);
    echo "Administrátor {$email} založen. Přihlaste se na /prihlaseni účtem Logto se stejným e-mailem.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Chyba: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
