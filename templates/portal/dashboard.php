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
    </div>
</section>
<div class="admin-content dashboard">
    <?php if ($role === 'admin'): ?>
        <ul class="admin-links">
            <li><a class="card admin-link" href="/admin"><strong>Administrace</strong><span>Záznamy, uživatelé a pozvánky.</span></a></li>
            <li><a class="card admin-link" href="/nabidky"><strong>Nabídky</strong><span>Tržiště tak, jak ho vidí členové.</span></a></li>
            <li><a class="card admin-link" href="/poptavky"><strong>Poptávky</strong><span>Co shánějí školy.</span></a></li>
        </ul>
    <?php else: ?>
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
                </div>
            </div>
            <?php if ($own !== []): ?>
                <ul class="own-list">
                    <?php foreach ($own as $row):
                        $state = (string) $row['state'];
                        $id = (int) $row['id'];
                        $isPublic = $state === ListingState::ACTIVE;
                        $previewUrl = ($isProducer ? '/nabidky/' : '/poptavky/') . $id;
                        ?>
                        <li class="own-item">
                            <span class="badge <?= e($badges[$state] ?? 'badge-muted') ?>"><?= e(ListingState::LABELS[$state] ?? $state) ?></span>
                            <div class="own-main">
                                <?php if ($isPublic): ?>
                                    <a class="own-name" href="<?= e($previewUrl) ?>"><?= e($row['product_name']) ?></a>
                                <?php else: ?>
                                    <span class="own-name"><?= e($row['product_name']) ?></span>
                                <?php endif; ?>
                                <span class="muted own-facts">
                                    <?php if ($isProducer): ?>
                                        <?= e(format_price((string) $row['unit_price'], (string) $row['price_unit'])) ?>
                                        · <?= e(format_number((string) $row['available_quantity'])) ?>&nbsp;<?= e($row['quantity_unit']) ?>
                                    <?php else: ?>
                                        <?= e(format_number((string) $row['requested_quantity'])) ?>&nbsp;<?= e($row['quantity_unit']) ?>
                                        · <?= e(format_date($row['needed_from'])) ?> – <?= e(format_date($row['needed_until'])) ?>
                                    <?php endif; ?>
                                </span>
                                <?php if (in_array($state, [ListingState::WITHDRAWN, ListingState::RESUBMITTED], true)): ?>
                                    <p class="own-notice"><strong>Staženo správcem:</strong> <?= e($row['withdrawal_reason']) ?>
                                        <?= $state === ListingState::WITHDRAWN ? '– upravte záznam, po opravě ho správce znovu uvolní.' : '– oprava čeká na uvolnění správcem.' ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="own-actions">
                                <?php if ($isPublic): ?><a class="button small secondary" href="<?= e($previewUrl) ?>">Náhled</a><?php endif; ?>
                                <a class="button small secondary" href="<?= $ownBase ?>/<?= $id ?>/upravit">Upravit</a>
                                <?php if (ListingState::canEnd($state)): ?>
                                    <form method="post" action="<?= $ownBase ?>/<?= $id ?>/ukoncit"><?= $csrf->field() ?><button class="button small secondary" type="submit">Ukončit</button></form>
                                <?php endif; ?>
                                <?php if (ListingState::canReactivate($state)): ?>
                                    <a class="button small secondary" href="<?= $ownBase ?>/<?= $id ?>/obnovit">Znovu zveřejnit</a>
                                <?php endif; ?>
                                <a class="button small danger" href="<?= $ownBase ?>/<?= $id ?>/smazat">Smazat</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($profile !== null): ?>
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
    <?php endif; ?>
    <form method="post" action="/odhlaseni">
        <?= $csrf->field() ?>
        <button class="button secondary" type="submit">Odhlásit se</button>
    </form>
</div>
