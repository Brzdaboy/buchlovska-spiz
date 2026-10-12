<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/** Líné připojení k MySQL/MariaDB s bezpečnými výchozími volbami PDO. */
final class Database
{
    /**
     * Časy v UTC a striktní SQL režim nezávisle na nastavení serveru
     * (na sdíleném hostingu může být výchozí režim benevolentní a tiše ořezávat data).
     */
    public const SESSION_SETUP_SQL = "SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";

    private ?PDO $pdo = null;

    public function __construct(private Config $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) $this->config->get('db.host'),
                (int) $this->config->get('db.port', 3306),
                (string) $this->config->get('db.name')
            );
            $this->pdo = new PDO($dsn, (string) $this->config->get('db.user'), (string) $this->config->get('db.pass'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $this->pdo->exec(self::SESSION_SETUP_SQL);
        }
        return $this->pdo;
    }
}
