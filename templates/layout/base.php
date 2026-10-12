<?php
/** @var string|null $title */
/** @var string|null $description */
/** @var string $content */
/** @var string $currentPath */
/** @var \App\Security\Session $session */
$flashes = isset($session) ? $session->pullFlashes() : [];
$currentPath ??= '/';
// Jen podle session, bez dotazu do DB (veřejné stránky na databázi nesahají).
$signedIn = isset($session) && is_int($session->get('user_id'));
$navItems = $signedIn
    ? ['/nabidky' => 'Nabídky', '/poptavky' => 'Poptávky'] // O projektu a Kontakt zůstávají v patičce
    : ['/o-projektu' => 'O projektu', '/jak-ziskat-pristup' => 'Jak získat přístup', '/kontakt' => 'Kontakt'];
// Aktivní je i podstránka (detail nabídky zvýrazní "Nabídky", detail v administraci "Administrace").
$current = static fn (string $path): string =>
    $path === $currentPath || ($path !== '/' && str_starts_with($currentPath, $path . '/')) ? ' aria-current="page"' : '';
?><!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? null) ? $title . ' · Buchlovská spíž' : 'Buchlovská spíž – místní potraviny pro školní jídelny') ?></title>
    <meta name="description" content="<?= e($description ?? 'Uzavřená platforma propojující místní zemědělce mikroregionu Buchlov se školami a školními jídelnami.') ?>">
    <meta name="theme-color" content="#1e5a36">
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
    <?php ob_start(); ?>
        <?php foreach ($navItems as $path => $label): ?>
            <a href="<?= e($path) ?>"<?= $current($path) ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($signedIn): ?>
            <?php if ($session->get('user_role') === 'admin'): ?>
                <a href="/admin"<?= $current('/admin') ?>>Administrace</a>
            <?php endif; ?>
            <a class="button small" href="/portal"<?= $current('/portal') ?>>Můj portál</a>
        <?php else: ?>
            <a class="button small" href="/prihlaseni"<?= $current('/prihlaseni') ?>>Přihlásit se</a>
        <?php endif; ?>
    <?php $navLinks = (string) ob_get_clean(); ?>
    <?php /* Dvě varianty bez JavaScriptu; v každé šířce je vidět (a čtečkám dostupná) jen jedna. */ ?>
    <nav class="nav nav-desktop" aria-label="Hlavní navigace"><?= $navLinks ?></nav>
    <details class="nav-mobile">
        <summary aria-label="Menu">☰</summary>
        <nav class="nav" aria-label="Hlavní navigace"><?= $navLinks ?></nav>
    </details>
</header>
<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<main id="obsah">
<?= $content ?>
</main>
<footer class="site-footer">
    <div class="footer-main">
        <a class="brand light" href="/" aria-label="Buchlovská spíž – úvod">
            <span class="brand-mark" aria-hidden="true">B</span>
            <span><strong>Buchlovská</strong><small>spíž</small></span>
        </a>
        <p>Místní potraviny pro školní jídelny.<br>Přímo od zemědělců z mikroregionu Buchlov.</p>
        <nav class="footer-col" aria-label="Platforma">
            <b>Platforma</b>
            <a href="/o-projektu">O projektu</a>
            <a href="/jak-ziskat-pristup">Jak získat přístup</a>
            <a href="/prihlaseni">Přihlášení</a>
        </nav>
        <nav class="footer-col" aria-label="Informace">
            <b>Informace</b>
            <a href="/kontakt">Kontakt</a>
            <a href="/ochrana-osobnich-udaju">Ochrana osobních údajů</a>
            <a href="/podminky-pouzivani">Podmínky používání</a>
        </nav>
    </div>
    <div class="footer-bottom">
        <span>© <?= e(date('Y')) ?> Buchlovská spíž</span>
        <span>Mikroregion Buchlov</span>
    </div>
</footer>
</body>
</html>
