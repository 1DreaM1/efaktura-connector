# Referenčné číselníky

Tieto dáta poskytujú lookup pre štandardné kódy v UBL dokladoch. Reader zachová aj kódy, ktoré v číselníku nepozná; `PeppolBillingDraftRules` používa členstvo v zoznamoch pri kontrole draftu.

Hostiteľská aplikácia môže číselníky ponúknuť ako výberové možnosti cez `CodeListRegistryInterface`; použitie a stabilné identifikátory opisuje [UBL sprievodca](../../docs/wiki/UBL-Documents.md#číselné-hodnoty-a-kódy). Tento súbor zachytáva pôvod dát, verzie a pravidlá ich aktualizácie.

## ISO 4217

Súbor [`iso-4217-currencies.json`](iso-4217-currencies.json) obsahuje kódy mien a ich anglické názvy podľa [číselníka Peppol BIS Billing](https://test-docs.peppol.eu/poacc/upgrade-3/2026-Q4-MR/codelist/ISO4217/). Vychádza z ISO 4217 číselníka vo vydaní Peppol **2026-Q4-MR**; uvedená verzia ISO je `2018-01-01`. Názov súboru a verzia sú definované v `CodeListResources::ISO_4217_CURRENCIES_FILE`, `CodeListResources::ISO_4217_CURRENCIES_VERSION` a `CodeListResources::ISO_4217_CURRENCIES_RELEASE`. `CurrencyCodeList` načítava dáta z `resources/code-lists/` a poskytuje kódy, názvy, možnosti výberu a `resolve()`, ktorý vráti `CurrencyCode|null`. Pri obnove aktualizuj dáta aj metadata spoločne.

## Electronic Address Scheme (EAS)

`electronic-address-schemes.json` obsahuje kódy CEF Electronic Address Scheme používané v `cbc:EndpointID/@schemeID` podľa [číselníka Peppol BIS Billing](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/codelist/eas/). Zoznam pochádza z vydania **2026-Q4-MR** a jeho verzia je definovaná v `CodeListResources::ELECTRONIC_ADDRESS_SCHEMES_FILE` a `CodeListResources::ELECTRONIC_ADDRESS_SCHEMES_RELEASE`. `ElectronicAddressSchemeCodeList` kontroluje presnú zhodu kódu; `PeppolBillingDraftRules` tým vykonáva pravidlo PEPPOL-EN16931-CL008 pre adresy dodávateľa a odberateľa. Test porovnáva pribalené kódy so zoznamom `$eaid` v pripnutom Peppol Schematrone.

## Peppol Participant Identifier Schemes

`ParticipantIdentifierSchemeCodeList` v `src/Domain/EFaktura/CodeList/` obsahuje iba aktívne a registrovateľné schémy z OpenPeppol eDEC vydania **9.7** ([oficiálny zoznam](https://docs.peppol.eu/edelivery/codelists/)). `OutboundDocumentPayload` aj SAPI/Gateway participant requesty vytvorené cez `ApiRequest::forParticipant()` vyžadujú schému z tohto zoznamu. Integrator API request DTO prijímajú participant ID ako neprázdne reťazce a tento číselník na nich neuplatňujú. `Participant::parse()` kontroluje iba syntax identifikátora a nepotvrdzuje registráciu konkrétnej hodnoty. Zoznam je oddelený od EAS: participant schémy identifikujú transportné strany, kým EAS sa používa v UBL `EndpointID/@schemeID`. Zoznam OpenPeppol sa mení; pri jeho aktualizácii zosúlaďte pribalené kódy a `ParticipantIdentifierSchemeCodeList::VERSION`.

## UN/ECE Recommendation 20

Súbor [`unece-rec20-names.json`](unece-rec20-names.json) v tomto adresári obsahuje oficiálne kódy a anglické názvy merných jednotiek používaných v `@unitCode`. Ide o [UN/ECE Revision 11e v Peppol vydaní 2026-Q4-MR](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/codelist/UNECERec20/); kódy Recommendation 21 sú zahrnuté s prefixom `X` podľa Peppol. Súbor a verzia sú definované v `CodeListResources::UNECE_REC20_NAMES_FILE` a `CodeListResources::UNECE_REC20_VERSION`; vydanie Peppol je v `CodeListResources::UNECE_REC20_RELEASE`. `UnitCodeList` súbor načítava z adresára `resources/code-lists/` a poskytuje iba presné kanonické kódy. `resolve()` vráti `UnitCode` iba pri presnej zhode s kódom v `units`; lokálne názvy, malé písmená a okolité medzery neprekladá ani nenormalizuje. Pri aktualizácii zachovaj oficiálne kódy a názvy a zosúlaď verziu so zdrojovým vydaním.

Súbor [`unit-aliases.json`](unit-aliases.json) je od oficiálneho číselníka oddelený a **neoficiálny**: mapuje bežné slovenské a české skratky (`ks`, `kg`, `hod`, …) na kódy UN/ECE pre voliteľné `UnitCodeList::fromLabel()` a `UnitCodeList::unit()`. Kľúče sú v normalizovanom tvare (malé písmená, bez diakritiky, bez koncovej bodky) a každá hodnota musí byť kód zo súboru `unece-rec20-names.json`; test `UnitCodeListAliasesTest` to overuje, vrátane zhody s presnými kódmi (alias nesmie zakryť iný kód, preto napríklad chýba `kw`). Súbor sa nezamieňa s oficiálnymi dátami a pri aktualizácii číselníka ho netreba upravovať, pokiaľ test nezlyhá. Nový alias pridaj len vtedy, keď je mapovanie jednoznačné.

## ISO 3166-1 a kódy Peppol

`CountryCodeList` v `Infrastructure\Ubl\CodeList` poskytuje krajiny ISO 3166-1 a špeciálne kódy používané Peppolom vrátane `1A` a `XI`. Zoznam je v PHP triede a jeho Peppol vydanie je v `CountryCodeList::PEPPOL_RELEASE`. `resolve()` vracia kanonický `CountryCode|null`; `name()` a `options()` slúžia na zobrazenie kódov. Pri úprave zoznamu zachovaj podporu špeciálnych Peppol kódov a uprav cielený test.

## Peppol BIS Billing 3.0 — 2026-Q4-MR

Súbor [`peppol-billing-3.0-2026-Q4-MR.json`](peppol-billing-3.0-2026-Q4-MR.json) obsahuje ďalšie zoznamy použité v Peppol BIS Billing 3.0. Kódy a názvy sú prevzaté z oficiálnych podstránok [zoznamov kódov vydania 2026-Q4-MR](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/codelist/). Súbor uvádza pri každom zozname identifikátor, prípadnú zdrojovú verziu, URL a všetky publikované položky.

| Identifikátor v `PeppolBillingCodeList` | `CodeListIdentifiers` | Zdrojový číselník | Použitie |
| --- | --- | --- | --- |
| `ICD` | `PEPPOL_BILLING_ICD` | ISO 6523 ICD | Schémy `schemeID` pre identifikátory strán a položiek |
| `VAT_CATEGORY` | `PEPPOL_BILLING_VAT_CATEGORY` | UNCL 5305 subset | Kategórie DPH; obsahuje aj `AE`, `G` a `K` používané v slovenských daňových scenároch |
| `ITEM_TYPE` | `PEPPOL_BILLING_ITEM_TYPE` | UNCL 7143 | Schémy klasifikácie položiek |
| `INVOICE_TYPE` | `PEPPOL_BILLING_INVOICE_TYPE` | UNCL 1001 Invoice subset | Kódy typu faktúry |
| `CREDIT_NOTE_TYPE` | `PEPPOL_BILLING_CREDIT_NOTE_TYPE` | UNCL 1001 CreditNote subset | Kódy typu dobropisu |
| `INVOICED_OBJECT_SCHEME` | `PEPPOL_BILLING_INVOICED_OBJECT_SCHEME` | UNCL 1153 | Schémy identifikátora fakturovaného objektu |
| `VAT_DATE_CODE` | `PEPPOL_BILLING_VAT_DATE_CODE` | UNCL 2005 subset | Kódy dátumu/obdobia DPH |
| `PAYMENT_MEANS` | `PEPPOL_BILLING_PAYMENT_MEANS` | UNCL 4461 | Kódy spôsobu platby |
| `ALLOWANCE_REASON` | `PEPPOL_BILLING_ALLOWANCE_REASON` | UNCL 5189 subset | Kódy dôvodu zľavy |
| `CHARGE_REASON` | `PEPPOL_BILLING_CHARGE_REASON` | UNCL 7161 | Kódy dôvodu poplatku |
| `MIME` | `PEPPOL_BILLING_MIME` | IANA subset | Hodnoty `mimeCode` príloh |
| `SEPA` | `PEPPOL_BILLING_SEPA` | OpenPeppol SEPA | Schéma identifikátora veriteľa SEPA |
| `VATEX` | `PEPPOL_BILLING_VATEX` | CEF VATEX | Kódy dôvodu oslobodenia alebo osobitného režimu DPH |

`PeppolBillingCodeList` poskytuje `all()`, `contains()`, `isCanonical()`, `resolve()`, `name()` a `options()` pre každý uvedený identifikátor, ako aj `release()`, `version()` a `source()`. Porovnávanie hodnôt je presné; resolver ich nenormalizuje. Rozsah zodpovedá úplným číselníkom Billing 3.0, nie iba hodnotám povoleným pre Slovensko. Národné obmedzenia a vzťahy medzi kódmi naďalej kontrolujú pravidlá Peppol Schematronu. `DocumentTypeCode`, `CreditNoteTypeCode` a `TaxCategoryCode` zároveň poskytujú pomenované konštanty; pre úplný zoznam vrátane názvov použite `PeppolBillingCodeList`.

`ParticipantIdentifierSchemeCodeList` zostáva samostatný zoznam pre transportné identifikátory účastníkov eDelivery. Nie je náhradou za ISO 6523 ICD ani za EAS používané v `cbc:EndpointID/@schemeID`.
