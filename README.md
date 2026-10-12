# Buchlovská spíž

Uzavřené B2B tržiště, které propojuje místní zemědělce mikroregionu Buchlov se školami a školními jídelnami.

- **Zadání:** `docs/SPECIFIKACE.md` + rozhodnutí v `docs/ROZHODNUTI.md` (rozhodnutí mají přednost)
- **Architektonická rozhodnutí:** `docs/adr/`
- **Provoz:** WEDOS webhosting, PHP 8.1+, MySQL/MariaDB, přihlášení přes Logto Cloud

## Struktura

| Složka | Obsah | Veřejná? |
|---|---|---|
| `public/` | `index.php` (front controller), CSS, obrázky | **ano** — jediný kořen webu |
| `src/` | aplikační kód (`App\…`), vlastní autoloader | ne |
| `templates/` | šablony v čistém PHP | ne |
| `config/` | `config.example.php` (vzor); skutečný `config.php` není v gitu | ne |
| `var/` | logy a cache | ne |
| `tests/` | PHPUnit: jednotkové a integrační testy | ne |
| `docs/` | zadání, rozhodnutí, ADR | ne |

Aplikace na serveru **nepotřebuje Composer** ani žádné knihovny třetích stran. Composer slouží jen pro PHPUnit při vývoji a v CI.

## Lokální vývoj

Požadavky: PHP 8.1+ s rozšířeními `pdo_mysql`, `mbstring`, `openssl`, `curl`.

```bash
cp config/config.example.php config/config.php   # a doplňte hodnoty
php -S localhost:8080 -t public public/index.php
```

Web poběží na <http://localhost:8080>.

### Databáze

Podporované: **MariaDB 10.6+** nebo **MySQL 8.0.16+** (starší verze nevynucují omezení `CHECK`).

```sql
CREATE DATABASE buchlovska_spiz CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
```

Údaje doplňte do `config/config.php` a spusťte migrace:

```bash
php bin/migrate.php            # provede chybějící migrace (vč. výchozích kategorií a jednotek)
php bin/migrate.php --status   # přehled
```

Schéma je v `migrations/`. Už provedenou migraci nikdy neupravujte; změny patří do nové (`002_…sql`).

## Testy

```bash
composer install     # jednou, stáhne PHPUnit
vendor/bin/phpunit   # nebo jen "phpunit", pokud ho máte globálně
```

Integrační testy spouštějí vestavěný server PHP a ověřují skutečné HTTP odpovědi.

Databázové testy běží jen s proměnnými prostředí a **celou testovací databázi smažou**
(název proto musí obsahovat „test“):

```bash
BSPIZ_TEST_DB_NAME=bspiz_test BSPIZ_TEST_DB_USER=… BSPIZ_TEST_DB_PASS=… vendor/bin/phpunit
```

## Bezpečnostní zásady

- Tajemství (heslo k DB, Logto secret) jsou jen v `config.php` mimo git a mimo veřejnou složku.
- Každý POST vyžaduje CSRF token; ověřuje se centrálně v `App::handle()`.
- Výstupy se escapují funkcí `e()`; SQL dotazy jen s parametry (PDO, bez emulace).
- Uživatel nikdy neuvidí detail chyby; detail jde do `var/log/`.
- Oprávnění a vlastnictví záznamů se kontroluje na serveru.
