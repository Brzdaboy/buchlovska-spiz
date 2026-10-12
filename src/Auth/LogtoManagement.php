<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\HttpClient;
use RuntimeException;

/**
 * Volání Logto Management API přes aplikaci typu Machine-to-machine.
 * Používá se pro pozvánky (jednorázový token + organizační pozvánka s e-mailem).
 */
final class LogtoManagement
{
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private HttpClient $http,
        private string $endpoint,
        private string $m2mAppId,
        private string $m2mAppSecret,
        private string $resource,
    ) {
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * Jednorázový token pro registraci na pozvánku (funguje i při vypnuté registraci).
     *
     * @return array{id: string, token: string}
     */
    public function createOneTimeToken(string $email, int $expiresInSeconds): array
    {
        $data = $this->call('POST', '/api/one-time-tokens', ['email' => $email, 'expiresIn' => $expiresInSeconds], 201);
        if (!is_string($data['id'] ?? null) || !is_string($data['token'] ?? null)) {
            throw new RuntimeException('Logto nevrátilo jednorázový token.');
        }
        return ['id' => $data['id'], 'token' => $data['token']];
    }

    public function revokeOneTimeToken(string $id): void
    {
        $this->call('PUT', '/api/one-time-tokens/' . rawurlencode($id) . '/status', ['status' => 'revoked'], 200);
    }

    /**
     * Organizační pozvánka; Logto pošle e-mail šablonou OrganizationInvitation s proměnnou {{link}}.
     *
     * @return string ID pozvánky v Logto
     */
    public function createOrganizationInvitation(string $organizationId, string $email, int $expiresAtUnix, string $link): string
    {
        $data = $this->call('POST', '/api/organization-invitations', [
            'invitee' => $email,
            'organizationId' => $organizationId,
            'expiresAt' => $expiresAtUnix * 1000,
            'messagePayload' => ['link' => $link],
        ], 201);
        if (!is_string($data['id'] ?? null)) {
            throw new RuntimeException('Logto nevrátilo ID pozvánky.');
        }
        return $data['id'];
    }

    public function revokeOrganizationInvitation(string $id): void
    {
        $this->call('PUT', '/api/organization-invitations/' . rawurlencode($id) . '/status', ['status' => 'Revoked'], 200);
    }

    public function acceptOrganizationInvitation(string $id, string $logtoUserId): void
    {
        $this->call('PUT', '/api/organization-invitations/' . rawurlencode($id) . '/status', [
            'status' => 'Accepted',
            'acceptedUserId' => $logtoUserId,
        ], 200);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $body, int $expectedStatus): array
    {
        $response = $this->http->sendJson($method, $this->endpoint . $path, $body, [
            'Authorization' => 'Bearer ' . $this->token(),
        ]);
        if ($response['status'] !== $expectedStatus) {
            // Tělo odpovědi neobsahuje tajemství; pomůže v logu při ladění konfigurace.
            throw new RuntimeException(sprintf('Logto %s %s vrátilo HTTP %d: %s', $method, $path, $response['status'], substr($response['body'], 0, 300)));
        }
        $data = json_decode($response['body'], true);
        return is_array($data) ? $data : [];
    }

    private function token(): string
    {
        if ($this->accessToken !== null && $this->tokenExpiresAt > time() + 30) {
            return $this->accessToken;
        }
        $response = $this->http->postForm($this->endpoint . '/oidc/token', [
            'grant_type' => 'client_credentials',
            'resource' => $this->resource,
            'scope' => 'all',
        ], [
            'Authorization' => 'Basic ' . base64_encode(rawurlencode($this->m2mAppId) . ':' . rawurlencode($this->m2mAppSecret)),
        ]);
        $data = json_decode($response['body'], true);
        if ($response['status'] !== 200 || !is_array($data) || !is_string($data['access_token'] ?? null)) {
            throw new RuntimeException('Nelze získat přístup k Logto Management API (HTTP ' . $response['status'] . '). Zkontrolujte M2M aplikaci.');
        }
        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 300);
        return $this->accessToken;
    }
}
