<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Validace nabídky (SPECIFIKACE §8): kategorie, název, přesná jednotková cena v Kč,
 * cenová jednotka, množství a jeho jednotka, dostupnost, volitelný konec, doprava.
 */
final class OfferValidator
{
    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $categories aktivní kategorie
     * @param array<int, string> $units aktivní jednotky
     * @param array<string, mixed>|null $existing při úpravě: dovolí ponechat i dnes neaktivní kategorii/jednotku
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $input, array $categories, array $units, string $today, ?array $existing = null): array
    {
        $errors = [];
        $text = static function (string $name) use ($input): string {
            $value = $input[$name] ?? '';
            return is_string($value) ? trim($value) : '';
        };

        $data = [
            'category_id' => self::id($text('category_id')),
            'product_name' => preg_replace('/\s+/u', ' ', $text('product_name')) ?? '',
            'description' => $text('description') === '' ? null : $text('description'),
            'unit_price' => self::decimal($text('unit_price'), 2),
            'price_unit_id' => self::id($text('price_unit_id')),
            'available_quantity' => self::decimal($text('available_quantity'), 3),
            'quantity_unit_id' => self::id($text('quantity_unit_id')),
            'availability_type' => $text('availability_type'),
            'available_from' => $text('available_from') === '' ? null : $text('available_from'),
            'available_until' => $text('available_until') === '' ? null : $text('available_until'),
            'delivery_terms' => $text('delivery_terms'),
        ];

        $allowed = static fn (?int $id, array $list, ?string $existingKey) =>
            $id !== null && (isset($list[$id]) || ($existing !== null && $existingKey !== null && (int) $existing[$existingKey] === $id));

        if (!$allowed($data['category_id'], $categories, 'category_id')) {
            $errors['category_id'] = 'Vyberte kategorii.';
        }
        if ($data['product_name'] === '') {
            $errors['product_name'] = 'Napište, co nabízíte (např. Brambory Adéla).';
        } elseif (mb_strlen($data['product_name']) > 150) {
            $errors['product_name'] = 'Název může mít nejvýš 150 znaků.';
        }
        if ($data['description'] !== null && mb_strlen($data['description']) > 2000) {
            $errors['description'] = 'Popis může mít nejvýš 2000 znaků.';
        }

        if ($data['unit_price'] === null || (float) $data['unit_price'] <= 0 || (float) $data['unit_price'] >= 100000000) {
            $errors['unit_price'] = 'Zadejte přesnou cenu v Kč větší než nula (např. 14,50). Cena „dohodou“ není možná.';
        }
        if (!$allowed($data['price_unit_id'], $units, 'price_unit_id')) {
            $errors['price_unit_id'] = 'Vyberte, za jakou jednotku cena platí.';
        }
        if ($data['available_quantity'] === null || (float) $data['available_quantity'] <= 0 || (float) $data['available_quantity'] >= 1000000000) {
            $errors['available_quantity'] = 'Zadejte dostupné množství větší než nula.';
        }
        if (!$allowed($data['quantity_unit_id'], $units, 'quantity_unit_id')) {
            $errors['quantity_unit_id'] = 'Vyberte jednotku množství.';
        }

        if ($data['availability_type'] === 'immediate') {
            $data['available_from'] = null;
        } elseif ($data['availability_type'] === 'from_date') {
            if (!self::isDate($data['available_from'])) {
                $errors['available_from'] = 'Zadejte datum, od kdy je produkt k dispozici.';
            }
        } else {
            $errors['availability_type'] = 'Zvolte, zda je produkt k dispozici ihned, nebo od data.';
        }

        if ($data['available_until'] !== null) {
            if (!self::isDate($data['available_until'])) {
                $errors['available_until'] = 'Zadejte platné datum.';
            } elseif ($data['available_until'] < $today) {
                $errors['available_until'] = 'Konec platnosti nemůže být v minulosti.';
            } elseif (self::isDate($data['available_from']) && $data['available_until'] < $data['available_from']) {
                $errors['available_until'] = 'Konec platnosti musí být po datu dostupnosti.';
            }
        }

        if ($data['delivery_terms'] === '') {
            $errors['delivery_terms'] = 'Popište podmínky dopravy (např. rozvoz do 20 km zdarma, nebo jen osobní odběr).';
        } elseif (mb_strlen($data['delivery_terms']) > 1000) {
            $errors['delivery_terms'] = 'Podmínky dopravy mohou mít nejvýš 1000 znaků.';
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /** "14,5" / "14.50" / "1 200,00" -> "14.50"; jinak null. */
    public static function decimal(string $value, int $scale): ?string
    {
        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value);
        if (!preg_match('/^\d{1,12}(\.\d{1,' . $scale . '})?$/', $normalized)) {
            return null;
        }
        return number_format((float) $normalized, $scale, '.', '');
    }

    public static function isDate(?string $value): bool
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y);
    }

    private static function id(string $value): ?int
    {
        return ctype_digit($value) && $value !== '0' && strlen($value) < 7 ? (int) $value : null;
    }
}
