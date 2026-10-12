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

    /** @return list<array{id: int, name: string, slug: string}> aktivní kategorie pro filtr tržiště */
    public function activeCategoryOptions(): array
    {
        $rows = $this->pdo->query('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'slug' => (string) $r['slug']], $rows);
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
}
