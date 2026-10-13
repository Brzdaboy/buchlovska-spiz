# Rozhodnutí doplňující SPECIFIKACE.md

Při rozporu mají tato rozhodnutí přednost před `SPECIFIKACE.md`.

## 12. 10. 2026

### Produkt

1. **Žádné předběžné schvalování.** Nové i upravené nabídky a poptávky se zveřejní okamžitě. Ruší se stavy `pending_approval` a `rejected` i schvalovací fronta (mění §3, §8–10, §17, §20.9, §20.12, §20.16).
2. **Dodatečné stažení adminem.** Admin může zveřejněný záznam stáhnout s povinným důvodem. Autor důvod uvidí ve svém portálu a může záznam opravit. Znovu se zveřejní až po uvolnění adminem. Obsah admin nikdy neupravuje.
3. **Přehled pro admina:** nové a upravené záznamy od jeho poslední návštěvy. Bez e-mailů.
4. ~~**Smazání autorem je měkké** (`deleted_at`).~~ Změněno rozhodnutím č. 12: smazání je okamžité a úplné, včetně historie změn.
5. **Reaktivace s prošlým datem** vyžaduje nové budoucí datum. Proběhne okamžitě, bez schválení.
6. ~~**Cenový filtr** jde použít jen spolu s vybranou cenovou jednotkou.~~ Zrušeno rozhodnutím č. 10.
7. **Jediný stav ručního ukončení:** „Ukončeno“. Žádné koncepty.
8. **Číselníky bez správy v administraci.** Kategorie a jednotky jsou pevný seznam z migrace (mění §2, §8 a §9: „spravovaný administrátorem“ neplatí). Změna seznamu = nová SQL migrace.
9. **Tržiště bez fotografií.** Nabídky ani poptávky nemají v seznamu ani v detailu žádný obrázek (mění §8 a §16 – „jednotná univerzální fotografie“). Seznam má dvě zobrazení: řádky (výchozí) a dlaždice; volbu si web pamatuje v session.
10. **Bez cenového filtru (13. 10. 2026).** Tržiště nefiltruje podle ceny; filtry jsou jen kategorie a období (mění §11 „filtrování … podle ceny nabídky“). Hledání v názvu ignoruje diakritiku a velikost písmen.
11. **Řazení nabídek (13. 10. 2026):** Nejnovější (výchozí) / Nejlevnější / Nejdražší podle částky bez ohledu na jednotku; volba se nepamatuje. Poptávky řazení nemají (jen od nejnovějších).
12. **Úklid dat (13. 10. 2026)**, podle Zásad ochrany osobních údajů §8:
    - smazaná nabídka/poptávka zmizí z databáze hned i s historií změn;
    - historie změn se drží stejně dlouho jako záznam (doplněno do zásad);
    - ukončené a vypršelé záznamy zůstávají, dokud je nesmaže autor;
    - denní úklid spouští jen plánovaná úloha na WEDOS (docs/UKLID.md): prošlé záznamy → „Vypršelo“, nevyužité pozvánky 30 dní po vypršení pryč, logy po 90 dnech, archivované účty 3 roky po archivaci smazat úplně včetně účtu v Logto;
    - administrace upozorní, když úklid neproběhl déle než 2 dny.

### Stavy nabídky i poptávky

| Stav | Význam | Vidí ostatní? |
|---|---|---|
| `active` | zveřejněno | ano (pokud nevypršelo datum) |
| `ended` | ručně ukončeno autorem | ne |
| `expired` | automaticky po datu konce | ne |
| `withdrawn` | staženo adminem s důvodem | ne |
| `resubmitted` | opraveno po stažení, čeká na uvolnění adminem | ne |

Měkké smazání je samostatný příznak `deleted_at`, ne stav.

### Technika a postup

- Kód v GitHub repozitáři `Brzdaboy/buchlovska-spiz`.
- Logto: vývojový tenant zakládá zadavatel; tajné klíče se neposílají do chatu.
- WEDOS: tarif zatím neznámý; cílíme na PHP 8.1–8.4 a MariaDB 10.6+ / MySQL 8.
- Práce po krocích; po každém kroku kontrola zadavatelem.

### Logto (dev tenant `https://vnac13.logto.app`)

- Veřejná registrace vypnutá, přihlášení e-mailem a heslem, ověřený stav k 12. 10. 2026.
- **Dvoufázové ověření (MFA) je vypnuté** na přání zadavatele, i pro administrátory.
  Odchyluje se to od SPECIFIKACE §5 a README („Administrátor používá vícefaktorové ověření“).
  Aplikace zatím MFA u adminů nevyžaduje; kontrola se dá později zapnout konfigurací.
  Před produkcí doporučeno znovu posoudit.

### Ke schválení klientem

Body 1–2 mění schválenou specifikaci; nechat potvrdit klientem.
