<?php
/**
 * Kontakt na provozovatele. Údaje se berou z konfigurace (site.*);
 * dokud je klient nepotvrdí, stránka ukáže viditelnou poznámku místo vymyšlených údajů.
 *
 * @var array{name: string, email: string, phone: string, address: string} $operator
 */
$hasContact = $operator['email'] !== '' || $operator['phone'] !== '';
?>
<section class="page-hero">
    <div class="page-hero-inner">
        <?php $crumbs = [['Kontakt', null]]; require dirname(__DIR__) . '/partials/breadcrumbs.php'; ?>
        <h1>Kontakt na provozovatele</h1>
        <p class="lead">Chcete se zapojit, potřebujete novou pozvánku nebo pomoc s účtem? Napište nebo zavolejte správci platformy.</p>
    </div>
</section>
<div class="prose">
    <?php if (!$hasContact): ?>
        <p class="placeholder-note" role="note">Kontaktní údaje provozovatele doplníme před spuštěním webu.</p>
    <?php else: ?>
        <div class="card">
            <dl class="contact-list">
                <?php if ($operator['name'] !== ''): ?><dt>Provozovatel</dt><dd><?= e($operator['name']) ?></dd><?php endif; ?>
                <?php if ($operator['email'] !== ''): ?><dt>E-mail</dt><dd><a href="mailto:<?= e($operator['email']) ?>"><?= e($operator['email']) ?></a></dd><?php endif; ?>
                <?php if ($operator['phone'] !== ''): ?><dt>Telefon</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $operator['phone'])) ?>"><?= e($operator['phone']) ?></a></dd><?php endif; ?>
                <?php if ($operator['address'] !== ''): ?><dt>Adresa</dt><dd><?= nl2br(e($operator['address'])) ?></dd><?php endif; ?>
            </dl>
        </div>
    <?php endif; ?>
    <p>Kontakty na jednotlivé zemědělce a školy najdete po přihlášení u jejich nabídek a poptávek.</p>
</div>
