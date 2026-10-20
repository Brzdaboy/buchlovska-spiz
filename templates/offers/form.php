<?php
/** @var array<string, mixed>|null $offer */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var array<int, string> $categories */
/** @var array<int, string> $units */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
['value' => $value, 'input' => $input, 'select' => $select, 'feedback' => $feedback] = require dirname(__DIR__) . '/partials/fields.php';
$isNew = $offer === null;
$availability = $value('availability_type') ?: 'immediate';
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [[$isNew ? 'Nová nabídka' : (string) $offer['product_name'], null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1><?= $isNew ? 'Nová nabídka' : 'Upravit nabídku' ?></h1>
        <?php if (!$isNew && $offer['status'] === 'withdrawn'): ?>
            <p class="hero-meta">Správce nabídku stáhl: <strong><?= e($offer['withdrawal_reason']) ?></strong>. Po opravě ji znovu uvolní.</p>
        <?php endif; ?>
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
                <?= $input('product_name', 'Název plodiny nebo výrobku', ['maxlength' => 150, 'placeholder' => 'Např. Brambory Adéla']) ?>
            </div>
            <?= $input('description', 'Popis', ['type' => 'textarea', 'required' => false, 'maxlength' => 2000, 'rows' => 3, 'placeholder' => 'Odrůda, kvalita, balení…']) ?>
        </fieldset>

        <fieldset>
            <legend>Cena a množství</legend>
            <div class="form-grid pairs">
                <?= $input('unit_price', 'Cena v Kč', ['inputmode' => 'decimal', 'placeholder' => 'Např. 14,50', 'hint' => 'Přesná cena za jednotku.']) ?>
                <?= $select('price_unit_id', 'Cena za', array_map(static fn ($u) => 'Kč / ' . $u, $units), ['legacy' => 'původní jednotka']) ?>
                <?= $input('available_quantity', 'Dostupné množství', ['inputmode' => 'decimal', 'placeholder' => 'Např. 800']) ?>
                <?= $select('quantity_unit_id', 'Jednotka množství', $units, ['legacy' => 'původní jednotka']) ?>
            </div>
        </fieldset>

        <fieldset class="availability">
            <legend>Dostupnost</legend>
            <?php [$availabilityFeedback, $availabilityAria] = $feedback('f-availability_type', 'availability_type', null); ?>
            <div class="field<?= isset($errors['availability_type']) ? ' has-error' : '' ?>" role="radiogroup" aria-label="Dostupnost"<?= $availabilityAria ?>>
                <div class="radio-row">
                    <label class="radio"><input type="radio" name="availability_type" value="immediate" <?= $availability === 'immediate' ? 'checked' : '' ?>> Ihned</label>
                    <label class="radio"><input type="radio" name="availability_type" value="from_date" <?= $availability === 'from_date' ? 'checked' : '' ?>> Od data</label>
                </div>
                <?= $availabilityFeedback ?>
            </div>
            <div class="form-grid pairs">
                <?php /* Pole „Dostupné od“ je povinné jen při „Od data“; při „Ihned“ ho schová CSS (:has) a server hodnotu zahodí. */ ?>
                <div class="availability-from"><?= $input('available_from', 'Dostupné od', ['type' => 'date']) ?></div>
                <?= $input('available_until', 'Nabídka platí do', ['type' => 'date', 'required' => false, 'min' => $today, 'hint' => 'Bez data platí do ručního ukončení.']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Doprava</legend>
            <?= $input('delivery_terms', 'Podmínky dopravy', ['type' => 'textarea', 'maxlength' => 1000, 'rows' => 3, 'placeholder' => 'Např. rozvoz do 20 km zdarma, jinak osobní odběr.']) ?>
        </fieldset>

        <div class="form-actions">
            <button class="button" type="submit"><?= $isNew ? 'Zveřejnit nabídku' : 'Uložit změny' ?></button>
            <a class="button secondary" href="/portal">Zrušit</a>
        </div>
    </form>
</div>
