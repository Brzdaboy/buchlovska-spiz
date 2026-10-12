<?php
/**
 * Podnavigace administrace. Vkládá se přes require, takže sdílí proměnné šablony:
 * všechny vlastní proměnné mají předponu $adminNav, aby nic nepřepsaly.
 *
 * @var string $currentPath
 */
$adminNavItems = [
    '/admin' => 'Přehled',
    '/admin/zaznamy' => 'Záznamy',
    '/admin/uzivatele' => 'Uživatelé',
    '/admin/pozvanky' => 'Pozvánky',
];
$adminNavActive = '/admin';
foreach (array_keys($adminNavItems) as $adminNavPath) {
    if ($adminNavPath !== '/admin' && str_starts_with($currentPath ?? '', $adminNavPath)) {
        $adminNavActive = $adminNavPath;
    }
}
if (str_starts_with($currentPath ?? '', '/admin/nabidky') || str_starts_with($currentPath ?? '', '/admin/poptavky')) {
    $adminNavActive = '/admin/zaznamy';
}
?>
<nav class="subnav" aria-label="Administrace">
    <?php foreach ($adminNavItems as $adminNavPath => $adminNavLabel): ?>
        <a href="<?= e($adminNavPath) ?>"<?= $adminNavPath === $adminNavActive ? ' aria-current="page"' : '' ?>><?= e($adminNavLabel) ?></a>
    <?php endforeach; ?>
</nav>
