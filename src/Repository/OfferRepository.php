<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Nabídky. Všechny metody pro autora filtrují podle producer_user_id
 * a ignorují smazané záznamy: cizí nebo smazaná nabídka "neexistuje".
 */
final class OfferRepository
{
    /** Sloupce, které smí měnit autor (stav, schválení a vlastník se nastavují jinde). */
    public const CONTENT_COLUMNS = [
        'category_id', 'product_name', 'description', 'unit_price', 'price_unit_id',
        'available_quantity', 'quantity_unit_id', 'availability_type', 'available_from',
        'available_until', 'delivery_terms',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findOwned(int $id, int $producerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM offers WHERE id = ? AND producer_user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $producerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> vlastní nabídky s názvy kategorií a jednotek */
    public function listOwned(int $producerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, c.name AS category_name, pu.code AS price_unit, qu.code AS quantity_unit
             FROM offers o
             JOIN categories c ON c.id = o.category_id
             JOIN units pu ON pu.id = o.price_unit_id
             JOIN units qu ON qu.id = o.quantity_unit_id
             WHERE o.producer_user_id = ? AND o.deleted_at IS NULL
             ORDER BY o.created_at DESC, o.id DESC'
        );
        $stmt->execute([$producerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $data */
    public function create(int $producerId, array $data): int
    {
        $columns = self::CONTENT_COLUMNS;
        $sql = sprintf(
            "INSERT INTO offers (producer_user_id, %s, currency, status, content_updated_at) VALUES (?, %s, 'CZK', 'active', UTC_TIMESTAMP())",
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->pdo->prepare($sql)->execute([$producerId, ...array_map(static fn ($c) => $data[$c], $columns)]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateContent(int $id, int $producerId, array $data, string $newStatus): void
    {
        $set = implode(', ', array_map(static fn ($c) => "{$c} = ?", self::CONTENT_COLUMNS));
        $sql = "UPDATE offers SET {$set}, status = ?, content_updated_at = UTC_TIMESTAMP()
                WHERE id = ? AND producer_user_id = ? AND deleted_at IS NULL";
        $values = array_map(static fn ($c) => $data[$c], self::CONTENT_COLUMNS);
        $this->pdo->prepare($sql)->execute([...$values, $newStatus, $id, $producerId]);
    }

    public function end(int $id, int $producerId): void
    {
        $this->pdo->prepare(
            "UPDATE offers SET status = 'ended', ended_at = UTC_TIMESTAMP() WHERE id = ? AND producer_user_id = ? AND deleted_at IS NULL AND status = 'active'"
        )->execute([$id, $producerId]);
    }

    public function reactivate(int $id, int $producerId, ?string $newUntil, bool $changeUntil): void
    {
        $sql = "UPDATE offers SET status = 'active', ended_at = NULL, content_updated_at = UTC_TIMESTAMP()"
            . ($changeUntil ? ', available_until = ?' : '')
            . " WHERE id = ? AND producer_user_id = ? AND deleted_at IS NULL AND status IN ('active', 'ended', 'expired')";
        $params = $changeUntil ? [$newUntil, $id, $producerId] : [$id, $producerId];
        $this->pdo->prepare($sql)->execute($params);
    }

    public function softDelete(int $id, int $producerId): void
    {
        $this->pdo->prepare('UPDATE offers SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND producer_user_id = ? AND deleted_at IS NULL')
            ->execute([$id, $producerId]);
    }
}
