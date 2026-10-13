<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;

/**
 * Souhrnná matice oprávnění (krok 12): KAŽDÁ trasa aplikace × KAŽDÁ identita.
 *
 * - Každá trasa musí být níže zařazená do pravidla – nová trasa bez pravidla test shodí.
 * - Odepřený přístup = přesměrování na přihlášení / profil / administraci, 403 nebo 404,
 *   a databáze se nesmí změnit.
 * - Cizí záznam (IDOR) = 404 a beze změny.
 * - POST bez CSRF tokenu = 419 a beze změny.
 */
final class AccessMatrixTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;

    /** Kdo smí na trasu (vzor => pravidlo). */
    private const RULES = [
        '/' => 'public', '/o-projektu' => 'public', '/jak-ziskat-pristup' => 'public', '/kontakt' => 'public',
        '/ochrana-osobnich-udaju' => 'public', '/podminky-pouzivani' => 'public', '/prihlaseni' => 'public',
        '/callback' => 'auth', '/pozvanka' => 'auth', '/odhlaseni' => 'public',
        '/cron/denni' => 'cron',
        '/portal' => 'member', '/nabidky' => 'member', '/nabidky/{id}' => 'member', '/poptavky' => 'member',
        '/poptavky/{id}' => 'member', '/zemedelci/{id}' => 'member', '/skoly/{id}' => 'member',
        '/profil' => 'profile',
        '/moje-nabidky' => 'producer', '/moje-nabidky/nova' => 'producer', '/moje-nabidky/{id}' => 'producer',
        '/moje-nabidky/{id}/upravit' => 'producer', '/moje-nabidky/{id}/ukoncit' => 'producer',
        '/moje-nabidky/{id}/obnovit' => 'producer', '/moje-nabidky/{id}/smazat' => 'producer',
        '/moje-poptavky' => 'school', '/moje-poptavky/nova' => 'school', '/moje-poptavky/{id}' => 'school',
        '/moje-poptavky/{id}/upravit' => 'school', '/moje-poptavky/{id}/ukoncit' => 'school',
        '/moje-poptavky/{id}/obnovit' => 'school', '/moje-poptavky/{id}/smazat' => 'school',
        '/admin' => 'admin', '/admin/pozvanky' => 'admin', '/admin/pozvanky/{id}/znovu' => 'admin',
        '/admin/pozvanky/{id}/zrusit' => 'admin', '/admin/zaznamy' => 'admin', '/admin/zaznamy/prohlednuto' => 'admin',
        '/admin/nabidky/{id}' => 'admin', '/admin/nabidky/{id}/stahnout' => 'admin', '/admin/nabidky/{id}/uvolnit' => 'admin',
        '/admin/poptavky/{id}' => 'admin', '/admin/poptavky/{id}/stahnout' => 'admin', '/admin/poptavky/{id}/uvolnit' => 'admin',
        '/admin/uzivatele' => 'admin', '/admin/uzivatele/{id}' => 'admin', '/admin/uzivatele/{id}/blokovat' => 'admin',
        '/admin/uzivatele/{id}/obnovit' => 'admin', '/admin/uzivatele/{id}/archivovat' => 'admin',
    ];

    /** Pravidlo => identity, které smějí dovnitř. */
    private const ALLOWED = [
        'member' => ['producer', 'school', 'admin'],
        'profile' => ['producer', 'school', 'incomplete'],
        'producer' => ['producer'],
        'school' => ['school'],
        'admin' => ['admin'],
    ];

    private const IDENTITIES = ['anon', 'blocked', 'incomplete', 'producer', 'school', 'admin'];

    private App $app;
    /** @var array<string, int> identita => id uživatele */
    private array $ids = [];
    private int $offer;
    private int $demand;
    private int $invitation;
    private int $otherOffer;
    private int $otherDemand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create(['db' => self::dbConfig(), 'cron' => ['key' => str_repeat('k', 40)]]);
        $this->ids['admin'] = $this->createUser('admin', ['logto_user_id' => 'l-admin']);
        $this->ids['producer'] = $this->member('producer', 'Farma A');
        $this->ids['school'] = $this->member('school', 'Škola A');
        $this->ids['blocked'] = $this->member('producer', 'Blokovaná farma', ['status' => 'blocked']);
        $this->ids['incomplete'] = $this->createUser('school', ['logto_user_id' => 'l-incomplete', 'status' => 'profile_incomplete']);
        $otherProducer = $this->member('producer', 'Farma B');
        $otherSchool = $this->member('school', 'Škola B');

        $this->offer = $this->insert('offers', $this->offerRow($this->ids['producer']));
        $this->demand = $this->insert('demands', $this->demandRow($this->ids['school'], ['needed_from' => '2030-01-01', 'needed_until' => '2030-02-01']));
        $this->otherOffer = $this->insert('offers', $this->offerRow($otherProducer));
        $this->otherDemand = $this->insert('demands', $this->demandRow($otherSchool, ['needed_from' => '2030-01-01', 'needed_until' => '2030-02-01']));
        $invited = $this->createUser('producer', ['logto_user_id' => null, 'status' => 'invited']);
        $this->invitation = $this->insert('invitations', [
            'user_id' => $invited, 'email' => 'pozvany@example.cz', 'role' => 'producer', 'invited_by' => $this->ids['admin'],
            'created_at' => '2030-01-01 10:00:00', 'expires_at' => '2030-01-08 10:00:00',
        ]);
    }

    /** @param array<string, scalar|null> $user */
    private function member(string $role, string $name, array $user = []): int
    {
        $id = $this->createUser($role, $user + ['logto_user_id' => 'l-' . bin2hex(random_bytes(4)), 'profile_completed_at' => '2026-01-01 10:00:00']);
        $profile = [
            'user_id' => $id, 'contact_name' => 'Kontakt', 'address_line' => 'Náves 1', 'municipality' => 'Buchlovice',
            'postal_code' => '68708', 'phone' => '600000000', 'public_email' => 'kontakt' . $id . '@example.cz',
            'privacy_consent_at' => '2026-01-01 10:00:00', 'contact_publication_consent_at' => '2026-01-01 10:00:00',
        ];
        $this->insert($role === 'producer' ? 'producer_profiles' : 'school_profiles', $profile + [($role === 'producer' ? 'producer_name' : 'school_name') => $name]);
        return $id;
    }

    private function signIn(string $identity): void
    {
        AppFactory::$sessionData = [];
        if ($identity !== 'anon') {
            AppFactory::$sessionData['user_id'] = $this->ids[$identity];
            AppFactory::$sessionData['user_role'] = $identity === 'incomplete' ? 'school' : ($identity === 'blocked' ? 'producer' : $identity);
        }
    }

    private function request(string $method, string $path, bool $withCsrf = true): Response
    {
        if ($method === 'GET') {
            return $this->app->handle(new Request('GET', $path));
        }
        $session = AppFactory::$sessionData;
        $this->app->handle(new Request('GET', '/')); // vytvoří CSRF token
        AppFactory::$sessionData += $session;
        $form = $withCsrf ? ['_csrf' => $this->app->csrf()->token()] : [];
        return $this->app->handle(new Request('POST', $path, [], $form + ['confirm' => '1', 'reason' => 'Test oprávnění']));
    }

    /** Konkrétní adresa pro vzor; {id} = vlastní/odpovídající záznam. */
    private function path(string $pattern): string
    {
        $id = match (true) {
            str_starts_with($pattern, '/moje-nabidky'), str_starts_with($pattern, '/nabidky'), str_starts_with($pattern, '/admin/nabidky') => $this->offer,
            str_starts_with($pattern, '/moje-poptavky'), str_starts_with($pattern, '/poptavky'), str_starts_with($pattern, '/admin/poptavky') => $this->demand,
            str_starts_with($pattern, '/zemedelci'), str_starts_with($pattern, '/admin/uzivatele') => $this->ids['producer'],
            str_starts_with($pattern, '/skoly') => $this->ids['school'],
            str_starts_with($pattern, '/admin/pozvanky') => $this->invitation,
            default => 0,
        };
        return str_replace('{id}', (string) $id, $pattern);
    }

    private function fingerprint(): string
    {
        $rows = $this->db()->query('CHECKSUM TABLE users, invitations, producer_profiles, school_profiles, offers, demands, audit_log')->fetchAll();
        return md5((string) json_encode($rows));
    }

    private static function isDenied(Response $r): bool
    {
        $location = $r->headers['Location'] ?? '';
        return in_array($r->status, [403, 404], true)
            || ($r->status >= 300 && $r->status < 400 && in_array($location, ['/prihlaseni', '/profil', '/admin'], true));
    }

    public function testEveryRouteHasAnAccessRule(): void
    {
        $missing = [];
        foreach ($this->app->router->routes() as [$method, $pattern]) {
            if (!isset(self::RULES[$pattern])) {
                $missing[] = "{$method} {$pattern}";
            }
        }
        self::assertSame([], $missing, 'Nové trasy je nutné zařadit do matice oprávnění.');
    }

    public function testDeniedIdentitiesAreRejectedWithoutSideEffects(): void
    {
        $checked = 0;
        foreach ($this->app->router->routes() as [$method, $pattern]) {
            $rule = self::RULES[$pattern] ?? 'missing';
            if (!isset(self::ALLOWED[$rule])) {
                continue;
            }
            foreach (self::IDENTITIES as $identity) {
                if (in_array($identity, self::ALLOWED[$rule], true)) {
                    continue;
                }
                $before = $this->fingerprint();
                $this->signIn($identity);
                $response = $this->request($method, $this->path($pattern));
                self::assertTrue(self::isDenied($response), "{$identity} {$method} {$pattern} -> {$response->status} " . ($response->headers['Location'] ?? ''));
                self::assertSame($before, $this->fingerprint(), "{$identity} {$method} {$pattern} změnil data");
                $checked++;
            }
        }
        self::assertGreaterThan(150, $checked);
    }

    public function testAllowedIdentitiesGetPagesShown(): void
    {
        foreach ($this->app->router->routes() as [$method, $pattern]) {
            $rule = self::RULES[$pattern] ?? 'missing';
            if ($method !== 'GET' || !isset(self::ALLOWED[$rule])) {
                continue;
            }
            foreach (self::ALLOWED[$rule] as $identity) {
                $this->signIn($identity);
                $response = $this->request('GET', $this->path($pattern));
                // Povolený = nedostane odepřený přístup ani chybu serveru (400 u neplatného stavu je v pořádku).
                $ok = !self::isDenied($response) && $response->status < 500;
                self::assertTrue($ok, "{$identity} GET {$pattern} -> {$response->status} " . ($response->headers['Location'] ?? ''));
            }
        }
    }

    public function testPublicPagesWorkForEveryone(): void
    {
        foreach (['/', '/o-projektu', '/jak-ziskat-pristup', '/kontakt', '/ochrana-osobnich-udaju', '/podminky-pouzivani'] as $path) {
            foreach (self::IDENTITIES as $identity) {
                $this->signIn($identity);
                self::assertSame(200, $this->request('GET', $path)->status, "{$identity} {$path}");
            }
        }
    }

    public function testCronIsHiddenWithoutKeyForEveryone(): void
    {
        foreach (self::IDENTITIES as $identity) {
            $this->signIn($identity);
            self::assertSame(404, $this->request('GET', '/cron/denni')->status, $identity);
        }
    }

    public function testForeignRecordsAreNotFoundAndUntouched(): void
    {
        $cases = [
            'producer' => ['/moje-nabidky', $this->otherOffer],
            'school' => ['/moje-poptavky', $this->otherDemand],
        ];
        foreach ($cases as $identity => [$base, $foreignId]) {
            foreach ([['GET', '/upravit'], ['GET', '/obnovit'], ['GET', '/smazat'], ['POST', ''], ['POST', '/ukoncit'], ['POST', '/obnovit'], ['POST', '/smazat']] as [$method, $suffix]) {
                $before = $this->fingerprint();
                $this->signIn($identity);
                $response = $this->request($method, "{$base}/{$foreignId}{$suffix}");
                self::assertSame(404, $response->status, "{$identity} {$method} {$base}/cizí{$suffix}");
                self::assertSame($before, $this->fingerprint());
            }
        }
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        foreach ($this->app->router->routes() as [$method, $pattern]) {
            if ($method !== 'POST') {
                continue;
            }
            $rule = self::RULES[$pattern] ?? 'public';
            $identity = self::ALLOWED[$rule][0] ?? 'producer';
            $before = $this->fingerprint();
            $this->signIn($identity);
            $response = $this->request('POST', $this->path($pattern), false);
            self::assertSame(419, $response->status, "POST {$pattern} bez tokenu");
            self::assertSame($before, $this->fingerprint(), "POST {$pattern} bez tokenu změnil data");
        }
    }
}
