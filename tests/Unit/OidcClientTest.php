<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthException;
use App\Auth\Jwt;
use App\Auth\OidcClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeLogto;

final class OidcClientTest extends TestCase
{
    private FakeLogto $logto;
    private OidcClient $client;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->logto = new FakeLogto();
        $this->cacheDir = sys_get_temp_dir() . '/bspiz-oidc-' . bin2hex(random_bytes(4));
        $this->client = new OidcClient($this->logto, FakeLogto::ENDPOINT, FakeLogto::CLIENT_ID, FakeLogto::CLIENT_SECRET, $this->cacheDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cacheDir . '/*') ?: []);
        @rmdir($this->cacheDir);
    }

    private function token(array $overrides = []): string
    {
        return $this->logto->idToken($this->logto->defaultClaims('n0nce', $overrides));
    }

    public function testValidEs384TokenIsAccepted(): void
    {
        $claims = $this->client->validateIdToken($this->token(), 'n0nce');
        self::assertSame('logto-user-1', $claims['sub']);
        self::assertSame('farma@example.cz', $claims['email']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidClaims(): iterable
    {
        yield 'cizí aplikace' => [['aud' => 'jina-aplikace']];
        yield 'cizí vydavatel' => [['iss' => 'https://zlo.example/oidc']];
        yield 'vypršel' => [['exp' => time() - 120]];
        yield 'z budoucnosti' => [['iat' => time() + 600]];
        yield 'jiný nonce' => [['nonce' => 'jiny']];
        yield 'bez sub' => [['sub' => '']];
        yield 'více aud bez azp' => [['aud' => [FakeLogto::CLIENT_ID, 'jina']]];
    }

    /** @dataProvider invalidClaims */
    public function testInvalidClaimsAreRejected(array $overrides): void
    {
        $this->expectException(AuthException::class);
        $this->client->validateIdToken($this->token($overrides), 'n0nce');
    }

    public function testTamperedPayloadIsRejected(): void
    {
        [$h, , $s] = explode('.', $this->token());
        $forged = Jwt::base64UrlEncode((string) json_encode($this->logto->defaultClaims('n0nce', ['sub' => 'admin'])));
        $this->expectException(AuthException::class);
        $this->client->validateIdToken("{$h}.{$forged}.{$s}", 'n0nce');
    }

    public function testAlgNoneIsRejected(): void
    {
        $claims = $this->logto->defaultClaims('n0nce');
        $token = Jwt::base64UrlEncode('{"alg":"none"}') . '.' . Jwt::base64UrlEncode((string) json_encode($claims)) . '.';
        $this->expectException(AuthException::class);
        $this->client->validateIdToken($token, 'n0nce');
    }

    public function testHmacWithPublicKeyIsRejected(): void
    {
        // Klasický útok: HS256 podepsaný veřejným klíčem jako tajemstvím.
        $claims = $this->logto->defaultClaims('n0nce');
        $input = Jwt::base64UrlEncode('{"alg":"HS256","kid":"key-1"}') . '.' . Jwt::base64UrlEncode((string) json_encode($claims));
        $token = $input . '.' . Jwt::base64UrlEncode(hash_hmac('sha256', $input, (string) json_encode($this->logto->jwks()), true));
        $this->expectException(AuthException::class);
        $this->client->validateIdToken($token, 'n0nce');
    }

    public function testTokenSignedByForeignKeyIsRejected(): void
    {
        $attacker = new FakeLogto();
        $token = $attacker->idToken($this->logto->defaultClaims('n0nce'));
        $this->expectException(AuthException::class);
        $this->client->validateIdToken($token, 'n0nce');
    }

    public function testKeyRotationRefetchesJwksOnce(): void
    {
        $this->client->validateIdToken($this->token(), 'n0nce'); // JWKS v cache
        $this->logto->rotateKey();
        $claims = $this->client->validateIdToken($this->token(), 'n0nce');
        self::assertSame('logto-user-1', $claims['sub']);
    }

    public function testRs256IsSupported(): void
    {
        $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $d = openssl_pkey_get_details($rsa);
        $jwks = [['kty' => 'RSA', 'kid' => 'r1', 'n' => Jwt::base64UrlEncode($d['rsa']['n']), 'e' => Jwt::base64UrlEncode($d['rsa']['e'])]];
        $input = Jwt::base64UrlEncode('{"alg":"RS256","kid":"r1"}') . '.' . Jwt::base64UrlEncode('{"sub":"x"}');
        openssl_sign($input, $sig, $rsa, OPENSSL_ALGO_SHA256);

        self::assertSame('x', Jwt::verify($input . '.' . Jwt::base64UrlEncode($sig), $jwks)['claims']['sub']);
    }

    public function testDiscoveryFromForeignIssuerIsRejected(): void
    {
        $this->logto->issuerOverride = 'https://zlo.example/oidc';
        $this->expectException(RuntimeException::class);
        $this->client->authorizationUrl('https://spiz.test/callback', 's', 'n', 'v');
    }

    public function testAuthorizationUrlUsesPkce(): void
    {
        $verifier = OidcClient::randomToken();
        $url = $this->client->authorizationUrl('https://spiz.test/callback', 'stav', 'nonce1', $verifier);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        self::assertStringStartsWith(FakeLogto::ENDPOINT . '/oidc/auth?', $url);
        self::assertSame(FakeLogto::CLIENT_ID, $q['client_id']);
        self::assertSame('https://spiz.test/callback', $q['redirect_uri']);
        self::assertSame('code', $q['response_type']);
        self::assertSame('stav', $q['state']);
        self::assertSame('nonce1', $q['nonce']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertSame(Jwt::base64UrlEncode(hash('sha256', $verifier, true)), $q['code_challenge']);
        self::assertStringContainsString('email', $q['scope']);
        self::assertArrayNotHasKey('code_verifier', $q, 'verifier nesmí do prohlížeče');
    }

    public function testCompleteLoginSendsVerifierAndClientCredentials(): void
    {
        $this->logto->nextClaims = $this->logto->defaultClaims('n0nce');
        $claims = $this->client->completeLogin('kod', 'https://spiz.test/callback', 'verifier-123', 'n0nce');

        self::assertSame('logto-user-1', $claims['sub']);
        $tokenRequest = array_values(array_filter($this->logto->requests, static fn ($r) => $r['method'] === 'POST'))[0];
        self::assertSame('verifier-123', $tokenRequest['form']['code_verifier']);
        self::assertSame('authorization_code', $tokenRequest['form']['grant_type']);
        self::assertSame('Basic ' . base64_encode('test-app:test-secret'), $tokenRequest['headers']['Authorization']);
    }

    public function testFailedCodeExchangeIsAuthError(): void
    {
        $this->logto->tokenStatus = 400;
        $this->expectException(AuthException::class);
        $this->client->completeLogin('kod', 'https://spiz.test/callback', 'v', 'n');
    }

    public function testEndSessionUrl(): void
    {
        $url = $this->client->endSessionUrl('https://spiz.test/');
        self::assertSame(FakeLogto::ENDPOINT . '/oidc/session/end?client_id=test-app&post_logout_redirect_uri=https%3A%2F%2Fspiz.test%2F', $url);
    }

    public function testKeyCoordinateWithoutLeadingZeroIsAccepted(): void
    {
        // Najdeme klíč, jehož souřadnice x začíná nulovým bajtem, a pošleme ji zkrácenou.
        for ($i = 0; $i < 5000; $i++) {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
            $d = openssl_pkey_get_details($key);
            $x = str_pad($d['ec']['x'], 48, "\x00", STR_PAD_LEFT);
            if ($x[0] === "\x00") {
                break;
            }
        }
        self::assertSame("\x00", $x[0], 'nepodařilo se najít vhodný klíč');
        $jwks = [[
            'kty' => 'EC', 'crv' => 'P-384', 'kid' => 'k',
            'x' => Jwt::base64UrlEncode(ltrim($x, "\x00")),
            'y' => Jwt::base64UrlEncode(str_pad($d['ec']['y'], 48, "\x00", STR_PAD_LEFT)),
        ]];
        $input = Jwt::base64UrlEncode('{"alg":"ES384","kid":"k"}') . '.' . Jwt::base64UrlEncode('{"sub":"z"}');
        openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA384);

        self::assertSame('z', Jwt::verify($input . '.' . Jwt::base64UrlEncode(Jwt::derToRaw($der, 48)), $jwks)['claims']['sub']);
    }

    public function testOversizedKeyCoordinateIsRejected(): void
    {
        $jwk = $this->logto->jwks()[0];
        $jwk['x'] = Jwt::base64UrlEncode("\x01" . Jwt::base64UrlDecode($jwk['x']));
        $this->expectException(AuthException::class);
        Jwt::verify($this->token(), [$jwk]);
    }

    public function testSignatureConversionRoundTrip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $raw = random_bytes(96);
            $raw[0] = "\x00"; // i čísla s úvodními nulami
            self::assertSame(bin2hex($raw), bin2hex(Jwt::derToRaw(Jwt::rawToDer($raw, 48), 48)));
        }
    }
}
