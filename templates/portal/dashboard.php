<?php
/** @var array<string, mixed> $user */
/** @var \App\Security\Csrf $csrf */
$roles = ['producer' => 'Zemědělec / producent', 'school' => 'Škola / školní jídelna', 'admin' => 'Administrátor'];
?>
<section class="page-hero">
    <div class="page-hero-inner">
        <p class="eyebrow">Můj portál</p>
        <h1>Vítejte v Buchlovské spíži</h1>
        <p class="lead">Jste přihlášeni jako <strong><?= e($user['email']) ?></strong> (<?= e($roles[$user['role']] ?? $user['role']) ?>).</p>
    </div>
</section>
<div class="prose">
    <?php if ($user['status'] === 'profile_incomplete'): ?>
        <p class="placeholder-note" role="note">Ještě je potřeba dokončit profil s kontaktními údaji. Formulář přibude v další verzi.</p>
    <?php endif; ?>
    <p>Nabídky, poptávky a správa vlastních záznamů se sem doplní v dalších krocích.</p>
    <form method="post" action="/odhlaseni">
        <?= $csrf->field() ?>
        <button class="button secondary" type="submit">Odhlásit se</button>
    </form>
</div>
