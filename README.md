# Konektor eFaktúr
[English summary](README.en.md)

Frameworkovo nezávislá PHP knižnica na integráciu so SAPI-SK, Peppol Gateway a Integrator API spoločnosti DataHosting. Balík poskytuje typované API operácie, samostatné SAPI OAuth a Integrator token klienty, HTTP transport a nástroje na čítanie, zápis a lokálnu validáciu UBL 2.1 dokladov.

Implementácia vychádza z testovacích [SAPI-SK API špecifikácií](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=SAPI-SK+API#/), [Peppol Gateway API v1](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=Peppol+Gateway+API+v1) a [Integrator API](https://test-sapisk.datahosting.sk/v3/api-docs/integrator-api). Prostredie poskytovateľa určuje konfigurácia aplikácie.

## Čo balík poskytuje

- SAPI a Peppol Gateway endpointy s OAuth autentifikáciou a samostatný Integrator API klient s `X-Integrator-Token`.
- Typované mapovanie Peppol webhook udalostí po overení cez hostiteľom dodaný podpisový verifikátor.
- Typované Core kontrakty a Domain hodnoty na hranici hostiteľskej aplikácie.
- Čítanie a zápis UBL Invoice a CreditNote; samostatne spustiteľné XSD a Schematron kontroly.
- Pribalené číselníky krajín, mien, jednotiek, identifikátorov a Peppol Billing, dostupné cez typované vyhľadávanie a `CodeListRegistryInterface`. Voliteľné aliasy jednotiek (`ks`, `kg`, `hod`) prevedie `UnitCodeList::unit()` na kódy UN/ECE.
- `FailureClassifier` zaradí zlyhanie do kategórie a určí, či je opakovateľné a či je výsledok neznámy; offline `TestingFakeSapiClient` slúži na testy hostiteľskej aplikácie bez poskytovateľa.
- Voliteľný Laravel bridge s pomenovanými OAuth klientmi a resolverom poverení z modelu zákazníka v adresári `integrations/laravel`.
- Konfiguráciu bridge zverejníte príkazom `php artisan vendor:publish --tag=efaktura-connector-config`.

Hostiteľská aplikácia vlastní obchodné workflow, databázu, plánovanie a HTTP prijímanie webhookov. Pri Peppol udalostiach vyberá uložený signing secret a dodáva podpisový verifikátor podľa potvrdeného kontraktu poskytovateľa; konektor ho pred parsovaním zavolá cez `PeppolWebhookEventFactory`. Konektor odoslanie dokumentu automaticky lokálne ani vzdialene nevaliduje.

## Požiadavky

- PHP 7.4 alebo novšie.
- Rozšírenia PHP: curl, ctype, dom, hash, json, libxml a mbstring.
- Java 11+, `proc_open` a `flock` pre lokálnu Schematron validáciu; `flock` aj pre lokálne Laravel locky, ak verzia alebo cache store nepodporuje atómové zámky.

## Inštalácia

Po zverejnení balíka na [Packagist](https://packagist.org/packages/dream1/efaktura-connector) ho nainštalujte v koreňovom adresári Composer projektu:

~~~sh
composer require dream1/efaktura-connector
~~~

## Použitie

Participant sa odovzdáva konkrétnej operácii; nie je súčasťou globálnej konfigurácie:

~~~php
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

// $sapiClient implementuje SapiClientInterface a je zostavený hostiteľskou aplikáciou.
$participant = Participant::parse('0245:1234567890');
$page = $sapiClient->receivedDocumentProvider()->getInboxPage(
    new ListReceivedDocumentsRequest($participant, null, PageSize::fromInt(50))
);
~~~

Ďalšie príklady konfigurácie, odoslania, príjmu a UBL práce sú v [návode na odoslanie](docs/wiki/Sending-Documents.md), [návode na príjem](docs/wiki/Receiving-Documents.md), [konfigurácii](docs/wiki/Configuration.md) a [UBL návode](docs/wiki/UBL-Documents.md).

Správa integrátorových OAuth klientov a participantov je popísaná v [návode Integrator API](docs/wiki/Integrator-API.md).

## Správanie a hranice

- API request predvolene povoľuje retry iba pre GET. Odoslanie dokumentu si zachováva explicitný idempotency key a automaticky sa neopakuje.
- UBL reader XML číta, ale nevykonáva obchodnú validáciu. Writer serializuje dodaný draft a sám nevyberá obchodné hodnoty.
- Lokálna UBL validácia, vzdialená kontrola, odoslanie, príjem, tracking a registrácia webhookov sú samostatné operácie.
- Knižnica neposkytuje HTTP endpoint ani predvolený podpisový algoritmus pre Peppol webhooky; `PeppolWebhookEventFactory` pred parsovaním volá injektovaný podpisový verifikátor.
- Predvolený cURL transport obmedzuje telo odpovede na 16 MiB; limit možno upraviť runtime konfiguráciou.

## Architektúra

~~~text
src/Domain                 hodnoty a UBL modely
src/Core                   kontrakty, konfigurácia, výsledky a výnimky
src/Infrastructure         API, transport, adaptéry a validácia
src/Testing                offline fake klient pre testy hostiteľa
integrations/laravel        voliteľné Laravel bindings
resources                  UBL schémy, Schematron pravidlá a code lists
tests                      architecture, unit a integračné testy
~~~

Jadro nezávisí od Laravelu ani od HTTP klienta. Podrobný opis hraníc modulov a hlavných tokov je v [architektúre a testovaní](docs/wiki/Architecture-and-Testing.md).

## Testy a štýl

~~~sh
composer test
composer check-style
composer check-format
composer analyse
~~~

Laravel bridge testy pre Laravel 5.6/PHP 7.4 a Laravel 13/PHP 8.4 majú samostatný postup; Laravel 8 sa testuje v hlavnom PHP 7.4 prostredí: [tools/laravel-bridge/README.md](tools/laravel-bridge/README.md).

## Dokumentácia

- [Domovská stránka wiki](docs/wiki/Home.md)
- [Verejné PHP API](docs/wiki/Public-API.md)
- [Verzovanie a kompatibilita](docs/wiki/Versioning-and-Compatibility.md)
- [Referencia API](docs/wiki/API-Reference.md)
- [Integrator API](docs/wiki/Integrator-API.md)
- [Transport a autentifikácia](docs/wiki/Transport-and-Authentication.md)
- [Validácia](docs/wiki/Validation.md)
- [Tracking a webhooky](docs/wiki/Tracking-and-Webhooks.md)
- [OpenAPI snapshot](docs/eFaktura/api/openapi.json)
- [Bezpečnostné hlásenia](SECURITY.md)
- [Licencia MIT](LICENSE)

Validačné artefakty tretích strán a ich licencie sú uvedené v [resources/validation/README.md](resources/validation/README.md).
