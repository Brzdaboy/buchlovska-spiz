<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Filtr tržiště z parametrů URL (SPECIFIKACE §11; cenový filtr zrušen – ROZHODNUTI č. 10).
 *
 *   q        hledaný text v názvu produktu
 *   kategorie slug kategorie
 *   od, do   období dostupnosti (nabídky) nebo potřeby (poptávky), RRRR-MM-DD
 *   strana   číslo stránky
 *
 * Neplatné hodnoty se do dotazu nepouštějí; uživatel dostane u pole vysvětlení.
 */
final class MarketFilter
{
    public const PER_PAGE = 12;
    private const MAX_QUERY = 100;

    /** @param array<string, string> $errors */
    private function __construct(
        public readonly string $q,
        public readonly ?int $categoryId,
        public readonly string $categorySlug,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly int $page,
        public readonly array $errors,
        /** @var array<string, string> původní (neplatné i platné) hodnoty pro formulář */
        public readonly array $input,
    ) {
    }

    /**
     * @param array<string, mixed> $query $_GET
     * @param list<array{id: int, name: string, slug: string}> $categories aktivní kategorie
     */
    public static function fromQuery(array $query, array $categories): self
    {
        $raw = static fn (string $key): string => is_string($query[$key] ?? null) ? trim($query[$key]) : '';
        $errors = [];

        $q = preg_replace('/\s+/u', ' ', $raw('q')) ?? '';
        $q = mb_substr($q, 0, self::MAX_QUERY);

        $categoryId = null;
        $slug = $raw('kategorie');
        foreach ($categories as $category) {
            if ($category['slug'] === $slug) {
                $categoryId = (int) $category['id'];
            }
        }
        if ($categoryId === null) {
            $slug = '';
        }

        $from = $raw('od') === '' ? null : $raw('od');
        $to = $raw('do') === '' ? null : $raw('do');
        if ($from !== null && !OfferValidator::isDate($from)) {
            $errors['od'] = 'Zadejte datum.';
            $from = null;
        }
        if ($to !== null && !OfferValidator::isDate($to)) {
            $errors['do'] = 'Zadejte datum.';
            $to = null;
        }
        if ($from !== null && $to !== null && $to < $from) {
            $errors['do'] = 'Konec období musí být po jeho začátku.';
            $to = null;
        }

        $page = $raw('strana');
        $page = ctype_digit($page) && strlen($page) < 6 && (int) $page > 0 ? (int) $page : 1;

        $input = ['q' => $q, 'kategorie' => $slug, 'od' => $raw('od'), 'do' => $raw('do')];

        return new self($q, $categoryId, $slug, $from, $to, $page, $errors, $input);
    }

    /** Je zapnutý nějaký filtr (kromě stránky)? */
    public function isActive(): bool
    {
        return $this->q !== '' || $this->categoryId !== null || $this->from !== null || $this->to !== null;
    }

    /** Počet zapnutých filtrů mimo hledání (kategorie, období) – číslo u tlačítka Filtry. */
    public function extraFilterCount(): int
    {
        return (int) ($this->categoryId !== null)
            + (int) ($this->from !== null || $this->to !== null);
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->q, $this->categoryId, $this->categorySlug, $this->from, $this->to,
            $page, $this->errors, $this->input
        );
    }

    /** Dotazovací řetězec platného filtru pro odkazy (stránkování), např. "?q=jablka&strana=2". */
    public function queryString(?int $page = null): string
    {
        $params = array_filter([
            'q' => $this->q,
            'kategorie' => $this->categorySlug,
            'od' => $this->from ?? '',
            'do' => $this->to ?? '',
            'strana' => ($page ?? $this->page) > 1 ? (string) ($page ?? $this->page) : '',
        ], static fn (string $v): bool => $v !== '');
        return $params === [] ? '' : '?' . http_build_query($params);
    }
}
