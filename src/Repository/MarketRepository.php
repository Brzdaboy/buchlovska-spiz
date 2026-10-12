<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\MarketFilter;
use PDO;

/**
 * Společné tržiště: jen zveřejněné záznamy (SPECIFIKACE §10, ROZHODNUTI č. 1–4).
 *
 * Záznam je vidět, když: stav je `active`, není smazaný, nevypršel (konec platnosti
 * je prázdný nebo dnes či později) a autor má aktivní účet s vyplněným profilem.
 * Stejná podmínka platí pro seznam i detail – na jednom místě.
 */
final class MarketRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    // --- nabídky ------------------------------------------------------------------------

    private const OFFER_FROM = "FROM offers o
        JOIN users u ON u.id = o.producer_user_id AND u.status = 'active'
        JOIN producer_profiles p ON p.user_id = o.producer_user_id
        JOIN categories c ON c.id = o.category_id
        JOIN units pu ON pu.id = o.price_unit_id
        JOIN units qu ON qu.id = o.quantity_unit_id
        WHERE o.status = 'active' AND o.deleted_at IS NULL
          AND (o.available_until IS NULL OR o.available_until >= ?)";

    private const OFFER_COLUMNS = 'o.id, o.producer_user_id, o.product_name, o.description, o.unit_price, o.available_quantity,
        o.availability_type, o.available_from, o.available_until, o.delivery_terms, o.created_at, o.content_updated_at,
        c.name AS category_name, pu.code AS price_unit, qu.code AS quantity_unit,
        p.producer_name AS owner_name, p.contact_name, p.address_line, p.postal_code, p.municipality,
        p.phone, p.public_email, p.website';

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function offers(MarketFilter $filter, string $today): array
    {
        $where = '';
        $params = [$today];
        if ($filter->q !== '') {
            $where .= " AND o.product_name LIKE ? ESCAPE '!'";
            $params[] = self::like($filter->q);
        }
        if ($filter->categoryId !== null) {
            $where .= ' AND o.category_id = ?';
            $params[] = $filter->categoryId;
        }
        // Dostupnost se překrývá se zvoleným obdobím. Nabídka "ihned" je dostupná od dneška.
        if ($filter->to !== null) {
            $where .= ' AND COALESCE(o.available_from, ?) <= ?';
            array_push($params, $today, $filter->to);
        }
        if ($filter->from !== null) {
            $where .= ' AND (o.available_until IS NULL OR o.available_until >= ?)';
            $params[] = $filter->from;
        }
        if ($filter->priceUnitId !== null) {
            $where .= ' AND o.price_unit_id = ?';
            $params[] = $filter->priceUnitId;
            if ($filter->maxPrice !== null) {
                $where .= ' AND o.unit_price <= ?';
                $params[] = $filter->maxPrice;
            }
        }
        return $this->page(self::OFFER_COLUMNS, self::OFFER_FROM . $where, $params, 'o', $filter->page);
    }

    /** @return array<string, mixed>|null */
    public function offer(int $id, string $today): ?array
    {
        return $this->one('SELECT ' . self::OFFER_COLUMNS . ' ' . self::OFFER_FROM . ' AND o.id = ?', [$today, $id]);
    }

    // --- poptávky -----------------------------------------------------------------------

    private const DEMAND_FROM = "FROM demands d
        JOIN users u ON u.id = d.school_user_id AND u.status = 'active'
        JOIN school_profiles s ON s.user_id = d.school_user_id
        JOIN categories c ON c.id = d.category_id
        JOIN units qu ON qu.id = d.quantity_unit_id
        WHERE d.status = 'active' AND d.deleted_at IS NULL AND d.needed_until >= ?";

    private const DEMAND_COLUMNS = 'd.id, d.school_user_id, d.product_name, d.requested_quantity, d.needed_from, d.needed_until,
        d.note, d.created_at, d.content_updated_at,
        c.name AS category_name, qu.code AS quantity_unit,
        s.school_name AS owner_name, s.contact_name, s.address_line, s.postal_code, s.municipality,
        s.phone, s.public_email, s.website';

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function demands(MarketFilter $filter, string $today): array
    {
        $where = '';
        $params = [$today];
        if ($filter->q !== '') {
            $where .= " AND d.product_name LIKE ? ESCAPE '!'";
            $params[] = self::like($filter->q);
        }
        if ($filter->categoryId !== null) {
            $where .= ' AND d.category_id = ?';
            $params[] = $filter->categoryId;
        }
        if ($filter->to !== null) {
            $where .= ' AND d.needed_from <= ?';
            $params[] = $filter->to;
        }
        if ($filter->from !== null) {
            $where .= ' AND d.needed_until >= ?';
            $params[] = $filter->from;
        }
        return $this->page(self::DEMAND_COLUMNS, self::DEMAND_FROM . $where, $params, 'd', $filter->page);
    }

    /** @return array<string, mixed>|null */
    public function demand(int $id, string $today): ?array
    {
        return $this->one('SELECT ' . self::DEMAND_COLUMNS . ' ' . self::DEMAND_FROM . ' AND d.id = ?', [$today, $id]);
    }

    // --- společné -----------------------------------------------------------------------

    /**
     * Stránka výsledků od nejnovějších. Stránka za koncem se posune na poslední.
     *
     * @param list<mixed> $params
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    private function page(string $columns, string $from, array $params, string $alias, int $page, int $perPage = MarketFilter::PER_PAGE): array
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT {$columns} {$from} ORDER BY {$alias}.created_at DESC, {$alias}.id DESC LIMIT "
            . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @param list<mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** Hledaný text jako vzor LIKE; znaky %, _ a ! se berou doslova. */
    private static function like(string $text): string
    {
        return '%' . strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
}
