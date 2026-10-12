<?php
/** @var array<string, mixed> $offer */
/** @var array<string, string> $errors */
/** @var string|null $value */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingState;

$needsDate = ListingState::reactivationNeedsNewDate($offer['available_until'], today());
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><a href="/moje-nabidky">Moje nabídky</a></p>
        <h1>Znovu zveřejnit: <?= e($offer['product_name']) ?></h1>
        <p class="lead">Nabídka se zveřejní hned, bez schvalování.</p>
    </div>
</section>
<div class="admin-content">
    <form method="post" action="/moje-nabidky/<?= (int) $offer['id'] ?>/obnovit" class="card profile-form">
        <?= $csrf->field() ?>
        <?php if ($needsDate): ?>
            <p>Původní konec platnosti (<?= e(format_date($offer['available_until'])) ?>) už uplynul. Zadejte nový, nebo pole nechte prázdné a nabídka bude platit do ručního ukončení.</p>
        <?php else: ?>
            <p>Platnost: <?= $offer['available_until'] === null ? 'do ručního ukončení' : 'do ' . e(format_date($offer['available_until'])) ?>.</p>
            <label class="checkbox"><input type="checkbox" name="change_until" value="1"<?= isset($errors['available_until']) ? ' checked' : '' ?>> <span>Chci změnit konec platnosti</span></label>
        <?php endif; ?>
        <?php
        $values = ['available_until' => (string) $value];
        ['input' => $input] = require dirname(__DIR__) . '/partials/fields.php';
        ?>
        <div class="form-grid">
            <?= $input('available_until', 'Nový konec platnosti', ['type' => 'date', 'required' => false, 'min' => today(), 'hint' => 'Prázdné = platí do ručního ukončení.']) ?>
        </div>
        <div class="form-actions">
            <button class="button" type="submit">Znovu zveřejnit</button>
            <a class="button secondary" href="/moje-nabidky">Zrušit</a>
        </div>
    </form>
</div>
