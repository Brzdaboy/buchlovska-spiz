<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repository\AuditLog;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Převede ověřenou identitu z Logto na lokální účet.
 *
 * Databáze aplikace je zdroj pravdy (ADR 001): samotná existence účtu v Logto
 * nestačí. Nový člověk se propojí jen přes platnou čekající pozvánku na svůj
 * ověřený e-mail; admin jen přes účet založený skriptem bin/create-admin.php.
 */
final class LoginService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private InvitationRepository $invitations,
        private AuditLog $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $claims ověřené claims z ID tokenu
     * @return array<string, mixed> řádek uživatele
     * @throws LoginDenied
     */
    public function resolve(array $claims, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $sub = (string) $claims['sub'];

        $user = $this->users->findByLogtoId($sub);
        if ($user !== null) {
            return $this->assertCanSignIn($user);
        }

        // Ještě nepropojený účet: jen přes ověřený e-mail.
        $email = is_string($claims['email'] ?? null) ? mb_strtolower(trim($claims['email'])) : '';
        if ($email === '') {
            throw new LoginDenied(LoginDenied::UNKNOWN);
        }
        if (($claims['email_verified'] ?? false) !== true) {
            throw new LoginDenied(LoginDenied::EMAIL_NOT_VERIFIED);
        }
        $user = $this->users->findByEmail($email);
        if ($user === null || $user['logto_user_id'] !== null || $user['status'] !== 'invited') {
            throw new LoginDenied(LoginDenied::UNKNOWN);
        }

        $userId = (int) $user['id'];
        if ($user['role'] === 'admin') {
            $this->link($userId, $sub, 'active', null);
        } else {
            $invitation = $this->invitations->findPendingForUser($userId);
            if ($invitation === null) {
                throw new LoginDenied(LoginDenied::UNKNOWN);
            }
            $expires = new DateTimeImmutable((string) $invitation['expires_at'], new DateTimeZone('UTC'));
            if ($expires <= $now) {
                $this->invitations->markExpired((int) $invitation['id']);
                throw new LoginDenied(LoginDenied::INVITATION_EXPIRED);
            }
            $this->link($userId, $sub, 'profile_incomplete', (int) $invitation['id']);
        }

        return $this->users->findById($userId) ?? throw new LoginDenied(LoginDenied::UNKNOWN);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function assertCanSignIn(array $user): array
    {
        if (in_array($user['status'], ['blocked', 'archived'], true)) {
            throw new LoginDenied(LoginDenied::BLOCKED);
        }
        if ($user['status'] === 'invited') {
            throw new LoginDenied(LoginDenied::UNKNOWN);
        }
        return $user;
    }

    private function link(int $userId, string $sub, string $status, ?int $invitationId): void
    {
        $this->pdo->beginTransaction();
        try {
            if (!$this->users->linkIdentity($userId, $sub, $status)) {
                throw new LoginDenied(LoginDenied::UNKNOWN);
            }
            if ($invitationId !== null) {
                $this->invitations->markAccepted($invitationId);
            }
            $this->audit->record($userId, 'user.identity_linked', 'user', $userId, ['invitation_id' => $invitationId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
