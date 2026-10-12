<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;
use Tests\Support\Fixtures;

/** Správa vlastních poptávek školy: oprávnění, vlastnictví, stavy, audit. */
final class DemandFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private App $app;
    private int $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create(['db' => self::dbConfig()]);
        $this->school = $this->createUser('school');
        $this->signInAs($this->school, 'school');
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

    private function createDemand(array $overrides = []): int
    {
        $this->post('/moje-poptavky', Fixtures::demandForm($overrides));
        return (int) $this->db()->query('SELECT MAX(id) FROM demands')->fetchColumn();
    }

    private function demand(int $id): array
    {
        return $this->db()->query("SELECT * FROM demands WHERE id = {$id}")->fetch() ?: [];
    }

    public function testSchoolCreatesDemandThatIsActiveImmediately(): void
    {
        $response = $this->post('/moje-poptavky', Fixtures::demandForm());
        self::assertSame('/moje-poptavky', $response->headers['Location']);

        $demand = $this->demand((int) $this->db()->query('SELECT MAX(id) FROM demands')->fetchColumn());
        self::assertSame(['active', $this->school, '120.000'], [$demand['status'], (int) $demand['school_user_id'], $demand['requested_quantity']]);
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'demand.created'")->fetchColumn());

        $list = $this->get('/moje-poptavky')->body;
        self::assertStringContainsString('Jablka', $list);
        self::assertStringContainsString('120&nbsp;kg', $list);
        self::assertStringNotContainsString('Kč', $list, 'poptávka nemá cenu');
    }

    public function testInvalidDemandIsRedisplayed(): void
    {
        $response = $this->post('/moje-poptavky', Fixtures::demandForm(['needed_until' => '']));
        self::assertSame(422, $response->status);
        self::assertStringContainsString('do kdy produkt potřebujete', $response->body);
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM demands')->fetchColumn());
    }

    public function testProducerAndAdminCannotManageDemands(): void
    {
        foreach (['producer', 'admin'] as $role) {
            $this->signInAs($this->createUser($role), $role);
            self::assertSame(403, $this->get('/moje-poptavky')->status, $role);
            self::assertSame(403, $this->post('/moje-poptavky', Fixtures::demandForm())->status, $role);
        }
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM demands')->fetchColumn());
    }

    public function testOtherSchoolsDemandIsUntouchable(): void
    {
        $id = $this->createDemand();
        $this->signInAs($this->createUser('school'), 'school');

        self::assertSame(404, $this->get("/moje-poptavky/{$id}/upravit")->status);
        self::assertSame(404, $this->post("/moje-poptavky/{$id}", Fixtures::demandForm(['product_name' => 'Cizí']))->status);
        self::assertSame(404, $this->post("/moje-poptavky/{$id}/ukoncit")->status);
        self::assertSame(404, $this->post("/moje-poptavky/{$id}/smazat", ['confirm' => '1'])->status);
        self::assertSame(['Jablka', 'active', null], [$this->demand($id)['product_name'], $this->demand($id)['status'], $this->demand($id)['deleted_at']]);
    }

    public function testStatusCannotBeInjected(): void
    {
        $id = $this->createDemand(['status' => 'withdrawn', 'school_user_id' => '999']);
        self::assertSame(['active', $this->school], [$this->demand($id)['status'], (int) $this->demand($id)['school_user_id']]);
    }

    public function testEditingWithdrawnDemandWaitsForRelease(): void
    {
        $id = $this->createDemand();
        $admin = $this->createUser('admin');
        $this->db()->exec("UPDATE demands SET status = 'withdrawn', withdrawn_by = {$admin}, withdrawn_at = UTC_TIMESTAMP(), withdrawal_reason = 'Duplicitní poptávka' WHERE id = {$id}");

        self::assertStringContainsString('Duplicitní poptávka', $this->get('/moje-poptavky')->body);
        $this->post("/moje-poptavky/{$id}", Fixtures::demandForm(['note' => 'Opraveno']));
        self::assertSame('resubmitted', $this->demand($id)['status']);
    }

    public function testDemandExpiresWithItsPeriodAndNeedsNewPeriodToReactivate(): void
    {
        $id = $this->createDemand();
        $this->db()->exec("UPDATE demands SET needed_from = CURDATE() - INTERVAL 20 DAY, needed_until = CURDATE() - INTERVAL 1 DAY WHERE id = {$id}");

        self::assertStringContainsString('Vypršelo', $this->get('/moje-poptavky')->body);
        self::assertStringContainsString('už skončilo', $this->get("/moje-poptavky/{$id}/obnovit")->body);

        // Odeslání s původním (prošlým) obdobím neprojde.
        $old = $this->demand($id);
        self::assertSame(422, $this->post("/moje-poptavky/{$id}/obnovit", ['needed_from' => $old['needed_from'], 'needed_until' => $old['needed_until']])->status);

        $until = date('Y-m-d', strtotime('+14 days'));
        $this->post("/moje-poptavky/{$id}/obnovit", ['needed_from' => date('Y-m-d'), 'needed_until' => $until]);
        self::assertSame(['active', $until], [$this->demand($id)['status'], $this->demand($id)['needed_until']]);
    }

    public function testEndAndReactivate(): void
    {
        $id = $this->createDemand();
        $this->post("/moje-poptavky/{$id}/ukoncit");
        self::assertSame('ended', $this->demand($id)['status']);

        $d = $this->demand($id);
        $this->post("/moje-poptavky/{$id}/obnovit", ['needed_from' => $d['needed_from'], 'needed_until' => $d['needed_until']]);
        self::assertSame('active', $this->demand($id)['status']);
    }

    public function testDeleteRequiresConfirmationAndIsSoft(): void
    {
        $id = $this->createDemand();
        $this->post("/moje-poptavky/{$id}/smazat");
        self::assertNull($this->demand($id)['deleted_at']);
        $this->post("/moje-poptavky/{$id}/smazat", ['confirm' => '1']);
        self::assertNotNull($this->demand($id)['deleted_at']);
        self::assertSame(404, $this->get("/moje-poptavky/{$id}/upravit")->status);
    }
}
