<?php /** @var string $message */ ?>
<section class="page-hero">
    <div class="page-hero-inner">
        <?php $crumbs = [['Pozvánka', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Pozvánku nelze použít</h1>
        <p class="lead"><?= e($message) ?></p>
        <p class="hero-actions">
            <a class="button" href="/kontakt">Kontaktovat správce</a>
            <a class="button secondary" href="/prihlaseni">Už mám účet – přihlásit se</a>
        </p>
    </div>
</section>
