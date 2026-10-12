<?php
/** @var \App\Domain\ListingType $type */
/** @var array<string, mixed> $user */
/** @var \App\Service\MarketFilter $filter */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var list<array{id: int, name: string, slug: string}> $categories */
/** @var array<int, string> $units */
$isOffer = $type->key === 'offer';
$base = '/' . $type->segment();
$in = $filter->input;
$errors = $filter->errors;
$items = $result['items'];

$errorText = static fn (string $name): string => isset($errors[$name])
    ? '<span class="field-error" id="m-' . $name . '-error">' . e($errors[$name]) . '</span>' : '';
$aria = static fn (string $name): string => isset($errors[$name])
    ? ' aria-describedby="m-' . $name . '-error" aria-invalid="true"' : '';

$count = $result['total'];
$noun = $isOffer
    ? ($count === 1 ? 'nabídka' : ($count >= 2 && $count <= 4 ? 'nabídky' : 'nabídek'))
    : ($count === 1 ? 'poptávka' : ($count >= 2 && $count <= 4 ? 'poptávky' : 'poptávek'));
$ownLink = $user['role'] === ($isOffer ? 'producer' : 'school')
    ? ($isOffer ? ['/moje-nabidky', 'Moje nabídky'] : ['/moje-poptavky', 'Moje poptávky'])
    : null;
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow">Tržiště</p>
        <h1><?= $isOffer ? 'Nabídky zemědělců' : 'Poptávky škol' ?></h1>
        <p class="lead"><?= $isOffer
            ? 'Co nabízejí místní zemědělci a producenti. U každé nabídky najdete kontakt – domluvíte se napřímo.'
            : 'Co shánějí školy a školní jídelny. U každé poptávky najdete kontakt na školu – domluvíte se napřímo.' ?></p>
        <?php if ($ownLink !== null): ?>
            <p><a class="button secondary small" href="<?= e($ownLink[0]) ?>"><?= e($ownLink[1]) ?></a></p>
        <?php endif; ?>
    </div>
</section>
<div class="admin-content market">
    <?php $extra = $filter->extraFilterCount(); $openFilters = $extra > 0 || $errors !== []; ?>
    <form class="card market-filter" method="get" action="<?= e($base) ?>" role="search" aria-label="Hledat a filtrovat">
        <div class="market-search">
            <label class="visually-hidden" for="m-q">Hledat produkt</label>
            <input id="m-q" name="q" type="search" value="<?= e($in['q']) ?>" maxlength="100" placeholder="<?= $isOffer ? 'Hledat produkt, např. brambory' : 'Hledat produkt, např. jablka' ?>">
            <button class="button market-search-submit" type="submit">
                <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M15.5 15.5 21 21" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                <span class="market-search-label">Hledat</span>
            </button>
        </div>
        <details class="market-more"<?= $openFilters ? ' open' : '' ?>>
            <summary class="button secondary">
                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                Filtry<?php if ($extra > 0): ?> <span class="filter-count" aria-label="aktivní: <?= $extra ?>"><?= $extra ?></span><?php endif; ?>
            </summary>
            <div class="market-filter-panel">
                <div class="market-filter-grid<?= $isOffer ? ' with-price' : '' ?>">
                    <div class="field">
                        <label for="m-kategorie">Kategorie</label>
                        <select id="m-kategorie" name="kategorie">
                            <option value="">Všechny kategorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category['slug']) ?>"<?= $category['slug'] === $in['kategorie'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field field-period" role="group" aria-labelledby="m-period-label">
                        <span class="filter-label" id="m-period-label"><?= $isOffer ? 'Dostupné v období' : 'Potřeba v období' ?></span>
                        <div class="period-inputs">
                            <label class="visually-hidden" for="m-od">Od</label>
                            <input id="m-od" name="od" type="date" value="<?= e($in['od']) ?>"<?= $aria('od') ?>>
                            <span aria-hidden="true">–</span>
                            <label class="visually-hidden" for="m-do">Do</label>
                            <input id="m-do" name="do" type="date" value="<?= e($in['do']) ?>"<?= $aria('do') ?>>
                        </div>
                        <?= $errorText('od') . $errorText('do') ?>
                    </div>
                    <?php if ($isOffer): ?>
                        <div class="field field-price<?= isset($errors['cena_do']) ? ' has-error' : '' ?>" role="group" aria-labelledby="m-price-label">
                            <span class="filter-label" id="m-price-label">Cena nejvýš</span>
                            <div class="price-inputs">
                                <label class="visually-hidden" for="m-cena">Cena v Kč</label>
                                <input id="m-cena" name="cena_do" inputmode="decimal" value="<?= e($in['cena_do']) ?>" placeholder="Kč"<?= $aria('cena_do') ?>>
                                <span aria-hidden="true">Kč /</span>
                                <label class="visually-hidden" for="m-jednotka">za jednotku</label>
                                <select id="m-jednotka" name="jednotka">
                                    <option value="">jednotka</option>
                                    <?php foreach ($units as $code): ?>
                                        <option value="<?= e($code) ?>"<?= $code === $in['jednotka'] ? ' selected' : '' ?>><?= e($code) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?= $errorText('cena_do') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="market-filter-actions">
                    <button class="button" type="submit">Použít filtry</button>
                </div>
            </div>
        </details>
    </form>

    <p class="market-count" role="status"><?= $filter->isActive() ? 'Nalezeno' : 'Celkem' ?> <strong><?= $count ?></strong> <?= $noun ?><?= $result['pages'] > 1 ? ' · strana ' . $result['page'] . ' z ' . $result['pages'] : '' ?>
        <?php if ($filter->isActive() || $errors !== []): ?> · <a href="<?= e($base) ?>">Zrušit hledání a filtry</a><?php endif; ?></p>

    <?php if ($items === []): ?>
        <div class="card empty-state">
            <?php if ($filter->isActive()): ?>
                <h2>Nic jsme nenašli</h2>
                <p class="muted">Zkuste jiný výraz nebo zrušte některý filtr.</p>
                <a class="button secondary" href="<?= e($base) ?>">Zobrazit vše</a>
            <?php else: ?>
                <h2><?= $isOffer ? 'Zatím tu nejsou žádné nabídky' : 'Zatím tu nejsou žádné poptávky' ?></h2>
                <p class="muted"><?= $isOffer ? 'Jakmile zemědělci něco nabídnou, objeví se to tady.' : 'Jakmile školy něco poptají, objeví se to tady.' ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php require __DIR__ . '/cards.php'; ?>
        <?php if ($result['pages'] > 1): ?>
            <nav class="pagination" aria-label="Stránkování">
                <?php if ($result['page'] > 1): ?>
                    <a class="button secondary small" rel="prev" href="<?= e($base . $filter->queryString($result['page'] - 1)) ?>">← Předchozí</a>
                <?php endif; ?>
                <ol>
                    <?php for ($p = 1; $p <= $result['pages']; $p++):
                        if ($p !== 1 && $p !== $result['pages'] && abs($p - $result['page']) > 2) {
                            if ($p === 2 || $p === $result['pages'] - 1) {
                                echo '<li class="gap" aria-hidden="true">…</li>';
                            }
                            continue;
                        }
                        ?>
                        <li><a href="<?= e($base . $filter->queryString($p)) ?>"<?= $p === $result['page'] ? ' aria-current="page"' : '' ?>><?= $p ?></a></li>
                    <?php endfor; ?>
                </ol>
                <?php if ($result['page'] < $result['pages']): ?>
                    <a class="button secondary small" rel="next" href="<?= e($base . $filter->queryString($result['page'] + 1)) ?>">Další →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
