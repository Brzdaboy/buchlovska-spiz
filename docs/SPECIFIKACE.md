# Buchlovská spíž — specifikace projektu

> Aktuální zdroj pravdy pro další návrh, vývoj i práci v Claude nebo ChatGPT.

## 1. Přehled projektu

**Název:** Buchlovská spíž  
**Oblast:** Mikroregion Buchlov  
**Účel:** Uzavřená B2B platforma propojující místní zemědělce a producenty se školami a školními jídelnami.

Platforma umožní zemědělcům zveřejňovat nabídky plodin a výrobků a školám zveřejňovat konkrétní poptávky. Oba typy záznamů uvidí pouze přihlášení uživatelé. Následná domluva, odběr a obchod proběhnou přímo mezi účastníky.

Web není e-shop. V první verzi nebude obsahovat košík, online objednávky, platby, fakturaci ani řízení dopravy. Očekává se přibližně 200 uživatelů.

## 2. Schválené principy

- Registrace je pouze na pozvánku zaslanou administrátorem e-mailem.
- Pozvánky, registraci, přihlášení, hesla a ověření identity zajišťuje Logto.
- Veřejná homepage obsahuje představení projektu, ale žádné nabídky, poptávky ani kontakty.
- Nabídky, poptávky a kontaktní údaje jsou dostupné pouze přihlášeným uživatelům.
- Zemědělec vytváří a spravuje své nabídky.
- Škola vytváří a spravuje své poptávky.
- Každý uživatel má vlastní portál se svými záznamy.
- Administrátor spravuje pozvánky, účty, nabídky, poptávky a číselníky.
- Oprávnění se kontrolují na serveru, nikoliv pouze skrytím prvků v rozhraní.

## 3. Uživatelské role

### Veřejný návštěvník

- vidí homepage, informace o projektu, kontakt a právní stránky,
- může přejít na přihlášení,
- nevidí tržiště, profily ani kontakty členů,
- nemůže se samostatně registrovat.

### Zemědělec / producent

- dostane e-mailovou pozvánku,
- dokončí registraci přes Logto a svůj profil,
- po přihlášení vidí uzavřené tržiště,
- spravuje jen svůj profil a své nabídky,
- ve vlastním portálu vidí své aktivní i neaktivní nabídky,
- může kontaktovat školu podle údajů u poptávky, pokud budou tyto údaje klientem potvrzeny jako viditelné.

### Škola / školní jídelna

- dostane e-mailovou pozvánku,
- dokončí registraci přes Logto a profil školy,
- po přihlášení vidí uzavřené tržiště,
- spravuje jen svůj profil a své poptávky,
- ve vlastním portálu vidí své aktivní i uzavřené poptávky,
- může kontaktovat zemědělce podle údajů u nabídky.

### Administrátor

- vytváří, odesílá, opakuje a ruší pozvánky,
- u pozvánky nastaví roli `producer` nebo `school`,
- spravuje a blokuje uživatelské účty,
- spravuje všechny nabídky a poptávky,
- schvaluje nebo zamítá každou novou či upravenou nabídku a poptávku před zveřejněním,
- při zamítnutí povinně uvede důvod,
- obsah záznamu sám neupravuje,
- spravuje kategorie a jednotky,
- vidí stav pozvánek a případný audit změn.

## 4. Registrace pouze na pozvánku

Tento proces nahrazuje dříve navrženou veřejnou žádost o registraci:

1. Administrátor zadá e-mail budoucího uživatele a vybere roli zemědělec nebo škola.
2. Systém prostřednictvím Logto odešle jednorázový zvací odkaz.
3. Příjemce otevře odkaz a dokončí registraci v Logto.
4. Uživatel si nastaví vlastní heslo; administrátor ho nikdy nezná.
5. Po návratu do aplikace doplní profilové a kontaktní údaje pro svou roli.
6. Aplikace propojí Logto identitu s lokálním účtem a rolí.
7. Po dokončení profilu se uživatel dostane do portálu a uzavřeného tržiště.

### Stavy pozvánky

- `pending` — dosud nepoužitá,
- `accepted` — registrace byla dokončena,
- `expired` — vypršela,
- `revoked` — administrátor ji zrušil.

### Stavy účtu

- `invited`,
- `profile_incomplete`,
- `active`,
- `blocked`,
- `archived`.

Pozvánka platí 7 dní. Po vypršení může administrátor uživateli jednotlivě odeslat novou pozvánku. Hromadný import ani rozesílání pozvánek nejsou součástí MVP.

## 5. Přihlášení a bezpečnost

