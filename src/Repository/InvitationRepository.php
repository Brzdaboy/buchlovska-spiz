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
}
