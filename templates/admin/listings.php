<?php
/** @var list<array<string, mixed>> $items */
/** @var string $filter */
/** @var string|null $seenSince */
/** @var array{new: int, resubmitted: int} $counts */
/** @var string $today */
/** @var \App\Security\Csrf $csrf */
use App\Domain\ListingState;
use App\Domain\ListingType;

$tabs = ['nove' => 'Nové a upravené (' . $counts['new'] . ')', 'k-uvolneni' => 'Čekají na uvolnění (' . $counts['resubmitted'] . ')', 'stazene' => 'Stažené', 'vse' => 'Vše'];
$badges = ['active' => 'badge-ok', 'ended' => 'badge-muted', 'expired' => 'badge-muted', 'withdrawn' => 'badge-danger', 'resubmitted' => 'badge-pending'];
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <?php $crumbs = [['Administrace', '/admin'], ['Záznamy', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Záznamy</h1>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</section>
<div class="admin-content">
    <div class="toolbar">
        <nav class="tabs" aria-label="Filtr záznamů">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="/admin/zaznamy?filtr=<?= e($key) ?>"<?= $key === $filter ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($filter === 'nove' && $items !== []): ?>
            <form method="post" action="/admin/zaznamy/prohlednuto"><?= $csrf->field() ?><button class="button small secondary" type="submit">Označit vše jako prohlédnuté</button></form>
        <?php endif; ?>
    </div>
    <?php if ($filter === 'nove'): ?>
        <p class="muted"><?= $seenSince === null ? 'Zatím jste přehled neoznačili jako prohlédnutý, zobrazují se všechny záznamy.' : 'Změny od ' . e(format_datetime($seenSince)) . '.' ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="card empty-state"><p class="muted">Nic tu není.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Záznam</th><th scope="col">Autor</th><th scope="col">Stav</th><th scope="col">Změněno</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item):
                    $type = ListingType::of((string) $item['type']);
                    $state = ListingState::effective((string) $item['status'], $item['end_date'], $today);
                    $isNew = $item['created_at'] === $item['content_updated_at'];
                    ?>
                    <tr>
                        <td data-label="Záznam">
                            <span class="muted"><?= e($type->label()) ?> · <?= e($item['category_name']) ?></span><br>
                            <a href="/admin/<?= e($type->segment()) ?>/<?= (int) $item['id'] ?>"><strong><?= e($item['product_name']) ?></strong></a>
                        </td>
                        <td data-label="Autor"><?= e($item['owner_name'] ?? $item['owner_email']) ?><?= $item['owner_status'] !== 'active' ? ' <span class="badge badge-danger">účet ' . e($item['owner_status'] === 'blocked' ? 'blokován' : $item['owner_status']) . '</span>' : '' ?></td>
                        <td data-label="Stav"><span class="badge <?= e($badges[$state] ?? 'badge-muted') ?>"><?= e(ListingState::LABELS[$state] ?? $state) ?></span></td>
                        <td data-label="Změněno"><?= e(format_datetime((string) $item['content_updated_at'])) ?><br><span class="muted"><?= $isNew ? 'nový' : 'upravený' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
