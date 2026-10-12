<?php
/**
 * Zásady ochrany osobních údajů – text dodal zadavatel (12. 10. 2026), převzato beze změn obsahu.
 * Místa [DOPLNIT …] jsou zvýrazněná; žluté poznámky jsou pokyny pro provozovatele
 * a před spuštěním se musí vyřešit a odstranit.
 */
$todo = static fn (string $text): string => '<mark class="todo">[' . e($text) . ']</mark>';
?>
<section class="page-hero">
    <div class="page-hero-inner">
        <p class="eyebrow">Právní informace</p>
        <h1>Zásady ochrany osobních údajů</h1>
        <p class="lead">Buchlovská spíž<br>
            Účinnost od: <?= $todo('DOPLNIT DATUM SPUŠTĚNÍ') ?><br>
            Poslední aktualizace: 12. 10. 2026</p>
    </div>
</section>
<div class="prose legal">
    <p class="placeholder-note" role="note">Před zveřejněním doplňte všechna místa označená [DOPLNIT] a nechte text zkontrolovat provozovatelem nebo právníkem. Nastavené doby uchování musí odpovídat skutečnému technickému nastavení webu, Logto, hostingu a záloh.</p>

    <h2>1. Kdo je správcem osobních údajů</h2>
    <p>Správcem osobních údajů zpracovávaných v rámci služby Buchlovská spíž je:</p>
    <p><?= $todo('DOPLNIT ÚPLNÝ NÁZEV PROVOZOVATELE') ?><br>
        IČO: <?= $todo('DOPLNIT') ?><br>
        Sídlo: <?= $todo('DOPLNIT') ?><br>
        E-mail pro záležitosti ochrany osobních údajů: <?= $todo('DOPLNIT') ?><br>
        Telefon: <?= $todo('DOPLNIT, POKUD MÁ BÝT UVEDEN') ?></p>
    <p>dále jen „správce“.</p>
    <p>Pokud správce jmenoval pověřence pro ochranu osobních údajů, lze jej kontaktovat na adrese: <?= $todo('DOPLNIT, NEBO CELÝ ODSTAVEC ODSTRANIT') ?>.</p>

    <h2>2. K čemu služba slouží</h2>
    <p>Buchlovská spíž je uzavřené internetové tržiště určené zejména zemědělcům, producentům, školám a školním jídelnám. Registrovaní uživatelé mohou zveřejňovat a prohlížet nabídky a poptávky a následně se mezi sebou kontaktovat přímo telefonem nebo e-mailem.</p>
    <p>Konkrétní nabídky, poptávky a kontaktní údaje nejsou dostupné běžným návštěvníkům veřejné části webu. Zobrazují se pouze přihlášeným uživatelům služby a oprávněným administrátorům.</p>

    <h2>3. Jaké osobní údaje zpracováváme</h2>
    <p>V závislosti na způsobu používání služby můžeme zpracovávat zejména:</p>
    <ul>
        <li>identifikační údaje uživatele a organizace, například jméno a příjmení kontaktní osoby, název organizace, IČO a její typ;</li>
        <li>kontaktní údaje, zejména e-mailovou adresu, telefonní číslo, adresu a webovou stránku;</li>
        <li>údaje o uživatelském účtu a oprávnění, například uživatelskou roli, stav účtu, datum registrace a posledního přihlášení;</li>
        <li>údaje obsažené v nabídkách a poptávkách, včetně názvu produktu, kategorie, množství, jednotky, ceny nabídky, období dostupnosti nebo potřeby a podmínek dopravy;</li>
        <li>údaje související se schvalováním záznamů, například stav schválení, datum rozhodnutí a důvod zamítnutí;</li>
        <li>technické a bezpečnostní údaje, například IP adresu, datum a čas přístupu, identifikaci relace, záznamy o přihlášení, údaje o zařízení a chybové nebo bezpečnostní logy;</li>
        <li>údaje obsažené v komunikaci se správcem, pokud nás uživatel kontaktuje.</li>
    </ul>
    <p>Do služby nevkládejte osobní údaje žáků, údaje o zdravotním stavu ani jiné citlivé údaje. Služba není určena k jejich zpracování.</p>

    <h2>4. Účely a právní důvody zpracování</h2>
    <h3>Zaslání pozvánky a založení účtu</h3>
    <p>E-mailovou adresu a základní identifikační údaje zpracováváme za účelem zaslání registrační pozvánky, ověření oprávněnosti registrace a založení účtu. Právním důvodem je provedení opatření před uzavřením smluvního vztahu nebo plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR. Pokud je pozvánka zaslána kontaktní osobě organizace na základě předchozí spolupráce nebo doporučení, může být zpracování založeno také na oprávněném zájmu správce provozovat bezpečné uzavřené tržiště podle čl. 6 odst. 1 písm. f) GDPR.</p>
    <h3>Provoz uživatelského účtu a tržiště</h3>
    <p>Údaje zpracováváme, abychom umožnili přihlášení, správu profilu, vytváření, schvalování, zveřejňování a správu nabídek a poptávek a vzájemné kontaktování uživatelů. Právním důvodem je plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR.</p>
    <h3>Bezpečnost a prevence zneužití</h3>
    <p>Technické záznamy, historii přihlášení a nezbytné auditní údaje zpracováváme za účelem zabezpečení služby, ochrany účtů, prevence zneužití a řešení technických incidentů. Právním důvodem je oprávněný zájem správce na bezpečném a spolehlivém provozu služby podle čl. 6 odst. 1 písm. f) GDPR.</p>
    <h3>Plnění právních povinností a ochrana právních nároků</h3>
    <p>Některé údaje můžeme uchovávat, pokud je to nezbytné pro splnění právní povinnosti podle čl. 6 odst. 1 písm. c) GDPR nebo pro určení, výkon či obhajobu právních nároků na základě oprávněného zájmu podle čl. 6 odst. 1 písm. f) GDPR.</p>
    <p>Osobní údaje nepoužíváme k zasílání marketingových sdělení, pokud k tomu nemáme samostatný právní důvod. E-maily o schválení, zamítnutí nebo vložení nabídky či poptávky služba podle současného nastavení neodesílá.</p>

    <h2>5. Odkud osobní údaje získáváme</h2>
    <p>Většinu údajů získáváme přímo od uživatele při registraci, správě profilu, používání služby nebo komunikaci se správcem.</p>
    <p>E-mail pro zaslání pozvánky můžeme získat také od organizace, kterou uživatel zastupuje, od osoby oprávněné doporučit její zapojení nebo z veřejně dostupného profesního kontaktu. Pokud jsme údaje nezískali přímo od uživatele, sdělíme mu při pozvání také jejich zdroj a informace požadované čl. 14 GDPR.</p>

    <h2>6. Kdo údaje uvidí a komu je předáváme</h2>
    <p>Údaje mohou být zpřístupněny pouze v nezbytném rozsahu:</p>
    <ul>
        <li>přihlášeným uživatelům Buchlovské spíže; u zveřejněných nabídek a poptávek zejména název organizace, adresa, telefon, e-mail a web;</li>
        <li>administrátorům a pověřeným osobám správce;</li>
        <li>poskytovateli autentizační služby Logto, který zajišťuje pozvánky, registraci a přihlášení;</li>
        <li>poskytovateli webhostingu a databáze WEDOS;</li>
        <li>dodavateli technické správy a vývoje webu <?= $todo('DOPLNIT JMÉNO / NÁZEV A POSTAVENÍ DODAVATELE') ?>, pokud má k údajům přístup;</li>
        <li>dalším dodavatelům nezbytným pro provoz, zabezpečení, zálohování nebo e-mailovou komunikaci, pokud budou zapojeni;</li>
        <li>orgánům veřejné moci, pokud nám předání ukládá právní předpis.</li>
    </ul>
    <p>S dodavateli, kteří pro správce zpracovávají osobní údaje, musí být uzavřeny odpovídající smlouvy o zpracování osobních údajů.</p>
    <p>Uživatelé nesmějí kontaktní údaje získané prostřednictvím tržiště používat k nevyžádanému marketingu, předávat je neoprávněným osobám ani je využívat v rozporu s účelem služby.</p>

    <h2>7. Předávání údajů mimo Evropský hospodářský prostor</h2>
    <p>Primárně usilujeme o zpracování osobních údajů v Evropském hospodářském prostoru. Někteří techničtí dodavatelé však mohou zpracovávat údaje také mimo Evropský hospodářský prostor.</p>
    <p>Pokud k takovému předání dochází, zajistí správce odpovídající právní mechanismus, zejména rozhodnutí Evropské komise o odpovídající ochraně nebo standardní smluvní doložky, a případně další ochranná opatření. Aktuální informace o konkrétním umístění dat a mechanismu předání poskytne správce na vyžádání.</p>
    <p class="placeholder-note" role="note">Před spuštěním ověřte datový region a smluvní podmínky konkrétního účtu Logto. Pokud budou všechna data zpracovávána pouze v EHP, upravte tento oddíl podle skutečnosti.</p>

    <h2>8. Jak dlouho údaje uchováváme</h2>
    <p>Navržené doby uchování pro první verzi služby jsou:</p>
    <ul>
        <li>nevyužitá registrační pozvánka: po dobu její platnosti 7 dnů a následně nejvýše 30 dnů pro bezpečnostní kontrolu a řešení chyb;</li>
        <li>údaje aktivního účtu: po dobu trvání účtu a používání služby;</li>
        <li>profil a obsah po ukončení účtu: nejvýše 3 roky, pokud je to nezbytné pro ochranu právních nároků; údaje nepotřebné pro tento účel budou odstraněny nebo anonymizovány dříve;</li>
        <li>smazané nabídky a poptávky: odstranění z aktivní databáze bez zbytečného odkladu; v zálohách mohou přetrvat nejvýše 30 dnů;</li>
        <li>bezpečnostní a provozní logy: nejvýše 90 dnů, není-li delší uchování nutné kvůli konkrétnímu bezpečnostnímu incidentu;</li>
        <li>komunikace se správcem: po dobu nezbytnou k vyřízení požadavku a následně nejvýše 3 roky;</li>
        <li>údaje, které musí být uchovány podle právních předpisů: po dobu stanovenou příslušným právním předpisem.</li>
    </ul>
    <p class="placeholder-note" role="note">Tyto lhůty jsou doporučením. Před zveřejněním je potvrďte a nastavte stejně v aplikaci, databázi, Logto, hostingu a systému záloh.</p>

    <h2>9. Povinnost poskytnout údaje</h2>
    <p>Poskytnutí údajů označených jako povinné je nezbytné pro vytvoření účtu a používání tržiště. Bez nich nelze registraci dokončit nebo službu v požadovaném rozsahu poskytovat. Poskytnutí nepovinných údajů je dobrovolné.</p>

    <h2>10. Automatizované rozhodování</h2>
    <p>Při zpracování osobních údajů nedochází k automatizovanému individuálnímu rozhodování ani profilování, které by pro uživatele mělo právní nebo obdobně významné účinky. Schválení nebo zamítnutí nabídky či poptávky provádí oprávněný administrátor.</p>

    <h2>11. Jaká máte práva</h2>
    <p>Za podmínek stanovených GDPR máte zejména právo:</p>
    <ul>
        <li>získat potvrzení, zda zpracováváme vaše osobní údaje, a požadovat k nim přístup;</li>
        <li>požadovat opravu nepřesných nebo doplnění neúplných údajů;</li>
        <li>požadovat výmaz osobních údajů, pokud již nejsou potřebné nebo je splněn jiný zákonný důvod;</li>
        <li>požadovat omezení zpracování;</li>
        <li>získat údaje ve strukturovaném, běžně používaném a strojově čitelném formátu a případně je předat jinému správci, pokud jsou splněny podmínky přenositelnosti;</li>
        <li>vznést námitku proti zpracování založenému na oprávněném zájmu;</li>
        <li>odvolat souhlas, pokud by bylo některé zpracování na souhlasu založeno; odvolání nemá vliv na zákonnost předchozího zpracování;</li>
        <li>podat stížnost u dozorového úřadu.</li>
    </ul>
    <p>Žádost můžete zaslat na <?= $todo('DOPLNIT E-MAIL PRO GDPR') ?> nebo na poštovní adresu správce uvedenou výše. Za vyřízení běžné žádosti neúčtujeme poplatek. Před vyřízením můžeme přiměřeným způsobem ověřit totožnost žadatele.</p>
    <p>Dozorovým úřadem je:<br>
        Úřad pro ochranu osobních údajů<br>
        Pplk. Sochora 27<br>
        170 00 Praha 7<br>
        Web: <a href="https://uoou.gov.cz" rel="noopener noreferrer" target="_blank">https://uoou.gov.cz</a></p>

    <h2>12. Zabezpečení údajů</h2>
    <p>Správce přijímá přiměřená technická a organizační opatření odpovídající povaze a rizikovosti zpracování. Patří mezi ně zejména šifrovaný přenos pomocí HTTPS, řízení přístupových oprávnění, oddělení uživatelských rolí, bezpečné přihlašování, pravidelné aktualizace, zálohování a evidence důležitých administrátorských operací.</p>
    <p>Žádný systém však nelze považovat za absolutně bezpečný. Pokud zjistíte podezření na zneužití účtu nebo bezpečnostní incident, kontaktujte nás bez zbytečného odkladu na <?= $todo('DOPLNIT KONTAKT') ?>.</p>

    <h2>13. Cookies a přihlášení</h2>
    <p>Web používá technické cookies nebo obdobné technologie nezbytné pro fungování stránky, zabezpečení relace a přihlášení prostřednictvím Logto. Bez těchto technologií nemusí být možné se přihlásit nebo používat chráněnou část služby.</p>
    <p>V současné verzi nepoužíváme analytické ani marketingové cookies. Pokud je v budoucnu začneme používat, budou před jejich uložením aktivovány pouze na základě odpovídajícího souhlasu a tyto zásady i nastavení cookies budou aktualizovány.</p>

    <h2>14. Změny těchto zásad</h2>
    <p>Tyto zásady můžeme přiměřeně aktualizovat, zejména pokud se změní fungování služby, používaní dodavatelé nebo právní požadavky. Aktuální verze bude vždy dostupná na webu Buchlovské spíže s uvedením data poslední aktualizace. O podstatných změnách, které se významně dotknou registrovaných uživatelů, je budeme vhodným způsobem informovat.</p>
</div>
