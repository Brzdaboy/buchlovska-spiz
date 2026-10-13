<?php
/** @var list<array<string, mixed>> $demands */
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
        <?php $crumbs = [['Moje poptávky', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Moje poptávky</h1>
        <p class="lead">Poptávky se zveřejňují hned a samy skončí s obdobím, kdy produkt potřebujete. Ukončenou poptávku můžete znovu zveřejnit.</p>
        <p><a class="button" href="/moje-poptavky/nova">Přidat poptávku <span aria-hidden="true">+</span></a></p>
    </div>
</section>
<div class="admin-content">
    <?php if ($demands === []): ?>
        <div class="card empty-state">
            <h2>Zatím nemáte žádnou poptávku</h2>
            <p class="muted">Napište, co vaše jídelna potřebuje, kolik a kdy.</p>
            <a class="button" href="/moje-poptavky/nova">Přidat poptávku</a>
        </div>
    <?php else: ?>
        <ul class="listing-list">
            <?php foreach ($demands as $demand):
                $state = ListingState::effective((string) $demand['status'], $demand['needed_until'], $today);
                $id = (int) $demand['id'];
                ?>
                <li class="card listing-item">
                    <div class="listing-main">
                        <p class="listing-meta">
                            <span class="badge <?= e($badges[$state] ?? 'badge-muted') ?>"><?= e(ListingState::LABELS[$state] ?? $state) ?></span>
                            <span><?= e($demand['category_name']) ?></span>
                        </p>
                        <h2><?= e($demand['product_name']) ?></h2>
                        <p class="listing-facts">
                            <strong><?= e(format_number((string) $demand['requested_quantity'])) ?>&nbsp;<?= e($demand['quantity_unit']) ?></strong>
                            · <?= e(format_date($demand['needed_from'])) ?> – <?= e(format_date($demand['needed_until'])) ?>
                        </p>
                        <?php if (in_array($state, [ListingState::WITHDRAWN, ListingState::RESUBMITTED], true)): ?>
                            <p class="notice-danger"><strong>Důvod stažení:</strong> <?= e($demand['withdrawal_reason']) ?>
                                <?= $state === ListingState::WITHDRAWN ? '<br>Upravte poptávku; po opravě ji správce znovu uvolní.' : '<br>Oprava je odeslaná, čeká na uvolnění správcem.' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="listing-actions">
                        <a class="button small secondary" href="/moje-poptavky/<?= $id ?>/upravit">Upravit</a>
                        <?php if (ListingState::canEnd($state)): ?>
                            <form method="post" action="/moje-poptavky/<?= $id ?>/ukoncit"><?= $csrf->field() ?><button class="button small secondary" type="submit">Ukončit</button></form>
                        <?php endif; ?>
                        <?php if (ListingState::canReactivate($state)): ?>
                            <a class="button small" href="/moje-poptavky/<?= $id ?>/obnovit">Znovu zveřejnit</a>
                        <?php endif; ?>
                        <a class="button small danger" href="/moje-poptavky/<?= $id ?>/smazat">Smazat</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
