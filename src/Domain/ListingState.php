<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Pravidla životního cyklu nabídky i poptávky (docs/ROZHODNUTI.md):
 *
 *   (nová) ──► active ──ukončit──► ended ──obnovit──► active
 *                 │  └─datum konce─► expired ─obnovit s novým datem─► active
 *                 └─admin stáhne─► withdrawn ─autor opraví─► resubmitted ─admin uvolní─► active
 *
 * Smazání autorem je měkké (deleted_at) a možné z každého stavu.
 * Žádné předběžné schvalování: nový i upravený záznam je hned aktivní.
 */
final class ListingState
{
    public const ACTIVE = 'active';
    public const ENDED = 'ended';
    public const EXPIRED = 'expired';
    public const WITHDRAWN = 'withdrawn';
    public const RESUBMITTED = 'resubmitted';

    public const LABELS = [
        self::ACTIVE => 'Zveřejněno',
        self::ENDED => 'Ukončeno',
        self::EXPIRED => 'Vypršelo',
        self::WITHDRAWN => 'Staženo správcem',
        self::RESUBMITTED => 'Čeká na uvolnění',
    ];

    /**
     * Skutečný stav: aktivní záznam s prošlým datem konce je "vypršelý"
     * i dřív, než ho přepíše plánovaná úloha.
     */
    public static function effective(string $status, ?string $endDate, string $today): string
    {
        if ($status === self::ACTIVE && $endDate !== null && $endDate < $today) {
            return self::EXPIRED;
        }
        return $status;
    }

    /** Stav po úpravě obsahu autorem. */
    public static function afterEdit(string $status): string
    {
        // Stažený záznam po opravě čeká na uvolnění adminem; ostatní stavy se nemění.
        return $status === self::WITHDRAWN ? self::RESUBMITTED : $status;
    }

    public static function canEnd(string $effective): bool
    {
        return $effective === self::ACTIVE;
    }

    public static function canReactivate(string $effective): bool
    {
        return in_array($effective, [self::ENDED, self::EXPIRED], true);
    }

    /**
     * Reaktivace je okamžitá; prošlé datum konce ale musí autor nejdřív posunout,
     * jinak by záznam hned znovu vypršel.
     */
    public static function reactivationNeedsNewDate(?string $endDate, string $today): bool
    {
        return $endDate !== null && $endDate < $today;
    }

    /** Vidí záznam ostatní členové? (stav + datum; smazání a účet autora řeší dotaz) */
    public static function isPublic(string $status, ?string $endDate, string $today): bool
    {
        return self::effective($status, $endDate, $today) === self::ACTIVE;
    }
}
