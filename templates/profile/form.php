<?php
/** @var string $role */
/** @var array<string, mixed> $values */
/** @var array<string, mixed>|null $existing */
/** @var array<string, string> $errors */
/** @var string $accountEmail */
/** @var \App\Security\Csrf $csrf */

$isNew = $existing === null;
$value = static fn (string $name): string => is_scalar($values[$name] ?? null) ? (string) $values[$name] : '';

/**
 * Vykreslí textové pole s popiskem, nápovědou a chybou (propojené přes aria-describedby).
 */
$field = static function (string $name, string $label, array $opts = []) use ($value, $errors): string {
    $id = 'f-' . $name;
    $type = $opts['type'] ?? 'text';
    $required = $opts['required'] ?? true;
    $describedBy = [];
    $html = '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . '">';
    $html .= '<label for="' . $id . '">' . e($label) . ($required ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="optional">(nepovinné)</span>') . '</label>';
    if (isset($opts['hint'])) {
        $html .= '<span class="hint" id="' . $id . '-hint">' . e($opts['hint']) . '</span>';
        $describedBy[] = $id . '-hint';
    }
    if (isset($errors[$name])) {
        $html .= '<span class="error" id="' . $id . '-error">' . e($errors[$name]) . '</span>';
        $describedBy[] = $id . '-error';
    }
    $html .= '<input id="' . $id . '" name="' . $name . '" type="' . $type . '" value="' . e($value($name)) . '"'
        . ($required ? ' required' : '')
        . (isset($opts['autocomplete']) ? ' autocomplete="' . e($opts['autocomplete']) . '"' : '')
        . (isset($opts['inputmode']) ? ' inputmode="' . e($opts['inputmode']) . '"' : '')
        . (isset($opts['maxlength']) ? ' maxlength="' . (int) $opts['maxlength'] . '"' : '')
        . ($describedBy !== [] ? ' aria-describedby="' . implode(' ', $describedBy) . '"' : '')
        . (isset($errors[$name]) ? ' aria-invalid="true"' : '')
        . '>';
    return $html . '</div>';
};
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><?= e($role === 'school' ? 'Škola / školní jídelna' : 'Zemědělec / producent') ?></p>
        <h1><?= $isNew ? 'Dokončení profilu' : 'Můj profil' ?></h1>
        <p class="lead">
            <?php if ($isNew): ?>
                Ještě krok: doplňte kontaktní údaje. Ostatní přihlášení členové je uvidí u vašich <?= $role === 'school' ? 'poptávek' : 'nabídek' ?>, aby vás mohli oslovit.
            <?php else: ?>
                Změny se zveřejní okamžitě u všech vašich <?= $role === 'school' ? 'poptávek' : 'nabídek' ?>.
            <?php endif; ?>
        </p>
    </div>
</section>
<div class="admin-content">
    <?php if ($errors !== []): ?>
        <div class="flash flash-error form-summary" role="alert">Formulář obsahuje chyby. Opravte prosím zvýrazněná pole.</div>
    <?php endif; ?>
    <form method="post" action="/profil" class="card profile-form" novalidate>
        <?= $csrf->field() ?>

        <fieldset>
            <legend><?= $role === 'school' ? 'Škola' : 'Farma nebo producent' ?></legend>
            <div class="form-grid">
                <?php if ($role === 'school'): ?>
                    <?= $field('school_name', 'Název školy nebo školní jídelny', ['autocomplete' => 'organization', 'maxlength' => 190]) ?>
                    <?= $field('organization_id', 'IČO nebo identifikátor organizace', ['required' => false, 'inputmode' => 'numeric', 'maxlength' => 20]) ?>
                <?php else: ?>
                    <?= $field('producer_name', 'Název farmy nebo producenta', ['autocomplete' => 'organization', 'maxlength' => 190]) ?>
                    <?= $field('company_id', 'IČO', ['required' => false, 'inputmode' => 'numeric', 'maxlength' => 10]) ?>
                <?php endif; ?>
                <?= $field('contact_name', 'Jméno a příjmení kontaktní osoby', ['autocomplete' => 'name', 'maxlength' => 150]) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Adresa</legend>
            <div class="form-grid">
                <?= $field('address_line', 'Ulice a číslo popisné', ['autocomplete' => 'street-address', 'maxlength' => 190]) ?>
                <?= $field('municipality', 'Obec', ['autocomplete' => 'address-level2', 'maxlength' => 120]) ?>
                <?= $field('postal_code', 'PSČ', ['autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'hint' => 'Např. 687 08']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Kontakty pro ostatní členy</legend>
            <div class="form-grid">
                <?= $field('phone', 'Telefon', ['type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 32]) ?>
                <?= $field('public_email', 'Kontaktní e-mail', ['type' => 'email', 'autocomplete' => 'email', 'maxlength' => 190, 'hint' => 'Může být jiný než přihlašovací (' . $accountEmail . ').']) ?>
                <?= $field('website', 'Web', ['required' => false, 'autocomplete' => 'url', 'maxlength' => 255, 'hint' => 'Např. www.farma.cz']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Souhlasy</legend>
            <?php if ($isNew): ?>
                <?php foreach ([
                    'privacy_consent' => 'Souhlasím se zpracováním osobních údajů pro provoz platformy podle <a href="/ochrana-osobnich-udaju" target="_blank" rel="noopener">zásad ochrany osobních údajů</a>.',
                    'contact_publication_consent' => 'Souhlasím, aby se adresa, telefon, e-mail a web zobrazovaly přihlášeným členům u mých ' . ($role === 'school' ? 'poptávek' : 'nabídek') . '.',
                ] as $name => $label): ?>
                    <div class="field<?= isset($errors[$name]) ? ' has-error' : '' ?>">
                        <?php if (isset($errors[$name])): ?><span class="error" id="f-<?= $name ?>-error"><?= e($errors[$name]) ?></span><?php endif; ?>
                        <label class="checkbox">
                            <input type="checkbox" name="<?= $name ?>" value="1" required <?= ($values[$name] ?? '') === '1' ? 'checked' : '' ?><?= isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="f-' . $name . '-error"' : '' ?>>
                            <span><?= $label /* statický text s odkazem */ ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">Souhlasy jste udělili <?= e(format_datetime((string) $existing['privacy_consent_at'], 'j. n. Y')) ?>. Pokud je chcete odvolat, kontaktujte správce.</p>
            <?php endif; ?>
        </fieldset>

        <div class="form-actions">
            <button class="button" type="submit"><?= $isNew ? 'Uložit a pokračovat' : 'Uložit změny' ?></button>
            <?php if (!$isNew): ?><a class="button secondary" href="/portal">Zpět do portálu</a><?php endif; ?>
        </div>
    </form>
</div>
