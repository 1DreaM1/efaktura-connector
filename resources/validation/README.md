# Lokálne validačné podklady e-faktúr

Tieto validačné súbory sú súčasťou Composer balíka `dream1/efaktura-connector`. `ValidationResources::directory()` a `ValidationResources::path()` určujú cestu ku koreňu zdrojov relatívne k nainštalovanému balíku. `ValidationResources::schematronStylesheets()` vráti použité XSLT v poradí vykonania. CEN a Peppol pravidlá sú uložené v samostatných verziovaných priečinkoch, preto ich možno aktualizovať nezávisle. Hostiteľská aplikácia nemusí súbory kopírovať do vlastného `resources/`.

## Obsah a správanie

Konektor poskytuje lokálne validátory; hostiteľ určuje, kedy ich zavolá v toku prípravy odchádzajúceho dokladu. Pri ich použití sa XML kontroluje:

- podľa UBL 2.1 Invoice alebo CreditNote XSD od OASIS vrátane importovaných spoločných schém;
- podľa CEN/EN 16931 UBL Schematron 1.3.17;
- podľa OpenPeppol Peppol BIS Billing UBL Schematron 3.0.22 z vydania `2026-Q4-MR`.

K 23. 11. 2026 sú pribalené [CEN EN 16931 artefakty](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17) 1.3.17 (vydané 12. 10. 2026) a úplný oficiálny UBL Schematron [OpenPeppol BIS Billing 3.0.22](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/) z vydania `2026-Q4-MR`, publikovaného 23. 11. 2026 a povinného od 24. 2. 2027. Peppol vydanie uvádza svoje EN 16931 artefakty 1.3.16; konektor nezávisle spúšťa vybraný CEN balík 1.3.17. Zdrojový Schematron je pripnutý v `peppol-3.0.22-2026-Q4-MR/` a jeho SHA-256 je `907FEAE87454F181CF364DA757A4E33D7C48AD9B72EF316601B364696524B230`. Balík používa finálnu špecifikáciu [OASIS UBL 2.1](https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html). Pri budúcej zmene overte nové upstream vydania; tento dátum nie je automatickou zárukou kompatibility vzdialeného Gateway validátora.

Schematron zdroje sú preložené do XSLT 2.0 cez SchXslt 1.10.1 a vykonáva ich Saxon-HE 12.10. Parser zachováva failed-assert záznamy so závažnosťou error, warning a info; chýbajúci alebo neznámy príznak považuje za error. V zloženom `LocalUblValidationResult` upozornenia a informačné nálezy samy osebe neblokujú `isValid()`, ale vynechané nálezy áno, pretože ich závažnosť nie je známa. Hostiteľská aplikácia môže navyše zavolať validačnú službu Peppol Gateway, ktorej pravidlá môžu mať inú verziu než lokálne súbory.

Oficiálny Peppol Schematron pre `2026-Q4-MR` obsahuje slovenské pravidlá `SK-R-001` až `SK-R-007`, ktoré kontroluje predvolený Laravel pipeline. Samostatný PHP krok `PeppolSlovakBillingValidationStage` je dostupný pre vlastné pipeline, ktoré tieto pravidlá v Schematrone nespúšťajú. Pravidlá sú publikované v [oficiálnom UBL balíku](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/rules/ubl-peppol/) a zmeny vydania opisujú [poznámky k vydaniu](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/release-notes/).

## Požiadavky pri spustení

Proces PHP potrebuje povolené funkcie `proc_open` a `flock` a Java 11 alebo novšiu. `SaxonSchematronRunner` najprv použije `JAVA_HOME/bin/java` (na Windows `java.exe`), ak súbor existuje, a inak hľadá `java` cez `PATH` procesu PHP. Neprístupný pracovný adresár sa odmietne ešte pred spustením procesu. Pri zlyhaní transformácie sa do technickej výnimky pridá obmedzený výpis zo stderr; jeho dočasný súbor sa odstráni spolu so vstupom a reportmi. XML, reporty, chybový výstup a súborové zámky validačných slotov sa ukladajú do systémového temp adresára. Validácia nevykonáva sieťové požiadavky. Zabezpečte, aby mal temp adresár obmedzený prístup a podporoval súborové zámky.

## Aktualizácia Peppol/CEN pravidiel

Aktualizuj zdrojové `.sch` súbory, skompilované `.xsl`, verziu použitú validátorom, licencie a testy ako jeden kontrolovaný celok. CEN a Peppol vydania sa môžu meniť nezávisle. Neprepisuj priečinok aktuálnej ani historickej verzie: pre každú zmenenú sadu vytvor nový priečinok `cen-<verzia>` alebo `peppol-<verzia>-<vydanie>`, aby bola zmena dohľadateľná a vratná.

