<?php
/**
 * Profil zemědělce nebo školy pro přihlášené členy: kontaktní údaje a zveřejněné záznamy.
 *
 * @var array<string, mixed> $member
 * @var bool $isProducer
 * @var \App\Domain\ListingType $type
 * @var list<array<string, mixed>> $items
 * @var string $view
 */
$name = (string) ($isProducer ? $member['producer_name'] : $member['school_name']);
$orgId = $isProducer ? ($member['company_id'] ?? null) : ($member['organization_id'] ?? null);
$postal = (string) $member['postal_code'];
$phoneHref = preg_replace('/[^0-9+]/', '', (string) $member['phone']);
$hideOwner = true;
$count = count($items);
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [[$name, null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1><?= e($name) ?></h1>
        <p class="lead"><?= $isProducer ? 'Zemědělec / producent' : 'Škola / školní jídelna' ?> · <?= e($member['municipality']) ?></p>
    </div>
</section>
<div class="admin-content detail-grid member-profile">
    <div>
        <h2 class="member-listings-heading"><?= $isProducer ? 'Aktuální nabídky' : 'Aktuální poptávky' ?> <span class="muted">(<?= $count ?>)</span></h2>
        <?php if ($items === []): ?>
            <div class="card empty-state"><p class="muted"><?= $isProducer ? 'Momentálně nic nenabízí.' : 'Momentálně nic nepoptává.' ?></p></div>
        <?php else: ?>
            <?php require __DIR__ . '/list.php'; ?>
        <?php endif; ?>
    </div>
    <aside class="card market-contact">
        <p class="eyebrow">Kontakt</p>
        <h2><?= e($name) ?></h2>
        <dl class="contact-list">
            <dt>Kontaktní osoba</dt><dd><?= e($member['contact_name']) ?></dd>
            <dt>Telefon</dt><dd><a href="tel:<?= e($phoneHref) ?>"><?= e($member['phone']) ?></a></dd>
            <dt>E-mail</dt><dd><a href="mailto:<?= e($member['public_email']) ?>"><?= e($member['public_email']) ?></a></dd>
            <dt>Adresa</dt><dd><?= e($member['address_line']) ?>, <?= e(substr($postal, 0, 3) . ' ' . substr($postal, 3)) ?> <?= e($member['municipality']) ?></dd>
            <?php if (!empty($member['website'])): ?>
                <dt>Web</dt><dd><a href="<?= e($member['website']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e(preg_replace('#^https?://(www\.)?#', '', rtrim((string) $member['website'], '/'))) ?></a></dd>
            <?php endif; ?>
            <?php if (!empty($orgId)): ?>
                <dt><?= $isProducer ? 'IČO' : 'IČO / identifikátor' ?></dt><dd><?= e($orgId) ?></dd>
            <?php endif; ?>
        </dl>
        <p class="muted small-print">Domluvte se přímo telefonem nebo e-mailem. Platforma objednávky ani platby nezprostředkovává.</p>
    </aside>
</div>
