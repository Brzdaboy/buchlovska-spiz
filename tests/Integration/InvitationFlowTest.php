<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;
use Tests\Support\FakeLogto;

/** Pozvánky: administrace -> Logto -> odkaz z e-mailu -> registrace -> účet. */
final class InvitationFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private FakeLogto $logto;
    private App $app;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logto = new FakeLogto();
        $this->app = $this->newApp();
        $this->adminId = $this->createUser('admin', ['logto_user_id' => 'logto-admin', 'email' => 'admin@example.cz']);
    }

    private function newApp(array $logto = []): App
    {
        return AppFactory::create([
            'db' => self::dbConfig(),
            'app' => ['var_dir' => sys_get_temp_dir() . '/bspiz-test-var-' . getmypid()],
            'logto' => $logto + [
                'endpoint' => FakeLogto::ENDPOINT,
                'app_id' => FakeLogto::CLIENT_ID,
                'app_secret' => FakeLogto::CLIENT_SECRET,
                'm2m_app_id' => 'm2m-id',
                'm2m_app_secret' => 'm2m-secret',
                'management_resource' => 'https://default.logto.app/api',
                'members_organization_id' => 'org-clenove',
            ],
        ], $this->logto);
    }

    private function signInAs(int $userId, string $role): void
    {
        AppFactory::$sessionData['user_id'] = $userId;
        AppFactory::$sessionData['user_role'] = $role;
    }

    private function post(string $path, array $form = []): Response
    {
        $this->app->handle(new Request('GET', '/')); // zajistí CSRF token v session
        return $this->app->handle(new Request('POST', $path, [], $form + ['_csrf' => $this->app->csrf()->token()]));
    }

    private function invite(string $email = 'farma@example.cz', string $role = 'producer'): Response
    {
        $this->signInAs($this->adminId, 'admin');
        return $this->post('/admin/pozvanky', ['email' => $email, 'role' => $role]);
    }

    private function flashes(): array
    {
        return array_column(AppFactory::$sessionData['_flash'] ?? [], 'message', 'type');
    }

    private function invitationRow(string $email): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM invitations WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: [];
    }

    // --- vytvoření -------------------------------------------------------------

    public function testAdminInvitesProducer(): void
    {
        $response = $this->invite('Farma@Example.cz');

        self::assertSame('/admin/pozvanky', $response->headers['Location']);
        self::assertStringContainsString('byla odeslána', $this->flashes()['success'] ?? '');

        $user = $this->db()->query("SELECT * FROM users WHERE email = 'farma@example.cz'")->fetch();
        self::assertSame(['producer', 'invited', null], [$user['role'], $user['status'], $user['logto_user_id']]);

        $inv = $this->invitationRow('farma@example.cz');
        self::assertSame('pending', $inv['status']);
        self::assertSame('ott-1', $inv['logto_one_time_token_id']);
        self::assertSame('inv-2', $inv['logto_invitation_id']);
        $validity = strtotime($inv['expires_at'] . ' UTC') - strtotime($inv['created_at'] . ' UTC');
        self::assertEqualsWithDelta(7 * 86400, $validity, 5, 'pozvánka platí 7 dní');

        // Logto: jednorázový token na 7 dní a pozvánka s odkazem na náš web
        $ott = $this->logto->managementCalls('POST', '/api/one-time-tokens')[0]['json'];
        self::assertSame(['email' => 'farma@example.cz', 'expiresIn' => 604800], $ott);
        $orgInv = $this->logto->managementCalls('POST', '/api/organization-invitations')[0]['json'];
        self::assertSame('org-clenove', $orgInv['organizationId']);
        self::assertSame('farma@example.cz', $orgInv['invitee']);
        self::assertSame(
            'https://spiz.test/pozvanka?id=' . $inv['id'] . '&token=TOKEN1abcdefgh',
            $orgInv['messagePayload']['link']
        );
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'invitation.created'")->fetchColumn());
    }

    public function testM2mCredentialsAreUsedForManagementApi(): void
    {
        $this->invite();
        $tokenRequest = array_values(array_filter($this->logto->requests, static fn ($r) => ($r['form']['grant_type'] ?? '') === 'client_credentials'))[0];
        self::assertSame('Basic ' . base64_encode('m2m-id:m2m-secret'), $tokenRequest['headers']['Authorization']);
        self::assertSame('https://default.logto.app/api', $tokenRequest['form']['resource']);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidInvitations(): iterable
    {
        yield 'neplatný e-mail' => ['neni-email', 'producer', 'platnou e-mailovou'];
        yield 'role admin' => ['x@example.cz', 'admin', 'Vyberte roli'];
        yield 'prázdná role' => ['x@example.cz', '', 'Vyberte roli'];
    }

    /** @dataProvider invalidInvitations */
    public function testInvalidInputIsRejected(string $email, string $role, string $message): void
    {
        $this->invite($email, $role);
        self::assertStringContainsString($message, $this->flashes()['error'] ?? '');
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM invitations')->fetchColumn());
        self::assertSame([], $this->logto->managementCalls('POST', '/api/'));
    }

    public function testExistingAccountCannotBeInvitedAgain(): void
    {
        $this->createUser('school', ['email' => 'skola@example.cz']);
        $this->invite('skola@example.cz', 'school');
        self::assertStringContainsString('už má účet', $this->flashes()['error'] ?? '');
    }

    public function testDuplicatePendingInvitationIsRejected(): void
    {
        $this->invite();
        AppFactory::$sessionData['_flash'] = [];
        $this->invite();
        self::assertStringContainsString('Poslat znovu', $this->flashes()['error'] ?? '');
        self::assertSame(1, (int) $this->db()->query('SELECT COUNT(*) FROM invitations')->fetchColumn());
    }

    public function testLogtoFailureLeavesNoHalfCreatedInvitation(): void
    {
        $this->logto->failOrganizationInvitation = true;
        $this->invite();

        self::assertStringContainsString('nepodařilo odeslat', $this->flashes()['error'] ?? '');
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM invitations')->fetchColumn());
        self::assertSame(0, (int) $this->db()->query("SELECT COUNT(*) FROM users WHERE role = 'producer'")->fetchColumn());
        self::assertCount(1, $this->logto->managementCalls('PUT', '/api/one-time-tokens/ott-1/status'), 'vytvořený token se v Logto zneplatní');
    }

    // --- znovu odeslat a zrušit --------------------------------------------------

    public function testResendReplacesInvitation(): void
    {
        $this->invite();
        $old = $this->invitationRow('farma@example.cz');

        $this->post('/admin/pozvanky/' . $old['id'] . '/znovu');

        $oldNow = $this->db()->query('SELECT status FROM invitations WHERE id = ' . (int) $old['id'])->fetchColumn();
        self::assertSame('revoked', $oldNow);
        $new = $this->invitationRow('farma@example.cz');
        self::assertNotSame($old['id'], $new['id']);
        self::assertSame('pending', $new['status']);
        self::assertCount(1, $this->logto->managementCalls('PUT', '/api/one-time-tokens/' . $old['logto_one_time_token_id'] . '/status'));
        self::assertCount(1, $this->logto->managementCalls('PUT', '/api/organization-invitations/' . $old['logto_invitation_id'] . '/status'));
        self::assertCount(2, $this->logto->managementCalls('POST', '/api/organization-invitations'), 'nový e-mail');
    }

    public function testFailedResendKeepsOriginalInvitationValid(): void
    {
        $this->invite();
        $old = $this->invitationRow('farma@example.cz');
        $this->logto->failOrganizationInvitation = true;

        $this->post('/admin/pozvanky/' . $old['id'] . '/znovu');

        self::assertSame('pending', $this->db()->query('SELECT status FROM invitations WHERE id = ' . (int) $old['id'])->fetchColumn());
        self::assertSame(1, (int) $this->db()->query('SELECT COUNT(*) FROM invitations')->fetchColumn());
    }

    public function testExpiredInvitationCanBeResent(): void
    {
        $this->invite();
        $old = $this->invitationRow('farma@example.cz');
        $this->db()->exec("UPDATE invitations SET created_at = UTC_TIMESTAMP() - INTERVAL 9 DAY, expires_at = UTC_TIMESTAMP() - INTERVAL 2 DAY WHERE id = {$old['id']}");

        $page = $this->app->handle(new Request('GET', '/admin/pozvanky'));
        self::assertStringContainsString('Vypršela', $page->body);

        $this->post('/admin/pozvanky/' . $old['id'] . '/znovu');
        self::assertSame('pending', $this->invitationRow('farma@example.cz')['status']);
    }

    public function testRevoke(): void
    {
        $this->invite();
        $inv = $this->invitationRow('farma@example.cz');

        $this->post('/admin/pozvanky/' . $inv['id'] . '/zrusit');

        self::assertSame('revoked', $this->invitationRow('farma@example.cz')['status']);
        self::assertCount(1, $this->logto->managementCalls('PUT', '/api/organization-invitations/' . $inv['logto_invitation_id'] . '/status'));
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'invitation.revoked'")->fetchColumn());
    }

    // --- oprávnění -------------------------------------------------------------------

    public function testOnlyActiveAdminCanManageInvitations(): void
    {
        $producer = $this->createUser('producer');
        $this->signInAs($producer, 'admin'); // podvržená role v session nic nezmění
        self::assertSame(403, $this->app->handle(new Request('GET', '/admin/pozvanky'))->status);
        self::assertSame(403, $this->post('/admin/pozvanky', ['email' => 'x@example.cz', 'role' => 'producer'])->status);
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM invitations')->fetchColumn());

        AppFactory::$sessionData = [];
        $anonymous = $this->app->handle(new Request('GET', '/admin/pozvanky'));
        self::assertSame('/prihlaseni', $anonymous->headers['Location']);
    }

    public function testListEscapesEmails(): void
    {
        $this->invite('"><script>x</script>@example.cz');
        // neplatný e-mail se ani neuloží; ověříme escapování na platné adrese s apostrofem
        $this->invite("o'neil@example.cz");
        $body = $this->app->handle(new Request('GET', '/admin/pozvanky'))->body;
        self::assertStringContainsString('o&apos;neil@example.cz', $body);
        self::assertStringNotContainsString('<script>x', $body);
    }

    // --- odkaz z e-mailu a registrace ---------------------------------------------------

    public function testInvitationLinkLeadsToLogtoWithOneTimeToken(): void
    {
        $this->invite();
        $inv = $this->invitationRow('farma@example.cz');
        AppFactory::$sessionData = [];

        $response = $this->app->handle(new Request('GET', '/pozvanka', ['id' => (string) $inv['id'], 'token' => 'TOKEN1abcdefgh']));

        self::assertSame(303, $response->status);
        parse_str((string) parse_url($response->headers['Location'], PHP_URL_QUERY), $q);
        self::assertSame('TOKEN1abcdefgh', $q['one_time_token']);
        self::assertSame('farma@example.cz', $q['login_hint']);
    }

    public function testFullRegistrationThroughInvitation(): void
    {
        $this->invite();
        $inv = $this->invitationRow('farma@example.cz');
        AppFactory::$sessionData = [];

        $redirect = $this->app->handle(new Request('GET', '/pozvanka', ['id' => (string) $inv['id'], 'token' => 'TOKEN1abcdefgh']));
        parse_str((string) parse_url($redirect->headers['Location'], PHP_URL_QUERY), $q);
        $this->logto->nextClaims = $this->logto->defaultClaims($q['nonce'], ['sub' => 'logto-nova-farma', 'email' => 'farma@example.cz']);
        $callback = $this->app->handle(new Request('GET', '/callback', ['code' => 'c', 'state' => $q['state']]));

        self::assertSame('/portal', $callback->headers['Location']);
        self::assertSame('accepted', $this->invitationRow('farma@example.cz')['status']);
        $accept = $this->logto->managementCalls('PUT', '/api/organization-invitations/' . $inv['logto_invitation_id'] . '/status');
        self::assertSame(['status' => 'Accepted', 'acceptedUserId' => 'logto-nova-farma'], $accept[0]['json'] ?? null);

        // Odkaz z e-mailu už znovu nepustí do registrace.
        $again = $this->app->handle(new Request('GET', '/pozvanka', ['id' => (string) $inv['id'], 'token' => 'TOKEN1abcdefgh']));
        self::assertSame('/prihlaseni', $again->headers['Location']);
    }

    /** @return iterable<string, array{string}> */
    public static function unusableStates(): iterable
    {
        yield 'zrušená' => ['revoked'];
        yield 'prošlá' => ['expired-by-date'];
    }

    /** @dataProvider unusableStates */
    public function testUnusableInvitationLinkShowsExplanation(string $state): void
    {
        $this->invite();
        $inv = $this->invitationRow('farma@example.cz');
        if ($state === 'revoked') {
            $this->db()->exec("UPDATE invitations SET status = 'revoked', revoked_at = UTC_TIMESTAMP() WHERE id = {$inv['id']}");
        } else {
            $this->db()->exec("UPDATE invitations SET created_at = UTC_TIMESTAMP() - INTERVAL 9 DAY, expires_at = UTC_TIMESTAMP() - INTERVAL 1 DAY WHERE id = {$inv['id']}");
        }
        $response = $this->app->handle(new Request('GET', '/pozvanka', ['id' => (string) $inv['id'], 'token' => 'TOKEN1abcdefgh']));
        self::assertSame(410, $response->status);
        self::assertStringContainsString('Pozvánku nelze použít', $response->body);
    }

    public function testMalformedInvitationLink(): void
    {
        self::assertSame(410, $this->app->handle(new Request('GET', '/pozvanka', ['id' => '1 OR 1=1', 'token' => 'x']))->status);
        self::assertSame(410, $this->app->handle(new Request('GET', '/pozvanka', ['id' => '999999', 'token' => 'abcdefghij']))->status);
    }
}
