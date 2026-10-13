<?php
/** @var list<array<string, mixed>> $offers */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingState;

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
        <?php $crumbs = [['Moje nabídky', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Moje nabídky</h1>
        <p class="lead">Nabídky se zveřejňují hned. Ukončenou nebo vypršelou nabídku můžete kdykoli znovu zveřejnit.</p>
        <p><a class="button" href="/moje-nabidky/nova">Přidat nabídku <span aria-hidden="true">+</span></a></p>
    </div>
</section>
<div class="admin-content">
    <?php if ($offers === []): ?>
        <div class="card empty-state">
            <h2>Zatím nemáte žádnou nabídku</h2>
            <p class="muted">Přidejte první: co nabízíte, za kolik a kolik toho máte.</p>
            <a class="button" href="/moje-nabidky/nova">Přidat nabídku</a>
        </div>
    <?php else: ?>
        <ul class="listing-list">
            <?php foreach ($offers as $offer):
                $state = ListingState::effective((string) $offer['status'], $offer['available_until'], $today);
                $id = (int) $offer['id'];
                ?>
                <li class="card listing-item">
                    <div class="listing-main">
                        <p class="listing-meta">
                            <span class="badge <?= e($badges[$state] ?? 'badge-muted') ?>"><?= e(ListingState::LABELS[$state] ?? $state) ?></span>
                            <span><?= e($offer['category_name']) ?></span>
                        </p>
                        <h2><?= e($offer['product_name']) ?></h2>
                        <p class="listing-facts">
                            <strong><?= e(format_price((string) $offer['unit_price'], (string) $offer['price_unit'])) ?></strong>
                            · <?= e(format_number((string) $offer['available_quantity'])) ?>&nbsp;<?= e($offer['quantity_unit']) ?>
                            · <?= $offer['availability_type'] === 'immediate' ? 'ihned' : 'od ' . e(format_date($offer['available_from'])) ?>
                            <?php if ($offer['available_until'] !== null): ?> · platí do <?= e(format_date($offer['available_until'])) ?><?php endif; ?>
                        </p>
                        <?php if (in_array($state, [ListingState::WITHDRAWN, ListingState::RESUBMITTED], true)): ?>
                            <p class="notice-danger"><strong>Důvod stažení:</strong> <?= e($offer['withdrawal_reason']) ?>
                                <?= $state === ListingState::WITHDRAWN ? '<br>Upravte nabídku; po opravě ji správce znovu uvolní.' : '<br>Oprava je odeslaná, čeká na uvolnění správcem.' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="listing-actions">
                        <a class="button small secondary" href="/moje-nabidky/<?= $id ?>/upravit">Upravit</a>
                        <?php if (ListingState::canEnd($state)): ?>
                            <form method="post" action="/moje-nabidky/<?= $id ?>/ukoncit"><?= $csrf->field() ?><button class="button small secondary" type="submit">Ukončit</button></form>
                        <?php endif; ?>
                        <?php if (ListingState::canReactivate($state)): ?>
                            <a class="button small" href="/moje-nabidky/<?= $id ?>/obnovit">Znovu zveřejnit</a>
                        <?php endif; ?>
                        <a class="button small danger" href="/moje-nabidky/<?= $id ?>/smazat">Smazat</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
