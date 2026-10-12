<?php
/**
 * Výpis záznamů tržiště: řádky (výchozí), nebo dlaždice. Bez fotografií (ROZHODNUTI č. 9).
 * Celý řádek / dlaždice je odkaz na detail (odkaz v názvu se roztáhne přes celou položku).
 *
 * @var \App\Domain\ListingType $type
 * @var list<array<string, mixed>> $items
 * @var string $view 'rows' | 'tiles'
 */
$listIsOffer = $type->key === 'offer';
$listClass = $view === 'tiles' ? 'market-tiles' : 'market-rows';
?>
<ul class="<?= $listClass ?>">
    <?php foreach ($items as $listItem):
        $listUrl = '/' . $type->segment() . '/' . (int) $listItem['id'];
        if ($listIsOffer) {
            $listMain = format_price((string) $listItem['unit_price'], (string) $listItem['price_unit']);
            $listFacts = format_number((string) $listItem['available_quantity']) . "\u{00A0}" . $listItem['quantity_unit']
                . ' · ' . ($listItem['availability_type'] === 'immediate' ? 'ihned' : 'od ' . format_date($listItem['available_from']))
                . ($listItem['available_until'] !== null ? ' · do ' . format_date($listItem['available_until']) : '');
        } else {
            $listMain = format_number((string) $listItem['requested_quantity']) . "\u{00A0}" . $listItem['quantity_unit'];
            $listFacts = 'potřeba ' . format_date($listItem['needed_from']) . ' – ' . format_date($listItem['needed_until']);
        }
        ?>
        <li class="market-item">
            <div class="market-item-head">
                <p class="market-item-category"><?= e($listItem['category_name']) ?></p>
                <h3><a href="<?= e($listUrl) ?>"><?= e($listItem['product_name']) ?></a></h3>
                <p class="market-item-owner"><?= e($listItem['owner_name']) ?> · <?= e($listItem['municipality']) ?></p>
            </div>
            <div class="market-item-numbers">
                <p class="market-item-main"><?= e($listMain) ?></p>
                <p class="market-item-facts"><?= e($listFacts) ?></p>
            </div>
            <span class="market-item-arrow" aria-hidden="true">→</span>
        </li>
    <?php endforeach; ?>
</ul>
