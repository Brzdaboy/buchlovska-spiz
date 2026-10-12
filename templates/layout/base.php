<?php
/** @var string|null $title */
/** @var string $content */
/** @var \App\Security\Session $session */
$flashes = isset($session) ? $session->pullFlashes() : [];
?><!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? null) ? $title . ' · Buchlovská spíž' : 'Buchlovská spíž') ?></title>
    <meta name="description" content="Uzavřená platforma propojující místní zemědělce mikroregionu Buchlov se školními jídelnami.">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="skip-link" href="#obsah">Přeskočit na obsah</a>
<header class="site-header">
    <a class="brand" href="/" aria-label="Buchlovská spíž – úvod">
        <span class="brand-mark" aria-hidden="true">B</span>
        <span><strong>Buchlovská</strong><small>spíž</small></span>
    </a>
</header>
<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<main id="obsah">
<?= $content ?>
</main>
<footer class="site-footer">
    <p>© <?= e(date('Y')) ?> Buchlovská spíž · Mikroregion Buchlov</p>
</footer>
</body>
</html>
