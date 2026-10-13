<?php
/** @var \App\Domain\ListingType $type */
/** @var array<string, mixed> $item */
/** @var list<array<string, mixed>> $history */
/** @var array<string, string> $errors */
/** @var string $reason */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingState;

$values = ['reason' => $reason];
['input' => $input] = require dirname(__DIR__) . '/partials/fields.php';
$state = ListingState::effective((string) $item['status'], $item[$type->endColumn()], $today);
$isOffer = $type->key === 'offer';
$badges = ['active' => 'badge-ok', 'ended' => 'badge-muted', 'expired' => 'badge-muted', 'withdrawn' => 'badge-danger', 'resubmitted' => 'badge-pending'];
$actions = [
    'created' => 'vytvořeno', 'updated' => 'upraveno', 'ended' => 'ukončeno', 'reactivated' => 'znovu zveřejněno',
    'deleted' => 'smazáno', 'withdrawn' => 'staženo správcem', 'released' => 'uvolněno správcem',
];
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [['Administrace', '/admin'], ['Záznamy', '/admin/zaznamy'], [(string) $item['product_name'], null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1><?= e($item['product_name']) ?></h1>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</section>
<div class="admin-content detail-grid">
    <div>
        <div class="card">
            <p class="listing-meta"><span class="badge <?= e($badges[$state] ?? 'badge-muted') ?>"><?= e(ListingState::LABELS[$state] ?? $state) ?></span> <span><?= e($item['category_name']) ?></span></p>
            <dl class="contact-list">
                <?php if ($isOffer): ?>
                    <dt>Cena</dt><dd><?= e(format_price((string) $item['unit_price'], (string) $item['price_unit'])) ?></dd>
                    <dt>Množství</dt><dd><?= e(format_number((string) $item['available_quantity'])) ?> <?= e($item['quantity_unit']) ?></dd>
                    <dt>Dostupnost</dt><dd><?= $item['availability_type'] === 'immediate' ? 'ihned' : 'od ' . e(format_date($item['available_from'])) ?><?= $item['available_until'] !== null ? ', platí do ' . e(format_date($item['available_until'])) : ', do ručního ukončení' ?></dd>
                    <dt>Doprava</dt><dd><?= nl2br(e($item['delivery_terms'])) ?></dd>
                    <?php if ($item['description'] !== null): ?><dt>Popis</dt><dd><?= nl2br(e($item['description'])) ?></dd><?php endif; ?>
                <?php else: ?>
                    <dt>Množství</dt><dd><?= e(format_number((string) $item['requested_quantity'])) ?> <?= e($item['quantity_unit']) ?></dd>
                    <dt>Období</dt><dd><?= e(format_date($item['needed_from'])) ?> – <?= e(format_date($item['needed_until'])) ?></dd>
                    <?php if ($item['note'] !== null): ?><dt>Poznámka</dt><dd><?= nl2br(e($item['note'])) ?></dd><?php endif; ?>
                <?php endif; ?>
                <dt>Autor</dt>
                <dd><a href="/admin/uzivatele/<?= (int) $item['owner_id'] ?>"><?= e($item['owner_name'] ?? $item['owner_email']) ?></a>
                    · <?= e($item['owner_phone'] ?? '') ?> · <?= e($item['owner_public_email'] ?? $item['owner_email']) ?></dd>
                <dt>Vytvořeno / změněno</dt><dd><?= e(format_datetime((string) $item['created_at'])) ?> / <?= e(format_datetime((string) $item['content_updated_at'])) ?></dd>
            </dl>
            <p class="muted small-print">Obsah záznamu může měnit jen jeho autor.</p>
        </div>

        <?php if (in_array($item['status'], ['withdrawn', 'resubmitted'], true)): ?>
            <div class="card">
                <h2><?= $item['status'] === 'resubmitted' ? 'Autor záznam opravil' : 'Záznam je stažený' ?></h2>
                <p><strong>Důvod stažení:</strong> <?= e($item['withdrawal_reason']) ?></p>
                <p class="muted">Stáhl <?= e($item['withdrawn_by_email'] ?? '–') ?>, <?= e(format_datetime((string) $item['withdrawn_at'])) ?>.</p>
                <form method="post" action="/admin/<?= e($type->segment()) ?>/<?= (int) $item['id'] ?>/uvolnit">
                    <?= $csrf->field() ?>
                    <button class="button" type="submit">Uvolnit a znovu zveřejnit</button>
                </form>
            </div>
        <?php elseif ($item['status'] === 'active'): ?>
            <form class="card profile-form" method="post" action="/admin/<?= e($type->segment()) ?>/<?= (int) $item['id'] ?>/stahnout" novalidate>
                <?= $csrf->field() ?>
                <h2>Stáhnout z tržiště</h2>
                <?= $input('reason', 'Důvod stažení', ['type' => 'textarea', 'rows' => 3, 'maxlength' => 1000, 'placeholder' => 'Např. Popis obsahuje odkaz na e-shop.', 'hint' => 'Autor důvod uvidí ve svém portálu a může záznam opravit.']) ?>
                <div class="form-actions"><button class="button danger" type="submit">Stáhnout záznam</button></div>
            </form>
        <?php endif; ?>
    </div>

    <aside class="card">
        <h2>Historie</h2>
        <?php if ($history === []): ?>
            <p class="muted">Bez záznamů.</p>
        <?php else: ?>
            <ol class="history">
                <?php foreach ($history as $event):
                    $action = substr((string) $event['action'], strpos((string) $event['action'], '.') + 1);
                    $details = json_decode((string) $event['details'], true) ?: [];
                    ?>
                    <li>
                        <strong><?= e($actions[$action] ?? $action) ?></strong>
                        <span class="muted"><?= e(format_datetime((string) $event['created_at'])) ?> · <?= e($event['actor_email'] ?? 'systém') ?></span>
                        <?php if (!empty($details['changed'])): ?><span class="muted">změny: <?= e(str_replace(',', ', ', (string) $details['changed'])) ?></span><?php endif; ?>
                        <?php if (!empty($details['reason'])): ?><span>„<?= e($details['reason']) ?>“</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </aside>
</div>
