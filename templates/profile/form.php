<?php
/** @var string $role */
/** @var array<string, mixed> $values */
/** @var array<string, mixed>|null $existing */
/** @var array<string, string> $errors */
/** @var string $accountEmail */
/** @var \App\Security\Csrf $csrf */
['input' => $input, 'checkbox' => $checkbox] = require dirname(__DIR__) . '/partials/fields.php';

$isNew = $existing === null;
$records = $role === 'school' ? 'poptávek' : 'nabídek';
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><?= e($role === 'school' ? 'Škola / školní jídelna' : 'Zemědělec / producent') ?></p>
        <h1><?= $isNew ? 'Dokončení profilu' : 'Můj profil' ?></h1>
        <p class="lead">
            <?php if ($isNew): ?>
                Ještě krok: doplňte kontaktní údaje. Ostatní přihlášení členové je uvidí u vašich <?= $records ?>, aby vás mohli oslovit.
            <?php else: ?>
                Změny se zveřejní okamžitě u všech vašich <?= $records ?>.
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
                    <?= $input('school_name', 'Název školy nebo školní jídelny', ['autocomplete' => 'organization', 'maxlength' => 190, 'placeholder' => 'Např. ZŠ a MŠ Buchlovice']) ?>
                    <?= $input('organization_id', 'IČO nebo identifikátor organizace', ['required' => false, 'inputmode' => 'numeric', 'maxlength' => 20, 'placeholder' => 'Např. 70939032']) ?>
                <?php else: ?>
                    <?= $input('producer_name', 'Název farmy nebo producenta', ['autocomplete' => 'organization', 'maxlength' => 190, 'placeholder' => 'Např. Farma Pod Barborkou']) ?>
                    <?= $input('company_id', 'IČO', ['required' => false, 'inputmode' => 'numeric', 'maxlength' => 10, 'placeholder' => '8 číslic']) ?>
                <?php endif; ?>
                <?= $input('contact_name', 'Jméno a příjmení kontaktní osoby', ['autocomplete' => 'name', 'maxlength' => 150, 'placeholder' => 'Např. Jana Nováková']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Adresa</legend>
            <div class="form-grid">
                <?= $input('address_line', 'Ulice a číslo popisné', ['autocomplete' => 'street-address', 'maxlength' => 190, 'placeholder' => 'Např. Náves 12']) ?>
                <?= $input('municipality', 'Obec', ['autocomplete' => 'address-level2', 'maxlength' => 120, 'placeholder' => 'Např. Buchlovice']) ?>
                <?= $input('postal_code', 'PSČ', ['autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'placeholder' => '687 08']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Kontakty pro ostatní členy</legend>
            <div class="form-grid">
                <?= $input('phone', 'Telefon', ['type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 32, 'placeholder' => '+420 600 123 456']) ?>
                <?= $input('public_email', 'Kontaktní e-mail', ['type' => 'email', 'autocomplete' => 'email', 'maxlength' => 190, 'placeholder' => 'Např. farma@email.cz', 'hint' => 'Může být jiný než přihlašovací (' . $accountEmail . ').']) ?>
                <?= $input('website', 'Web', ['required' => false, 'autocomplete' => 'url', 'maxlength' => 255, 'placeholder' => 'www.farma.cz']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Souhlasy</legend>
            <?php if ($isNew): ?>
                <?= $checkbox('privacy_consent', 'Souhlasím se zpracováním osobních údajů pro provoz platformy podle <a href="/ochrana-osobnich-udaju" target="_blank" rel="noopener">zásad ochrany osobních údajů</a>.') ?>
                <?= $checkbox('contact_publication_consent', 'Souhlasím, aby se adresa, telefon, e-mail a web zobrazovaly přihlášeným členům u mých ' . $records . '.') ?>
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
