<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Repository\UserRepository;
use Tests\AppFactory;
use Tests\Support\FakeLogto;

/**
 * Celý průběh přihlášení: aplikace -> (falešné) Logto -> callback -> lokální účet.
 */
final class AuthFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private FakeLogto $logto;
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logto = new FakeLogto();
        $this->app = $this->newApp();
    }

    private function newApp(): App
    {
        return AppFactory::create([
            'db' => self::dbConfig(),
            'app' => ['var_dir' => sys_get_temp_dir() . '/bspiz-test-var-' . getmypid()],
            'logto' => [
                'endpoint' => FakeLogto::ENDPOINT,
                'app_id' => FakeLogto::CLIENT_ID,
                'app_secret' => FakeLogto::CLIENT_SECRET,
            ],
        ], $this->logto);
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    /** Spustí přihlášení a vrátí parametry, které aplikace poslala do Logto. */
    private function startLogin(): array
    {
        $response = $this->get('/prihlaseni');
        self::assertSame(303, $response->status);
        parse_str((string) parse_url($response->headers['Location'], PHP_URL_QUERY), $params);
        return $params;
    }

    /** Projde celé přihlášení s danými claims a vrátí odpověď callbacku. */
    private function loginAs(array $claimOverrides): Response
    {
        $params = $this->startLogin();
        $this->logto->nextClaims = $this->logto->defaultClaims($params['nonce'], $claimOverrides);
        return $this->get('/callback', ['code' => 'kod-123', 'state' => $params['state']]);
    }

    private function invite(string $email, string $role = 'producer', string $expiresAt = '+7 days'): int
    {
        $admin = $this->createUser('admin');
        $userId = $this->insert('users', ['email' => $email, 'role' => $role, 'status' => 'invited']);
        $this->insert('invitations', [
            'user_id' => $userId, 'email' => $email, 'role' => $role, 'invited_by' => $admin,
            'created_at' => gmdate('Y-m-d H:i:s', strtotime('-8 days')),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime($expiresAt)),
        ]);
        return $userId;
    }

    private function userRow(int $id): array
    {
        return (new UserRepository($this->db()))->findById($id) ?? [];
    }

    // --- začátek přihlášení -------------------------------------------------

    public function testLoginRedirectsToLogtoWithPkce(): void
    {
        $response = $this->get('/prihlaseni');
        self::assertSame(303, $response->status);
        self::assertStringStartsWith(FakeLogto::ENDPOINT . '/oidc/auth?', $response->headers['Location']);
        parse_str((string) parse_url($response->headers['Location'], PHP_URL_QUERY), $params);
        self::assertSame('https://spiz.test/callback', $params['redirect_uri']);
        self::assertSame('S256', $params['code_challenge_method']);
        self::assertGreaterThanOrEqual(43, strlen($params['state']));
    }

    // --- úspěšné přihlášení -------------------------------------------------

    public function testInvitedProducerIsLinkedAndSignedIn(): void
    {
        $userId = $this->invite('farma@example.cz');

        $response = $this->loginAs(['email' => 'Farma@Example.cz', 'sub' => 'logto-abc']);

        self::assertSame(303, $response->status);
        self::assertSame('/portal', $response->headers['Location']);
        $user = $this->userRow($userId);
        self::assertSame('logto-abc', $user['logto_user_id']);
        self::assertSame('profile_incomplete', $user['status']);
        self::assertNotNull($user['last_login_at']);
        self::assertSame('accepted', $this->db()->query("SELECT status FROM invitations WHERE user_id = {$userId}")->fetchColumn());
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM audit_log WHERE action = 'user.identity_linked' AND target_id = {$userId}")->fetchColumn());

        // Neúplný profil: portál pošle nejdřív na formulář profilu.
        $portal = $this->get('/portal');
        self::assertSame('/profil', $portal->headers['Location']);
        $profile = $this->get('/profil');
        self::assertSame(200, $profile->status);
        self::assertStringContainsString('Dokončení profilu', $profile->body);
        self::assertStringContainsString('farma@example.cz', $profile->body);
    }

    public function testReturningUserIsFoundByLogtoIdEvenWithChangedEmail(): void
    {
        $userId = $this->createUser('school', ['logto_user_id' => 'logto-skola', 'email' => 'skola@example.cz']);
        $response = $this->loginAs(['sub' => 'logto-skola', 'email' => 'nova-adresa@example.cz']);
        self::assertSame('/portal', $response->headers['Location']);
        self::assertSame($userId, AppFactory::$sessionData['user_id']);
    }

    public function testAdminCreatedByCliIsActivatedOnFirstLogin(): void
    {
        $id = (new UserRepository($this->db()))->createAdmin('spravce@example.cz');
        $this->loginAs(['sub' => 'logto-admin', 'email' => 'spravce@example.cz']);
        self::assertSame('active', $this->userRow($id)['status']);
    }

    // --- odmítnutí ------------------------------------------------------------

    public function testUnknownEmailIsDeniedAndNothingIsCreated(): void
    {
        $response = $this->loginAs(['email' => 'cizi@example.cz', 'sub' => 'logto-cizi']);
        self::assertSame(403, $response->status);
        self::assertStringContainsString('jen na pozvánku', $response->body);
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertArrayNotHasKey('user_id', AppFactory::$sessionData);
    }

    public function testExpiredInvitationIsDenied(): void
    {
        $userId = $this->invite('pozde@example.cz', 'school', '-1 hour');
        $response = $this->loginAs(['email' => 'pozde@example.cz', 'sub' => 'logto-pozde']);

        self::assertSame(403, $response->status);
        self::assertStringContainsString('vypršela', $response->body);
        self::assertNull($this->userRow($userId)['logto_user_id']);
        self::assertSame('expired', $this->db()->query("SELECT status FROM invitations WHERE user_id = {$userId}")->fetchColumn());
    }

    public function testUnverifiedEmailCannotClaimInvitation(): void
    {
        $userId = $this->invite('neovereno@example.cz');
        $response = $this->loginAs(['email' => 'neovereno@example.cz', 'email_verified' => false]);
        self::assertSame(403, $response->status);
        self::assertNull($this->userRow($userId)['logto_user_id']);
    }

    public function testInvitationCannotBeClaimedTwiceByAnotherIdentity(): void
    {
        $this->invite('farma@example.cz');
        $this->loginAs(['email' => 'farma@example.cz', 'sub' => 'logto-prvni']);

        $this->app = $this->newApp();
        $response = $this->loginAs(['email' => 'farma@example.cz', 'sub' => 'logto-druhy']);
        self::assertSame(403, $response->status);
    }

    public function testBlockedUserIsDenied(): void
    {
        $this->createUser('producer', ['logto_user_id' => 'logto-blok', 'status' => 'blocked']);
        $response = $this->loginAs(['sub' => 'logto-blok']);
        self::assertSame(403, $response->status);
        self::assertStringContainsString('zablokovaný', $response->body);
    }

    public function testInvitedAdminWithoutVerifiedEmailIsDenied(): void
    {
        (new UserRepository($this->db()))->createAdmin('spravce@example.cz');
        self::assertSame(403, $this->loginAs(['email' => 'spravce@example.cz', 'email_verified' => false])->status);
    }

    // --- ochrana callbacku ----------------------------------------------------

    public function testCallbackWithoutStartedLoginIsRejected(): void
    {
        self::assertSame(400, $this->get('/callback', ['code' => 'x', 'state' => 'y'])->status);
    }

    public function testCallbackWithWrongStateIsRejected(): void
    {
        $this->startLogin();
        self::assertSame(400, $this->get('/callback', ['code' => 'x', 'state' => 'podvrzeny'])->status);
    }

    public function testCallbackCannotBeReplayed(): void
    {
        $this->invite('farma@example.cz');
        $params = $this->startLogin();
        $this->logto->nextClaims = $this->logto->defaultClaims($params['nonce']);
        self::assertSame(303, $this->get('/callback', ['code' => 'k', 'state' => $params['state']])->status);
        self::assertSame(400, $this->get('/callback', ['code' => 'k', 'state' => $params['state']])->status);
    }

    public function testExpiredPendingLoginIsRejected(): void
    {
        $params = $this->startLogin();
        AppFactory::$sessionData['oidc_pending']['created'] = time() - 3600;
        self::assertSame(400, $this->get('/callback', ['code' => 'k', 'state' => $params['state']])->status);
    }

    public function testTokenWithWrongNonceIsRejected(): void
    {
        $this->invite('farma@example.cz');
        $params = $this->startLogin();
        $this->logto->nextClaims = $this->logto->defaultClaims('cizi-nonce');
        self::assertSame(400, $this->get('/callback', ['code' => 'k', 'state' => $params['state']])->status);
        self::assertArrayNotHasKey('user_id', AppFactory::$sessionData);
    }

    public function testLogtoErrorShowsFriendlyPage(): void
    {
        $this->startLogin();
        $response = $this->get('/callback', ['error' => 'access_denied']);
        self::assertSame(400, $response->status);
        self::assertStringContainsString('Zkusit znovu', $response->body);
    }

    // --- chráněné stránky a odhlášení ------------------------------------------

    public function testPortalRequiresLogin(): void
    {
        $response = $this->get('/portal');
        self::assertSame(303, $response->status);
        self::assertSame('/prihlaseni', $response->headers['Location']);
    }

    public function testBlockingTakesEffectImmediately(): void
    {
        $userId = $this->createUser('producer', ['logto_user_id' => 'logto-x']);
        $this->loginAs(['sub' => 'logto-x']);
        self::assertSame(200, $this->get('/portal')->status);

        $this->db()->exec("UPDATE users SET status = 'blocked' WHERE id = {$userId}");

        self::assertSame(303, $this->get('/portal')->status);
        self::assertArrayNotHasKey('user_id', AppFactory::$sessionData, 'session se ukončí');
    }

    public function testLogoutClearsSessionAndEndsLogtoSession(): void
    {
        $this->createUser('producer', ['logto_user_id' => 'logto-y']);
        $this->loginAs(['sub' => 'logto-y']);
        $token = $this->app->csrf()->token();

        $response = $this->app->handle(new Request('POST', '/odhlaseni', [], ['_csrf' => $token]));

        self::assertSame(303, $response->status);
        self::assertStringStartsWith(FakeLogto::ENDPOINT . '/oidc/session/end?client_id=test-app', $response->headers['Location']);
        self::assertArrayNotHasKey('user_id', AppFactory::$sessionData);
        self::assertSame(303, $this->get('/portal')->status);
    }

    public function testLogoutRequiresCsrfToken(): void
    {
        $this->createUser('producer', ['logto_user_id' => 'logto-z']);
        $this->loginAs(['sub' => 'logto-z']);
        $response = $this->app->handle(new Request('POST', '/odhlaseni'));
        self::assertSame(419, $response->status);
        self::assertArrayHasKey('user_id', AppFactory::$sessionData);
    }

    public function testLoggedInUserSkipsLogtoOnLoginPage(): void
    {
        $this->createUser('producer', ['logto_user_id' => 'logto-w']);
        $this->loginAs(['sub' => 'logto-w']);
        self::assertSame('/portal', $this->get('/prihlaseni')->headers['Location']);
    }
}
