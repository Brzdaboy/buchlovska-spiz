<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;
use Tests\Support\Fixtures;

/** Správa vlastních nabídek: oprávnění, vlastnictví, stavy, audit. */
final class OfferFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private App $app;
    private int $producer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create(['db' => self::dbConfig()]);
        $this->producer = $this->createUser('producer');
        $this->signInAs($this->producer, 'producer');
    }

    private function signInAs(int $userId, string $role): void
    {
        AppFactory::$sessionData['user_id'] = $userId;
        AppFactory::$sessionData['user_role'] = $role;
    }

    private function get(string $path): Response
    {
        return $this->app->handle(new Request('GET', $path));
    }

    private function post(string $path, array $form = []): Response
    {
        $this->get('/');
        return $this->app->handle(new Request('POST', $path, [], $form + ['_csrf' => $this->app->csrf()->token()]));
    }

    private function createOffer(array $overrides = []): int
    {
        $this->post('/moje-nabidky', Fixtures::offerForm($overrides));
        return (int) $this->db()->query('SELECT MAX(id) FROM offers')->fetchColumn();
    }

    private function offer(int $id): array
    {
        return $this->db()->query("SELECT * FROM offers WHERE id = {$id}")->fetch() ?: [];
    }

    private function auditCount(string $action): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = '{$action}'")->fetchColumn();
    }

    // --- vytvoření ----------------------------------------------------------------

    public function testProducerCreatesOfferThatIsActiveImmediately(): void
    {
        $response = $this->post('/moje-nabidky', Fixtures::offerForm(['unit_price' => '14,5']));

        self::assertSame('/portal', $response->headers['Location']);
        $offer = $this->offer((int) $this->db()->query('SELECT MAX(id) FROM offers')->fetchColumn());
        self::assertSame('active', $offer['status'], 'bez předběžného schvalování');
        self::assertSame('14.50', $offer['unit_price']);
        self::assertSame('CZK', $offer['currency']);
        self::assertSame($this->producer, (int) $offer['producer_user_id']);
        self::assertSame(1, $this->auditCount('offer.created'));

        $list = $this->get('/portal')->body;
        self::assertStringContainsString('Brambory Adéla', $list);
        self::assertStringContainsString('14,50', $list);
        self::assertStringContainsString('Zveřejněno', $list);
    }

    public function testInvalidOfferIsRedisplayedAndNotSaved(): void
    {
        $response = $this->post('/moje-nabidky', Fixtures::offerForm(['unit_price' => 'dohodou', 'product_name' => '<b>x</b>']));
        self::assertSame(422, $response->status);
        self::assertStringContainsString('Cena „dohodou“ není možná', $response->body);
        self::assertStringContainsString('value="&lt;b&gt;x&lt;/b&gt;"', $response->body);
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM offers')->fetchColumn());
    }

    public function testStatusAndOwnerCannotBeInjectedViaForm(): void
    {
        $other = $this->createUser('producer');
        $id = $this->createOffer(['status' => 'withdrawn', 'producer_user_id' => (string) $other, 'currency' => 'EUR']);
        $offer = $this->offer($id);
        self::assertSame(['active', $this->producer, 'CZK'], [$offer['status'], (int) $offer['producer_user_id'], $offer['currency']]);
    }

    // --- oprávnění a vlastnictví ---------------------------------------------------

    public function testSchoolAndAdminCannotManageOffers(): void
    {
        foreach (['school', 'admin'] as $role) {
            $this->signInAs($this->createUser($role), $role);
            self::assertSame(403, $this->get('/moje-nabidky')->status, $role);
            self::assertSame(403, $this->post('/moje-nabidky', Fixtures::offerForm())->status, $role);
        }
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM offers')->fetchColumn());
    }

    public function testIncompleteProfileCannotCreateOffers(): void
    {
        $this->signInAs($this->createUser('producer', ['status' => 'profile_incomplete']), 'producer');
        self::assertSame('/profil', $this->get('/moje-nabidky/nova')->headers['Location']);
    }

    public function testOtherProducersOfferIsInvisibleAndUntouchable(): void
    {
        $id = $this->createOffer();
        $this->signInAs($this->createUser('producer'), 'producer');

        self::assertSame(404, $this->get("/moje-nabidky/{$id}/upravit")->status);
        self::assertSame(404, $this->post("/moje-nabidky/{$id}", Fixtures::offerForm(['product_name' => 'Ukradeno']))->status);
        self::assertSame(404, $this->post("/moje-nabidky/{$id}/ukoncit")->status);
        self::assertSame(404, $this->post("/moje-nabidky/{$id}/smazat", ['confirm' => '1'])->status);
        self::assertStringNotContainsString('Brambory', $this->get('/portal')->body);

        $offer = $this->offer($id);
        self::assertSame(['Brambory Adéla', 'active', null], [$offer['product_name'], $offer['status'], $offer['deleted_at']]);
    }

    // --- úpravy a stavy ------------------------------------------------------------------

    public function testEditPublishesImmediatelyAndAuditsChangedFields(): void
    {
        $id = $this->createOffer();
        $form = $this->get("/moje-nabidky/{$id}/upravit")->body;
        self::assertStringContainsString('value="14,50"', $form);
        self::assertStringContainsString('value="800"', $form);

        $this->post("/moje-nabidky/{$id}", Fixtures::offerForm(['unit_price' => '12', 'available_quantity' => '500']));

        $offer = $this->offer($id);
        self::assertSame(['12.00', '500.000', 'active'], [$offer['unit_price'], $offer['available_quantity'], $offer['status']]);
        $details = $this->db()->query("SELECT details FROM audit_log WHERE action = 'offer.updated'")->fetchColumn();
        self::assertSame(['changed' => 'unit_price,available_quantity', 'status' => 'active'], json_decode((string) $details, true));
    }

    public function testEditingWithdrawnOfferWaitsForRelease(): void
    {
        $id = $this->createOffer();
        $admin = $this->createUser('admin');
        $this->db()->exec("UPDATE offers SET status = 'withdrawn', withdrawn_by = {$admin}, withdrawn_at = UTC_TIMESTAMP(), withdrawal_reason = 'Nevhodný popis' WHERE id = {$id}");

        $list = $this->get('/portal')->body;
        self::assertStringContainsString('Staženo správcem', $list);
        self::assertStringContainsString('Nevhodný popis', $list);
        self::assertStringNotContainsString("/moje-nabidky/{$id}/obnovit", $list, 'stažený záznam nejde obnovit sám');

        $this->post("/moje-nabidky/{$id}", Fixtures::offerForm(['description' => 'Opravený popis']));
        self::assertSame('resubmitted', $this->offer($id)['status']);
        self::assertSame(400, $this->post("/moje-nabidky/{$id}/obnovit")->status, 'ani přímým požadavkem');
    }

    public function testEndAndReactivate(): void
    {
        $id = $this->createOffer();
        $this->post("/moje-nabidky/{$id}/ukoncit");
        self::assertSame('ended', $this->offer($id)['status']);
        self::assertNotNull($this->offer($id)['ended_at']);

        $this->post("/moje-nabidky/{$id}/obnovit");
        $offer = $this->offer($id);
        self::assertSame('active', $offer['status'], 'reaktivace je okamžitá');
        self::assertNull($offer['ended_at']);
        self::assertSame(1, $this->auditCount('offer.ended'));
        self::assertSame(1, $this->auditCount('offer.reactivated'));
    }

    public function testExpiredOfferNeedsNewDateOrNoneToReactivate(): void
    {
        $id = $this->createOffer();
        $this->db()->exec("UPDATE offers SET available_until = CURDATE() - INTERVAL 3 DAY WHERE id = {$id}");

        self::assertStringContainsString('Vypršelo', $this->get('/portal')->body);
        self::assertStringContainsString('už uplynul', $this->get("/moje-nabidky/{$id}/obnovit")->body);

        $past = $this->post("/moje-nabidky/{$id}/obnovit", ['available_until' => date('Y-m-d', strtotime('-1 day'))]);
        self::assertSame(422, $past->status);

        $future = date('Y-m-d', strtotime('+30 days'));
        $this->post("/moje-nabidky/{$id}/obnovit", ['available_until' => $future]);
        self::assertSame(['active', $future], [$this->offer($id)['status'], $this->offer($id)['available_until']]);
    }

    public function testExpiredOfferCanBeReactivatedWithoutEndDate(): void
    {
        $id = $this->createOffer();
        $this->db()->exec("UPDATE offers SET status = 'expired', available_until = CURDATE() - INTERVAL 3 DAY WHERE id = {$id}");
        $this->post("/moje-nabidky/{$id}/obnovit", ['available_until' => '']);
        self::assertSame(['active', null], [$this->offer($id)['status'], $this->offer($id)['available_until']]);
    }

    public function testActiveOfferCannotBeEndedTwiceOrReactivated(): void
    {
        $id = $this->createOffer();
        self::assertSame(400, $this->get("/moje-nabidky/{$id}/obnovit")->status);
        $this->post("/moje-nabidky/{$id}/ukoncit");
        $this->post("/moje-nabidky/{$id}/ukoncit");
        self::assertSame(1, $this->auditCount('offer.ended'));
    }

    // --- smazání ----------------------------------------------------------------------------

    public function testDeleteRequiresConfirmationAndIsSoft(): void
    {
        $id = $this->createOffer();
        self::assertStringContainsString('Smazat nabídku?', $this->get("/moje-nabidky/{$id}/smazat")->body);

        $this->post("/moje-nabidky/{$id}/smazat");
        self::assertNull($this->offer($id)['deleted_at'], 'bez potvrzení se nic nestane');

        $this->post("/moje-nabidky/{$id}/smazat", ['confirm' => '1']);
        self::assertNotNull($this->offer($id)['deleted_at'], 'řádek zůstává v DB (měkké smazání)');
        $list = $this->get('/portal')->body;
        self::assertStringContainsString('je smazaná', $list);
        self::assertStringNotContainsString("/moje-nabidky/{$id}/upravit", $list, 'v seznamu už není');
        self::assertSame(404, $this->get("/moje-nabidky/{$id}/upravit")->status);
        self::assertSame(1, $this->auditCount('offer.deleted'));
    }
}
