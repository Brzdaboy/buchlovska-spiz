<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Support\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Základ pro testy nad skutečnou MariaDB/MySQL.
 * Připojení z proměnných BSPIZ_TEST_DB_* (bez nich se testy přeskočí).
 * Databázi před během vyčistí a nasadí migrace; každý test běží v transakci,
 * která se na konci vrátí.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static ?PDO $pdo = null;

    /** false = test pracuje přes aplikaci s vlastním připojením; tabulky se místo rollbacku vyprázdní. */
    protected bool $useTransaction = true;

    public static function setUpBeforeClass(): void
    {
        $name = getenv('BSPIZ_TEST_DB_NAME');
        if (!is_string($name) || $name === '') {
            return;
        }
        if (!str_contains($name, 'test')) {
            throw new \RuntimeException('Testovací databáze musí mít v názvu "test" (ochrana před smazáním ostrých dat).');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('BSPIZ_TEST_DB_HOST') ?: '127.0.0.1',
            (int) (getenv('BSPIZ_TEST_DB_PORT') ?: 3306),
            $name
        );
        self::$pdo = new PDO($dsn, (string) getenv('BSPIZ_TEST_DB_USER'), (string) getenv('BSPIZ_TEST_DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Stejný režim jako aplikace (App\Support\Database)
        self::$pdo->exec(\App\Support\Database::SESSION_SETUP_SQL);
        self::resetSchema(self::$pdo);
        (new Migrator(self::$pdo, dirname(__DIR__, 2) . '/migrations'))->migrate();
    }

    public static function resetSchema(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as $row) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $row[0]) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            self::markTestSkipped('Databázové testy vyžadují BSPIZ_TEST_DB_NAME (a další BSPIZ_TEST_DB_*).');
        }
        if ($this->useTransaction) {
            self::$pdo->beginTransaction();
        } else {
            self::truncateData(self::$pdo);
        }
    }

    protected function tearDown(): void
    {
        if (self::$pdo !== null && self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
    }

    /** Smaže data (ne číselníky) mezi testy, které nepoužívají transakci. */
    public static function truncateData(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'offers', 'demands', 'invitations', 'producer_profiles', 'school_profiles', 'users'] as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array<string, string|int> konfigurace DB pro aplikaci v testu */
    public static function dbConfig(): array
    {
        return [
            'host' => getenv('BSPIZ_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('BSPIZ_TEST_DB_PORT') ?: 3306),
            'name' => (string) getenv('BSPIZ_TEST_DB_NAME'),
            'user' => (string) getenv('BSPIZ_TEST_DB_USER'),
            'pass' => (string) getenv('BSPIZ_TEST_DB_PASS'),
        ];
    }

    protected function db(): PDO
    {
        return self::$pdo ?? throw new \LogicException('Bez databáze.');
    }

    /** @param array<string, scalar|null> $row */
    protected function insert(string $table, array $row): int
    {
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->db()->prepare($sql)->execute(array_values($row));
        return (int) $this->db()->lastInsertId();
    }

    protected function idOf(string $table, string $column, string $value): int
    {
        $stmt = $this->db()->prepare("SELECT id FROM {$table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, scalar|null> $overrides */
    protected function createUser(string $role, array $overrides = []): int
    {
        static $n = 0;
        $n++;
        return $this->insert('users', $overrides + [
            'logto_user_id' => 'logto-' . $role . '-' . $n . '-' . bin2hex(random_bytes(3)),
            'email' => $role . $n . '-' . bin2hex(random_bytes(3)) . '@example.cz',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    /** @param array<string, scalar|null> $overrides */
    protected function offerRow(int $producerId, array $overrides = []): array
    {
        return $overrides + [
            'producer_user_id' => $producerId,
            'category_id' => $this->idOf('categories', 'slug', 'zelenina'),
            'product_name' => 'Brambory Adéla',
            'unit_price' => '14.50',
            'price_unit_id' => $this->idOf('units', 'code', 'kg'),
            'available_quantity' => '800',
            'quantity_unit_id' => $this->idOf('units', 'code', 'kg'),
            'availability_type' => 'immediate',
            'delivery_terms' => 'Rozvoz po Buchlovicích zdarma.',
        ];
    }

    /** @param array<string, scalar|null> $overrides */
    protected function demandRow(int $schoolId, array $overrides = []): array
    {
        return $overrides + [
            'school_user_id' => $schoolId,
            'category_id' => $this->idOf('categories', 'slug', 'ovoce'),
            'product_name' => 'Jablka',
            'requested_quantity' => '120',
            'quantity_unit_id' => $this->idOf('units', 'code', 'kg'),
            'needed_from' => '2026-11-01',
            'needed_until' => '2026-11-30',
        ];
    }

    /** Ověří, že databáze příkaz odmítne. */
    protected function assertRejected(callable $operation, string $message = ''): void
    {
        $this->db()->exec('SAVEPOINT before_reject');
        try {
            $operation();
        } catch (\PDOException $e) {
            $this->db()->exec('ROLLBACK TO SAVEPOINT before_reject');
            // 23xxx = porušení integrity (NOT NULL, UNIQUE, FK, CHECK), 22xxx = neplatná data,
            // 01000 + 1265 = neplatná hodnota ENUM ve striktním režimu.
            // Jiná chyba (např. 42S22 neznámý sloupec) by znamenala chybu v testu, ne v omezení.
            // MySQL 8 hlásí porušení CHECK jako HY000 + 3819 (MariaDB jako 23000 + 4025).
            $errno = $e->errorInfo[1] ?? null;
            $isEnumReject = $e->getCode() === '01000' && $errno === 1265;
            $isMysqlCheck = $e->getCode() === 'HY000' && $errno === 3819;
            self::assertTrue(
                $isEnumReject || $isMysqlCheck || preg_match('/^2[23]/', (string) $e->getCode()) === 1,
                'Neočekávaná chyba: ' . $e->getMessage()
            );
            return;
        }
        self::fail('Databáze měla operaci odmítnout. ' . $message);
    }
}
