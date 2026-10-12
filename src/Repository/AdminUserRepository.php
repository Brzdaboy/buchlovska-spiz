<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Správa účtů administrátorem: přehled, blokace, archivace, obnovení. */
final class AdminUserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param string|null $role producer|school|admin
     * @return list<array<string, mixed>>
     */
    public function list(?string $role, ?string $status): array
    {
        $where = ["u.status <> 'invited'"];
        $params = [];
        if ($role !== null) {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        if ($status !== null) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.role, u.status, u.last_login_at, u.created_at,
                    COALESCE(pp.producer_name, sp.school_name) AS organization,
                    COALESCE(pp.municipality, sp.municipality) AS municipality,
                    (SELECT COUNT(*) FROM offers o WHERE o.producer_user_id = u.id AND o.deleted_at IS NULL) AS offer_count,
                    (SELECT COUNT(*) FROM demands d WHERE d.school_user_id = u.id AND d.deleted_at IS NULL) AS demand_count
             FROM users u
             LEFT JOIN producer_profiles pp ON pp.user_id = u.id
             LEFT JOIN school_profiles sp ON sp.user_id = u.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY u.role, COALESCE(pp.producer_name, sp.school_name, u.email)'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null uživatel s profilem */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*,
                    COALESCE(pp.producer_name, sp.school_name) AS organization,
                    COALESCE(pp.contact_name, sp.contact_name) AS contact_name,
                    COALESCE(pp.address_line, sp.address_line) AS address_line,
                    COALESCE(pp.municipality, sp.municipality) AS municipality,
                    COALESCE(pp.postal_code, sp.postal_code) AS postal_code,
                    COALESCE(pp.phone, sp.phone) AS phone,
                    COALESCE(pp.public_email, sp.public_email) AS public_email,
                    COALESCE(pp.website, sp.website) AS website,
                    COALESCE(pp.company_id, sp.organization_id) AS company_id
             FROM users u
             LEFT JOIN producer_profiles pp ON pp.user_id = u.id
             LEFT JOIN school_profiles sp ON sp.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> záznamy uživatele (bez smazaných) */
    public function listings(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 'offer' AS type, id, product_name, status, available_until AS end_date, created_at FROM offers WHERE producer_user_id = ? AND deleted_at IS NULL
             UNION ALL
             SELECT 'demand' AS type, id, product_name, status, needed_until AS end_date, created_at FROM demands WHERE school_user_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC"
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function block(int $id): bool
    {
        return $this->change($id, "status = 'blocked', blocked_at = UTC_TIMESTAMP()", "status IN ('active', 'profile_incomplete')");
    }

    /** Odblokování i obnovení z archivu: aktivní, pokud má profil, jinak musí profil doplnit. */
    public function restore(int $id): bool
    {
        return $this->change(
            $id,
            "status = IF(profile_completed_at IS NULL, 'profile_incomplete', 'active'), blocked_at = NULL, archived_at = NULL",
            "status IN ('blocked', 'archived')"
        );
    }

    public function archive(int $id): bool
    {
        return $this->change($id, "status = 'archived', archived_at = UTC_TIMESTAMP()", "status IN ('active', 'profile_incomplete', 'blocked')");
    }

    private function change(int $id, string $set, string $allowedFrom): bool
    {
        // Admin účty se přes administraci neblokují (zabrání zamknutí jediného správce).
        $stmt = $this->pdo->prepare("UPDATE users SET {$set} WHERE id = ? AND role IN ('producer', 'school') AND {$allowedFrom}");
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    /** @return array<string, int> počty aktivních účtů podle role */
    public function activeCounts(): array
    {
        $rows = $this->pdo->query("SELECT role, COUNT(*) FROM users WHERE status = 'active' GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map('intval', is_array($rows) ? $rows : []) + ['producer' => 0, 'school' => 0, 'admin' => 0];
    }

    public function markReviewSeen(int $adminId): void
    {
        $this->pdo->prepare('UPDATE users SET last_review_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$adminId]);
    }
}
