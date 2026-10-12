<?php
/** @var array<string, mixed> $user */
/** @var array<string, mixed>|null $profile */
/** @var array{active: int, inactive: int, attention: int} $summary */
/** @var int $ownCount */
/** @var list<array<string, mixed>> $latestOffers */
/** @var list<array<string, mixed>> $latestDemands */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingType;

$role = (string) $user['role'];
$roles = ['producer' => 'Zemědělec / producent', 'school' => 'Škola / školní jídelna', 'admin' => 'Správce'];
$name = $profile['producer_name'] ?? $profile['school_name'] ?? null;

// Zemědělec vidí jako první poptávky škol, škola nabídky zemědělců (rychlý přístup, SPECIFIKACE §11).
$sections = [
    'offer' => [$latestOffers, 'Nejnovější nabídky', '/nabidky', 'Všechny nabídky'],
    'demand' => [$latestDemands, 'Nejnovější poptávky škol', '/poptavky', 'Všechny poptávky'],
];
if ($role === 'producer') {
    $sections = array_reverse($sections, true);
}
$ownBase = $role === 'producer' ? '/moje-nabidky' : '/moje-poptavky';
$isProducer = $role === 'producer';
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><?= e($roles[$role] ?? $role) ?></p>
        <h1><?= e($name ?? 'Můj portál') ?></h1>
        <p class="lead">Přihlášeni jako <strong><?= e($user['email']) ?></strong>.</p>
    </div>
</section>
<div class="admin-content dashboard">
    <?php if ($role === 'admin'): ?>
        <ul class="admin-links">
            <li><a class="card admin-link" href="/admin"><strong>Administrace</strong><span>Záznamy, uživatelé a pozvánky.</span></a></li>
            <li><a class="card admin-link" href="/nabidky"><strong>Nabídky</strong><span>Tržiště tak, jak ho vidí členové.</span></a></li>
            <li><a class="card admin-link" href="/poptavky"><strong>Poptávky</strong><span>Co shánějí školy.</span></a></li>
        </ul>
    <?php elseif ($profile !== null): ?>
        <div class="card own-summary">
            <div>
                <h2><?= $isProducer ? 'Moje nabídky' : 'Moje poptávky' ?></h2>
                <?php if ($ownCount === 0): ?>
                    <p class="muted"><?= $isProducer ? 'Zatím nic nenabízíte.' : 'Zatím nic nepoptáváte.' ?></p>
                <?php else: ?>
                    <ul class="summary-list">
                        <li><span class="badge badge-ok"><?= $summary['active'] ?></span> zveřejněno</li>
                        <li><span class="badge badge-muted"><?= $summary['inactive'] ?></span> ukončeno nebo vypršelo</li>
                        <?php if ($summary['attention'] > 0): ?>
                            <li><span class="badge badge-danger"><?= $summary['attention'] ?></span> staženo správcem – podívejte se proč</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="own-summary-actions">
                <a class="button" href="<?= $ownBase ?>/nova"><?= $isProducer ? 'Přidat nabídku' : 'Přidat poptávku' ?> <span aria-hidden="true">+</span></a>
                <?php if ($ownCount > 0): ?><a class="button secondary" href="<?= $ownBase ?>">Spravovat</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($sections as $key => [$items, $heading, $url, $more]): $type = ListingType::of($key); ?>
        <section class="dashboard-section" aria-labelledby="dash-<?= $key ?>">
            <div class="section-head">
                <h2 id="dash-<?= $key ?>"><?= e($heading) ?></h2>
                <a href="<?= e($url) ?>"><?= e($more) ?> <span aria-hidden="true">→</span></a>
            </div>
            <?php if ($items === []): ?>
                <p class="muted"><?= $key === 'offer' ? 'Zatím žádné nabídky.' : 'Zatím žádné poptávky.' ?></p>
            <?php else: ?>
                <?php require dirname(__DIR__) . '/market/cards.php'; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if ($profile !== null): ?>
        <div class="card">
            <h2>Kontakty, které vidí ostatní členové</h2>
            <dl class="contact-list">
                <dt>Adresa</dt><dd><?= e($profile['address_line']) ?>, <?= e(substr((string) $profile['postal_code'], 0, 3) . ' ' . substr((string) $profile['postal_code'], 3)) ?> <?= e($profile['municipality']) ?></dd>
                <dt>Telefon</dt><dd><?= e($profile['phone']) ?></dd>
                <dt>E-mail</dt><dd><?= e($profile['public_email']) ?></dd>
                <?php if (!empty($profile['website'])): ?><dt>Web</dt><dd><a href="<?= e($profile['website']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e($profile['website']) ?></a></dd><?php endif; ?>
            </dl>
            <p><a class="button secondary small" href="/profil">Upravit profil</a></p>
        </div>
    <?php endif; ?>
    <form method="post" action="/odhlaseni">
        <?= $csrf->field() ?>
        <button class="button secondary" type="submit">Odhlásit se</button>
    </form>
</div>
