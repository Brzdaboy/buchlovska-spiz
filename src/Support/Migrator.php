<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

/**
 * Jednoduché SQL migrace: soubory migrations/NNN_nazev.sql se spouštějí
 * vzestupně a jednou. Kontrolní součet brání tichému přepsání migrace,
 * která už běžela.
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    /** @return list<string> názvy právě provedených migrací */
    public function migrate(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $done = [];

        foreach ($this->available() as $version => $file) {
            $sql = (string) file_get_contents($file);
            $checksum = hash('sha256', $sql);
            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version], $checksum)) {
                    throw new RuntimeException("Migrace {$version} byla po spuštění změněna. Vytvořte novou migraci místo úpravy staré.");
                }
                continue;
            }
            foreach (self::splitStatements($sql) as $statement) {
                // DDL v MySQL/MariaDB stejně potvrzuje transakci, proto bez ní.
                $this->pdo->exec($statement);
            }
            $insert = $this->pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
            $insert->execute([$version, $checksum]);
            $done[] = $version;
        }
        return $done;
    }

    /** @return array<string, array{applied: bool}> */
    public function status(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $result = [];
        foreach (array_keys($this->available()) as $version) {
            $result[$version] = ['applied' => isset($applied[$version])];
        }
        return $result;
    }

    /** @return array<string, string> verze => cesta, seřazeno */
    private function available(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        $result = [];
        foreach ($files as $file) {
            $name = basename($file, '.sql');
            if (!preg_match('/^\d{3}_[a-z0-9_]+$/', $name)) {
                throw new RuntimeException("Neplatný název migrace: {$name}");
            }
            $result[$name] = $file;
        }
        ksort($result, SORT_STRING);
        return $result;
    }

    /** @return array<string, string> verze => kontrolní součet */
    private function applied(): array
    {
        $rows = $this->pdo->query('SELECT version, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        return is_array($rows) ? $rows : [];
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(100) PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci'
        );
    }

    /**
     * Rozdělí SQL na příkazy podle středníků mimo řetězce a komentáře.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\') {
                    $current .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $current .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === ';') {
                if (trim($current) !== '') {
                    $statements[] = trim($current);
                }
                $current = '';
                continue;
            }
            $current .= $char;
        }
        if (trim($current) !== '') {
            $statements[] = trim($current);
        }
        return $statements;
    }
}
