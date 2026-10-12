<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\HttpClient;
use RuntimeException;

/**
 * Klient OpenID Connect pro Logto: Authorization Code Flow + PKCE (S256).
 * Viz docs/adr/001-logto.md.
 */
final class OidcClient
{
    private const LEEWAY_SECONDS = 60;
    private const CACHE_TTL = 3600;

    /** @var array<string, mixed>|null */
    private ?array $metadata = null;

    public function __construct(
        private HttpClient $http,
        private string $endpoint,
        private string $clientId,
        private string $clientSecret,
        private string $cacheDir,
    ) {
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * Sestaví adresu pro přesměrování na přihlášení v Logto.
     *
     * @param array<string, string> $extraParams např. one_time_token, login_hint
     */
    public function authorizationUrl(string $redirectUri, string $state, string $nonce, string $codeVerifier, array $extraParams = []): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ];
        return $this->meta('authorization_endpoint') . '?' . http_build_query($params + $extraParams, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Vymění autorizační kód za tokeny a ověří ID token.
     *
     * @return array<string, mixed> ověřené claims (sub, email, email_verified…)
     * @throws AuthException
     */
    public function completeLogin(string $code, string $redirectUri, string $codeVerifier, string $expectedNonce, ?int $now = null): array
    {
        $response = $this->http->postForm($this->meta('token_endpoint'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ], [
            'Authorization' => 'Basic ' . base64_encode(rawurlencode($this->clientId) . ':' . rawurlencode($this->clientSecret)),
        ]);
        if ($response['status'] !== 200) {
            throw new AuthException('Výměna kódu selhala (HTTP ' . $response['status'] . ').');
        }
        $tokens = json_decode($response['body'], true);
        if (!is_array($tokens) || !is_string($tokens['id_token'] ?? null)) {
            throw new AuthException('Odpověď neobsahuje ID token.');
        }

        $claims = $this->validateIdToken($tokens['id_token'], $expectedNonce, $now);

        // E-mail by měl být v ID tokenu (scope email); jinak ho doplníme z userinfo.
        if (!isset($claims['email']) && is_string($tokens['access_token'] ?? null)) {
            $claims += $this->userInfo($tokens['access_token'], (string) $claims['sub']);
        }
        return $claims;
    }

    /**
     * @return array<string, mixed>
     * @throws AuthException
     */
    public function validateIdToken(string $idToken, string $expectedNonce, ?int $now = null): array
    {
        $now ??= time();
        try {
            $verified = Jwt::verify($idToken, $this->jwks(false));
        } catch (AuthException $e) {
            if ($e->reason !== 'unknown_kid') {
                throw $e;
            }
            // Logto mohlo rotovat klíče: jednou načteme JWKS znovu.
            $verified = Jwt::verify($idToken, $this->jwks(true));
        }
        $claims = $verified['claims'];

        if (($claims['iss'] ?? null) !== $this->meta('issuer')) {
            throw new AuthException('Token vydal neznámý vydavatel.');
        }
        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        if (!in_array($this->clientId, $audiences, true)) {
            throw new AuthException('Token není určen pro tuto aplikaci.');
        }
        if (is_array($aud) && count($aud) > 1 && ($claims['azp'] ?? null) !== $this->clientId) {
            throw new AuthException('Token není určen pro tuto aplikaci (azp).');
        }
        if (!is_int($claims['exp'] ?? null) || $claims['exp'] < $now - self::LEEWAY_SECONDS) {
            throw new AuthException('Platnost tokenu vypršela.');
        }
        if (!is_int($claims['iat'] ?? null) || $claims['iat'] > $now + self::LEEWAY_SECONDS) {
            throw new AuthException('Token je vydán v budoucnosti.');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($expectedNonce, $claims['nonce'])) {
            throw new AuthException('Nesouhlasí nonce (možné opakované použití tokenu).');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new AuthException('Token neobsahuje identitu uživatele.');
        }
        return $claims;
    }

    public function endSessionUrl(string $postLogoutRedirectUri): string
    {
        return $this->meta('end_session_endpoint') . '?' . http_build_query([
            'client_id' => $this->clientId,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function codeChallenge(string $verifier): string
    {
        return Jwt::base64UrlEncode(hash('sha256', $verifier, true));
    }

    /** Náhodný řetězec pro state, nonce a PKCE verifier (43+ znaků). */
    public static function randomToken(): string
    {
        return Jwt::base64UrlEncode(random_bytes(32));
    }

    /** @return array<string, mixed> */
    private function userInfo(string $accessToken, string $expectedSub): array
    {
        $response = $this->http->get($this->meta('userinfo_endpoint'), ['Authorization' => 'Bearer ' . $accessToken]);
        $info = json_decode($response['body'], true);
        if ($response['status'] !== 200 || !is_array($info) || ($info['sub'] ?? null) !== $expectedSub) {
            throw new AuthException('Nepodařilo se načíst údaje uživatele.');
        }
        return array_intersect_key($info, ['email' => true, 'email_verified' => true, 'name' => true]);
    }

    private function meta(string $key): string
    {
        if ($this->metadata === null) {
            $metadata = $this->cachedJson('discovery', $this->endpoint . '/oidc/.well-known/openid-configuration', false);
            // Ochrana proti podvržené konfiguraci: vydavatel musí odpovídat nastavenému tenantu.
            if (($metadata['issuer'] ?? null) !== $this->endpoint . '/oidc') {
                throw new RuntimeException('Konfigurace Logto patří jinému vydavateli.');
            }
            $this->metadata = $metadata;
        }
        $value = $this->metadata[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Konfigurace Logto neobsahuje {$key}.");
        }
        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function jwks(bool $refresh): array
    {
        $jwks = $this->cachedJson('jwks', $this->meta('jwks_uri'), $refresh);
        $keys = $jwks['keys'] ?? null;
        if (!is_array($keys)) {
            throw new RuntimeException('JWKS neobsahuje klíče.');
        }
        return array_values($keys);
    }

    /** @return array<string, mixed> */
    private function cachedJson(string $name, string $url, bool $refresh): array
    {
        $file = $this->cacheDir . '/oidc-' . $name . '-' . substr(hash('sha256', $url), 0, 16) . '.json';
        if (!$refresh && is_file($file) && filemtime($file) > time() - self::CACHE_TTL) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $response = $this->http->get($url);
        $data = json_decode($response['body'], true);
        if ($response['status'] !== 200 || !is_array($data)) {
            throw new RuntimeException("Nelze načíst {$name} z Logto (HTTP {$response['status']}).");
        }
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0750, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return $data;
    }
}
