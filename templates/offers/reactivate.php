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
        <div class="field<?= isset($errors['available_until']) ? ' has-error' : '' ?>">
            <label for="f-available_until">Nový konec platnosti <span class="optional">(nepovinné)</span></label>
            <?php if (isset($errors['available_until'])): ?><span class="error" id="f-until-error"><?= e($errors['available_until']) ?></span><?php endif; ?>
            <input id="f-available_until" type="date" name="available_until" min="<?= e(today()) ?>" value="<?= e((string) $value) ?>"<?= isset($errors['available_until']) ? ' aria-invalid="true" aria-describedby="f-until-error"' : '' ?>>
        </div>
        <div class="form-actions">
            <button class="button" type="submit">Znovu zveřejnit</button>
            <a class="button secondary" href="/moje-nabidky">Zrušit</a>
        </div>
    </form>
</div>