- Autentizaci zajišťuje Logto Cloud přes OpenID Connect / OAuth 2.0.
- Veřejná samoobslužná registrace bude vypnuta.
- Hesla se neposílají e-mailem a nejsou v MySQL databázi aplikace.
- Aplikace ukládá pouze Logto ID uživatele, roli, stav a profilová data.
- Celý web běží přes HTTPS.
- Relace používají bezpečné cookies `HttpOnly`, `Secure` a vhodné `SameSite`.
- Každá změna dat prochází serverovou kontrolou identity, role a vlastnictví záznamu.
- Administrátor používá vícefaktorové ověření.
- Tajné klíče Logto jsou mimo veřejný zdrojový kód.
- Formuláře mají CSRF ochranu, SQL dotazy jsou parametrizované a výstupy escapované proti XSS.

## 6. Profil zemědělce

Při dokončení profilu zadává:

- jméno a příjmení kontaktní osoby,
- název farmy nebo producenta,
- adresu, obec a PSČ,
- telefon,
- kontaktní e-mail,
- web, pokud jej má,
- případně IČO,
- potřebné souhlasy.

Adresa, telefon, e-mail a web se automaticky zobrazí u jeho nabídek, ale pouze přihlášeným uživatelům.

## 7. Profil školy

Při dokončení profilu zadává:

- název školy nebo školní jídelny,
- jméno kontaktní osoby,
- adresu, obec a PSČ,
- telefon,
- kontaktní e-mail,
- web, pokud jej má,
- případně IČO nebo identifikátor organizace,
- potřebné souhlasy.

U každé schválené poptávky se ostatním přihlášeným uživatelům zobrazí adresa, telefon, e-mail a web školy.

Jednu školu zastupuje právě jeden uživatelský účet.

## 8. Nabídky zemědělců

Povinná pole potvrzená klientem:

- kategorie vybraná z administrátorem spravovaného seznamu,
- název plodiny nebo výrobku zadaný volným textem,
- povinná přesná jednotková cena; varianta „dohodou“ ani prázdná cena nejsou povoleny,
- cenová jednotka, například Kč/kg, Kč/t, Kč/ks nebo Kč/l,
- dostupné množství,
- jednotka množství,
- dostupnost: ihned, nebo konkrétní datum,
- volitelné datum ukončení platnosti nabídky; bez něj nabídka platí až do ručního ukončení,
- podmínky dopravy — volný text.

Z profilu se automaticky zobrazí:

- název producenta,
- adresa,
- telefon,
- e-mail,
- web, pokud je vyplněn.

Doporučená systémová pole:

- kategorie,
- volitelný popis,
- stav `draft`, `pending_approval`, `active`, `rejected`, `inactive`, `sold_out`, `expired`,
- datum vytvoření a změny,
- datum schválení a administrátor, který záznam schválil.

Nabídky neobsahují uživatelské fotografie. V rozhraní se použije jednotný univerzální vizuál, případně předem připravený obrázek nebo ikona podle kategorie produktu. Jednu farmu nebo producenta zastupuje právě jeden uživatelský účet.

## 9. Poptávky škol

Povinná pole potvrzená klientem:

- kategorie vybraná z administrátorem spravovaného seznamu,
- název plodiny nebo výrobku zadaný volným textem,
- požadované množství,
- jednotka množství,
- období, kdy škola produkt potřebuje.

Poptávka neobsahuje cenu, maximální cenu ani rozpočet.

Doporučená systémová pole:

- kategorie,
- volitelná poznámka,
- začátek a konec požadovaného období,
- stav `draft`, `pending_approval`, `active`, `rejected`, `fulfilled`, `cancelled`, `expired`,
- datum vytvoření a změny,
- datum schválení a administrátor, který záznam schválil.

U poptávky se zobrazí adresa, telefon, e-mail a web školy. Komunikace probíhá výhradně mimo platformu: uživatel si opíše telefon nebo e-mail a školu kontaktuje přímo. Systém neobsahuje tlačítko „Mám zájem“, kontaktní formulář, interní zprávy ani evidenci reakcí.

## 10. Matice viditelnosti a oprávnění

| Obsah / akce | Veřejnost | Zemědělec | Škola | Administrátor |
|---|---:|---:|---:|---:|
| Veřejná homepage | Ano | Ano | Ano | Ano |
| Nabídky | Ne | Ano | Ano | Ano |
| Poptávky | Ne | Ano | Ano | Ano |
| Kontakty u nabídky | Ne | Ano | Ano | Ano |
| Kontakty školy u poptávky | Ne | Ano | Ano | Ano |
| Vlastní portál | Ne | Své nabídky | Své poptávky | Správa všeho |
| Vytvořit nabídku | Ne | Ano | Ne | Dle oprávnění |
| Vytvořit poptávku | Ne | Ne | Ano | Dle oprávnění |

