# ADR 001 — Integrace Logto

- **Stav:** navrženo (12. 10. 2026), čeká na ruční ověření na vývojovém tenantu
- **Kontext:** registrace jen na pozvánku platnou 7 dní, role zemědělec / škola / admin, admin s vícefaktorovým ověřením, hosting WEDOS (PHP 8.1+, bez Composeru na serveru).

## Co Logto nabízí (ověřeno v dokumentaci)

| Potřeba | Mechanismus v Logto |
|---|---|
| Vypnout veřejnou registraci | Console → *Sign-in & account → Sign-up and sign-in → Advanced options → Enable user registration* = vypnuto |
| Pozvat konkrétního uživatele i při vypnuté registraci | *One-time token* (`POST /api/one-time-tokens`, `email`, `expiresIn` v sekundách). Uživatel přihlášený s tímto tokenem se automaticky zaregistruje. |
| Použít token | `signIn` s parametry `one_time_token` a `login_hint` (e-mail) |
| Odeslat e-mail přes Logto | Organizační pozvánky: `POST /api/organization-invitations` + `POST /api/organization-invitations/{id}/message`. E-mail odchází přes e-mailový konektor podle šablony `OrganizationInvitation` s proměnnou `{{link}}`. |
| Vynutit MFA jen pro adminy | Organizace s požadavkem MFA: členové musí MFA nastavit při dalším přihlášení a nemohou ho přeskočit. Globální *Mandatory MFA* by zatěžovala všechny zemědělce a školy. |

## Rozhodnutí

### 1. Pozvánky

Zdrojem pravdy je **lokální tabulka `invitations`**. Logto zajišťuje identitu a doručení e-mailu.

1. Admin zadá e-mail a roli. Aplikace uloží pozvánku `pending` s `expires_at = now + 7 dní`.
2. Aplikace vytvoří one-time token (`expiresIn = 604800`) a organizační pozvánku do organizace „Buchlovská spíž – členové“. Pak zavolá `/message` s `link = https://<doména>/pozvanka/prijmout?id=<lokální id>&token=<one-time token>`. E-mail tedy odešle Logto.
3. Odkaz vede do aplikace. Ta zkontroluje lokální pozvánku (`pending`, neexpirovaná) a přesměruje na Logto s `one_time_token` a `login_hint`.
4. Po návratu (callback) aplikace **znovu ověří**, že pro ověřený e-mail z ID tokenu existuje platná pozvánka. Teprve pak založí lokální účet a pozvánku označí `accepted`.
5. Opětovné odeslání = zrušení staré pozvánky (`revoked`) a vytvoření nové. Zrušení = `revoked` lokálně i v Logto.

**Proč lokální kontrola:** dokumentace Logto neuvádí, jak one-time token předčasně zneplatnit. Aplikace proto nikdy nevěří pouhé existenci identity v Logto. Kdo nemá lokální účet ani platnou pozvánku, je po callbacku odmítnut.

**Záložní varianta:** pokud se ukáže, že organizační pozvánky v Logto Cloud nejdou použít (např. konektor nepodporuje šablonu), pošle stejný odkaz aplikace sama přes SMTP na WEDOS. Zbytek toku zůstane stejný.

### 2. Klient OpenID Connect: vlastní, bez závislostí

Oficiální SDK `logto/sdk` (PHP ≥ 8.1) táhne závislosti guzzle, php-jwt, phpfastcache a phpdotenv. Na WEDOS by to znamenalo nahrávat celý `vendor/`. Aplikace potřebuje jen malou část standardu, proto **implementujeme vlastní malý klient** (`src/Auth/Oidc*`) jen nad rozšířeními `openssl`, `curl` a `json`:

- Authorization Code Flow + PKCE (S256), `state` a `nonce` uložené v session;
- výměna kódu za tokeny s `client_secret_basic`;
- ID token: ověření podpisu přes JWKS (ES384/RS256), `iss`, `aud`, `exp`, `iat`, `nonce`;
- discovery dokument a JWKS se ukládají do cache v souboru;
- Management API: token přes `client_credentials` z M2M aplikace.

Kód se pokryje jednotkovými testy s vlastními testovacími klíči. SDK zůstává jako referenční implementace.

### 3. Role a MFA

> **Aktualizace 12. 10. 2026:** zadavatel rozhodl MFA zatím nezapínat (viz `docs/ROZHODNUTI.md`).
> Níže popsané vynucení MFA pro adminy se implementuje jako volba `security.require_admin_mfa`
> (výchozí `false`), aby šlo před produkcí zapnout bez zásahu do kódu.

- Role tržiště (`producer`, `school`, `admin`) a stav účtu jsou **jen v MySQL**, Logto o nich neví.
- Admini jsou navíc členy organizace „Buchlovská spíž – správa“ s povinnou MFA.
- Při každém přihlášení admina aplikace přes Management API zkontroluje, že má MFA aktivní (`GET /api/users/{id}/mfa-verifications`). Bez ní ho do administrace nepustí.
- První admin vznikne CLI skriptem `bin/create-admin.php`, ne přes web.

### 4. Odhlášení a blokace

- Odhlášení je POST s CSRF tokenem: lokální session se zruší a uživatel se přesměruje na `end_session_endpoint`.
- Blokace se kontroluje při **každém** požadavku z lokální DB. Volitelně se účet pozastaví i v Logto (`PATCH /api/users/{id}/is-suspended`).

## Ověřeno na dev tenantu `vnac13` (12. 10. 2026, veřejné endpointy)

- `signInMode: SignIn` — veřejná registrace je vypnutá.
- Registrace: e-mail + heslo + ověření e-mailu; přihlášení: e-mail + heslo.
- ID tokeny jsou podepsané **ES384**; PKCE `S256`; `client_secret_basic` podporováno.
- Dostupné scopes zahrnují `email`, `urn:logto:scope:organizations`.
- Issuer: `https://vnac13.logto.app/oidc`.

## Co ověřit ručně na dev tenantu (krok 0b)

1. Vypnutá registrace a odkaz s one-time tokenem nového uživatele zaregistrují.
2. One-time token s `expiresIn = 604800` Logto přijme (maximum není zdokumentované).
3. Organizační pozvánka s vlastním `link` přijde e-mailem přes vestavěný konektor Logto Cloud.
4. Organizace s povinnou MFA vynutí nastavení TOTP při přihlášení.
5. Endpoint `mfa-verifications` vrátí nastavený faktor.
6. Zda je one-time token po použití spotřebován (jinak spoléháme na lokální kontrolu v kroku 4 toku).

## Zdroje

- https://docs.logto.io/end-user-flows/sign-up-and-sign-in/disable-user-registration
- https://docs.logto.io/end-user-flows/one-time-token
- https://docs.logto.io/end-user-flows/organization-experience/invite-organization-members
- https://docs.logto.io/end-user-flows/mfa/configure-mfa
- https://docs.logto.io/quick-starts/php
- https://github.com/logto-io/php
