<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;
use Tests\Support\Fixtures;

/** Administrace: přehled záznamů, stažení/uvolnění, uživatelé, číselníky, oprávnění. */
final class AdminFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private App $app;
    private int $admin;
    private int $producer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create(['db' => self::dbConfig()]);
        $this->admin = $this->createUser('admin');
        $this->producer = $this->createUser('producer', ['profile_completed_at' => '2026-10-01 10:00:00']);
        $this->as($this->admin, 'admin');
    }

    private function as(int $userId, string $role): void
    {
        AppFactory::$sessionData['user_id'] = $userId;
        AppFactory::$sessionData['user_role'] = $role;
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    private function post(string $path, array $form = []): Response
    {
        $this->get('/');
        return $this->app->handle(new Request('POST', $path, [], $form + ['_csrf' => $this->app->csrf()->token()]));
    }

    private function producerOffer(array $overrides = []): int
    {
        $this->as($this->producer, 'producer');
        $this->post('/moje-nabidky', Fixtures::offerForm($overrides));
        $this->as($this->admin, 'admin');
        return (int) $this->db()->query('SELECT MAX(id) FROM offers')->fetchColumn();
    }

    private function value(string $sql): mixed
    {
        return $this->db()->query($sql)->fetchColumn();
    }

    // --- oprávnění ----------------------------------------------------------------------

    public function testNonAdminsCannotReachAdministration(): void
    {
        $offer = $this->producerOffer();
        $school = $this->createUser('school');
        foreach ([[$this->producer, 'producer'], [$school, 'school']] as [$id, $role]) {
            $this->as($id, 'admin'); // ani podvržená role v session nepomůže
            foreach (['/admin', '/admin/zaznamy', '/admin/uzivatele', "/admin/nabidky/{$offer}", "/admin/uzivatele/{$this->producer}"] as $path) {
                self::assertSame(403, $this->get($path)->status, "{$role} GET {$path}");
            }
            self::assertSame(403, $this->post("/admin/nabidky/{$offer}/stahnout", ['reason' => 'Pokus o zneužití'])->status);
            self::assertSame(403, $this->post("/admin/uzivatele/{$this->producer}/blokovat")->status);
        }
        self::assertSame('active', $this->value("SELECT status FROM offers WHERE id = {$offer}"));
        self::assertSame('active', $this->value("SELECT status FROM users WHERE id = {$this->producer}"));
    }

    public function testAdminCannotEditListingContent(): void
    {
        $offer = $this->producerOffer();
        self::assertSame(405, $this->post("/admin/nabidky/{$offer}", Fixtures::offerForm(['product_name' => 'Přepsáno']))->status);
        self::assertSame(403, $this->post("/moje-nabidky/{$offer}", Fixtures::offerForm(['product_name' => 'Přepsáno']))->status);
        self::assertSame('Brambory Adéla', $this->value("SELECT product_name FROM offers WHERE id = {$offer}"));
    }

    // --- přehled nových a upravených -----------------------------------------------------------

    public function testOverviewShowsChangesSinceLastReview(): void
    {
        $offer = $this->producerOffer();
        $this->as($this->admin, 'admin');
        self::assertStringContainsString('Brambory Adéla', $this->get('/admin/zaznamy')->body);
        self::assertStringContainsString('<b>1</b>', $this->get('/admin')->body);

        $this->post('/admin/zaznamy/prohlednuto');
        self::assertStringNotContainsString('Brambory Adéla', $this->get('/admin/zaznamy')->body);

        // Úprava autorem se v přehledu znovu objeví.
        $this->db()->exec("UPDATE users SET last_review_seen_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = {$this->admin}");
        $this->db()->exec("UPDATE offers SET created_at = created_at - INTERVAL 1 HOUR WHERE id = {$offer}");
        $this->as($this->producer, 'producer');
        $this->post("/moje-nabidky/{$offer}", Fixtures::offerForm(['unit_price' => '15']));
        $this->as($this->admin, 'admin');
        $body = $this->get('/admin/zaznamy')->body;
        self::assertStringContainsString('Brambory Adéla', $body);
        self::assertStringContainsString('upravený', $body);
    }

    // --- stažení a uvolnění ----------------------------------------------------------------------

    public function testWithdrawRequiresReason(): void
    {
        $offer = $this->producerOffer();
        $response = $this->post("/admin/nabidky/{$offer}/stahnout", ['reason' => '  ']);
        self::assertSame(422, $response->status);
        self::assertStringContainsString('Napište důvod stažení', $response->body);
        self::assertSame('active', $this->value("SELECT status FROM offers WHERE id = {$offer}"));
    }

    public function testWithdrawAndReleaseCycle(): void
    {
        $offer = $this->producerOffer();
        $this->post("/admin/nabidky/{$offer}/stahnout", ['reason' => 'Popis obsahuje odkaz na e-shop.']);

        $row = $this->db()->query("SELECT * FROM offers WHERE id = {$offer}")->fetch();
        self::assertSame(['withdrawn', 'Popis obsahuje odkaz na e-shop.', $this->admin], [$row['status'], $row['withdrawal_reason'], (int) $row['withdrawn_by']]);
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM audit_log WHERE action = 'offer.withdrawn' AND target_id = {$offer}"));

        // Autor opraví -> čeká na uvolnění; v přehledu "k uvolnění".
        $this->as($this->producer, 'producer');
        $this->post("/moje-nabidky/{$offer}", Fixtures::offerForm(['description' => 'Bez odkazu']));
        $this->as($this->admin, 'admin');
        self::assertStringContainsString('Brambory Adéla', $this->get('/admin/zaznamy', ['filtr' => 'k-uvolneni'])->body);
        $detail = $this->get("/admin/nabidky/{$offer}")->body;
        self::assertStringContainsString('Autor záznam opravil', $detail);
        self::assertStringContainsString('staženo správcem', $detail, 'historie z auditu');

        $this->post("/admin/nabidky/{$offer}/uvolnit");
        $row = $this->db()->query("SELECT status, withdrawal_reason FROM offers WHERE id = {$offer}")->fetch();
        self::assertSame(['active', null], [$row['status'], $row['withdrawal_reason']]);
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM audit_log WHERE action = 'offer.released'"));
    }

    public function testOnlyActiveListingCanBeWithdrawnAndOnlyWithdrawnReleased(): void
    {
        $offer = $this->producerOffer();
        $this->db()->exec("UPDATE offers SET status = 'ended' WHERE id = {$offer}");
        $this->post("/admin/nabidky/{$offer}/stahnout", ['reason' => 'Nevhodný obsah záznamu']);
        self::assertSame('ended', $this->value("SELECT status FROM offers WHERE id = {$offer}"));
        $this->post("/admin/nabidky/{$offer}/uvolnit");
        self::assertSame('ended', $this->value("SELECT status FROM offers WHERE id = {$offer}"), 'uvolnění neobnoví ukončený záznam');
    }

    public function testDemandsCanBeWithdrawnToo(): void
    {
        $school = $this->createUser('school');
        $this->as($school, 'school');
        $this->post('/moje-poptavky', Fixtures::demandForm());
        $demand = (int) $this->value('SELECT MAX(id) FROM demands');
        $this->as($this->admin, 'admin');

        self::assertStringContainsString('Jablka', $this->get("/admin/poptavky/{$demand}")->body);
        $this->post("/admin/poptavky/{$demand}/stahnout", ['reason' => 'Duplicitní poptávka']);
        self::assertSame('withdrawn', $this->value("SELECT status FROM demands WHERE id = {$demand}"));
    }

    public function testDeletedListingIsNotFoundForAdmin(): void
    {
        $offer = $this->producerOffer();
        $this->db()->exec("UPDATE offers SET deleted_at = UTC_TIMESTAMP() WHERE id = {$offer}");
        self::assertSame(404, $this->get("/admin/nabidky/{$offer}")->status);
    }

    // --- uživatelé --------------------------------------------------------------------------------

    public function testBlockRestoreAndArchive(): void
    {
        self::assertStringContainsString('Uživatelé', $this->get('/admin/uzivatele')->body);

        $this->post("/admin/uzivatele/{$this->producer}/blokovat");
        self::assertSame('blocked', $this->value("SELECT status FROM users WHERE id = {$this->producer}"));

        // Zablokovaný uživatel je okamžitě odhlášen.
        $this->as($this->producer, 'producer');
        self::assertSame('/prihlaseni', $this->get('/moje-nabidky')->headers['Location']);

        $this->as($this->admin, 'admin');
        $this->post("/admin/uzivatele/{$this->producer}/obnovit");
        self::assertSame('active', $this->value("SELECT status FROM users WHERE id = {$this->producer}"));

        $this->post("/admin/uzivatele/{$this->producer}/archivovat");
        self::assertSame('archived', $this->value("SELECT status FROM users WHERE id = {$this->producer}"));
        self::assertSame(3, (int) $this->value("SELECT COUNT(*) FROM audit_log WHERE target_type = 'user' AND target_id = {$this->producer}"));
    }

    public function testRestoredUserWithoutProfileMustCompleteIt(): void
    {
        $user = $this->createUser('school', ['status' => 'blocked']);
        $this->post("/admin/uzivatele/{$user}/obnovit");
        self::assertSame('profile_incomplete', $this->value("SELECT status FROM users WHERE id = {$user}"));
    }

    public function testAdminAccountsCannotBeBlocked(): void
    {
        $other = $this->createUser('admin');
        $this->post("/admin/uzivatele/{$other}/blokovat");
        $this->post("/admin/uzivatele/{$this->admin}/blokovat");
        self::assertSame(['active', 'active'], [
            $this->value("SELECT status FROM users WHERE id = {$other}"),
            $this->value("SELECT status FROM users WHERE id = {$this->admin}"),
        ]);
    }

    // --- číselníky ----------------------------------------------------------------------------

    public function testCatalogsAreNotEditableInAdministration(): void
    {
        // Kategorie a jednotky jsou pevné (ROZHODNUTI č. 8), správa číselníků v administraci není.
        self::assertSame(404, $this->get('/admin/ciselniky')->status);
        self::assertSame(404, $this->post('/admin/kategorie', ['name' => 'Byliny'])->status);
        self::assertSame(404, $this->post('/admin/jednotky', ['code' => 'bal', 'label' => 'balení'])->status);
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM categories WHERE name = 'Byliny'"));
        self::assertStringNotContainsString('Číselníky', $this->get('/admin')->body);
    }
}
