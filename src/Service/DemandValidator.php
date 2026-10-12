<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Validace poptávky školy (SPECIFIKACE §9): kategorie, název, požadované množství
 * a jednotka, období potřeby (od–do). Poptávka NEOBSAHUJE cenu ani rozpočet.
 */
final class DemandValidator
{
    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $categories aktivní kategorie
     * @param array<int, string> $units aktivní jednotky
     * @param array<string, mixed>|null $existing při úpravě dovolí ponechat dnes neaktivní kategorii/jednotku
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
            'category_id' => ctype_digit($text('category_id')) && strlen($text('category_id')) < 7 ? (int) $text('category_id') : null,
            'product_name' => preg_replace('/\s+/u', ' ', $text('product_name')) ?? '',
            'requested_quantity' => OfferValidator::decimal($text('requested_quantity'), 3),
            'quantity_unit_id' => ctype_digit($text('quantity_unit_id')) && strlen($text('quantity_unit_id')) < 7 ? (int) $text('quantity_unit_id') : null,
            'needed_from' => $text('needed_from'),
            'needed_until' => $text('needed_until'),
            'note' => $text('note') === '' ? null : $text('note'),
        ];

        $allowed = static fn (?int $id, array $list, string $key) =>
            $id !== null && $id > 0 && (isset($list[$id]) || ($existing !== null && (int) $existing[$key] === $id));

        if (!$allowed($data['category_id'], $categories, 'category_id')) {
            $errors['category_id'] = 'Vyberte kategorii.';
        }
        if ($data['product_name'] === '') {
            $errors['product_name'] = 'Napište, co škola potřebuje (např. Jablka).';
        } elseif (mb_strlen($data['product_name']) > 150) {
            $errors['product_name'] = 'Název může mít nejvýš 150 znaků.';
        }
        if ($data['requested_quantity'] === null || (float) $data['requested_quantity'] <= 0 || (float) $data['requested_quantity'] >= 1000000000) {
            $errors['requested_quantity'] = 'Zadejte požadované množství větší než nula.';
        }
        if (!$allowed($data['quantity_unit_id'], $units, 'quantity_unit_id')) {
            $errors['quantity_unit_id'] = 'Vyberte jednotku množství.';
        }

        $fromOk = OfferValidator::isDate($data['needed_from']);
        $untilOk = OfferValidator::isDate($data['needed_until']);
        if (!$fromOk) {
            $errors['needed_from'] = 'Zadejte, od kdy produkt potřebujete.';
        }
        if (!$untilOk) {
            $errors['needed_until'] = 'Zadejte, do kdy produkt potřebujete.';
        } elseif ($data['needed_until'] < $today) {
            $errors['needed_until'] = 'Konec období nemůže být v minulosti.';
        } elseif ($fromOk && $data['needed_until'] < $data['needed_from']) {
            $errors['needed_until'] = 'Konec období musí být po jeho začátku.';
        }

        if ($data['note'] !== null && mb_strlen($data['note']) > 2000) {
            $errors['note'] = 'Poznámka může mít nejvýš 2000 znaků.';
        }

        return ['data' => $data, 'errors' => $errors];
    }
}
