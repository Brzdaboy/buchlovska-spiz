# Bezpečnost – přehled a kontrola (krok 12, 13. 10. 2026)

## Ověřeno automatickými testy (běží při každé změně na GitHubu)

| Oblast | Jak je chráněno | Test |
|---|---|---|
| Oprávnění ke každé adrese | Každá trasa má pravidlo (veřejná / člen / zemědělec / škola / správce). Nová trasa bez pravidla shodí test. Pro 6 identit (nepřihlášený, zablokovaný, neúplný profil, zemědělec, škola, správce) se ověřuje, že odepřený přístup nic nezmění v databázi. | `AccessMatrixTest` |
| Cizí záznamy (IDOR) | Úprava, ukončení, obnovení i smazání cizí nabídky/poptávky vrací 404 a nic nemění. | `AccessMatrixTest`, `OfferFlowTest`, `DemandFlowTest` |
| CSRF | Každý POST bez platného tokenu vrací 419 a nic nemění. | `AccessMatrixTest` |
| Přihlášení (Logto, OIDC) | PKCE S256, state, nonce, ověření podpisu ID tokenu (ES384/ES256/RS256), iss/aud/exp. Účet se propojí jen přes platnou pozvánku na ověřený e-mail. | `OidcClientTest`, `AuthFlowTest`, `InvitationFlowTest` |
| Zablokovaný účet | Stav se čte z databáze při každém požadavku; zablokovaný je okamžitě odhlášen. | `AuthFlowTest`, `AccessMatrixTest` |
| Tržiště jen pro členy | Seznamy, detaily, profily i kontakty vyžadují aktivní účet s profilem. | `MarketFlowTest` |
| XSS | Veškerý text od uživatelů se escapuje (`e()`); CSP bez inline skriptů a stylů. | `MarketFlowTest`, `SecurityTest` |
| SQL injection | Jen připravené dotazy; názvy tabulek a řazení jen z pevných seznamů. | revize kódu |
| Denní úklid | Adresa `/cron/denni` bez správného klíče (min. 32 znaků) vrací 404; porovnání v konstantním čase. | `MaintenanceTest`, `AccessMatrixTest` |

## Hlavičky a relace

- CSP: `default-src 'self'`, žádné inline skripty, `frame-ancestors 'none'`, `form-action` jen web a Logto.
- `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, na HTTPS `HSTS`.
- `Cache-Control: no-store, private` – stránky s kontakty nezůstanou v cizích mezipamětech.
- Session cookie: `HttpOnly`, `SameSite=Lax`, na HTTPS `Secure` + prefix `__Host-`; výměna ID při přihlášení
  i odhlášení; vypršení po 120 min nečinnosti.
- Chyby: v produkci bez technických detailů; detail jen do logu.

## Tajemství

- `config.php` (hesla k databázi, Logto secret, klíč cronu) není v gitu; v historii repozitáře žádné tajemství není.
- Na WEDOS patří `config.php` mimo veřejnou složku (`../buchlovska-config.php`).
- Kořenový `.htaccess` posílá všechny požadavky do `public/`; `src/`, `config/`, `var/`, šablony ani migrace
  nejsou z webu dostupné.

## Otevřené body k rozhodnutí

1. **Dvoufázové ověření (MFA) pro správce je vypnuté** (na přání zadavatele, viz ROZHODNUTI). Správce může
   blokovat účty a vidí všechny kontakty – doporučujeme MFA v Logto pro správce zapnout před spuštěním.
2. Klíč cronu je součástí adresy, takže se objeví v logu přístupů na WEDOS. Je to běžná praxe; klíč lze
   kdykoli změnit v `config.php` a v plánované úloze.
