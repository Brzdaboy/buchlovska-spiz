<?php
/** @var list<array<string, mixed>> $users */
/** @var string $roleFilter */
/** @var string $statusFilter */
$roles = ['producer' => 'Zemědělec', 'school' => 'Škola', 'admin' => 'Správce'];
$statuses = ['active' => ['Aktivní', 'badge-ok'], 'profile_incomplete' => ['Neúplný profil', 'badge-pending'], 'blocked' => ['Blokovaný', 'badge-danger'], 'archived' => ['Archivovaný', 'badge-muted']];
$roleTabs = ['' => 'Všichni', 'zemedelci' => 'Zemědělci', 'skoly' => 'Školy', 'spravci' => 'Správci'];
$statusTabs = ['' => 'Všechny stavy', 'aktivni' => 'Aktivní', 'neuplny-profil' => 'Neúplný profil', 'blokovani' => 'Blokovaní', 'archiv' => 'Archiv'];
$link = static fn (string $role, string $status): string => '/admin/uzivatele?' . http_build_query(array_filter(['role' => $role, 'stav' => $status]));
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [['Administrace', '/admin'], ['Uživatelé', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Uživatelé</h1>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</section>
<div class="admin-content">
    <div class="toolbar">
        <nav class="tabs" aria-label="Role">
            <?php foreach ($roleTabs as $key => $label): ?>
                <a href="<?= e($link($key, $statusFilter)) ?>"<?= $key === $roleFilter ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <nav class="tabs" aria-label="Stav účtu">
            <?php foreach ($statusTabs as $key => $label): ?>
                <a href="<?= e($link($roleFilter, $key)) ?>"<?= $key === $statusFilter ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <p class="muted">Pozvaní, kteří ještě nedokončili registraci, jsou v <a href="/admin/pozvanky">Pozvánkách</a>.</p>
    <?php if ($users === []): ?>
        <div class="card empty-state"><p class="muted">Žádní uživatelé.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Organizace</th><th scope="col">Role</th><th scope="col">Stav</th><th scope="col">Záznamy</th><th scope="col">Poslední přihlášení</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): [$statusLabel, $badge] = $statuses[$u['status']] ?? [$u['status'], 'badge-muted']; ?>
                    <tr>
                        <td data-label="Organizace"><a href="/admin/uzivatele/<?= (int) $u['id'] ?>"><strong><?= e($u['organization'] ?? '(bez profilu)') ?></strong></a><br><span class="muted"><?= e($u['email']) ?><?= $u['municipality'] ? ' · ' . e($u['municipality']) : '' ?></span></td>
                        <td data-label="Role"><?= e($roles[$u['role']] ?? $u['role']) ?></td>
                        <td data-label="Stav"><span class="badge <?= e($badge) ?>"><?= e($statusLabel) ?></span></td>
                        <td data-label="Záznamy"><?= $u['role'] === 'producer' ? (int) $u['offer_count'] . ' nabídek' : ($u['role'] === 'school' ? (int) $u['demand_count'] . ' poptávek' : '–') ?></td>
                        <td data-label="Poslední přihlášení"><?= e(format_datetime($u['last_login_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
