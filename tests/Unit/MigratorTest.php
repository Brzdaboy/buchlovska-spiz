<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testSplitsStatementsIgnoringQuotesAndComments(): void
    {
        $sql = <<<'SQL'
            -- komentář; se středníkem
            CREATE TABLE a (x INT);
            /* blok; komentář */
            INSERT INTO a VALUES ('text; se středníkem'), ('it\'s');
            INSERT INTO a VALUES ("dvojité; uvozovky")
            SQL;

        self::assertSame([
            'CREATE TABLE a (x INT)',
            "INSERT INTO a VALUES ('text; se středníkem'), ('it\\'s')",
            'INSERT INTO a VALUES ("dvojité; uvozovky")',
        ], Migrator::splitStatements($sql));
    }

    public function testInitMigrationSplitsIntoExpectedStatements(): void
    {
        $statements = Migrator::splitStatements((string) file_get_contents(dirname(__DIR__, 2) . '/migrations/001_init.sql'));
        $creates = array_filter($statements, static fn (string $s): bool => str_starts_with($s, 'CREATE TABLE'));
        self::assertCount(9, $creates);
        self::assertCount(11, $statements, '9× CREATE TABLE + 2× INSERT');
    }
}
