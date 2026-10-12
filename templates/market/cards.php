<?php
/**
 * Mřížka karet tržiště. Vkládá se přes require (seznam, dashboard).
 * Všechny karty mají jednu společnou fotografii (SPECIFIKACE §16) – je jen dekorativní.
 *
 * @var \App\Domain\ListingType $type
 * @var list<array<string, mixed>> $items
 */
$cardsIsOffer = $type->key === 'offer';
?>
<ul class="market-grid">
    <?php foreach ($items as $cardItem):
        $cardUrl = '/' . $type->segment() . '/' . (int) $cardItem['id'];
        ?>
        <li class="market-card">
            <img class="market-card-photo" src="/assets/img/card-480.webp" srcset="/assets/img/card-480.webp 480w, /assets/img/card-960.webp 960w"
                 sizes="(max-width: 700px) 100vw, 360px" width="480" height="270" alt="" loading="lazy" decoding="async">
            <div class="market-card-body">
                <p class="market-card-category"><?= e($cardItem['category_name']) ?></p>
                <h3><a href="<?= e($cardUrl) ?>"><?= e($cardItem['product_name']) ?></a></h3>
                <?php if ($cardsIsOffer): ?>
                    <p class="market-card-price"><?= e(format_price((string) $cardItem['unit_price'], (string) $cardItem['price_unit'])) ?></p>
                    <p class="market-card-facts">
                        <?= e(format_number((string) $cardItem['available_quantity'])) ?>&nbsp;<?= e($cardItem['quantity_unit']) ?>
                        · <?= $cardItem['availability_type'] === 'immediate' ? 'ihned' : 'od ' . e(format_date($cardItem['available_from'])) ?>
                        <?php if ($cardItem['available_until'] !== null): ?> · do <?= e(format_date($cardItem['available_until'])) ?><?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="market-card-price"><?= e(format_number((string) $cardItem['requested_quantity'])) ?>&nbsp;<?= e($cardItem['quantity_unit']) ?></p>
                    <p class="market-card-facts">potřeba <?= e(format_date($cardItem['needed_from'])) ?> – <?= e(format_date($cardItem['needed_until'])) ?></p>
                <?php endif; ?>
                <p class="market-card-owner"><?= e($cardItem['owner_name']) ?> · <?= e($cardItem['municipality']) ?></p>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
