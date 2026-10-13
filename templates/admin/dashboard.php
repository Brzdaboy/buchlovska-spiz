<?php
/** @var array{new: int, resubmitted: int} $counts */
/** @var array<string, int> $users */
/** @var int $pendingInvitations */
/** @var array{last_run: string, report: array<string, int>}|null $maintenance */
$maintenanceAge = $maintenance === null ? null : (time() - (int) strtotime($maintenance['last_run']));
$maintenanceLate = $maintenanceAge === null || $maintenanceAge > 2 * 86400;
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [['Administrace', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Správa Buchlovské spíže</h1>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</section>
<div class="admin-content">
    <ul class="stat-grid">
        <li><a class="card stat" href="/admin/zaznamy?filtr=nove">
            <b><?= (int) $counts['new'] ?></b><span>nových a upravených záznamů od vaší poslední kontroly</span></a></li>
        <li><a class="card stat<?= $counts['resubmitted'] > 0 ? ' stat-attention' : '' ?>" href="/admin/zaznamy?filtr=k-uvolneni">
            <b><?= (int) $counts['resubmitted'] ?></b><span>opravených záznamů čeká na uvolnění</span></a></li>
        <li><a class="card stat" href="/admin/uzivatele?role=zemedelci&amp;stav=aktivni">
            <b><?= (int) $users['producer'] ?></b><span>aktivních zemědělců</span></a></li>
        <li><a class="card stat" href="/admin/uzivatele?role=skoly&amp;stav=aktivni">
            <b><?= (int) $users['school'] ?></b><span>aktivních škol</span></a></li>
        <li><a class="card stat" href="/admin/pozvanky">
            <b><?= (int) $pendingInvitations ?></b><span>platných pozvánek čeká na registraci</span></a></li>
    </ul>
    <?php if ($maintenanceLate): ?>
        <p class="notice-danger" role="alert"><strong>Denní úklid <?= $maintenance === null ? 'zatím nikdy neproběhl' : 'neproběhl déle než 2 dny' ?>.</strong>
            Zkontrolujte plánovanou úlohu na WEDOS (návod v docs/UKLID.md). Bez ní se prošlé záznamy nepřepínají
            a stará data se nemažou podle zásad ochrany osobních údajů.</p>
    <?php else: ?>
        <p class="muted small-print">Denní úklid naposledy proběhl <?= e(format_datetime(gmdate('Y-m-d H:i:s', (int) strtotime($maintenance['last_run'])))) ?>.</p>
    <?php endif; ?>
    <p class="muted">Nabídky a poptávky se zveřejňují bez schvalování. Nevhodný záznam můžete v detailu stáhnout s uvedením důvodu.</p>
</div>
