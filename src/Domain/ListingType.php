<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Popis dvou typů záznamů tržiště (nabídka, poptávka) pro společný kód:
 * tabulka, sloupec autora, sloupec konce platnosti, URL segment, popisky.
 */
final class ListingType
{
    private const TYPES = [
        'offer' => [
            'table' => 'offers', 'owner' => 'producer_user_id', 'end' => 'available_until',
            'segment' => 'nabidky', 'label' => 'Nabídka', 'label_plural' => 'Nabídky', 'owner_role' => 'producer',
            'owner_profile' => 'producer_profiles', 'owner_name' => 'producer_name',
        ],
        'demand' => [
            'table' => 'demands', 'owner' => 'school_user_id', 'end' => 'needed_until',
            'segment' => 'poptavky', 'label' => 'Poptávka', 'label_plural' => 'Poptávky', 'owner_role' => 'school',
            'owner_profile' => 'school_profiles', 'owner_name' => 'school_name',
        ],
    ];

    /** @param array<string, string> $meta */
    private function __construct(public readonly string $key, private array $meta)
    {
    }

    public static function of(string $key): self
    {
        if (!isset(self::TYPES[$key])) {
            throw new \InvalidArgumentException('Neznámý typ záznamu.');
        }
        return new self($key, self::TYPES[$key]);
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::of('offer'), self::of('demand')];
    }

    public function table(): string { return $this->meta['table']; }
    public function ownerColumn(): string { return $this->meta['owner']; }
    public function endColumn(): string { return $this->meta['end']; }
    public function segment(): string { return $this->meta['segment']; }
    public function label(): string { return $this->meta['label']; }
    public function labelPlural(): string { return $this->meta['label_plural']; }
    public function ownerRole(): string { return $this->meta['owner_role']; }
    public function ownerProfileTable(): string { return $this->meta['owner_profile']; }
    public function ownerNameColumn(): string { return $this->meta['owner_name']; }
}
