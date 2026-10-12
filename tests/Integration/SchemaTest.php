<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Support\Migrator;
use RuntimeException;

final class SchemaTest extends DatabaseTestCase
{
    // --- migrace a výchozí data ---------------------------------------------

    public function testMigrationsAreIdempotent(): void
    {
        $this->db()->commit(); // DDL by transakci stejně ukončilo
        self::assertSame([], (new Migrator($this->db(), dirname(__DIR__, 2) . '/migrations'))->migrate());
        $this->db()->beginTransaction();
    }

    public function testChangedMigrationIsDetected(): void
    {
        $dir = sys_get_temp_dir() . '/bspiz-migrations-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/001_init.sql', file_get_contents(dirname(__DIR__, 2) . '/migrations/001_init.sql') . "\n-- upraveno");
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/změněna/');
            (new Migrator($this->db(), $dir))->migrate();
        } finally {
            unlink($dir . '/001_init.sql');
            rmdir($dir);
        }
    }

    public function testDefaultCategoriesAndUnits(): void
    {
        $categories = $this->db()->query('SELECT name FROM categories ORDER BY sort_order')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([
            'Zelenina', 'Ovoce', 'Obiloviny a mouka', 'Maso a uzeniny', 'Mléko a mléčné výrobky',
            'Vejce', 'Pečivo', 'Med a včelí produkty', 'Nápoje', 'Ostatní',
        ], $categories);

        $units = $this->db()->query('SELECT code FROM units ORDER BY sort_order')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['kg', 't', 'g', 'l', 'ml', 'ks'], $units);
    }

    public function testCzechCollationSortsCh(): void
    {
        // V češtině je "ch" za "h": Hrušky < Chřest < Ibišek
        $rows = $this->db()->query(
            "SELECT x FROM (SELECT 'Chřest' AS x UNION SELECT 'Hrušky' UNION SELECT 'Ibišek') t ORDER BY x COLLATE utf8mb4_czech_ci"
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['Hrušky', 'Chřest', 'Ibišek'], $rows);
    }

    // --- uživatelé a pozvánky -----------------------------------------------

    public function testEmailMustBeLowercase(): void
    {
        $this->assertRejected(fn () => $this->createUser('producer', ['email' => 'Farma@Example.cz']));
    }

    public function testActiveUserNeedsLogtoIdentity(): void
    {
        $this->assertRejected(fn () => $this->createUser('school', ['logto_user_id' => null, 'status' => 'active']));
        self::assertGreaterThan(0, $this->createUser('school', ['logto_user_id' => null, 'status' => 'invited']));
    }

    public function testDuplicateEmailAndLogtoIdRejected(): void
    {
        $this->createUser('producer', ['email' => 'jedna@example.cz', 'logto_user_id' => 'abc']);
        $this->assertRejected(fn () => $this->createUser('school', ['email' => 'jedna@example.cz']));
        $this->assertRejected(fn () => $this->createUser('school', ['logto_user_id' => 'abc']));
    }

    public function testOnlyOnePendingInvitationPerEmail(): void
    {
        $admin = $this->createUser('admin');
        $invitee = $this->createUser('producer', ['logto_user_id' => null, 'status' => 'invited', 'email' => 'nova@example.cz']);
        $row = [
            'user_id' => $invitee, 'email' => 'nova@example.cz', 'role' => 'producer',
            'invited_by' => $admin, 'created_at' => '2026-10-12 10:00:00', 'expires_at' => '2026-10-19 10:00:00',
        ];
        $first = $this->insert('invitations', $row);
        $this->assertRejected(fn () => $this->insert('invitations', $row), 'druhá čekající pozvánka');

        // Po zrušení první lze vystavit novou (opětovné odeslání).
        $this->db()->prepare("UPDATE invitations SET status = 'revoked', revoked_at = NOW() WHERE id = ?")->execute([$first]);
        self::assertGreaterThan($first, $this->insert('invitations', $row));
    }

    public function testInvitationStateConsistency(): void
    {
        $admin = $this->createUser('admin');
        $invitee = $this->createUser('school', ['logto_user_id' => null, 'status' => 'invited']);
        $base = [
            'user_id' => $invitee, 'email' => 'x@example.cz', 'role' => 'school', 'invited_by' => $admin,
            'created_at' => '2026-10-12 10:00:00', 'expires_at' => '2026-10-19 10:00:00',
        ];
        $this->assertRejected(fn () => $this->insert('invitations', ['status' => 'accepted'] + $base), 'přijatá bez data');
        $this->assertRejected(fn () => $this->insert('invitations', ['status' => 'revoked'] + $base), 'zrušená bez data');
        $this->assertRejected(fn () => $this->insert('invitations', ['expires_at' => '2026-10-12 09:00:00'] + $base), 'platnost do minulosti');
        $this->assertRejected(fn () => $this->insert('invitations', ['role' => 'admin'] + $base), 'admina nelze pozvat');
    }

    // --- profily -------------------------------------------------------------

    /** @param array<string, scalar|null> $overrides */
    private function producerProfile(int $userId, array $overrides = []): int
    {
        return $this->insert('producer_profiles', $overrides + [
            'user_id' => $userId, 'contact_name' => 'Jan Novák', 'producer_name' => 'Farma Pod Barborkou',
            'address_line' => 'Náves 12', 'municipality' => 'Buchlovice', 'postal_code' => '68708',
            'phone' => '+420 600 000 000', 'public_email' => 'farma@example.cz',
            'privacy_consent_at' => '2026-10-12 10:00:00', 'contact_publication_consent_at' => '2026-10-12 10:00:00',
        ]);
    }

    public function testProfileValidation(): void
    {
        $this->producerProfile($this->createUser('producer'), ['company_id' => '12345678']);
        $this->assertRejected(fn () => $this->producerProfile($this->createUser('producer'), ['company_id' => '12345678']), 'stejné IČO = jedna organizace');
        $this->assertRejected(fn () => $this->producerProfile($this->createUser('producer'), ['postal_code' => '687 08']), 'PSČ jen 5 číslic');
        $this->assertRejected(fn () => $this->producerProfile($this->createUser('producer'), ['company_id' => '1234']), 'IČO má 8 číslic');
    }

    // --- nabídky -------------------------------------------------------------

    public function testValidOfferIsAccepted(): void
    {
        $id = $this->insert('offers', $this->offerRow($this->createUser('producer')));
        $offer = $this->db()->query("SELECT status, currency FROM offers WHERE id = {$id}")->fetch();
        self::assertSame(['status' => 'active', 'currency' => 'CZK'], $offer, 'bez schvalování: hned aktivní');
    }

    /** @return iterable<string, array{array<string, scalar|null>}> */
    public static function invalidOffers(): iterable
    {
        yield 'nulová cena' => [['unit_price' => '0']];
        yield 'záporná cena' => [['unit_price' => '-5']];
        yield 'bez ceny' => [['unit_price' => null]];
        yield 'jiná měna' => [['currency' => 'EUR']];
        yield 'nulové množství' => [['available_quantity' => '0']];
        yield 'prázdný název' => [['product_name' => '   ']];
        yield 'prázdná doprava' => [['delivery_terms' => '']];
        yield 'ihned s datem' => [['availability_type' => 'immediate', 'available_from' => '2026-11-01']];
        yield 'od data bez data' => [['availability_type' => 'from_date', 'available_from' => null]];
        yield 'konec před začátkem' => [['availability_type' => 'from_date', 'available_from' => '2026-11-10', 'available_until' => '2026-11-01']];
        yield 'stažení bez důvodu' => [['status' => 'withdrawn']];
        yield 'neznámý stav' => [['status' => 'pending_approval']];
    }

    /**
     * @dataProvider invalidOffers
     * @param array<string, scalar|null> $overrides
     */
    public function testInvalidOfferIsRejected(array $overrides): void
    {
        $producer = $this->createUser('producer');
        $this->assertRejected(fn () => $this->insert('offers', $this->offerRow($producer, $overrides)));
    }

    public function testWithdrawnOfferWithReasonIsAccepted(): void
    {
        $admin = $this->createUser('admin');
        $id = $this->insert('offers', $this->offerRow($this->createUser('producer'), [
            'status' => 'withdrawn', 'withdrawn_by' => $admin, 'withdrawn_at' => '2026-10-12 11:00:00',
            'withdrawal_reason' => 'Nevhodný obsah popisu.',
        ]));
        self::assertGreaterThan(0, $id);
    }

    // --- poptávky ------------------------------------------------------------

    public function testDemandValidation(): void
    {
        $school = $this->createUser('school');
        self::assertGreaterThan(0, $this->insert('demands', $this->demandRow($school)));
        $this->assertRejected(fn () => $this->insert('demands', $this->demandRow($school, ['needed_until' => '2026-10-01'])), 'konec před začátkem');
        $this->assertRejected(fn () => $this->insert('demands', $this->demandRow($school, ['requested_quantity' => '0'])), 'nulové množství');
        $this->assertRejected(fn () => $this->insert('demands', $this->demandRow($school, ['needed_until' => null])), 'období je povinné');
    }

    public function testDemandHasNoPriceColumns(): void
    {
        $columns = $this->db()->query('SHOW COLUMNS FROM demands')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($columns as $column) {
            self::assertStringNotContainsString('price', (string) $column, 'poptávka nesmí obsahovat cenu (SPECIFIKACE §9)');
        }
    }

    // --- číselníky a audit ----------------------------------------------------

    public function testUsedCategoryAndUnitCannotBeDeleted(): void
    {
        $this->insert('offers', $this->offerRow($this->createUser('producer')));
        $this->assertRejected(fn () => $this->db()->exec("DELETE FROM categories WHERE slug = 'zelenina'"));
        $this->assertRejected(fn () => $this->db()->exec("DELETE FROM units WHERE code = 'kg'"));
    }

    public function testAuditDetailsMustBeJson(): void
    {
        $this->assertRejected(fn () => $this->insert('audit_log', ['action' => 'x', 'target_type' => 'offer', 'details' => 'ne-json']));
        self::assertGreaterThan(0, $this->insert('audit_log', ['action' => 'offer.withdrawn', 'target_type' => 'offer', 'details' => '{"reason":"test"}']));
    }
}
