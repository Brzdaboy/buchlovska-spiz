<?php
/** @var list<array<string, mixed>> $invitations */
/** @var array<string, string> $roles */
/** @var bool $configured */
/** @var array<string, string> $old */
/** @var \App\Security\Csrf $csrf */
$statusLabels = [
    'pending' => ['Čeká', 'badge-pending'],
    'accepted' => ['Přijata', 'badge-ok'],
    'expired' => ['Vypršela', 'badge-muted'],
    'revoked' => ['Zrušena', 'badge-muted'],
];
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><a href="/admin">Administrace</a></p>
        <h1>Pozvánky</h1>
        <p class="lead">Registrace je jen na pozvánku. Odkaz v e-mailu platí 7 dní a lze ho použít jednou.</p>
    </div>
</section>
<div class="admin-content">
    <?php if (!$configured): ?>
        <p class="placeholder-note" role="note">Odesílání pozvánek není nastavené. V <code>config.php</code> doplňte údaje M2M aplikace Logto a <code>logto.members_organization_id</code>.</p>
    <?php endif; ?>

    <form class="card form-inline" method="post" action="/admin/pozvanky">
        <?= $csrf->field() ?>
        <h2>Nová pozvánka</h2>
        <div class="form-row">
            <?php
            $values = $old;
            ['input' => $input] = require dirname(__DIR__) . '/partials/fields.php';
            ?>
            <?= $input('email', 'E-mail pozvaného', ['type' => 'email', 'maxlength' => 190, 'autocomplete' => 'off', 'placeholder' => 'Např. farma@email.cz']) ?>
            <fieldset class="field">
                <legend>Role <span class="req" aria-hidden="true">*</span></legend>
                <?php foreach ($roles as $value => $label): ?>
                    <label class="radio">
                        <input type="radio" name="role" value="<?= e($value) ?>" required <?= ($old['role'] ?? '') === $value ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button class="button" type="submit">Odeslat pozvánku</button>
        </div>
    </form>

    <h2>Odeslané pozvánky</h2>
    <?php if ($invitations === []): ?>
        <p class="muted">Zatím nebyla odeslána žádná pozvánka.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr><th scope="col">E-mail</th><th scope="col">Role</th><th scope="col">Stav</th><th scope="col">Odeslána</th><th scope="col">Platí do</th><th scope="col"><span class="visually-hidden">Akce</span></th></tr>
                </thead>
                <tbody>
                <?php foreach ($invitations as $inv):
                    [$label, $badge] = $statusLabels[$inv['effective_status']] ?? [$inv['effective_status'], 'badge-muted'];
                    $canResend = in_array($inv['effective_status'], ['pending', 'expired'], true) && $inv['status'] !== 'revoked' && $inv['user_status'] === 'invited';
                    $canRevoke = $inv['effective_status'] === 'pending';
                    ?>
                    <tr>
                        <td data-label="E-mail"><?= e($inv['email']) ?></td>
                        <td data-label="Role"><?= e($roles[$inv['role']] ?? $inv['role']) ?></td>
                        <td data-label="Stav"><span class="badge <?= e($badge) ?>"><?= e($label) ?></span></td>
                        <td data-label="Odeslána"><?= e(format_datetime($inv['created_at'])) ?></td>
                        <td data-label="Platí do"><?= e(format_datetime($inv['expires_at'])) ?></td>
                        <td class="actions"><div class="action-group">
                            <?php if ($canResend): ?>
                                <form method="post" action="/admin/pozvanky/<?= (int) $inv['id'] ?>/znovu">
                                    <?= $csrf->field() ?><button class="button small secondary" type="submit">Poslat znovu</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canRevoke): ?>
                                <form method="post" action="/admin/pozvanky/<?= (int) $inv['id'] ?>/zrusit">
                                    <?= $csrf->field() ?><button class="button small danger" type="submit">Zrušit</button>
                                </form>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
