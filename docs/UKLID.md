# Denní úklid

Jednou denně (ideálně v noci) musí proběhnout úklid. Web sám od sebe nic nespouští –
nastavuje se to jednou jako **plánovaná úloha (cron) na WEDOS**.

## Co úklid dělá

1. Nabídky s datem „platí do“ v minulosti a poptávky po konci období přepne na **Vypršelo**
   (v historii záznamu jako „systém“). Na tržišti už vidět nejsou, jde o soulad dat.
2. **Nevyužité pozvánky** smaže 30 dní po vypršení nebo zrušení (celkem 37 dní od odeslání),
   spolu s nedokončeným účtem.
3. **Archivované účty** smaže 3 roky po archivaci: profil, nabídky/poptávky, historii změn
   a také účet v Logto (e-mail a heslo). Když Logto zrovna neodpoví, účet se zkusí znovu další noc.
   Vypnout: `'maintenance' => ['delete_logto_accounts' => false]` v config.php.
4. **Soubory logů** (`var/log/app-*.log`) starší 90 dní smaže.

Smazané nabídky a poptávky se mažou hned při smazání autorem, ne až úklidem.
Ukončené a vypršelé záznamy, které autor nesmaže, zůstávají.

## Nastavení na WEDOS

### Varianta A – volání adresy (funguje na každém webhostingu)

1. V `config.php` vyplňte náhodný klíč (aspoň 32 znaků):
   ```php
   'cron' => ['key' => 'sem-dlouhy-nahodny-retezec'],
   ```
   Vygenerovat jde např. příkazem `php -r "echo bin2hex(random_bytes(24));"`.
2. V administraci WEDOS → webhosting → **Plánované úlohy (cron)** přidejte úlohu,
   která jednou denně (např. ve 3:00) zavolá adresu:
   ```
   https://<vaše-doména>/cron/denni?klic=<klíč z config.php>
   ```
3. Klíč nikomu neposílejte. Se špatným nebo prázdným klíčem adresa vrací „stránka nenalezena“.

### Varianta B – spuštění PHP skriptu (pokud tarif dovolí PHP v cronu)

```
php /cesta/k/webu/bin/daily.php
```

## Kontrola

- Úspěšné volání adresy vrátí text `OK` a počty provedených změn.
- V administraci (Přehled) je vidět, kdy úklid naposledy proběhl. Pokud neproběhl déle než
  2 dny, administrace to červeně hlásí.
- Lokálně si úklid vyzkoušíte příkazem `php bin/daily.php`.
