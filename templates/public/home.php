<?php
/*
 * Veřejná homepage. Podle SPECIFIKACE §16 nesmí zobrazovat živá data tržiště:
 * žádné nabídky, poptávky, počty ani kontakty členů. Vše níže je statický text.
 */
$categories = [
    ['Zelenina', '🥬', 'mint'], ['Ovoce', '🍎', 'rose'], ['Obiloviny a mouka', '🌾', 'wheat'],
    ['Maso a uzeniny', '🥩', 'rose'], ['Mléko a mléčné výrobky', '🥛', 'cream'], ['Vejce', '🥚', 'cream'],
    ['Pečivo', '🍞', 'wheat'], ['Med a včelí produkty', '🍯', 'honey'], ['Nápoje', '🧃', 'sage'], ['Ostatní', '🧺', 'sage'],
];
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span class="dot" aria-hidden="true">●</span> Lokální potraviny z mikroregionu Buchlov</p>
        <h1>Z Buchlovska<br>rovnou do <em>školních kuchyní</em></h1>
        <p class="lead">Propojujeme místní zemědělce a producenty se školami a školními jídelnami. Zemědělci zveřejňují nabídky, školy poptávky a domluví se spolu napřímo.</p>
        <div class="hero-actions">
            <a class="button" href="/prihlaseni">Přihlásit se <span aria-hidden="true">→</span></a>
            <a class="button secondary" href="/jak-ziskat-pristup">Jak získat přístup</a>
        </div>
        <ul class="trust-row">
            <li>Jen pro pozvané členy</li>
            <li>Přímý kontakt farma – škola</li>
            <li>Místní původ</li>
        </ul>
    </div>
    <div class="hero-visual">
        <img src="/assets/img/hero-960.webp"
             srcset="/assets/img/hero-640.webp 640w, /assets/img/hero-960.webp 960w, /assets/img/hero-1600.webp 1600w"
             sizes="(max-width: 1000px) 100vw, 50vw"
             width="1600" height="900"
             alt="Čerstvá zelenina, vejce, med a obilí z místních farem"
             fetchpriority="high">
        <div class="local-badge" aria-hidden="true"><b>Místní</b><small>z Buchlovska</small></div>
    </div>
</section>

<section class="section" aria-labelledby="pro-koho">
    <div class="section-intro">
        <p class="eyebrow">Pro koho je Buchlovská spíž</p>
        <h2 id="pro-koho">Dvě strany jednoho stolu</h2>
    </div>
    <div class="audience-grid">
        <article class="audience-card tone-mint">
            <h3>Zemědělci a producenti</h3>
            <p>Nabídněte svou úrodu a výrobky školám v okolí.</p>
            <ol class="numbered">
                <li>zveřejníte nabídku s cenou, množstvím a dostupností,</li>
                <li>uvidíte, co školy právě poptávají,</li>
                <li>školy vás kontaktují přímo telefonem nebo e-mailem.</li>
            </ol>
        </article>
        <article class="audience-card tone-honey">
            <h3>Školy a školní jídelny</h3>
            <p>Najděte místní suroviny pro svou kuchyni.</p>
            <ol class="numbered">
                <li>zveřejníte poptávku s množstvím a obdobím, kdy surovinu potřebujete,</li>
                <li>projdete aktuální nabídky zemědělců z regionu,</li>
                <li>s producentem se domluvíte napřímo.</li>
            </ol>
        </article>
    </div>
</section>

<section class="band" aria-labelledby="co-se-potkava">
    <div class="section">
        <div class="section-intro">
            <p class="eyebrow">Co se tu potkává</p>
            <h2 id="co-se-potkava">Od zeleniny po med</h2>
            <p>Nabídky i poptávky se třídí do kategorií. Konkrétní nabídky a poptávky vidí jen přihlášení členové.</p>
        </div>
        <ul class="category-grid">
            <?php foreach ($categories as [$name, $icon, $tone]): ?>
                <li class="category-card tone-<?= e($tone) ?>">
                    <span class="category-icon" aria-hidden="true"><?= e($icon) ?></span>
                    <strong><?= e($name) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section how" aria-labelledby="jak-to-funguje">
    <div class="how-intro">
        <p class="eyebrow">Jednoduché propojení</p>
        <h2 id="jak-to-funguje">Od místního pole<br>až na školní talíř</h2>
        <p>Žádné objednávky přes web, žádné platby ani prostředníci. Buchlovská spíž vám pomůže najít správného partnera a spojit se s ním napřímo.</p>
        <a class="button secondary" href="/jak-ziskat-pristup">Jak se zapojit <span aria-hidden="true">→</span></a>
    </div>
    <ol class="steps">
        <li>
            <span class="step-no">01</span><span class="step-icon" aria-hidden="true">✉️</span>
            <div><h3>Pozvánka od správce</h3><p>Správce platformy vám pošle e-mailovou pozvánku. Registraci dokončíte do 7 dní.</p></div>
        </li>
        <li>
            <span class="step-no">02</span><span class="step-icon" aria-hidden="true">🧺</span>
            <div><h3>Profil a vlastní záznamy</h3><p>Doplníte kontaktní údaje a zveřejníte nabídky, nebo jako škola poptávky.</p></div>
        </li>
        <li>
            <span class="step-no">03</span><span class="step-icon" aria-hidden="true">🤝</span>
            <div><h3>Domluva napřímo</h3><p>Druhá strana vás kontaktuje telefonem nebo e-mailem. Odběr i platbu si domluvíte sami.</p></div>
        </li>
    </ol>
</section>

<section class="about" aria-labelledby="o-projektu-kratce">
    <div class="about-inner">
        <div class="about-mark" aria-hidden="true">B<span>•</span></div>
        <div>
            <p class="eyebrow">Společně pro náš region</p>
            <h2 id="o-projektu-kratce">Dobré jídlo začíná blízko</h2>
            <p>Buchlovská spíž podporuje místní ekonomiku, zkracuje cestu potravin a pomáhá dětem poznat, odkud jejich jídlo pochází.</p>
            <p><a href="/o-projektu">Více o projektu</a></p>
        </div>
        <ul class="facts">
            <li><b>Uzavřená</b>nabídky a poptávky vidí jen přihlášení členové</li>
            <li><b>Bez e-shopu</b>žádné objednávky ani platby přes web</li>
            <li><b>Napřímo</b>kontakt mezi farmou a školou bez prostředníka</li>
        </ul>
    </div>
</section>
