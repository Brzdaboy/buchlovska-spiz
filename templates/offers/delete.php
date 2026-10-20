<?php
/** @var array<string, mixed> $offer */
/** @var \App\Security\Csrf $csrf */
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [['Smazat: ' . $offer['product_name'], null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Smazat nabídku?</h1>
        <p class="hero-meta">Nabídka <strong>„<?= e($offer['product_name']) ?>“</strong> zmizí z tržiště i z vašeho seznamu. Smazání nejde vrátit zpět.</p>
    </div>
</section>
<div class="admin-content">
    <form method="post" action="/moje-nabidky/<?= (int) $offer['id'] ?>/smazat" class="card">
        <?= $csrf->field() ?>
        <input type="hidden" name="confirm" value="1">
        <p class="muted">Pokud ji chcete jen dočasně skrýt, použijte raději „Ukončit“. Ukončenou nabídku lze kdykoli znovu zveřejnit.</p>
        <div class="form-actions">
            <button class="button danger" type="submit">Ano, smazat</button>
            <a class="button secondary" href="/portal">Ne, ponechat</a>
        </div>
    </form>
</div>
