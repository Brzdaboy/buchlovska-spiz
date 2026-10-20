<?php
/** @var array<string, mixed> $demand */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
['input' => $input] = require dirname(__DIR__) . '/partials/fields.php';
$periodOver = $demand['needed_until'] < $today;
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [['Znovu zveřejnit: ' . $demand['product_name'], null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Znovu zveřejnit: <?= e($demand['product_name']) ?></h1>
    </div>
</section>
<div class="admin-content">
    <form method="post" action="/moje-poptavky/<?= (int) $demand['id'] ?>/obnovit" class="card profile-form" novalidate>
        <?= $csrf->field() ?>
        <p>
            <?php if ($periodOver): ?>
                Původní období (<?= e(format_date($demand['needed_from'])) ?> – <?= e(format_date($demand['needed_until'])) ?>) už skončilo. Zadejte nové.
            <?php else: ?>
                Zkontrolujte období, kdy produkt potřebujete.
            <?php endif; ?>
        </p>
        <div class="form-grid pairs">
            <?= $input('needed_from', 'Od', ['type' => 'date']) ?>
            <?= $input('needed_until', 'Do', ['type' => 'date', 'min' => $today, 'hint' => 'Po tomto dni se poptávka sama ukončí.']) ?>
        </div>
        <div class="form-actions">
            <button class="button" type="submit">Znovu zveřejnit</button>
            <a class="button secondary" href="/portal">Zrušit</a>
        </div>
    </form>
</div>
