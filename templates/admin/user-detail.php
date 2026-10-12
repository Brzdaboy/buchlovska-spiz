<?php
/** @var array<string, mixed> $user */
/** @var list<array<string, mixed>> $listings */
/** @var bool $isSelf */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingState;
use App\Domain\ListingType;

$roles = ['producer' => 'Zemědělec / producent', 'school' => 'Škola / školní jídelna', 'admin' => 'Správce'];
$statuses = ['active' => 'Aktivní', 'profile_incomplete' => 'Neúplný profil', 'blocked' => 'Blokovaný', 'archived' => 'Archivovaný', 'invited' => 'Pozvaný'];
$badges = ['active' => 'badge-ok', 'profile_incomplete' => 'badge-pending', 'blocked' => 'badge-danger'];
$manageable = in_array($user['role'], ['producer', 'school'], true) && !$isSelf;
$action = static fn (string $path, string $label, string $class): string =>
    '<form method="post" action="/admin/uzivatele/' . (int) $user['id'] . '/' . $path . '">' . $csrf->field()
    . '<button class="button small ' . $class . '" type="submit">' . e($label) . '</button></form>';
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><a href="/admin/uzivatele">Uživatelé</a> · <?= e($roles[$user['role']] ?? $user['role']) ?></p>
        <h1><?= e($user['organization'] ?? $user['email']) ?></h1>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</section>
<div class="admin-content detail-grid">
    <div>
        <div class="card">
            <p class="listing-meta"><span class="badge <?= e($badges[$user['status']] ?? 'badge-muted') ?>"><?= e($statuses[$user['status']] ?? $user['status']) ?></span></p>
            <dl class="contact-list">
                <dt>Přihlašovací e-mail</dt><dd><?= e($user['email']) ?></dd>
                <?php if ($user['organization'] !== null): ?>
                    <dt>Kontaktní osoba</dt><dd><?= e($user['contact_name']) ?></dd>
                    <dt>Adresa</dt><dd><?= e($user['address_line']) ?>, <?= e(substr((string) $user['postal_code'], 0, 3) . ' ' . substr((string) $user['postal_code'], 3)) ?> <?= e($user['municipality']) ?></dd>
                    <dt>Telefon / e-mail</dt><dd><?= e($user['phone']) ?> · <?= e($user['public_email']) ?></dd>
                    <?php if ($user['website']): ?><dt>Web</dt><dd><?= e($user['website']) ?></dd><?php endif; ?>
                    <?php if ($user['company_id']): ?><dt>IČO</dt><dd><?= e($user['company_id']) ?></dd><?php endif; ?>
                <?php endif; ?>
                <dt>Registrace / poslední přihlášení</dt><dd><?= e(format_datetime($user['created_at'])) ?> / <?= e(format_datetime($user['last_login_at'])) ?></dd>
            </dl>
        </div>
        <h2>Záznamy</h2>
        <?php if ($listings === []): ?>
            <p class="muted">Žádné nabídky ani poptávky.</p>
        <?php else: ?>
            <ul class="plain-list">
                <?php foreach ($listings as $l): $t = ListingType::of((string) $l['type']); $s = ListingState::effective((string) $l['status'], $l['end_date'], $today); ?>
                    <li><a href="/admin/<?= e($t->segment()) ?>/<?= (int) $l['id'] ?>"><?= e($l['product_name']) ?></a> <span class="muted">· <?= e($t->label()) ?> · <?= e(ListingState::LABELS[$s] ?? $s) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <aside class="card">
        <h2>Účet</h2>
        <?php if (!$manageable): ?>
            <p class="muted"><?= $isSelf ? 'Vlastní účet nelze blokovat ani archivovat.' : 'Účty správců se v administraci nemění.' ?></p>
        <?php else: ?>
            <div class="stack">
                <?php if (in_array($user['status'], ['active', 'profile_incomplete'], true)): ?>
                    <p class="muted">Blokace platí okamžitě: uživatel se odhlásí a jeho záznamy zmizí z tržiště.</p>
                    <?= $action('blokovat', 'Zablokovat účet', 'danger') ?>
                <?php endif; ?>
                <?php if (in_array($user['status'], ['blocked', 'archived'], true)): ?>
                    <?= $action('obnovit', $user['status'] === 'blocked' ? 'Odblokovat účet' : 'Obnovit z archivu', '') ?>
                <?php endif; ?>
                <?php if ($user['status'] !== 'archived'): ?>
                    <p class="muted">Archivace je pro organizace, které se z platformy odhlásily.</p>
                    <?= $action('archivovat', 'Archivovat účet', 'secondary') ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
