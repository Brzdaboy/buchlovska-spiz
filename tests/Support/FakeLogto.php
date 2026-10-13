<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\Jwt;
use App\Support\HttpClient;
use RuntimeException;

/**
 * Falešný Logto tenant pro testy: discovery, JWKS a token endpoint.
 * Podepisuje ID tokeny skutečným klíčem P-384 (ES384), stejně jako Logto.
 */
final class FakeLogto implements HttpClient
{
    public const ENDPOINT = 'https://test.logto.app';
    public const CLIENT_ID = 'test-app';
    public const CLIENT_SECRET = 'test-secret';

    /** @var \OpenSSLAsymmetricKey */
    private $key;
    private string $kid = 'key-1';
    /** @var list<array{method: string, url: string, form?: array<string, string>, headers: array<string, string>}> */
    public array $requests = [];
    /** Claims, které token endpoint vloží do příštího ID tokenu (přepíší výchozí). */
    public array $nextClaims = [];
    public int $tokenStatus = 200;
    /** Simulace selhání vytvoření organizační pozvánky (např. chybí e-mailový konektor). */
    public bool $failOrganizationInvitation = false;
    private int $sequence = 0;
    public ?string $issuerOverride = null;

    /** Simulace výpadku Logto při mazání účtu. */
    public bool $failUserDeletion = false;

    public function __construct()
    {
        $this->rotateKey();
    }

    public function rotateKey(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
        if ($key === false) {
            throw new RuntimeException('Nelze vytvořit testovací klíč.');
        }
        $this->key = $key;
        $this->kid = 'key-' . bin2hex(random_bytes(4));
    }

    /** @param array<string, mixed> $claims */
    public function idToken(array $claims, string $alg = 'ES384'): string
    {
        $header = ['alg' => $alg, 'typ' => 'JWT', 'kid' => $this->kid];
        $input = Jwt::base64UrlEncode((string) json_encode($header)) . '.' . Jwt::base64UrlEncode((string) json_encode($claims));
        openssl_sign($input, $der, $this->key, OPENSSL_ALGO_SHA384);
        return $input . '.' . Jwt::base64UrlEncode(Jwt::derToRaw($der, 48));
    }

    /** @return array<string, mixed> */
    public function defaultClaims(string $nonce, array $overrides = []): array
    {
        $now = time();
        return $overrides + [
            'iss' => self::ENDPOINT . '/oidc',
            'aud' => self::CLIENT_ID,
            'sub' => 'logto-user-1',
            'exp' => $now + 3600,
            'iat' => $now,
            'nonce' => $nonce,
            'email' => 'farma@example.cz',
            'email_verified' => true,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details($this->key);
        return [[
            'kty' => 'EC', 'crv' => 'P-384', 'use' => 'sig', 'alg' => 'ES384', 'kid' => $this->kid,
            'x' => Jwt::base64UrlEncode($details['ec']['x']),
            'y' => Jwt::base64UrlEncode($details['ec']['y']),
        ]];
    }

    public function get(string $url, array $headers = []): array
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers];
        if ($url === self::ENDPOINT . '/oidc/.well-known/openid-configuration') {
            return $this->json([
                'issuer' => $this->issuerOverride ?? self::ENDPOINT . '/oidc',
                'authorization_endpoint' => self::ENDPOINT . '/oidc/auth',
                'token_endpoint' => self::ENDPOINT . '/oidc/token',
                'end_session_endpoint' => self::ENDPOINT . '/oidc/session/end',
                'jwks_uri' => self::ENDPOINT . '/oidc/jwks',
                'userinfo_endpoint' => self::ENDPOINT . '/oidc/me',
            ]);
        }
        if ($url === self::ENDPOINT . '/oidc/jwks') {
            return $this->json(['keys' => $this->jwks()]);
        }
        return ['status' => 404, 'body' => '{}'];
    }

    public function postForm(string $url, array $form, array $headers = []): array
    {
        $this->requests[] = ['method' => 'POST', 'url' => $url, 'form' => $form, 'headers' => $headers];
        if ($url !== self::ENDPOINT . '/oidc/token') {
            return ['status' => 404, 'body' => '{}'];
        }
        if (($form['grant_type'] ?? '') === 'client_credentials') {
            return $this->json(['access_token' => 'm2m-access-token', 'expires_in' => 3600, 'token_type' => 'Bearer']);
        }
        if ($this->tokenStatus !== 200) {
            return ['status' => $this->tokenStatus, 'body' => '{"error":"invalid_grant"}'];
        }
        $claims = $this->nextClaims;
        return $this->json(['id_token' => $this->idToken($claims), 'access_token' => 'at', 'token_type' => 'Bearer']);
    }

    public function sendJson(string $method, string $url, array $json, array $headers = []): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'json' => $json, 'headers' => $headers];
        if (($headers['Authorization'] ?? '') !== 'Bearer m2m-access-token') {
            return ['status' => 401, 'body' => '{}'];
        }
        $path = substr($url, strlen(self::ENDPOINT));
        $this->sequence++;
        if ($method === 'POST' && $path === '/api/one-time-tokens') {
            return ['status' => 201, 'body' => (string) json_encode(['id' => 'ott-' . $this->sequence, 'token' => 'TOKEN' . $this->sequence . 'abcdefgh'])];
        }
        if ($method === 'POST' && $path === '/api/organization-invitations') {
            if ($this->failOrganizationInvitation) {
                return ['status' => 501, 'body' => '{"message":"No email connector is configured for the tenant."}'];
            }
            return ['status' => 201, 'body' => (string) json_encode(['id' => 'inv-' . $this->sequence, 'status' => 'Pending'])];
        }
        if ($method === 'DELETE' && preg_match('#^/api/users/[^/]+$#', $path)) {
            return $this->failUserDeletion ? ['status' => 500, 'body' => '{"message":"boom"}'] : ['status' => 204, 'body' => ''];
        }
        if ($method === 'PUT' && preg_match('#^/api/(one-time-tokens|organization-invitations)/[^/]+/status$#', $path)) {
            return ['status' => 200, 'body' => '{}'];
        }
        return ['status' => 404, 'body' => '{}'];
    }

    /** @return list<array<string, mixed>> požadavky na Management API */
    public function managementCalls(string $method, string $pathPrefix): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (array $r): bool => $r['method'] === $method && str_starts_with($r['url'], self::ENDPOINT . $pathPrefix)
        ));
    }

    /** @return array{status: int, body: string} */
    private function json(array $data): array
    {
        return ['status' => 200, 'body' => (string) json_encode($data)];
    }
}
