# Rozhodnutí doplňující SPECIFIKACE.md

Při rozporu mají tato rozhodnutí přednost před `SPECIFIKACE.md`.

## 12. 10. 2026

### Produkt

1. **Žádné předběžné schvalování.** Nové i upravené nabídky a poptávky se zveřejní okamžitě. Ruší se stavy `pending_approval` a `rejected` i schvalovací fronta (mění §3, §8–10, §17, §20.9, §20.12, §20.16).
2. **Dodatečné stažení adminem.** Admin může zveřejněný záznam stáhnout s povinným důvodem. Autor důvod uvidí ve svém portálu a může záznam opravit. Znovu se zveřejní až po uvolnění adminem. Obsah admin nikdy neupravuje.
3. **Přehled pro admina:** nové a upravené záznamy od jeho poslední návštěvy. Bez e-mailů.
4. **Smazání autorem je měkké** (`deleted_at`). Záznam zmizí všem, v DB zůstane pro audit.
5. **Reaktivace s prošlým datem** vyžaduje nové budoucí datum. Proběhne okamžitě, bez schválení.
6. **Cenový filtr** jde použít jen spolu s vybranou cenovou jednotkou.
7. **Jediný stav ručního ukončení:** „Ukončeno“. Žádné koncepty.

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
