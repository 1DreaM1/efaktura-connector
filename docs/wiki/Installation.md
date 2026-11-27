# Inštalácia a požiadavky

<details>
<summary>Obsah stránky</summary>

- [Požiadavky](#požiadavky)
- [Inštalácia cez Composer](#inštalácia-cez-composer)
- [Vývojová verzia z vetvy main](#vývojová-verzia-z-vetvy-main)
- [Po inštalácii](#po-inštalácii)
- [Autoload](#autoload)
- [Overenie balíka](#overenie-balíka)
- [Validačné zdroje](#validačné-zdroje)

</details>

## Požiadavky

- PHP 7.4 alebo novšie.
- Ak sa používa Laravel bridge, Composer voliteľne navrhuje `illuminate/support` v rozsahu `^5.6 || ^8.83 || ^9.0 || ^10.0 || ^11.0 || ^12.0 || ^13.0`. PHP verzia musí spĺňať požiadavky zvolenej verzie Laravelu. CI testuje bridge na Laravel 5.6, 8 a 13; jadro balíka testuje od PHP 7.4.
- PHP rozšírenia: `curl`, `ctype`, `dom`, `hash`, `json`, `libxml` a `mbstring`.
- Java 11 alebo novšia a dostupné funkcie PHP `proc_open` a `flock`, ak aplikácia spúšťa lokálnu Schematron validáciu. `flock` a zapisovateľný temp adresár sú potrebné aj pri Laravel verzii alebo cache store bez atómových cache lockov.
- Composer na správu závislostí.

Java sa hľadá najprv v `JAVA_HOME/bin` (na Windows `java.exe`) a potom v `PATH` procesu PHP. Pre Apache alebo IIS nastavte premenné prostredia pre samotný worker; konfigurácia interaktívneho shellu nemusí byť dostupná webovému procesu.

## Inštalácia cez Composer

Balík je zverejnený na [Packagist](https://packagist.org/packages/dream1/efaktura-connector). Nainštalujte ho v koreňovom adresári hostiteľskej aplikácie:

```sh
composer require dream1/efaktura-connector
```

## Vývojová verzia z vetvy main

Nevydanú verziu z vetvy `main` môžete vyžiadať explicitne; Packagist ju ponúka ako `dev-main`:

```sh
composer require dream1/efaktura-connector:dev-main
```

Explicitná požiadavka `:dev-main` funguje aj pri `minimum-stability` nastavenom na `stable`. Vývojová verzia nemá záruku kompatibility; v produkcii používajte vydané verzie.

## Po inštalácii

**Laravel.** Service provider sa zaregistruje sám cez Composer package discovery. Ďalej:

1. Zverejnite konfiguráciu: `php artisan vendor:publish --tag=efaktura-connector-config`. Súbor vznikne v `config/efaktura-connector.php` vašej aplikácie a práve ten upravujte (nie súbor vo `vendor/`).
2. Do `.env` doplňte `EFAKTURA_BASE_URL`, `EFAKTURA_CLIENT_ID`, `EFAKTURA_CLIENT_SECRET` a `EFAKTURA_CLIENT_SCOPE` podľa údajov od poskytovateľa. Kompletný zoznam premenných je v [Laravel bridge](Laravel#konfigurácia), vysvetlenie, kde a ako hodnoty meniť, v [Kde a ako upraviť hodnoty](Laravel#kde-a-ako-upraviť-hodnoty).
3. Po zmene spustite `php artisan config:clear` (pri `config:cache` ho spustite znova) a overte nastavenie príkazom `php artisan efaktura:check`.

**Bez Laravelu.** Konfiguráciu zostavíte v kóde cez `SapiClientsConfiguration` a klientov cez `ConnectorFactory`; postup je v [Konfigurácii](Configuration) a v [Verejnom PHP API](Public-API#samostatné-zostavenie-sapi-klienta). Poverenia čítajte z vlastného úložiska tajomstiev alebo premenných prostredia.

## Autoload

Composer registruje PSR-4 namespace:

```text
DreaM1\EfakturaConnector\        → src/
DreaM1\EfakturaConnector\Laravel\ → integrations/laravel/src/
```

Composer deklaruje pre voliteľný Laravel bridge Laravel 5.6 a 8.83–13; balík vyžaduje PHP 7.4 alebo novšie a PHP musí spĺňať aj požiadavky zvolenej verzie Laravelu. CI overuje bridge na Laravel 5.6/PHP 7.4 a Laravel 8/PHP 7.4, ako aj Laravel 13/PHP 8.4. Laravel načíta service provider cez Composer package discovery. Jadro nemá povinnú závislosť od Laravelu.

Konfiguráciu bridge zverejníte príkazom `php artisan vendor:publish --tag=efaktura-connector-config`. Pôvodný tag `config` zostáva dostupný kvôli spätnej kompatibilite.

## Overenie balíka

V samostatnom checkoute nainštalujte vývojové závislosti a spustite PHPUnit:

```sh
composer install
composer test
```

Schematron testy, ktoré spúšťajú pribalený Saxon, vyžadujú Javu 11+, povolené `proc_open` a `flock`. Bez toho možno použiť XSD validáciu a ostatné časti konektora nezávisle.

## Validačné zdroje

UBL schémy, CEN/EN 16931 a Peppol pravidlá, Saxon HE aj licenčné oznámenia sú súčasťou balíka v `resources/validation`. Hostiteľ ich nemusí kopírovať do vlastného adresára `resources`. Verzie a cesty sprístupňuje `ValidationResources`.

<details>
<summary>Súvisiace návody</summary>

- [Konfigurácia](Configuration)
- [Laravel bridge](Laravel)

</details>