Zemědělci vidí schválené nabídky ostatních zemědělců a školy vidí schválené poptávky ostatních škol. Celé přihlášené tržiště je tedy společné pro obě role.

Nová nabídka či poptávka dostane stav `pending_approval` a ostatním uživatelům se zobrazí až po schválení administrátorem. Autor ji ve svém portálu vidí včetně aktuálního stavu. Pozdější úpravy již schváleného záznamu se zveřejní okamžitě a neprocházejí opakovaným schválením.

Při zamítnutí musí administrátor uvést důvod. Autor jej uvidí ve svém portálu, může záznam opravit a znovu jej odeslat ke schválení. O schválení ani zamítnutí se uživateli neposílá e-mail.

Nabídku i poptávku může autor kdykoliv ručně ukončit. Nabídka může platit bez časového omezení; pokud zemědělec zadá volitelné datum konce, systém ji po jeho dosažení automaticky odstraní z aktivního tržiště. Poptávka se automaticky ukončí na konci období, ve kterém škola produkt potřebuje. Ukončený záznam zůstává ve vlastním portálu jako neaktivní nebo expirovaný a autor jej může znovu aktivovat okamžitě, bez dalšího schválení. Autor může záznam také trvale smazat; tato akce musí vyžadovat potvrzení a po provedení ji nelze běžně obnovit.

Změny profilu, adresy a kontaktních údajů se zveřejní okamžitě bez schvalování administrátorem.

## 11. Portál přihlášeného uživatele

### Společná část

- dashboard s nejnovějšími nabídkami a poptávkami,
- samostatné seznamy Nabídky a Poptávky,
- fulltextové vyhledávání podle názvu produktu,
- filtrování podle kategorie, období dostupnosti či potřeby a ceny nabídky,
- filtr podle obce nebo lokality se v první verzi nepoužije,
- výchozí řazení od nejnovějších záznamů,
- stránkování po 12 záznamech,
- detail nabídky nebo poptávky,
- přístup ke kontaktům podle oprávnění.

Kontaktování neprobíhá prostřednictvím webu. Uživatel použije zobrazený telefon nebo e-mail a spojí se s druhou stranou přímo.

### Portál zemědělce

- souhrn vlastních nabídek podle stavu,
- vytvoření nabídky,
- úprava, deaktivace a smazání vlastní nabídky,
- správa profilu,
- rychlý přístup k poptávkám škol.

### Portál školy

- souhrn vlastních poptávek podle stavu,
- vytvoření poptávky,
- úprava, uzavření a smazání vlastní poptávky,
- správa profilu,
- rychlý přístup k nabídkám zemědělců.

## 12. Stránky a obrazovky

### Veřejná část

- homepage s úvodním textem,
- informace o projektu,
- kontakt na provozovatele,
- přihlášení přes Logto,
- ochrana osobních údajů a podmínky,
- případně informace „Jak získat přístup“ bez registračního formuláře.

### Přihlášená část

- dashboard podle role,
- samostatná stránka Nabídky,
- samostatná stránka Poptávky,
- detail nabídky a poptávky,
- vlastní záznamy,
- vytvoření a editace záznamu,
- profil účtu.

### Administrace

- dashboard,
- vytvoření a správa pozvánek,
- výběr role při pozvání,
- seznam uživatelů a stav účtu,
- blokování a archivace,
- správa nabídek a poptávek,
- správa kategorií a jednotek,
- případně auditní protokol.

## 13. E-mailové události

Minimální sada:

1. Pozvánka k registraci.
2. Opětovné odeslání pozvánky.
3. Obnovení hesla přes Logto.
4. Bezpečnostní upozornění podle nastavení Logto.
5. Volitelně informace o zablokování nebo obnovení účtu.

Systém neposílá e-mail při schválení ani zamítnutí nabídky či poptávky. Uživatel sleduje stav a případný důvod zamítnutí ve svém portálu.

Administrátor nedostává e-mail ani při vložení nového záznamu ke schválení. Čekající záznamy vidí ve schvalovací frontě v administraci.

Budoucí notifikace mohou upozorňovat na nové nabídky či poptávky, shodu kategorie nebo blížící se konec platnosti.

## 14. Datový model

### Výchozí kategorie

- Zelenina
- Ovoce
- Obiloviny a mouka
- Maso a uzeniny
- Mléko a mléčné výrobky
- Vejce
- Pečivo
- Med a včelí produkty
- Nápoje
- Ostatní

Administrátor může kategorie později přidávat, upravovat, řadit a deaktivovat.

