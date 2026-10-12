<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Zápis do auditního protokolu. Do details nikdy nepatří tokeny ani hesla. */
final class AuditLog
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, scalar|null> $details */
    public function record(?int $actorUserId, string $action, string $targetType, ?int $targetId, array $details = []): void
    {
        $this->pdo->prepare('INSERT INTO audit_log (actor_user_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                $actorUserId,
                $action,
                $targetType,
                $targetId,
                $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
    }
}
