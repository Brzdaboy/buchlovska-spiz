<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;
use Tests\Support\Fixtures;

/** Dokončení a úprava profilu přes skutečné HTTP požadavky aplikace a MariaDB. */
final class ProfileFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create(['db' => self::dbConfig()]);
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

    private function post(string $path, array $form): Response
    {
        $this->get('/');
        return $this->app->handle(new Request('POST', $path, [], $form + ['_csrf' => $this->app->csrf()->token()]));
    }

    private function newMember(string $role = 'producer'): int
    {
        $id = $this->createUser($role, ['status' => 'profile_incomplete']);
        $this->signInAs($id, $role);
        return $id;
    }

    private function status(int $id): string
    {
        return (string) $this->db()->query("SELECT status FROM users WHERE id = {$id}")->fetchColumn();
    }

    public function testIncompleteProfileIsRedirectedFromPortal(): void
    {
        $this->newMember();
        self::assertSame('/profil', $this->get('/portal')->headers['Location']);
    }

    public function testProducerCompletesProfile(): void
    {
        $id = $this->newMember();

        $response = $this->post('/profil', Fixtures::producerProfile(['website' => 'farma.cz']));

        self::assertSame('/portal', $response->headers['Location']);
        self::assertSame('active', $this->status($id));
        $profile = $this->db()->query("SELECT * FROM producer_profiles WHERE user_id = {$id}")->fetch();
        self::assertSame('68708', $profile['postal_code']);
        self::assertSame('https://farma.cz', $profile['website']);
        self::assertNotNull($profile['privacy_consent_at']);
        self::assertNotNull($profile['contact_publication_consent_at']);
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'profile.completed'")->fetchColumn());

        $portal = $this->get('/portal');
        self::assertSame(200, $portal->status);
        self::assertStringContainsString('Farma Pod Barborkou', $portal->body);
        self::assertStringContainsString('687 08 Buchlovice', $portal->body);
    }

    public function testSchoolCompletesProfile(): void
    {
        $id = $this->newMember('school');
        $form = $this->get('/profil')->body;
        self::assertStringContainsString('name="school_name"', $form);
        self::assertStringNotContainsString('name="producer_name"', $form);

        $this->post('/profil', [
            'school_name' => 'ZŠ a MŠ Buchlovice', 'contact_name' => 'Marie Malá', 'address_line' => 'Školní 1',
            'municipality' => 'Buchlovice', 'postal_code' => '68708', 'phone' => '572 000 000',
            'public_email' => 'jidelna@zs.cz', 'privacy_consent' => '1', 'contact_publication_consent' => '1',
        ]);
        self::assertSame('active', $this->status($id));
        self::assertSame('ZŠ a MŠ Buchlovice', $this->db()->query("SELECT school_name FROM school_profiles WHERE user_id = {$id}")->fetchColumn());
    }

    public function testInvalidFormIsRedisplayedWithErrorsAndNothingSaved(): void
    {
        $id = $this->newMember();
        $response = $this->post('/profil', Fixtures::producerProfile([
            'postal_code' => 'abc', 'producer_name' => '<script>alert(1)</script>', 'privacy_consent' => '',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('PSČ musí mít 5 číslic', $response->body);
        self::assertStringContainsString('aria-invalid="true"', $response->body);
        self::assertStringContainsString('value="&lt;script&gt;alert(1)&lt;/script&gt;"', $response->body, 'zadaná hodnota zůstane, escapovaná');
        self::assertStringNotContainsString('<script>alert(1)', $response->body);
        self::assertSame('profile_incomplete', $this->status($id));
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM producer_profiles')->fetchColumn());
    }

    public function testEditingKeepsConsentsAndPublishesImmediately(): void
    {
        $id = $this->newMember();
        $this->post('/profil', Fixtures::producerProfile());
        $consent = $this->db()->query("SELECT privacy_consent_at FROM producer_profiles WHERE user_id = {$id}")->fetchColumn();

        $form = $this->get('/profil')->body;
        self::assertStringContainsString('Můj profil', $form);
        self::assertStringNotContainsString('name="privacy_consent"', $form, 'souhlasy se při úpravě znovu nevyžadují');

        $this->post('/profil', Fixtures::producerProfile(['phone' => '777 111 222', 'privacy_consent' => '', 'contact_publication_consent' => '']));

        $row = $this->db()->query("SELECT phone, privacy_consent_at FROM producer_profiles WHERE user_id = {$id}")->fetch();
        self::assertSame('777 111 222', $row['phone']);
        self::assertSame($consent, $row['privacy_consent_at']);
        self::assertSame('active', $this->status($id));
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'profile.updated'")->fetchColumn());
    }

    public function testDuplicateCompanyIdShowsFriendlyError(): void
    {
        $other = $this->newMember();
        $this->post('/profil', Fixtures::producerProfile(['company_id' => '00006947']));

        $id = $this->newMember();
        $response = $this->post('/profil', Fixtures::producerProfile(['company_id' => '00006947']));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('už v Buchlovské spíži má účet', $response->body);
        self::assertSame('profile_incomplete', $this->status($id));
        self::assertSame('active', $this->status($other));
    }

    public function testAdminHasNoOrganizationProfile(): void
    {
        $this->signInAs($this->createUser('admin'), 'admin');
        self::assertSame('/admin', $this->get('/profil')->headers['Location']);
    }

    public function testProfileRequiresLoginAndCsrf(): void
    {
        self::assertSame('/prihlaseni', $this->get('/profil')->headers['Location']);
        $this->newMember();
        self::assertSame(419, $this->app->handle(new Request('POST', '/profil', [], Fixtures::producerProfile()))->status);
    }

    public function testRoleCannotBeChangedViaForm(): void
    {
        $id = $this->newMember('producer');
        $this->post('/profil', Fixtures::producerProfile(['role' => 'admin', 'status' => 'active', 'school_name' => 'X']));
        $user = $this->db()->query("SELECT role FROM users WHERE id = {$id}")->fetch();
        self::assertSame('producer', $user['role']);
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM school_profiles')->fetchColumn());
    }
}
