<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ListingType;
use PDO;

/**
 * Pohled administrátora na nabídky a poptávky. Admin záznamy čte, stahuje
 * a uvolňuje, ale NIKDY nemění jejich obsah (SPECIFIKACE §3).
 */
final class AdminListingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Seznam záznamů obou typů pro přehled.
     *
     * @param 'new'|'resubmitted'|'withdrawn'|'all' $filter
     * @return list<array<string, mixed>>
     */
    public function overview(string $filter, ?string $seenSince, int $limit = 200): array
    {
        $parts = [];
        $params = [];
        foreach (ListingType::all() as $type) {
            $where = ['l.deleted_at IS NULL'];
            if ($filter === 'new') {
                if ($seenSince !== null) {
                    $where[] = 'l.content_updated_at > ?';
                    $params[] = $seenSince;
                }
            } elseif ($filter === 'resubmitted') {
                $where[] = "l.status = 'resubmitted'";
            } elseif ($filter === 'withdrawn') {
                $where[] = "l.status IN ('withdrawn', 'resubmitted')";
            }
            $parts[] = sprintf(
                "SELECT '%s' AS type, l.id, l.product_name, l.status, l.%s AS end_date, l.created_at, l.content_updated_at,
                        c.name AS category_name, p.%s AS owner_name, u.email AS owner_email, u.status AS owner_status
                 FROM %s l
                 JOIN categories c ON c.id = l.category_id
                 JOIN users u ON u.id = l.%s
                 LEFT JOIN %s p ON p.user_id = u.id
                 WHERE %s",
                $type->key,
                $type->endColumn(),
                $type->ownerNameColumn(),
                $type->table(),
                $type->ownerColumn(),
                $type->ownerProfileTable(),
                implode(' AND ', $where)
            );
        }
        $sql = implode(' UNION ALL ', $parts) . ' ORDER BY content_updated_at DESC, id DESC LIMIT ' . max(1, min($limit, 500));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{new: int, resubmitted: int} */
    public function counts(?string $seenSince): array
    {
        $new = 0;
        $resubmitted = 0;
        foreach (ListingType::all() as $type) {
            $stmt = $this->pdo->prepare(
                'SELECT SUM(content_updated_at > COALESCE(?, \'1970-01-01\')), SUM(status = \'resubmitted\')
                 FROM ' . $type->table() . ' WHERE deleted_at IS NULL'
            );
            $stmt->execute([$seenSince]);
            [$n, $r] = $stmt->fetch(PDO::FETCH_NUM);
            $new += (int) $n;
            $resubmitted += (int) $r;
        }
        return ['new' => $new, 'resubmitted' => $resubmitted];
    }

    /** @return array<string, mixed>|null detail včetně autora a číselníků (smazané nevrací) */
    public function find(ListingType $type, int $id): ?array
    {
        $units = $type->key === 'offer'
            ? ', pu.code AS price_unit, qu.code AS quantity_unit'
            : ', qu.code AS quantity_unit';
        $joins = $type->key === 'offer'
            ? 'JOIN units pu ON pu.id = l.price_unit_id JOIN units qu ON qu.id = l.quantity_unit_id'
            : 'JOIN units qu ON qu.id = l.quantity_unit_id';
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT l.*, c.name AS category_name, u.email AS owner_email, u.status AS owner_status, u.id AS owner_id,
                    p.%s AS owner_name, p.phone AS owner_phone, p.public_email AS owner_public_email,
                    w.email AS withdrawn_by_email %s
             FROM %s l
             JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = l.%s
             LEFT JOIN %s p ON p.user_id = u.id
             LEFT JOIN users w ON w.id = l.withdrawn_by
             %s
             WHERE l.id = ? AND l.deleted_at IS NULL',
            $type->ownerNameColumn(),
            $units,
            $type->table(),
            $type->ownerColumn(),
            $type->ownerProfileTable(),
            $joins
        ));
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** Stažení: jen zveřejněný záznam (ukončené ostatní stejně nevidí), s povinným důvodem. */
    public function withdraw(ListingType $type, int $id, int $adminId, string $reason): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . $type->table() . " SET status = 'withdrawn', withdrawn_by = ?, withdrawn_at = UTC_TIMESTAMP(), withdrawal_reason = ?
             WHERE id = ? AND deleted_at IS NULL AND status = 'active'"
        );
        $stmt->execute([$adminId, $reason, $id]);
        return $stmt->rowCount() === 1;
    }

    /** Uvolnění staženého nebo opraveného záznamu zpět do tržiště. */
    public function release(ListingType $type, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . $type->table() . " SET status = 'active', withdrawn_by = NULL, withdrawn_at = NULL, withdrawal_reason = NULL
             WHERE id = ? AND deleted_at IS NULL AND status IN ('withdrawn', 'resubmitted')"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> historie záznamu z auditu (nejnovější nahoře) */
    public function history(ListingType $type, int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.action, a.details, a.created_at, u.email AS actor_email, u.role AS actor_role
             FROM audit_log a LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE a.target_type = ? AND a.target_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 50'
        );
        $stmt->execute([$type->key, $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
