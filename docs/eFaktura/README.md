# Technický prehľad eFaktúry

Táto dokumentácia opisuje implementáciu API spoločnosti datahosting.sk pre eFaktúru: rozhrania SAPI-SK, Peppol Gateway a vybrané operácie Integrator API. Zhrňuje aj validačné artefakty používané knižnicou `dream1/efaktura-connector`. Konektor poskytuje technické rozhranie; hostiteľská aplikácia riadi prípravu, účtovný workflow, trvalé uloženie, používateľské správy aj doručenie webhookov.

Lokálny XSD alebo Schematron výsledok nie je sám osebe potvrdením splnenia všetkých povinností podľa zákona o DPH. Knižnica neurčuje, či konkrétna transakcia spadá do zákonnej povinnosti alebo výnimky, a neposkytuje účtovný workflow, uchovávanie dokladov ani oznamovanie údajov finančnej správe. Tieto požiadavky treba posúdiť podľa [zákona č. 385/2025 Z. z.](https://www.slov-lex.sk/ezbierky/pravne-predpisy/SK/ZZ/2025/385) a aktuálnych [informácií Finančnej správy](https://www.financnasprava.sk/_img/pfsedit/Dokumenty_PFS/Zverejnovanie_dok/Aktualne/DPH/2026/2026.11.20_009_DPH_2026_IM_FAQ_eFakt.pdf) v kontexte konkrétneho nasadenia a doručovacej služby.

## Rozhrania poskytovateľa

| Rozhranie | Použitie v konektore | Typické cesty |
| --- | --- | --- |
| SAPI-SK | OAuth token, odoslanie jedného dokladu, inbox, detail a technické potvrdenie príjmu, stav tokenu. | `/sapi/auth/token`, `/sapi/document/send`, `/sapi/document/receive...` |
| Peppol Gateway v1 | 12 operácií: discovery, vzdialená validácia, batch odosielanie, profil participanta, stav odoslania, Message Level Status a register webhookov. | `/v1/discovery/receives`, `/v1/validate-document`, `/v1/send-documents`, `/v1/sent-documents/...`, `/v1/webhooks` |
| Integrator API | 10 operácií: deväť operácií na správu OAuth klientov a participantov plus odoslanie PDS subjects. Správa klientov používa `X-Integrator-Token`; PDS používa podpis `X-PDS-Secret`. | `/v1/integrators/{integratorId}/clients...`, `/v1/integrators/{integratorId}/participants...`, `/v1/integrators/{integratorId}/pds/subjects` |

Gateway používa Bearer access token získaný cez SAPI OAuth client credentials. Participant ID sa odovzdáva pri participant-scoped volaní, nie ako globálna identita v konfigurácii.

SAPI `POST /sapi/document/send` a Gateway `POST /v1/validate-document` zdieľajú formát požiadavky s metadátami dokladu a UBL XML. Odpoveď odoslania obsahuje ID u poskytovateľa a stav `ACCEPTED` alebo `REJECTED`; iba `ACCEPTED` potvrdzuje technické prijatie. Následný stav prenosu sa číta samostatne cez Gateway tracking alebo webhook. Technické acknowledge prijatého dokladu nie je jeho obchodným schválením.

Konektor podporuje Gateway batch odosielanie cez `POST /v1/send-documents`; výsledok obsahuje súhrnný počet a ID dokladov, nie samostatné výsledky jednotlivých položiek. Hostiteľská aplikácia naďalej vlastní HTTP route pre webhooky. Peppol doručenia spracúva `PeppolWebhookEventFactory`, ktorá vyžaduje explicitný `PeppolWebhookSignatureVerifierInterface`. Knižnica obsahuje `PeppolWebhookHmacSha256SignatureVerifier`, ktorý overuje HMAC-SHA256 nad `{t}.{x-sapi-webhook-id}.{rawBody}` podľa testu webhooku potvrdeného DataHosting. PDS subjects sa odosielajú cez `IntegratorAccountClientInterface::submitPdsSubjects()` ako podpísaná požiadavka Integrator API. Úplný zoznam operácií a mapovaní je v [API referencii](../wiki/API-Reference.md) a v [návode na webhooky](../wiki/Tracking-and-Webhooks.md).

## UBL a validačné schémy

Dokumentový formát je UBL 2.1, s podporou koreňových elementov `Invoice` a `CreditNote`. Writer vytvára XML z typovaných draftov; reader vytvára model hlavičky, strán, riadkov a daní. UBL čítanie ani zápis automaticky nespúšťajú obchodnú validáciu.

| Vrstva | Lokálny artefakt alebo služba | Čo kontroluje |
| --- | --- | --- |
| XML bezpečnosť | `UblXmlDocumentLoader`, `Support\Xml\SecureXmlDocumentParser` | Bezpečné parsovanie bez sieťového prístupu, limit veľkosti a podporované UBL korene. |
| XML štruktúra | OASIS UBL 2.1 XSD, `UblSchemaValidator` | Povinnosť, typ a poradie XML elementov podľa UBL schémy. |
| Fakturačné pravidlá | CEN/EN 16931 Schematron 1.3.17 a oficiálny OpenPeppol BIS Billing 3.0.22 z vydania `2026-Q4-MR`, `PeppolSchematronValidator` | Obchodné pravidlá faktúry, Peppol profilu a slovenské pravidlá `SK-R-001` až `SK-R-007`; CEN a Peppol verzie sa spravujú nezávisle. |
| Zložená lokálna kontrola | `LocalUblValidator` | Spustí nakonfigurované validačné kroky v poradí zo zoznamu; Laravel bridge predvolene používa UBL XSD, doplnkovú kontrolu BR-CO-25 a Peppol Schematron. Q4-MR Schematron obsahuje aj slovenské pravidlá. Výsledky vráti genericky podľa ID kroku. |
| Vzdialená kontrola | Peppol Gateway `/v1/validate-document` | Kontrola podľa konfigurácie poskytovateľa; ide o samostatné sieťové volanie. |

Schematron pravidlá sa vykonávajú cez pribalený Saxon-HE 12.10; runtime vyžaduje Javu 11+ a dostupné PHP funkcie `proc_open` a `flock`. Lokálne pravidlá nemusia byť totožné s verziou pravidiel, ktorú používa Gateway. Detailné cesty, licencie a aktualizačný postup sú v [README validačných podkladov](../../resources/validation/README.md).

## Autoritatívne podklady

- [Lokálny kombinovaný OpenAPI snapshot](api/openapi.json) obsahuje SAPI-SK, Peppol Gateway aj samostatnú Integrator API skupinu. Prítomnosť operácie v snapshot-e sama neznamená, že ju knižnica implementuje.
- [SAPI-SK OpenAPI pre testovacie prostredie](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=SAPI-SK+API#/).
- [Peppol Gateway v1 OpenAPI pre testovacie prostredie](https://test-sapisk.datahosting.sk/swagger-ui/index.html?urls.primaryName=Peppol+Gateway+API+v1).
- [Integrator API OpenAPI pre testovacie prostredie](https://test-sapisk.datahosting.sk/v3/api-docs/integrator-api).

## Súvisiaca dokumentácia konektora

- [Tok dát a prehľad tried](../wiki/Data-Flow-and-Classes.md)
- [Prehľad API endpointov](../wiki/API-Reference.md)
- [Čítanie a zápis UBL](../wiki/UBL-Documents.md)
- [Lokálna validácia](../wiki/Validation.md)
- [Architektúra a hlavné toky](../wiki/Architecture-and-Testing.md)
