<?php /** @var string $message */ ?>
<section class="page-hero">
    <div class="page-hero-inner">
        <?php $crumbs = [['Přihlášení', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Přihlášení se nepovedlo</h1>
        <p class="lead"><?= e($message) ?></p>
        <p class="hero-actions">
            <a class="button" href="/prihlaseni">Zkusit znovu</a>
            <a class="button secondary" href="/jak-ziskat-pristup">Jak získat přístup</a>
        </p>
    </div>
</section>
