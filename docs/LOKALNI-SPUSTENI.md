# Spuštění na vlastním počítači

Návod pro vyzkoušení webu včetně přihlášení přes Logto (vývojový tenant).

## 1. Co nainstalovat

**Windows:** [XAMPP](https://www.apachefriends.org/) s PHP 8.2 nebo novějším. Obsahuje PHP i databázi MariaDB.

**macOS:** přes [Homebrew](https://brew.sh/):

```bash
brew install php mariadb
brew services start mariadb
```

Ověření v terminálu (na Windows v „Shell“ z ovládacího panelu XAMPP):

```bash
php -v          # musí být 8.1 nebo vyšší
php -m          # v seznamu musí být: curl, openssl, pdo_mysql, mbstring
```

Pokud některé rozšíření chybí, odkomentujte v `php.ini` řádek `extension=curl` (případně `openssl`, `pdo_mysql`, `mbstring`).

## 2. Stažení projektu

```bash
git clone https://github.com/Brzdaboy/buchlovska-spiz.git
cd buchlovska-spiz
```

(Bez gitu: na GitHubu **Code → Download ZIP** a rozbalit.)

## 3. Databáze

V XAMPP spusťte **MySQL** a otevřete **phpMyAdmin** (záložka SQL), na macOS příkaz `mariadb -u root`. Spusťte:

```sql
CREATE DATABASE buchlovska_spiz CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
CREATE USER 'buchlovska'@'localhost' IDENTIFIED BY 'zvolte-heslo';
GRANT ALL ON buchlovska_spiz.* TO 'buchlovska'@'localhost';
```

## 4. Konfigurace

Zkopírujte `config/config.example.php` jako `config/config.php` a vyplňte:

| Klíč | Hodnota |
|---|---|
| `app.url` | `http://localhost:8080` |
| `db.host` / `db.name` / `db.user` / `db.pass` | `127.0.0.1` / `buchlovska_spiz` / `buchlovska` / heslo z kroku 3 |
| `logto.endpoint` | `https://vnac13.logto.app` |
| `logto.app_id`, `logto.app_secret` | z Logto Console → Applications → *Buchlovská spíž (dev)* |

`config/config.php` je v `.gitignore`: do gitu se nedostane. Nikomu ho neposílejte.

V Logto Console musí mít aplikace nastaveno:
- **Redirect URI:** `http://localhost:8080/callback`
- **Post sign-out redirect URI:** `http://localhost:8080/`

## 5. Tabulky a váš účet administrátora

```bash
php bin/migrate.php
php bin/create-admin.php vas@email.cz
```

E-mail musí být stejný jako u vašeho uživatele v Logto (User management).

## 6. Spuštění

```bash
php -S localhost:8080 -t public public/index.php
```

Otevřete <http://localhost:8080>, klikněte na **Přihlásit se**, přihlaste se v Logto a měli byste skončit v **Můj portál** jako administrátor.

## Když něco nejde

| Příznak | Řešení |
|---|---|
| „Služba je dočasně nedostupná“ | Aplikace nenašla `config/config.php`, nebo v něm chybí `app.url` / `app.env`. |
| „Přihlášení zatím není spuštěné“ | V `config.php` chybí `logto.app_id` nebo `logto.app_secret`. |
| „Přihlášení se nepodařilo ověřit“ | Podívejte se do `var/log/app-<datum>.log`. Častá příčina na Windows: chybí certifikáty pro curl, viz níže. |
| Logto hlásí *redirect_uri did not match* | V Logto Console doplňte přesně `http://localhost:8080/callback`. |
| „Tento účet nemá přístup“ | E-mail v `create-admin.php` se liší od e-mailu v Logto, nebo není ověřený. |

**Certifikáty na Windows (XAMPP):** stáhněte <https://curl.se/ca/cacert.pem> do `C:\xampp\php\extras\ssl\cacert.pem` a do `php.ini` doplňte:

```ini
curl.cainfo = "C:\xampp\php\extras\ssl\cacert.pem"
openssl.cafile = "C:\xampp\php\extras\ssl\cacert.pem"
```
