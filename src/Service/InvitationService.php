<?php

declare(strict_types=1);

namespace App\Service;

use App\Auth\LogtoManagement;
use App\Repository\AuditLog;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Support\Logger;
use PDO;
use Throwable;

/**
 * Pozvánky (SPECIFIKACE §4): admin zadá e-mail a roli, Logto pošle odkaz platný 7 dní.
 * Zdrojem pravdy je lokální tabulka invitations (ADR 001).
 */
final class InvitationService
{
    public const VALIDITY_SECONDS = 7 * 24 * 3600;
    public const ROLES = ['producer' => 'Zemědělec / producent', 'school' => 'Škola / školní jídelna'];

    /** @param \Closure(int $invitationId, string $token): string $linkBuilder */
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private InvitationRepository $invitations,
        private AuditLog $audit,
        private LogtoManagement $logto,
        private string $organizationId,
        private \Closure $linkBuilder,
        private Logger $logger,
    ) {
    }

    /** @throws InvitationError */
    public function invite(string $rawEmail, string $role, int $adminId): int
    {
        $email = mb_strtolower(trim($rawEmail));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new InvitationError('Zadejte platnou e-mailovou adresu.');
        }
        if (!isset(self::ROLES[$role])) {
            throw new InvitationError('Vyberte roli zemědělec nebo škola.');
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null && $user['status'] !== 'invited') {
            throw new InvitationError('Tento e-mail už má účet. Jedna organizace může mít jen jeden účet.');
        }
        if ($user !== null && $this->invitations->findPendingForUser((int) $user['id']) !== null) {
            throw new InvitationError('Na tento e-mail už čeká pozvánka. Použijte „Poslat znovu“.');
        }

        $userId = $user !== null ? (int) $user['id'] : null;
        $invitationId = $this->sendNew($email, $role, $adminId, $userId);
        $this->audit->record($adminId, 'invitation.created', 'invitation', $invitationId, ['role' => $role]);
        return $invitationId;
    }

    /** Opětovné odeslání = zrušení staré pozvánky a nová se stejným e-mailem a rolí. */
    public function resend(int $invitationId, int $adminId): int
    {
        $old = $this->invitations->findById($invitationId) ?? throw new InvitationError('Pozvánka neexistuje.');
        if (!in_array($old['status'], ['pending', 'expired'], true)) {
            throw new InvitationError('Znovu lze poslat jen čekající nebo prošlou pozvánku.');
        }
        $user = $this->users->findById((int) $old['user_id']);
        if ($user === null || $user['status'] !== 'invited') {
            throw new InvitationError('Uživatel už registraci dokončil.');
        }
        // Stará pozvánka se zruší ve stejné transakci jako vznik nové:
        // když odeslání selže, původní pozvánka zůstane platná.
        $newId = $this->sendNew((string) $old['email'], (string) $old['role'], $adminId, (int) $user['id'], $invitationId);
        $this->revokeInLogto($old);
        $this->audit->record($adminId, 'invitation.resent', 'invitation', $newId, ['previous_id' => $invitationId]);
        return $newId;
    }

    public function revoke(int $invitationId, int $adminId): void
    {
        $invitation = $this->invitations->findById($invitationId) ?? throw new InvitationError('Pozvánka neexistuje.');
        if ($invitation['status'] !== 'pending') {
            throw new InvitationError('Zrušit lze jen čekající pozvánku.');
        }
        $this->invitations->markRevoked($invitationId);
        $this->revokeInLogto($invitation);
        $this->audit->record($adminId, 'invitation.revoked', 'invitation', $invitationId);
    }

    /** Po propojení účtu označí pozvánku jako přijatou i v Logto (nekritické). */
    public function markAcceptedInLogto(int $userId, string $logtoUserId): void
    {
        $invitation = $this->invitations->findLatestAcceptedForUser($userId);
        if ($invitation === null || !is_string($invitation['logto_invitation_id'])) {
            return;
        }
        try {
            $this->logto->acceptOrganizationInvitation($invitation['logto_invitation_id'], $logtoUserId);
        } catch (Throwable $e) {
            $this->logger->error('Pozvánku v Logto nelze označit jako přijatou: ' . $e->getMessage());
        }
    }

    private function sendNew(string $email, string $role, int $adminId, ?int $existingUserId, ?int $replaceInvitationId = null): int
    {
        $expiresAt = time() + self::VALIDITY_SECONDS;

        $this->pdo->beginTransaction();
        $oneTimeToken = null;
        try {
            $userId = $existingUserId ?? $this->users->createInvited($email, $role);
            if ($existingUserId !== null) {
                $this->users->updateInvitedRole($existingUserId, $role);
            }
            if ($replaceInvitationId !== null) {
                $this->invitations->markRevoked($replaceInvitationId);
            }
            $invitationId = $this->invitations->create($userId, $email, $role, $adminId, $expiresAt);

            $oneTimeToken = $this->logto->createOneTimeToken($email, self::VALIDITY_SECONDS);
            $link = ($this->linkBuilder)($invitationId, $oneTimeToken['token']);
            $logtoInvitationId = $this->logto->createOrganizationInvitation($this->organizationId, $email, $expiresAt, $link);

            $this->invitations->setLogtoIds($invitationId, $logtoInvitationId, $oneTimeToken['id']);
            $this->pdo->commit();
            return $invitationId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($oneTimeToken !== null) {
                $this->safely(fn () => $this->logto->revokeOneTimeToken($oneTimeToken['id']));
            }
            $this->logger->error('Pozvánku nelze odeslat: ' . $e->getMessage());
            throw new InvitationError('Pozvánku se nepodařilo odeslat přes Logto. Zkuste to později; podrobnosti jsou v logu.');
        }
    }

    /** @param array<string, mixed> $invitation */
    private function revokeInLogto(array $invitation): void
    {
        if (is_string($invitation['logto_one_time_token_id'] ?? null)) {
            $this->safely(fn () => $this->logto->revokeOneTimeToken($invitation['logto_one_time_token_id']));
        }
        if (is_string($invitation['logto_invitation_id'] ?? null)) {
            $this->safely(fn () => $this->logto->revokeOrganizationInvitation($invitation['logto_invitation_id']));
        }
    }

    /**
     * Zrušení v Logto je pojistka: přístup stejně hlídá lokální pozvánka,
     * takže chyba Logto nesmí zablokovat zrušení u nás.
     */
    private function safely(callable $operation): void
    {
        try {
            $operation();
        } catch (Throwable $e) {
            $this->logger->error('Operace v Logto selhala (nekritické): ' . $e->getMessage());
        }
    }
}
