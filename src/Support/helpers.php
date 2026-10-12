<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escapuje hodnotu pro bezpečný výstup do HTML (text i atributy). */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