1. **Vyber vydania.** Zaznamenaj presnú verziu CEN/EN 16931 validačných artefaktov aj identifikátor vydania Peppol a over, že sťahuješ správny oficiálny UBL `.sch` artefakt. Over podporu používanej Peppol BIS Billing verzie a kompatibilitu s testovacou validačnou službou poskytovateľa.

2. **Pridaj zdrojové `.sch` súbory.** CEN súbor patrí do `resources/validation/cen-<verzia>/CEN-EN16931-UBL.sch`; Peppol súbor patrí do `resources/validation/peppol-<verzia>-<vydanie>/PEPPOL-EN16931-UBL.sch`. Zachovaj celý oficiálny zdrojový súbor a zaznamenaj jeho SHA-256. Zdrojom pravdy sú `.sch` súbory; `.xsl` ručne neupravuj.

3. **Skompiluj XSLT pripnutým SchXslt.** Použi SchXslt 1.10.1 z jeho [stránky vydaní](https://codeberg.org/SchXslt/schxslt/releases) a priložený Saxon. Z koreňa samostatného repozitára konektora spusti PowerShell:

   ```powershell
   $packageRoot = (Resolve-Path '.').Path
   $validation = Join-Path $packageRoot 'resources/validation'
   $saxon = Join-Path $validation 'saxon-he-12.10'
   $classpath = "$(Join-Path $saxon 'saxon-he-12.10.jar');$(Join-Path $saxon 'lib/*')"
   $rules = Join-Path $validation 'cen-1.3.17'
   $compiler = 'C:\tools\schxslt-1.10.1\schxslt-1.10.1\2.0\pipeline-for-svrl.xsl'

   java -cp $classpath net.sf.saxon.Transform `
       "-s:$(Join-Path $rules 'CEN-EN16931-UBL.sch')" `
       "-xsl:$compiler" `
       "-o:$(Join-Path $rules 'CEN-EN16931-UBL.xsl')"

   $rules = Join-Path $validation 'peppol-3.0.22-2026-Q4-MR'
   java -cp $classpath net.sf.saxon.Transform `
       "-s:$(Join-Path $rules 'PEPPOL-EN16931-UBL.sch')" `
       "-xsl:$compiler" `
       "-o:$(Join-Path $rules 'PEPPOL-EN16931-UBL.xsl')"
   ```

   Ak sa zmení verzia SchXslt alebo Saxon, aktualizuj ju samostatne a spolu s ňou príslušné licencie a oznámenia.

4. **Vyber nové verzie v konektore.** V `src/Infrastructure/EFaktura/Validation/ValidationResources.php` aktualizuj `CEN_RULES_VERSION` alebo `PEPPOL_RULES_VERSION` a pri Peppol aj `PEPPOL_RULES_RELEASE`. `RULESET_DIRECTORIES` musí ukazovať každú sadu na jej zodpovedajúci verziovaný priečinok. Ak sa mení Saxon, uprav `SAXON_VERSION` na tom istom mieste.

5. **Zaznamenaj pôvod a licencie.** Aktualizuj tento súbor a `README.md` každého nového priečinka: uveď tagy/verzie, dátum alebo identifikátor zdrojov a použité nástroje. Zachovaj alebo vymeň licenčné texty a oznámenia podľa podmienok vydaní.

6. Over zmenu v tomto repozitári. Skontroluj, že obe používané XSLT súbory existujú, a spusti cielené PHPUnit testy z koreňa balíka:

   - vendor/bin/phpunit tests/Unit/Infrastructure/EFaktura/Validation/Schematron/PeppolSchematronValidatorTest.php
   - vendor/bin/phpunit tests/Unit/Infrastructure/EFaktura/Validation/Schematron/UblSchematronReportParserTest.php
   - vendor/bin/phpunit tests/Unit/Infrastructure/EFaktura/Validation/Schematron/SaxonSchematronRunnerTest.php
   - vendor/bin/phpunit tests/Unit/Infrastructure/EFaktura/Validation/Schematron/SaxonProcessExecutorTest.php

   Testy v `SaxonSchematronRunnerTest.php`, ktoré spúšťajú pribalený Saxon, potrebujú Javu 11+, Saxon súbory a povolené `proc_open` a `flock`; tieto prípady sa preskočia, keď chýba Java, Saxon alebo `proc_open`. `SaxonProcessExecutorTest.php` potrebuje povolené `proc_open` a `flock`. Celú testovaciu sadu možno spustiť príkazom `composer test`. Testy overujú konektor samostatne; testovacie doklady neposielaj do produkcie. Zmeny UBL XSD treba navyše overiť testom `tests/Unit/Infrastructure/EFaktura/Validation/Schema/UblSchemaValidatorTest.php`.

## Aktualizácia UBL XSD

UBL XSD sú nezávislé od CEN a Peppol Schematron pravidiel. Pri opravnom vydaní UBL 2.1 vymeň celý adresár `resources/validation/ubl-2.1/xsd/` za kompletný oficiálny balík OASIS. Zachovaj štruktúru `maindoc/` a `common/`; samotné hlavné XSD súbory nestačia, pretože importujú spoločné schémy.

Ak sa mení verzia UBL, zosúlaď schémy s `src/Core/Ubl/UblVersion.php`, generovaním UBL XML v hostiteľskej aplikácii a testami. Pred zmenou over kompatibilitu u poskytovateľa.

## Pôvod a licencie

- **UBL XSD:** [OASIS UBL 2.1](https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html). Autorské práva a oznámenia zostávajú v súboroch XSD.
- **CEN Schematron:** [CEN EN16931 validation artefacts, tag `validation-1.3.17`](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17); licenčný text EUPL 1.2 je v `cen-1.3.17/notices/EUPL-1.2-en.pdf`.
- **Peppol Schematron:** oficiálny [UBL zdroj pre vydanie `2026-Q4-MR`](https://test-docs.peppol.eu/poacc/billing/3.0/2026-Q4-MR/files/PEPPOL-EN16931-UBL.sch), publikovaný 23. 11. 2026; SHA-256 je zaznamenaný v `peppol-3.0.22-2026-Q4-MR/README.md`. Obsahuje zmeny pravidiel `2026-Q4-MR` bez lokálnych úprav; licenčný text EUPL 1.2 a licencia kompilátora sú v tomto priečinku.
- **Kompilátor Schematron:** [SchXslt 1.10.1](https://codeberg.org/SchXslt/schxslt); licenčný text je pri každej sade pravidiel v jej verziovanom priečinku.
- **Behové prostredie Schematron:** Saxon-HE 12.10 pod MPL 2.0. Licencia a oznámenia knižníc sú v `saxon-he-12.10/notices/`.

## Strojová evidencia a kontrola podkladov

`rules-manifest.json` eviduje verzie, zdroje, licencie a SHA-256 aktívnych validačných artefaktov, číselníkov a behového prostredia. Obsahuje aj mapovanie vlastných PHP pravidiel na ich zdrojové súbory a testy. Zoznam `.xsd`, `.sch`, `.xsl` a JSON číselníkov v spravovaných priečinkoch musí byť úplne pokrytý manifestom.

Kontrolu možno spustiť z koreňa repozitára:

```sh
php tools/check-validation-resources.php
```

Príkaz pracuje offline. Overí kontrolné súčty, prítomnosť súborov, súlad verzií v PHP konštantách a JSON metadátach a existenciu uvedených testov. Rovnaká kontrola sa spúšťa v CI pri PHP 7.4. Pri aktualizácii podkladov uprav manifest spolu s verziami, artefaktmi a testami.

Workflow `Upstream validation rules` každý pondelok a na ručné spustenie porovná vydania CEN, Peppol a OpenPeppol eDEC s manifestom. Kontroluje aj SHA-256 Schematronu z aktuálneho odkazu Peppol. Pri rozdiele vytvorí alebo aktualizuje jedno GitHub issue; pri nedostupnom zdroji workflow zlyhá a neoznačí stav ako aktuálny. Workflow pravidlá automaticky nesťahuje ani nemení.

Lokálnu kontrolu zdrojov možno spustiť cez `php tools/check-upstream-rule-releases.php`; potrebuje prístup na internet a rozšírenia PHP cURL a DOM.

## Kontrola validačných zdrojov

CI kontroluje integritu verzovaných validačných zdrojov cez `php tools/check-validation-resources.php`: overuje hash, prítomnosť artefaktov a ich verzie v manifeste. PHPUnit pokrýva Saxon runner, proces a SVRL parser. Samostatná PHP implementácia pravidiel `SK-R-001` až `SK-R-007` sa behaviorálne testuje v `tests/Unit/Infrastructure/EFaktura/Validation/PeppolSlovakBillingValidationStageTest.php`. Test `PeppolSchematronValidatorTest` spúšťa pribalené XSLT nad syntetickými UBL dokladmi a kontroluje slovenské pravidlá, jednu CEN požiadavku a jednu Peppol požiadavku. Ostatné CEN a OpenPeppol pravidlá zatiaľ nemajú samostatné behaviorálne regresné testy nad UBL dokladmi.

Pri aktualizácii `.sch` a `.xsl` súborov zachovaj verziovanie, licencie a kontrolné súčty podľa tohto návodu.
