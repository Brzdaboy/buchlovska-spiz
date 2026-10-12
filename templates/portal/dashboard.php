<?php
/** @var array<string, mixed> $user */
/** @var array<string, mixed>|null $profile */
/** @var \App\Security\Csrf $csrf */
$roles = ['producer' => 'Zemědělec / producent', 'school' => 'Škola / školní jídelna', 'admin' => 'Administrátor'];
$name = $profile['producer_name'] ?? $profile['school_name'] ?? null;
?>
<section class="page-hero compact">
    <div class="page-hero-inner wide">
        <p class="eyebrow"><?= e($roles[$user['role']] ?? $user['role']) ?></p>
        <h1><?= e($name ?? 'Můj portál') ?></h1>
        <p class="lead">Přihlášeni jako <strong><?= e($user['email']) ?></strong>.</p>
    </div>
</section>
<div class="admin-content">
    <?php if ($user['role'] === 'admin'): ?>
        <ul class="admin-links">
            <li><a class="card admin-link" href="/admin"><strong>Administrace</strong><span>Pozvánky, účty a číselníky.</span></a></li>
        </ul>
    <?php elseif ($profile !== null): ?>
        <div class="card">
            <h2>Kontakty, které vidí ostatní členové</h2>
            <dl class="contact-list">
                <dt>Adresa</dt><dd><?= e($profile['address_line']) ?>, <?= e(substr((string) $profile['postal_code'], 0, 3) . ' ' . substr((string) $profile['postal_code'], 3)) ?> <?= e($profile['municipality']) ?></dd>
                <dt>Telefon</dt><dd><?= e($profile['phone']) ?></dd>
                <dt>E-mail</dt><dd><?= e($profile['public_email']) ?></dd>
                <?php if (!empty($profile['website'])): ?><dt>Web</dt><dd><a href="<?= e($profile['website']) ?>" rel="noopener nofollow" target="_blank"><?= e($profile['website']) ?></a></dd><?php endif; ?>
            </dl>
            <p><a class="button secondary small" href="/profil">Upravit profil</a></p>
        </div>
        <p class="muted"><?= $user['role'] === 'school' ? 'Poptávky' : 'Nabídky' ?> a tržiště přibudou v dalších krocích.</p>
    <?php endif; ?>
    <form method="post" action="/odhlaseni">
        <?= $csrf->field() ?>
        <button class="button secondary" type="submit">Odhlásit se</button>
    </form>
</div>
