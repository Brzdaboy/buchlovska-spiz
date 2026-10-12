<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class InvitationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Čekající pozvánka pro uživatele, bez ohledu na expiraci.
     *
     * @return array<string, mixed>|null
     */
    public function findPendingForUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM invitations WHERE user_id = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function markAccepted(int $id): void
    {
        $this->pdo->prepare("UPDATE invitations SET status = 'accepted', accepted_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'pending'")
            ->execute([$id]);
    }

    public function markExpired(int $id): void
    {
        $this->pdo->prepare("UPDATE invitations SET status = 'expired' WHERE id = ? AND status = 'pending'")->execute([$id]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invitations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findLatestAcceptedForUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM invitations WHERE user_id = ? AND status = 'accepted' ORDER BY accepted_at DESC, id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(int $userId, string $email, string $role, int $invitedBy, int $expiresAtUnix): int
    {
        $this->pdo->prepare(
            'INSERT INTO invitations (user_id, email, role, invited_by, created_at, expires_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?)'
        )->execute([$userId, $email, $role, $invitedBy, gmdate('Y-m-d H:i:s', $expiresAtUnix)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setLogtoIds(int $id, string $logtoInvitationId, string $oneTimeTokenId): void
    {
        $this->pdo->prepare('UPDATE invitations SET logto_invitation_id = ?, logto_one_time_token_id = ? WHERE id = ?')
            ->execute([$logtoInvitationId, $oneTimeTokenId, $id]);
    }

    public function markRevoked(int $id): void
    {
        $this->pdo->prepare("UPDATE invitations SET status = 'revoked', revoked_at = UTC_TIMESTAMP() WHERE id = ? AND status IN ('pending', 'expired')")
            ->execute([$id]);
    }

    /**
     * Přehled pro administraci, nejnovější nahoře. Prošlé čekající pozvánky
     * se hlásí jako "expired" i bez běhu cronu.
     *
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, u.status AS user_status,
                    IF(i.status = 'pending' AND i.expires_at <= UTC_TIMESTAMP(), 'expired', i.status) AS effective_status
             FROM invitations i JOIN users u ON u.id = i.user_id
             ORDER BY i.created_at DESC, i.id DESC LIMIT " . max(1, min($limit, 500))
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