### Výchozí jednotky a měna

- `kg`
- `t`
- `g`
- `l`
- `ml`
- `ks`

Uživatel vybírá jednotku z pevného seznamu; nemůže zadat vlastní textovou jednotku. Jedinou podporovanou měnou jsou české koruny (`CZK`, zobrazované jako `Kč`). Administrátor může seznam jednotek později spravovat.

### `users`

- `id`, `logto_user_id`, `email`, `role`, `status`,
- `profile_completed_at`, `last_login_at`, `created_at`, `updated_at`.

### `invitations`

- `id`, `email`, `role`, `logto_invitation_id`, `status`,
- `invited_by`, `expires_at`, `accepted_at`, `created_at`.

### `producer_profiles`

- `user_id`, `contact_name`, `producer_name`,
- `address_line`, `municipality`, `postal_code`,
- `phone`, `public_email`, `website`, `company_id`, `privacy_consent_at`.

### `school_profiles`

- `user_id`, `school_name`, `contact_name`,
- `address_line`, `municipality`, `postal_code`,
- `phone`, `public_email`, `website`, `organization_id`, `privacy_consent_at`.

### `offers`

- `id`, `producer_user_id`, `product_name`, `category_id`, `description`,
- `unit_price`, `currency`, `price_unit_id`,
- `available_quantity`, `quantity_unit_id`,
- `availability_type`, `available_from`, `delivery_terms`,
- `available_until`,
- `status`, `reviewed_by`, `reviewed_at`, `rejection_reason`,
- `created_at`, `updated_at`.

### `demands`

- `id`, `school_user_id`, `product_name`, `category_id`,
- `requested_quantity`, `quantity_unit_id`,
- `needed_from`, `needed_until`, `note`,
- `status`, `reviewed_by`, `reviewed_at`, `rejection_reason`,
- `created_at`, `updated_at`.

### Další tabulky

- `categories`,
- `units`,
- `admin_audit_log`,
- případně `notification_log`.

## 15. Technická architektura

| Vrstva | Technologie | Úloha |
|---|---|---|
| Hosting | WEDOS webhosting | PHP aplikace, databáze a soubory |
| Backend | PHP 8.x | Logika, oprávnění, administrace a integrace Logto |
| Databáze | MySQL / MariaDB | Uživatelé, profily, nabídky, poptávky a číselníky |
| Frontend | HTML5, CSS3, JavaScript | Responzivní rozhraní |
| Autentizace | Logto Cloud, OpenID Connect | Pozvánky, registrace, přihlášení a hesla |
| E-mail | Logto konektor / SMTP | Pozvánky a systémové zprávy |
| HTTPS | TLS certifikát | Šifrovaný přenos |

Logto ověří identitu. Role a oprávnění tržiště zůstávají v MySQL a PHP aplikace podle nich rozhoduje, co uživatel vidí a může změnit.

### Vlastnictví a provoz účtů

- Produkční účet Logto bude založený a spravovaný dodavatelem webu.
- Účet musí být veden na pracovní e-mail dodavatele, chráněný vícefaktorovým ověřením a evidovaný v předávací dokumentaci.
- Musí existovat postup pro bezpečné předání nebo převod účtu klientovi při ukončení spolupráce.
- Web poběží na nové samostatné doméně; konkrétní název se ještě vybere a zaregistruje.
- Pro systémové a registrační e-maily vznikne nová projektová adresa na této doméně.
- Přesnou adresu je nutné určit před konfigurací e-mailového konektoru Logto, SPF, DKIM a DMARC.

## 16. Vizuální směr

- moderní přírodní vzhled,
- světlé krémové pozadí a tmavě zelené prvky,
- jemné pastelové plochy a zaoblené karty,
- kvalitní fotografie regionálních potravin,
- důvěryhodný a přístupný vzhled pro zemědělce, školy a obce.

Homepage musí vysvětlit smysl projektu a vést členy k přihlášení. Nesmí zobrazovat živá data tržiště.

Nabídky ani poptávky neobsahují uživatelské fotografie. Všechny karty a detaily používají jednu společnou univerzální fotografii dodanou nebo schválenou zadavatelem.

## 17. Rozsah MVP

### Součást první verze

- veřejná informační homepage,
- registrace pouze na pozvánku přes Logto,
- role zemědělec, škola a administrátor,
- dokončení profilu podle role,
- neveřejný katalog nabídek a poptávek,
- detail nabídky a poptávky,
- kontakty dostupné pouze přihlášeným,
- vlastní portál uživatele,
- správa vlastních nabídek zemědělcem,
- správa vlastních poptávek školou,
- administrace pozvánek, účtů, záznamů, kategorií a jednotek,
- schvalovací fronta nových a upravených nabídek a poptávek,
- systémové e-maily,
- právní a informační stránky,
- provoz na WEDOS.

