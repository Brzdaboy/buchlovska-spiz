<?php
/** @var array<string, mixed> $user */
/** @var array<string, mixed>|null $profile */
/** @var array{active: int, inactive: int, attention: int} $summary */
/** @var list<array<string, mixed>> $own vlastní záznamy s klíčem 'state' (efektivní stav) */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingState;

$role = (string) $user['role'];
$roles = ['producer' => 'Zemědělec / producent', 'school' => 'Škola / školní jídelna', 'admin' => 'Správce'];
$name = $profile['producer_name'] ?? $profile['school_name'] ?? null;
$isProducer = $role === 'producer';
$ownBase = $isProducer ? '/moje-nabidky' : '/moje-poptavky';
$badges = [
    ListingState::ACTIVE => 'badge-ok',
    ListingState::ENDED => 'badge-muted',
    ListingState::EXPIRED => 'badge-muted',
    ListingState::WITHDRAWN => 'badge-danger',
    ListingState::RESUBMITTED => 'badge-pending',
];
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
        <section class="card own-listings" aria-labelledby="own-heading">
            <div class="own-summary">
                <div>
                    <h2 id="own-heading"><?= $isProducer ? 'Moje nabídky' : 'Moje poptávky' ?></h2>
                    <?php if ($own === []): ?>
                        <p class="muted"><?= $isProducer ? 'Zatím nic nenabízíte.' : 'Zatím nic nepoptáváte.' ?></p>
                    <?php else: ?>
                        <ul class="summary-list">
                            <li><span class="badge badge-ok"><?= $summary['active'] ?></span> zveřejněno</li>
                            <li><span class="badge badge-muted"><?= $summary['inactive'] ?></span> ukončeno nebo vypršelo</li>
                            <?php if ($summary['attention'] > 0): ?>
                                <li><span class="badge badge-danger"><?= $summary['attention'] ?></span> staženo správcem</li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="own-summary-actions">
                    <a class="button" href="<?= $ownBase ?>/nova"><?= $isProducer ? 'Přidat nabídku' : 'Přidat poptávku' ?> <span aria-hidden="true">+</span></a>
                    <?php if ($own !== []): ?><a class="button secondary" href="<?= $ownBase ?>">Spravovat</a><?php endif; ?>
                </div>
            </div>
            <?php if ($own !== []): ?>
                <ul class="own-list">
                    <?php foreach ($own as $row): $state = (string) $row['state']; ?>
                        <li>
                            <span class="badge <?= e($badges[$state] ?? 'badge-muted') ?>"><?= e(ListingState::LABELS[$state] ?? $state) ?></span>
                            <a class="own-name" href="<?= $ownBase ?>/<?= (int) $row['id'] ?>/upravit"><?= e($row['product_name']) ?></a>
                            <span class="muted own-facts">
                                <?php if ($isProducer): ?>
                                    <?= e(format_price((string) $row['unit_price'], (string) $row['price_unit'])) ?>
                                    · <?= e(format_number((string) $row['available_quantity'])) ?>&nbsp;<?= e($row['quantity_unit']) ?>
                                <?php else: ?>
                                    <?= e(format_number((string) $row['requested_quantity'])) ?>&nbsp;<?= e($row['quantity_unit']) ?>
                                    · <?= e(format_date($row['needed_from'])) ?> – <?= e(format_date($row['needed_until'])) ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="card">
            <h2>Moje údaje</h2>
            <p class="muted">Takto vás vidí ostatní členové u <?= $isProducer ? 'vašich nabídek' : 'vašich poptávek' ?>.</p>
            <dl class="contact-list">
                <dt><?= $isProducer ? 'Název' : 'Škola' ?></dt><dd><?= e($name) ?></dd>
                <dt>Kontaktní osoba</dt><dd><?= e($profile['contact_name']) ?></dd>
                <dt>Adresa</dt><dd><?= e($profile['address_line']) ?>, <?= e(substr((string) $profile['postal_code'], 0, 3) . ' ' . substr((string) $profile['postal_code'], 3)) ?> <?= e($profile['municipality']) ?></dd>
                <dt>Telefon</dt><dd><?= e($profile['phone']) ?></dd>
                <dt>E-mail</dt><dd><?= e($profile['public_email']) ?></dd>
                <?php if (!empty($profile['website'])): ?><dt>Web</dt><dd><a href="<?= e($profile['website']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e($profile['website']) ?></a></dd><?php endif; ?>
            </dl>
            <p><a class="button secondary small" href="/profil">Upravit údaje</a></p>
        </div>
    <?php endif; ?>
    <form method="post" action="/odhlaseni">
        <?= $csrf->field() ?>
        <button class="button secondary" type="submit">Odhlásit se</button>
    </form>
</div>
