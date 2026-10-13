<?php
/**
 * Drobečková navigace nad nadpisem stránky: Domů › … › aktuální stránka.
 * „Domů“ vede přihlášeného na Můj portál, nepřihlášeného na úvodní stránku.
 *
 * Použití: <?php $crumbs = [['Nabídky', '/nabidky'], ['Brambory', null]]; require …/partials/breadcrumbs.php; ?>
 * Poslední položka je aktuální stránka (bez odkazu).
 *
 * @var list<array{0: string, 1: string|null}> $crumbs
 * @var \App\Security\Session|null $session
 */
$crumbsHome = isset($session) && is_int($session->get('user_id')) ? '/portal' : '/';
$crumbsAll = array_merge([['Domů', $crumbsHome]], $crumbs);
$crumbsLast = count($crumbsAll) - 1;
?>
<nav class="breadcrumbs" aria-label="Drobečková navigace">
    <ol>
        <?php foreach ($crumbsAll as $crumbsIndex => [$crumbsLabel, $crumbsUrl]): ?>
            <li><?php if ($crumbsIndex === $crumbsLast || $crumbsUrl === null): ?><span aria-current="page"><?= e($crumbsLabel) ?></span><?php else: ?><a href="<?= e($crumbsUrl) ?>"><?= e($crumbsLabel) ?></a><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
