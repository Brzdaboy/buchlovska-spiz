<?php
/** @var int $status */
/** @var string $message */
/** @var string|null $debugDetail */
?>
<section class="page-section narrow">
    <?php $crumbs = [[$status === 404 ? 'Stránka nebyla nalezena' : 'Chyba ' . $status, null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
    <h1><?= e($status === 404 ? 'Stránka nebyla nalezena' : 'Tohle se nepovedlo') ?></h1>
    <p class="lead"><?= e($message) ?></p>
    <?php if (!empty($debugDetail)): ?>
        <pre class="debug"><?= e($debugDetail) ?></pre>
    <?php endif; ?>
    <p><a class="button" href="/">Zpět na úvod</a></p>
</section>
