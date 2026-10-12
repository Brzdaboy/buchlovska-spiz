<?php
/**
 * Pomocné funkce pro formuláře záznamů (nabídky, poptávky).
 * Vrací pole closure; použití: ['input' => $input, 'select' => $select, ...] = require ...
 *
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 */

$value = static fn (string $name): string => is_scalar($values[$name] ?? null) ? (string) $values[$name] : '';

$describe = static function (string $id, string $name, ?string $hint) use ($errors): array {
    $html = '';
    $ids = [];
    if ($hint !== null) {
        $html .= '<span class="hint" id="' . $id . '-hint">' . e($hint) . '</span>';
        $ids[] = $id . '-hint';
    }
    if (isset($errors[$name])) {
        $html .= '<span class="error" id="' . $id . '-error">' . e($errors[$name]) . '</span>';
        $ids[] = $id . '-error';
    }
    $attrs = ($ids !== [] ? ' aria-describedby="' . implode(' ', $ids) . '"' : '') . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
    return [$html, $attrs];
};

$label = static fn (string $id, string $text, bool $required): string =>
    '<label for="' . $id . '">' . e($text) . ($required ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="optional">(nepovinné)</span>') . '</label>';

$input = static function (string $name, string $text, array $o = []) use ($value, $describe, $label, $errors): string {
    $id = 'f-' . $name;
    $required = $o['required'] ?? true;
    [$extra, $attrs] = $describe($id, $name, $o['hint'] ?? null);
    $html = '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . ($o['class'] ?? '') . '">' . $label($id, $text, $required) . $extra;
    if (($o['type'] ?? '') === 'textarea') {
        $html .= '<textarea id="' . $id . '" name="' . $name . '" rows="' . (int) ($o['rows'] ?? 4) . '"' . ($required ? ' required' : '')
            . (isset($o['maxlength']) ? ' maxlength="' . (int) $o['maxlength'] . '"' : '') . $attrs . '>' . e($value($name)) . '</textarea>';
    } else {
        $html .= '<input id="' . $id . '" name="' . $name . '" type="' . e($o['type'] ?? 'text') . '" value="' . e($value($name)) . '"'
            . ($required ? ' required' : '')
            . (isset($o['inputmode']) ? ' inputmode="' . e($o['inputmode']) . '"' : '')
            . (isset($o['min']) ? ' min="' . e($o['min']) . '"' : '')
            . (isset($o['maxlength']) ? ' maxlength="' . (int) $o['maxlength'] . '"' : '')
            . $attrs . '>';
    }
    return $html . '</div>';
};

/** @param array<int, string> $options id => popisek */
$select = static function (string $name, string $text, array $options, array $o = []) use ($value, $describe, $label, $errors): string {
    $id = 'f-' . $name;
    [$extra, $attrs] = $describe($id, $name, $o['hint'] ?? null);
    $current = $value($name);
    // Při úpravě zůstane vybraná i dnes už neaktivní položka číselníku.
    if ($current !== '' && !isset($options[(int) $current]) && isset($o['legacy'])) {
        $options[(int) $current] = $o['legacy'];
    }
    $html = '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . '">' . $label($id, $text, true) . $extra
        . '<select id="' . $id . '" name="' . $name . '" required' . $attrs . '><option value="">' . e($o['placeholder'] ?? 'Vyberte') . '</option>';
    foreach ($options as $optionValue => $optionLabel) {
        $html .= '<option value="' . (int) $optionValue . '"' . ((string) $optionValue === $current ? ' selected' : '') . '>' . e($optionLabel) . '</option>';
    }
    return $html . '</select></div>';
};

return ['value' => $value, 'input' => $input, 'select' => $select];
