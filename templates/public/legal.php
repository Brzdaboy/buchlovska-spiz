<?php
/**
 * Právní stránky. Texty dodá provozovatel (README: právní texty nevymýšlet).
 *
 * @var string $heading
 */
?>
<section class="page-hero">
    <div class="page-hero-inner">
        <?php $crumbs = [[$heading, null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1><?= e($heading) ?></h1>
    </div>
</section>
<div class="prose">
    <p class="placeholder-note" role="note">Text připravuje provozovatel a bude zveřejněn před spuštěním webu.</p>
</div>
