<?php
/**
 * Jednotné formulářové prvky pro všechny formuláře.
 *
 * Rozvržení každého pole:  popisek  →  pole  →  chyba, nebo (bez chyby) nápověda.
 * Pole v jednom řádku tak začínají ve stejné výšce bez ohledu na délku nápovědy.
 * Krátké příklady patří do 'placeholder', trvale potřebné vysvětlení do 'hint'.
 * Nápověda i chyba jsou s polem propojené přes aria-describedby.
 *
 * Použití v šabloně:
 *   ['value' => $value, 'input' => $input, 'select' => $select, 'checkbox' => $checkbox, 'feedback' => $feedback]
 *       = require dirname(__DIR__) . '/partials/fields.php';
 *
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 */

$values ??= [];
$errors ??= [];

$value = static fn (string $name): string => is_scalar($values[$name] ?? null) ? (string) $values[$name] : '';

/**
 * Text pod polem (chyba, nebo nápověda) a atributy, které ho s polem propojí.
 *
 * @return array{0: string, 1: string} [HTML pod polem, atributy pro pole]
 */
$feedback = static function (string $id, string $name, ?string $hint) use ($errors): array {
    if (isset($errors[$name])) {
        return [
            '<span class="field-error" id="' . $id . '-error">' . e($errors[$name]) . '</span>',
            ' aria-describedby="' . $id . '-error" aria-invalid="true"',
        ];
    }
    if ($hint !== null) {
        return ['<span class="field-hint" id="' . $id . '-hint">' . e($hint) . '</span>', ' aria-describedby="' . $id . '-hint"'];
    }
    return ['', ''];
};

$label = static fn (string $id, string $text, bool $required): string =>
    '<label for="' . $id . '">' . e($text)
    . ($required ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="optional">(nepovinné)</span>')
    . '</label>';

$attr = static fn (array $o, string $key, string $name): string =>
    isset($o[$key]) ? ' ' . $name . '="' . e((string) $o[$key]) . '"' : '';

/**
 * Textové pole, e-mail, telefon, datum nebo textarea.
 * Volby: type, required, placeholder, hint, inputmode, autocomplete, min, maxlength, rows.
 */
$input = static function (string $name, string $text, array $o = []) use ($value, $feedback, $label, $attr, $errors): string {
    $id = 'f-' . $name;
    $required = $o['required'] ?? true;
    [$below, $aria] = $feedback($id, $name, $o['hint'] ?? null);
    $common = ($required ? ' required' : '')
        . $attr($o, 'placeholder', 'placeholder') . $attr($o, 'maxlength', 'maxlength')
        . $attr($o, 'autocomplete', 'autocomplete') . $aria;

    if (($o['type'] ?? '') === 'textarea') {
        $control = '<textarea id="' . $id . '" name="' . $name . '" rows="' . (int) ($o['rows'] ?? 4) . '"' . $common . '>'
            . e($value($name)) . '</textarea>';
    } else {
        $control = '<input id="' . $id . '" name="' . $name . '" type="' . e($o['type'] ?? 'text') . '" value="' . e($value($name)) . '"'
            . $common . $attr($o, 'inputmode', 'inputmode') . $attr($o, 'min', 'min') . '>';
    }
    return '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . '">'
        . $label($id, $text, $required) . $control . $below . '</div>';
};

/**
 * Výběr z číselníku. Volby: placeholder, hint, legacy (popisek pro dnes neaktivní uloženou hodnotu).
 *
 * @param array<int, string> $options id => popisek
 */
$select = static function (string $name, string $text, array $options, array $o = []) use ($value, $feedback, $label, $errors): string {
    $id = 'f-' . $name;
    [$below, $aria] = $feedback($id, $name, $o['hint'] ?? null);
    $current = $value($name);
    if ($current !== '' && !isset($options[(int) $current]) && isset($o['legacy'])) {
        $options[(int) $current] = $o['legacy'];
    }
    $html = '<select id="' . $id . '" name="' . $name . '" required' . $aria . '>'
        . '<option value="">' . e($o['placeholder'] ?? 'Vyberte') . '</option>';
    foreach ($options as $optionValue => $optionLabel) {
        $html .= '<option value="' . (int) $optionValue . '"' . ((string) $optionValue === $current ? ' selected' : '') . '>'
            . e($optionLabel) . '</option>';
    }
    return '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . '">'
        . $label($id, $text, true) . $html . '</select>' . $below . '</div>';
};

/**
 * Zaškrtávací pole se souhlasem. $labelHtml je důvěryhodný statický text (může obsahovat odkaz).
 */
$checkbox = static function (string $name, string $labelHtml) use ($value, $feedback, $errors): string {
    $id = 'f-' . $name;
    [$below, $aria] = $feedback($id, $name, null);
    return '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . '">'
        . '<label class="checkbox" for="' . $id . '"><input id="' . $id . '" type="checkbox" name="' . $name . '" value="1" required'
        . ($value($name) === '1' ? ' checked' : '') . $aria . '> <span>' . $labelHtml . '</span></label>'
        . $below . '</div>';
};

return ['value' => $value, 'input' => $input, 'select' => $select, 'checkbox' => $checkbox, 'feedback' => $feedback];
