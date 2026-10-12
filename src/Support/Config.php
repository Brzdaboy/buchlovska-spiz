<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Konfigurace aplikace. Soubor config.php leží mimo veřejnou složku a nikdy
 * není v gitu; vzor je config/config.example.php.
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
        foreach (['app.url', 'app.env'] as $required) {
            if ($this->get($required) === null) {
                throw new RuntimeException("Chybí povinná konfigurace: {$required}");
            }
        }
        if (!in_array($this->get('app.env'), ['production', 'development', 'test'], true)) {
            throw new RuntimeException('app.env musí být production, development nebo test.');
        }
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException('Konfigurační soubor nebyl nalezen.');
        }
        $values = require $path;
        if (!is_array($values)) {
            throw new RuntimeException('Konfigurační soubor musí vracet pole.');
        }
        return new self($values);
    }

    /**
     * Najde config.php v tomto pořadí:
     * 1. proměnná prostředí BSPIZ_CONFIG (např. SetEnv v .htaccess),
     * 2. <projekt>/config/config.php,
     * 3. o úroveň výš než projekt (mimo www na WEDOS).
     */
    public static function locate(string $projectRoot): string
    {
        $explicit = getenv('BSPIZ_CONFIG');
        if (is_string($explicit) && $explicit !== '') {
            // Výslovně zadaná cesta nesmí potichu spadnout na jiný soubor.
            if (!is_file($explicit)) {
                throw new RuntimeException('Konfigurační soubor z BSPIZ_CONFIG neexistuje.');
            }
            return $explicit;
        }
        $candidates = [
            $projectRoot . '/config/config.php',
            dirname($projectRoot) . '/buchlovska-config.php',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException('Konfigurační soubor nebyl nalezen.');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function isProduction(): bool
    {
        return $this->get('app.env') === 'production';
    }

    public function isDebug(): bool
    {
        return !$this->isProduction() && (bool) $this->get('app.debug', false);
    }

    public function isHttps(): bool
    {
        return str_starts_with((string) $this->get('app.url'), 'https://');
    }
}
