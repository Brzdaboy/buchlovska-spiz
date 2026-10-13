<?php /* Zobrazí se, jen dokud v config.php chybí údaje Logto. */ ?>
<section class="page-hero">
    <div class="page-hero-inner">
        <?php $crumbs = [['Přihlášení', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Přihlášení pro členy</h1>
        <p class="lead">Přihlášení zatím není spuštěné. Brzy se tudy dostanete k nabídkám a poptávkám.</p>
        <p><a class="button secondary" href="/jak-ziskat-pristup">Jak získat přístup</a></p>
    </div>
</section>
