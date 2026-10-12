<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Číselníky kategorií a jednotek. */
final class CatalogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, string> id => název (jen aktivní, v pořadí) */
    public function activeCategories(): array
    {
        return $this->pairs('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
    }

    /** @return array<int, string> id => kód jednotky (jen aktivní) */
    public function activeUnits(): array
    {
        return $this->pairs('SELECT id, code FROM units WHERE is_active = 1 ORDER BY sort_order, code');
    }

    /** @return array<int, string> */
    private function pairs(string $sql): array
    {
        $result = [];
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as [$id, $label]) {
            $result[(int) $id] = (string) $label;
        }
        return $result;
    }

    /** @return list<array<string, mixed>> všechny kategorie vč. neaktivních, s počtem použití */
    public function allCategories(): array
    {
        return $this->pdo->query(
            'SELECT c.*, (SELECT COUNT(*) FROM offers o WHERE o.category_id = c.id AND o.deleted_at IS NULL)
                       + (SELECT COUNT(*) FROM demands d WHERE d.category_id = c.id AND d.deleted_at IS NULL) AS usage_count
             FROM categories c ORDER BY c.sort_order, c.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> všechny jednotky vč. neaktivních */
    public function allUnits(): array
    {
        return $this->pdo->query(
            'SELECT u.*, (SELECT COUNT(*) FROM offers o WHERE (o.price_unit_id = u.id OR o.quantity_unit_id = u.id) AND o.deleted_at IS NULL)
                       + (SELECT COUNT(*) FROM demands d WHERE d.quantity_unit_id = u.id AND d.deleted_at IS NULL) AS usage_count
             FROM units u ORDER BY u.sort_order, u.code'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCategory(string $name, string $slug, int $sortOrder): int
    {
        $this->pdo->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)')->execute([$name, $slug, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $id, string $name, int $sortOrder, bool $active): void
    {
        $this->pdo->prepare('UPDATE categories SET name = ?, sort_order = ?, is_active = ? WHERE id = ?')
            ->execute([$name, $sortOrder, $active ? 1 : 0, $id]);
    }

    public function createUnit(string $code, string $label, int $sortOrder): int
    {
        $this->pdo->prepare('INSERT INTO units (code, label, sort_order) VALUES (?, ?, ?)')->execute([$code, $label, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Kód jednotky se po vytvoření nemění (je součástí zobrazených cen a množství). */
    public function updateUnit(int $id, string $label, int $sortOrder, bool $active): void
    {
        $this->pdo->prepare('UPDATE units SET label = ?, sort_order = ?, is_active = ? WHERE id = ?')
            ->execute([$label, $sortOrder, $active ? 1 : 0, $id]);
    }

    public function categoryExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    public function unitExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM units WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    /** "Mléko a mléčné výrobky" -> "mleko-a-mlecne-vyrobky" */
    public static function slugify(string $name): string
    {
        $ascii = strtr(mb_strtolower($name), [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o',
            'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
        ]);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    }
}