### Mimo MVP

- košík, objednávky a platby,
- fakturace,
- logistika,
- interní chat,
- tlačítko „Mám zájem“, kontaktní formuláře a interní zprávy,
- interní nabídky zemědělců na poptávku,
- automatické párování,
- pokročilé notifikace,
- hodnocení uživatelů,
- veřejný katalog,
- mobilní aplikace,
- vícejazyčnost.

## 18. Stav prototypu

Aktuální návrh: <https://buchlovska-spiz.michalaa.chatgpt.site>

Prototyp odpovídá staršímu veřejnému katalogu a musí se upravit:

- skrýt nabídky a kontakty před veřejností,
- přidat přihlášenou část a vlastní portály,
- přidat roli školy a poptávky,
- přidat dashboard podle role,
- nahradit veřejnou registraci pozvánkami přes Logto.

Backend, databáze, Logto integrace a administrace zatím nejsou považovány za dokončené.

## 19. Otevřené otázky

1. Jaký bude přesný název nové domény?
2. Jaká bude přesná projektová e-mailová adresa pro pozvánky?
3. Jaký konkrétní tarif WEDOS bude použit a kdo poskytne přístupy?

## 20. Instrukce pro další AI

1. Tento dokument je aktuální zdroj pravdy.
2. Registrace je výhradně na e-mailovou pozvánku přes Logto.
3. Nabídky, poptávky, profily a kontakty nejsou veřejné.
4. Zemědělec spravuje své nabídky, škola své poptávky.
5. Každý má vlastní portál a přístup do uzavřeného tržiště podle role.
6. Hesla nikdy neukládej v aplikační MySQL databázi ani neposílej e-mailem.
7. Oprávnění kontroluj na serveru.
8. Nepřidávej objednávky, platby, chat ani logistiku bez výslovného souhlasu.
9. Každá nabídka a poptávka musí před zveřejněním projít schválením administrátorem.
10. Nepřidávej uživatelské fotografie, interní kontaktní tlačítka ani více účtů pro jednu organizaci.
11. Nabídka vždy obsahuje přesnou jednotkovou cenu; poptávka školy neobsahuje žádnou cenu ani rozpočet.
12. První zveřejnění schvaluje administrátor, následné úpravy schváleného záznamu se publikují okamžitě.
13. Pozvánka platí 7 dní a administrátor pozvánky odesílá jednotlivě.
14. Nabídky a poptávky se ukončují ručně nebo automaticky podle data; v historii zůstávají zachované.
15. Datum konce nabídky je volitelné, poptávka končí s obdobím potřeby a archivovaný záznam lze znovu aktivovat.
16. Reaktivace je okamžitá. Zamítnutí musí mít důvod a umožnit opravu; stav se sleduje v portálu bez e-mailu uživateli.
17. Produkt má vybranou kategorii a vlastní textový název. Jednotky se vybírají z pevného seznamu. Administrátor sleduje schvalovací frontu bez e-mailových upozornění.
18. Výchozí seznam obsahuje deset kategorií a jednotky kg, t, g, l, ml a ks. Jedinou měnou je Kč.
19. Tržiště podporuje hledání a všechny potvrzené filtry, řadí od nejnovějších a používá jednu univerzální fotografii pro všechny záznamy.
20. Nabídky a poptávky mají samostatné stránky, zobrazují se po 12 položkách a filtr lokality se v MVP nepoužije.
21. Administrátor obsah neupravuje, pouze schvaluje či zamítá. Autor může záznam trvale smazat a změny profilu se zveřejňují okamžitě.
22. Logto spravuje dodavatel, web bude na nové samostatné doméně a pozvánky odejdou z nové projektové e-mailové adresy.

## 21. Krátký prompt pro Claude

```text
Jsi technický a produktový spolupracovník projektu Buchlovská spíž. Přečti soubor Buchlovska-spiz-specifikace.md a používej jej jako jediný aktuální zdroj pravdy. Registrace je výhradně na pozvánku přes Logto. Nabídky, poptávky a kontakty jsou dostupné jen přihlášeným uživatelům. Zemědělec spravuje své nabídky, škola své poptávky a každý má vlastní portál. Nejprve shrň své porozumění, upozorni na relevantní otevřené otázky a poté pokračuj v zadaném úkolu. Neměň schválená rozhodnutí bez souhlasu zadavatele.
```

---

**Poslední aktualizace:** 9. října 2026
