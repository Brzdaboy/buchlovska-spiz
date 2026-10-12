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

if (!function_exists('format_date')) {
    /** Kalendářní datum "2026-11-05" -> "5. 11. 2026". */
    function format_date(?string $date): string
    {
        if ($date === null || $date === '') {
            return '–';
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        return $d ? $d->format('j. n. Y') : $date;
    }
}

if (!function_exists('format_number')) {
    /** Číslo po česku bez zbytečných nul: 800.000 -> "800", 1250.5 -> "1 250,5". */
    function format_number(string|int|float $value, int $maxDecimals = 3): string
    {
        $formatted = number_format((float) $value, $maxDecimals, ',', "\u{00A0}");
        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}

if (!function_exists('format_price')) {
    /** 14.5 + "kg" -> "14,50 Kč / kg" */
    function format_price(string|float $price, string $unit): string
    {
        return number_format((float) $price, 2, ',', "\u{00A0}") . "\u{00A0}Kč\u{00A0}/\u{00A0}" . $unit;
    }
}

if (!function_exists('today')) {
    /** Dnešní datum v časové zóně aplikace (Europe/Prague) jako "Y-m-d". */
    function today(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('e')) {
    /** Escapuje hodnotu pro bezpečný výstup do HTML (text i atributy). */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
