<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Poptávky škol. Metody pro autora filtrují podle school_user_id
 * a ignorují smazané záznamy: cizí nebo smazaná poptávka "neexistuje".
 */
final class DemandRepository
{
    /** Sloupce, které smí měnit autor. */
    public const CONTENT_COLUMNS = [
        'category_id', 'product_name', 'requested_quantity', 'quantity_unit_id',
        'needed_from', 'needed_until', 'note',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findOwned(int $id, int $schoolId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM demands WHERE id = ? AND school_user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $schoolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listOwned(int $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, c.name AS category_name, u.code AS quantity_unit
             FROM demands d
             JOIN categories c ON c.id = d.category_id
             JOIN units u ON u.id = d.quantity_unit_id
             WHERE d.school_user_id = ? AND d.deleted_at IS NULL
             ORDER BY d.created_at DESC, d.id DESC'
        );
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $data */
    public function create(int $schoolId, array $data): int
    {
        $columns = self::CONTENT_COLUMNS;
        $sql = sprintf(
            "INSERT INTO demands (school_user_id, %s, status, content_updated_at) VALUES (?, %s, 'active', UTC_TIMESTAMP())",
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->pdo->prepare($sql)->execute([$schoolId, ...array_map(static fn ($c) => $data[$c], $columns)]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateContent(int $id, int $schoolId, array $data, string $newStatus): void
    {
        $set = implode(', ', array_map(static fn ($c) => "{$c} = ?", self::CONTENT_COLUMNS));
        $sql = "UPDATE demands SET {$set}, status = ?, content_updated_at = UTC_TIMESTAMP()
                WHERE id = ? AND school_user_id = ? AND deleted_at IS NULL";
        $values = array_map(static fn ($c) => $data[$c], self::CONTENT_COLUMNS);
        $this->pdo->prepare($sql)->execute([...$values, $newStatus, $id, $schoolId]);
    }

    public function end(int $id, int $schoolId): void
    {
        $this->pdo->prepare(
            "UPDATE demands SET status = 'ended', ended_at = UTC_TIMESTAMP() WHERE id = ? AND school_user_id = ? AND deleted_at IS NULL AND status = 'active'"
        )->execute([$id, $schoolId]);
    }

    public function reactivate(int $id, int $schoolId, string $neededFrom, string $neededUntil): void
    {
        $this->pdo->prepare(
            "UPDATE demands SET status = 'active', ended_at = NULL, needed_from = ?, needed_until = ?, content_updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND school_user_id = ? AND deleted_at IS NULL AND status IN ('active', 'ended', 'expired')"
        )->execute([$neededFrom, $neededUntil, $id, $schoolId]);
    }

    /**
     * Smazání autorem: záznam i jeho historie změn zmizí z databáze hned (ROZHODNUTI č. 4 a 12).
     */
    public function delete(int $id, int $schoolId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM demands WHERE id = ? AND school_user_id = ?');
            $stmt->execute([$id, $schoolId]);
            if ($stmt->rowCount() > 0) {
                $this->pdo->prepare("DELETE FROM audit_log WHERE target_type = 'demand' AND target_id = ?")->execute([$id]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
