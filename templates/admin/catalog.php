<?php
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $units */
/** @var \App\Security\Csrf $csrf */
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow">Administrace</p>
        <h1>Číselníky</h1>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</section>
<div class="admin-content">
    <p class="muted">Položky se nemažou, jen vypínají: záznamy, které je používají, si je ponechají, ale do nových se už nenabízejí. Pořadí určuje číslo (menší = výš).</p>

    <h2>Kategorie</h2>
    <div class="table-wrap">
        <table class="data-table catalog-table">
            <thead><tr><th scope="col">Název</th><th scope="col">Pořadí</th><th scope="col">Nabízet</th><th scope="col">Použito</th><th scope="col"><span class="visually-hidden">Uložit</span></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): $form = 'cat-' . (int) $c['id']; ?>
                <tr>
                    <td data-label="Název"><input form="<?= $form ?>" name="name" value="<?= e($c['name']) ?>" maxlength="100" required aria-label="Název kategorie"></td>
                    <td data-label="Pořadí"><input form="<?= $form ?>" name="sort_order" value="<?= (int) $c['sort_order'] ?>" inputmode="numeric" class="short" aria-label="Pořadí"></td>
                    <td data-label="Nabízet"><label class="checkbox"><input form="<?= $form ?>" type="checkbox" name="is_active" value="1"<?= $c['is_active'] ? ' checked' : '' ?>> <span>ano</span></label></td>
                    <td data-label="Použito"><?= (int) $c['usage_count'] ?>×</td>
                    <td class="actions"><form id="<?= $form ?>" method="post" action="/admin/kategorie/<?= (int) $c['id'] ?>"><?= $csrf->field() ?><button class="button small secondary" type="submit">Uložit</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form class="card form-inline" method="post" action="/admin/kategorie">
        <?= $csrf->field() ?>
        <div class="form-row">
            <div class="field"><label for="new-cat">Nová kategorie</label><input id="new-cat" name="name" maxlength="100" required placeholder="Např. Byliny a koření"></div>
            <div class="field field-short"><label for="new-cat-sort">Pořadí</label><input id="new-cat-sort" name="sort_order" inputmode="numeric" value="<?= (int) (max(array_column($categories, 'sort_order') ?: [0]) + 10) ?>"></div>
            <button class="button" type="submit">Přidat kategorii</button>
        </div>
    </form>

    <h2>Jednotky</h2>
    <div class="table-wrap">
        <table class="data-table catalog-table">
            <thead><tr><th scope="col">Zkratka</th><th scope="col">Název</th><th scope="col">Pořadí</th><th scope="col">Nabízet</th><th scope="col">Použito</th><th scope="col"><span class="visually-hidden">Uložit</span></th></tr></thead>
            <tbody>
            <?php foreach ($units as $u): $form = 'unit-' . (int) $u['id']; ?>
                <tr>
                    <td data-label="Zkratka"><strong><?= e($u['code']) ?></strong></td>
                    <td data-label="Název"><input form="<?= $form ?>" name="label" value="<?= e($u['label']) ?>" maxlength="30" required aria-label="Název jednotky"></td>
                    <td data-label="Pořadí"><input form="<?= $form ?>" name="sort_order" value="<?= (int) $u['sort_order'] ?>" inputmode="numeric" class="short" aria-label="Pořadí"></td>
                    <td data-label="Nabízet"><label class="checkbox"><input form="<?= $form ?>" type="checkbox" name="is_active" value="1"<?= $u['is_active'] ? ' checked' : '' ?>> <span>ano</span></label></td>
                    <td data-label="Použito"><?= (int) $u['usage_count'] ?>×</td>
                    <td class="actions"><form id="<?= $form ?>" method="post" action="/admin/jednotky/<?= (int) $u['id'] ?>"><?= $csrf->field() ?><button class="button small secondary" type="submit">Uložit</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form class="card form-inline" method="post" action="/admin/jednotky">
        <?= $csrf->field() ?>
        <div class="form-row">
            <div class="field field-short"><label for="new-unit-code">Zkratka</label><input id="new-unit-code" name="code" maxlength="8" required placeholder="bal"></div>
            <div class="field"><label for="new-unit-label">Název</label><input id="new-unit-label" name="label" maxlength="30" required placeholder="balení"></div>
            <div class="field field-short"><label for="new-unit-sort">Pořadí</label><input id="new-unit-sort" name="sort_order" inputmode="numeric" value="<?= (int) (max(array_column($units, 'sort_order') ?: [0]) + 10) ?>"></div>
            <button class="button" type="submit">Přidat jednotku</button>
        </div>
    </form>
</div>
