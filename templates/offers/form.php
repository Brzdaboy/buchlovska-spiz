<?php
/** @var array<string, mixed>|null $offer */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var array<int, string> $categories */
/** @var array<int, string> $units */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
['value' => $value, 'input' => $input, 'select' => $select] = require dirname(__DIR__) . '/partials/fields.php';
$isNew = $offer === null;
$availability = $value('availability_type') ?: 'immediate';
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><a href="/moje-nabidky">Moje nabídky</a></p>
        <h1><?= $isNew ? 'Nová nabídka' : 'Upravit nabídku' ?></h1>
        <p class="lead">
            <?php if ($isNew): ?>
                Nabídka se zveřejní hned po uložení. Kontakty se k ní doplní z vašeho profilu.
            <?php elseif ($offer['status'] === 'withdrawn'): ?>
                Správce nabídku stáhl: <strong><?= e($offer['withdrawal_reason']) ?></strong>. Po opravě ji znovu uvolní.
            <?php else: ?>
                Změny se zveřejní okamžitě.
            <?php endif; ?>
        </p>
    </div>
</section>
<div class="admin-content">
    <?php if ($errors !== []): ?>
        <div class="flash flash-error form-summary" role="alert">Formulář obsahuje chyby. Opravte prosím zvýrazněná pole.</div>
    <?php endif; ?>
    <form method="post" action="<?= $isNew ? '/moje-nabidky' : '/moje-nabidky/' . (int) $offer['id'] ?>" class="card profile-form" novalidate>
        <?= $csrf->field() ?>
        <fieldset>
            <legend>Co nabízíte</legend>
            <div class="form-grid">
                <?= $select('category_id', 'Kategorie', $categories, ['legacy' => 'Původní kategorie (už se nepoužívá)']) ?>
                <?= $input('product_name', 'Název plodiny nebo výrobku', ['maxlength' => 150, 'hint' => 'Např. Brambory Adéla, Květový med']) ?>
            </div>
            <?= $input('description', 'Popis', ['type' => 'textarea', 'required' => false, 'maxlength' => 2000, 'rows' => 3, 'hint' => 'Odrůda, kvalita, balení…']) ?>
        </fieldset>

        <fieldset>
            <legend>Cena a množství</legend>
            <div class="form-grid pairs">
                <?= $input('unit_price', 'Cena v Kč', ['inputmode' => 'decimal', 'hint' => 'Přesná cena za jednotku, např. 14,50']) ?>
                <?= $select('price_unit_id', 'Cena za', array_map(static fn ($u) => 'Kč / ' . $u, $units), ['legacy' => 'původní jednotka']) ?>
                <?= $input('available_quantity', 'Dostupné množství', ['inputmode' => 'decimal']) ?>
                <?= $select('quantity_unit_id', 'Jednotka množství', $units, ['legacy' => 'původní jednotka']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Dostupnost</legend>
            <div class="field<?= isset($errors['availability_type']) ? ' has-error' : '' ?>">
                <?php if (isset($errors['availability_type'])): ?><span class="error"><?= e($errors['availability_type']) ?></span><?php endif; ?>
                <label class="radio"><input type="radio" name="availability_type" value="immediate" <?= $availability === 'immediate' ? 'checked' : '' ?>> Ihned</label>
                <label class="radio"><input type="radio" name="availability_type" value="from_date" <?= $availability === 'from_date' ? 'checked' : '' ?>> Od data</label>
            </div>
            <div class="form-grid">
                <?= $input('available_from', 'Dostupné od', ['type' => 'date', 'required' => false, 'hint' => 'Vyplňte jen při volbě „Od data“.']) ?>
                <?= $input('available_until', 'Nabídka platí do', ['type' => 'date', 'required' => false, 'min' => $today, 'hint' => 'Bez data platí do ručního ukončení.']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Doprava</legend>
            <?= $input('delivery_terms', 'Podmínky dopravy', ['type' => 'textarea', 'maxlength' => 1000, 'rows' => 3, 'hint' => 'Např. rozvoz do 20 km zdarma, jinak osobní odběr.']) ?>
        </fieldset>

        <div class="form-actions">
            <button class="button" type="submit"><?= $isNew ? 'Zveřejnit nabídku' : 'Uložit změny' ?></button>
            <a class="button secondary" href="/moje-nabidky">Zrušit</a>
        </div>
    </form>
</div>
