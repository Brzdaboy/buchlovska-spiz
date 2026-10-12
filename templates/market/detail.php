<?php
/** @var \App\Domain\ListingType $type */
/** @var array<string, mixed> $item */
/** @var bool $isOwn */
$isOffer = $type->key === 'offer';
$postal = (string) $item['postal_code'];
$phoneHref = preg_replace('/[^0-9+]/', '', (string) $item['phone']);
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><a href="/<?= e($type->segment()) ?>"><?= $isOffer ? 'Nabídky' : 'Poptávky' ?></a> · <?= e($item['category_name']) ?></p>
        <h1><?= e($item['product_name']) ?></h1>
        <?php if ($isOwn): ?>
            <p class="lead">Toto je vaše <?= $isOffer ? 'nabídka' : 'poptávka' ?>.
                <a href="/<?= $isOffer ? 'moje-nabidky' : 'moje-poptavky' ?>/<?= (int) $item['id'] ?>/upravit">Upravit</a></p>
        <?php endif; ?>
    </div>
</section>
<div class="admin-content detail-grid market-detail">
    <div>
        <div class="card market-detail-main">
            <?php if ($isOffer): ?>
                <p class="market-detail-price"><?= e(format_price((string) $item['unit_price'], (string) $item['price_unit'])) ?></p>
                <dl class="contact-list">
                    <dt>Množství</dt><dd><?= e(format_number((string) $item['available_quantity'])) ?>&nbsp;<?= e($item['quantity_unit']) ?></dd>
                    <dt>Dostupnost</dt><dd><?= $item['availability_type'] === 'immediate' ? 'ihned' : 'od ' . e(format_date($item['available_from'])) ?><?= $item['available_until'] !== null ? ', nabídka platí do ' . e(format_date($item['available_until'])) : '' ?></dd>
                    <dt>Doprava</dt><dd><?= nl2br(e($item['delivery_terms'])) ?></dd>
                    <?php if ($item['description'] !== null): ?><dt>Popis</dt><dd><?= nl2br(e($item['description'])) ?></dd><?php endif; ?>
                    <dt>Zveřejněno</dt><dd><?= e(format_datetime((string) $item['created_at'], 'j. n. Y')) ?><?= $item['content_updated_at'] > $item['created_at'] ? ', upraveno ' . e(format_datetime((string) $item['content_updated_at'], 'j. n. Y')) : '' ?></dd>
                </dl>
            <?php else: ?>
                <p class="market-detail-price"><?= e(format_number((string) $item['requested_quantity'])) ?>&nbsp;<?= e($item['quantity_unit']) ?></p>
                <dl class="contact-list">
                    <dt>Potřeba v období</dt><dd><?= e(format_date($item['needed_from'])) ?> – <?= e(format_date($item['needed_until'])) ?></dd>
                    <?php if ($item['note'] !== null): ?><dt>Poznámka</dt><dd><?= nl2br(e($item['note'])) ?></dd><?php endif; ?>
                    <dt>Zveřejněno</dt><dd><?= e(format_datetime((string) $item['created_at'], 'j. n. Y')) ?><?= $item['content_updated_at'] > $item['created_at'] ? ', upraveno ' . e(format_datetime((string) $item['content_updated_at'], 'j. n. Y')) : '' ?></dd>
                </dl>
            <?php endif; ?>
        </div>
    </div>
    <aside class="card market-contact">
        <p class="eyebrow"><?= $isOffer ? 'Nabízí' : 'Poptává' ?></p>
        <h2><?= e($item['owner_name']) ?></h2>
        <dl class="contact-list">
            <dt>Kontaktní osoba</dt><dd><?= e($item['contact_name']) ?></dd>
            <dt>Telefon</dt><dd><a href="tel:<?= e($phoneHref) ?>"><?= e($item['phone']) ?></a></dd>
            <dt>E-mail</dt><dd><a href="mailto:<?= e($item['public_email']) ?>"><?= e($item['public_email']) ?></a></dd>
            <dt>Adresa</dt><dd><?= e($item['address_line']) ?>, <?= e(substr($postal, 0, 3) . ' ' . substr($postal, 3)) ?> <?= e($item['municipality']) ?></dd>
            <?php if (!empty($item['website'])): ?>
                <dt>Web</dt><dd><a href="<?= e($item['website']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e(preg_replace('#^https?://(www\.)?#', '', rtrim((string) $item['website'], '/'))) ?></a></dd>
            <?php endif; ?>
        </dl>
        <p class="muted small-print">Domluvte se přímo telefonem nebo e-mailem. Platforma objednávky ani platby nezprostředkovává.</p>
    </aside>
</div>
