<?php

declare(strict_types=1);

if (!function_exists('format_datetime')) {
    /** Čas z databáze (UTC) zobrazí v časové zóně aplikace, např. "19. 10. 2026 14:05". */
    function format_datetime(?string $utc, string $format = 'j. n. Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '–';
        }
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
    }
}

if (!function_exists('e')) {
    /** Escapuje hodnotu pro bezpečný výstup do HTML (text i atributy). */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
