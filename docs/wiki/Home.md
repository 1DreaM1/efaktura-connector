# Konektor eFaktúr

Frameworkovo nezávislá PHP knižnica na prácu s eFaktúrou spoločnosti datahosting.sk. Spája rozhrania SAPI-SK, Peppol Gateway a Integrator API, OAuth autentifikáciu, typované API služby, modely dokumentov a nástroje pre UBL 2.1.

Knižnica spracuje jednotlivé operácie. O tom, kedy doklad vytvoriť, overiť, odoslať alebo uložiť, rozhoduje hostiteľská aplikácia.

## Kde začať

| Ak chcete… | Pokračujte sem |
| --- | --- |
| Nainštalovať knižnicu a pripojiť sa k API | [Inštalácia](Installation) → [Konfigurácia](Configuration) → [Transport a autentifikácia](Transport-and-Authentication) |
| Pripraviť a odoslať faktúru | [UBL dokumenty](UBL-Documents) → [Validácia](Validation) → [Odosielanie](Sending-Documents) |
| Prijímať dokumenty a sledovať ich stav | [Príjem dokumentov](Receiving-Documents) → [Stav a webhooky](Tracking-and-Webhooks) |
| Rýchlo pochopiť celý tok na príkladoch | [Praktické scenáre a diagramy](Practical-Scenarios) |
| Použiť Laravel | [Laravel bridge](Laravel) |
| Spravovať OAuth klientov Integrator API | [Integrator API](Integrator-API) |
| Ukladať OAuth poverenia pri zákazníkoch | [OAuth poverenia v Laraveli](Tenant-Clients-in-Laravel) |
| Nájsť konkrétny endpoint alebo triedu | [Prehľad API](API-Reference) → [Tok dát a triedy](Data-Flow-and-Classes) |
| Pozrieť tok SAPI, Gateway, Integrator a PDS requestov | [Graf toku API requestov](Request-Flow) |
| Pochopiť vykonanie requestu alebo tok dokumentu | [HTTP transport](Transport-and-Authentication) · [UBL](UBL-Documents) · [Validácia](Validation) · [Odosielanie](Sending-Documents) · [Príjem](Receiving-Documents) |
| Riešiť chybu pri volaní | [Riešenie problémov](Troubleshooting) → [Výnimky a opakovanie](Errors-Retry-and-Idempotency) |

## Knižnica v skratke

| Časť | Čo v nej nájdete |
| --- | --- |
| `Core` a `Domain` | Verejné rozhrania, konfiguráciu, typované výsledky, doménové modely a hodnoty UBL. |
| `Infrastructure/EFaktura` | HTTP transport, SAPI OAuth, Integrator token transport, SAPI/Gateway/Integrator adaptéry, dokumenty a webhooky. |
| `Infrastructure/Ubl` a `Support/Xml` | Čítanie, zápis, validácia a číselníky UBL; bezpečné XML parsovanie zdieľané medzi Core a Infrastructure. |
| `integrations/laravel` | Voliteľný service provider a adaptéry pre Laravel. |
| `resources/validation` | Verziované XSD, Schematron, Saxon a licenčné oznámenia. |

## Rozhrania API

| Rozhranie | Použitie |
| --- | --- |
| SAPI-SK | OAuth token, odoslanie cez SAPI, doručená pošta, stiahnutie a technické potvrdenie príjmu dokumentu. |
| Peppol Gateway | Discovery, validácia, profil participanta, stav odoslania, MLS a správa webhookov. Používa Bearer token získaný cez SAPI. |
| Integrator API | Správa OAuth klientov a participant prístupov, stránkovaný zoznam participantov, aktivácie, mesačné súhrny a PDS subject submission. Používa samostatné hlavičky Integrator API a PDS požiadaviek. |

## Základné pravidlá

- SAPI potvrdzuje technické prijatie požiadavky; samo osebe nepotvrdzuje doručenie faktúry príjemcovi.
- Čítanie UBL a jeho validácia sú samostatné operácie.
- Knižnica neukladá dokumenty ani neurčuje workflow hostiteľskej aplikácie.
- Výnimky obsahujú technické správy. Používateľské texty a preklady patria do hostiteľskej aplikácie.

## API a technické podklady

Knižnica vychádza z testovacích [SAPI-SK API špecifikácií](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=SAPI-SK+API#/) a [Peppol Gateway API v1](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=Peppol+Gateway+API+v1). Testovací server prostredia nastavuje hostiteľská aplikácia v `base_url`.

Lokálny kombinovaný kontrakt API je v `docs/eFaktura/api/openapi.json`; testovacie OpenAPI zdroje sú dostupné pre [SAPI-SK](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=SAPI-SK+API#/), [Peppol Gateway v1](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=Peppol+Gateway+API+v1) a [Integrator API](https://test-sapisk.datahosting.sk/v3/api-docs/integrator-api). Ďalšie podklady o SAPI-SK, Peppol Gateway a validačných artefaktoch nájdete v [technickom prehľade eFaktúry](../eFaktura/README.md). Verzie validačných zdrojov a postup ich aktualizácie opisuje `resources/validation/README.md`.

> **Údržba wiki:** Zdrojové stránky sú v `docs/wiki/`. Pri zmene verejného API alebo konfigurácie aktualizujte príslušnú stránku spolu so zdrojovým kódom.
