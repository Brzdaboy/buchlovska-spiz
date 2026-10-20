<?php
/** @var array<string, mixed>|null $demand */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var array<int, string> $categories */
/** @var array<int, string> $units */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
['input' => $input, 'select' => $select] = require dirname(__DIR__) . '/partials/fields.php';
$isNew = $demand === null;
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [[$isNew ? 'Nová poptávka' : (string) $demand['product_name'], null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1><?= $isNew ? 'Nová poptávka' : 'Upravit poptávku' ?></h1>
        <?php if (!$isNew && $demand['status'] === 'withdrawn'): ?>
            <p class="hero-meta">Správce poptávku stáhl: <strong><?= e($demand['withdrawal_reason']) ?></strong>. Po opravě ji znovu uvolní.</p>
        <?php endif; ?>
    </div>
</section>
<div class="admin-content">
    <?php if ($errors !== []): ?>
        <div class="flash flash-error form-summary" role="alert">Formulář obsahuje chyby. Opravte prosím zvýrazněná pole.</div>
    <?php endif; ?>
    <form method="post" action="<?= $isNew ? '/moje-poptavky' : '/moje-poptavky/' . (int) $demand['id'] ?>" class="card profile-form" novalidate>
        <?= $csrf->field() ?>
        <fieldset>
            <legend>Co potřebujete</legend>
            <div class="form-grid">
                <?= $select('category_id', 'Kategorie', $categories, ['legacy' => 'Původní kategorie (už se nepoužívá)']) ?>
                <?= $input('product_name', 'Název plodiny nebo výrobku', ['maxlength' => 150, 'placeholder' => 'Např. Jablka']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Množství</legend>
            <div class="form-grid pairs">
                <?= $input('requested_quantity', 'Požadované množství', ['inputmode' => 'decimal', 'placeholder' => 'Např. 120']) ?>
                <?= $select('quantity_unit_id', 'Jednotka', $units, ['legacy' => 'původní jednotka']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Období, kdy produkt potřebujete</legend>
            <div class="form-grid pairs">
                <?= $input('needed_from', 'Od', ['type' => 'date']) ?>
                <?= $input('needed_until', 'Do', ['type' => 'date', 'min' => $today, 'hint' => 'Po tomto dni se poptávka sama ukončí.']) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Poznámka</legend>
            <?= $input('note', 'Poznámka pro zemědělce', ['type' => 'textarea', 'required' => false, 'maxlength' => 2000, 'rows' => 3, 'placeholder' => 'Např. dodávky jednou týdně, velikost, kvalita…']) ?>
        </fieldset>

        <div class="form-actions">
            <button class="button" type="submit"><?= $isNew ? 'Zveřejnit poptávku' : 'Uložit změny' ?></button>
            <a class="button secondary" href="/portal">Zrušit</a>
        </div>
    </form>
</div>
