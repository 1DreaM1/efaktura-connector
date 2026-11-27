# Kontrolný zoznam vydania

Použi tento zoznam pri príprave verejného repozitára alebo distribučného balíka. Podmienky licencie musia zodpovedať právam, ktoré držiteľ práv poskytuje.

## Práva a licencia

- [ ] Urči držiteľa práv a over oprávnenie zverejniť zdrojový kód.
- [ ] Potvrď, že copyright označenie `DreaM1` zodpovedá držiteľovi práv; podľa potreby použi presný právny názov.
- [ ] Over potrebné súhlasy a licenčné práva od všetkých prispievateľov.
- [ ] Skontroluj, že `LICENSE` aj pole `license` v `composer.json` uvádzajú MIT.

## Materiály tretích strán

- [ ] Over práva a podmienky pre OpenAPI podklady, UBL schémy, CEN/Peppol pravidlá, SchXslt, Saxon a pribalené Java knižnice.
- [ ] Zachovaj požadované licenčné texty, autorské oznámenia a odkazy na pôvod všetkých distribuovaných artefaktov.
- [ ] Skontroluj, že licencia vlastného kódu nemení licencie ani práva k cudzím artefaktom.

## Obsah repozitára a tajomstvá

- [ ] Skontroluj sledované súbory aj Git históriu na prístupové údaje, súkromné kľúče, interné URL, osobné údaje a reálne faktúry.
- [ ] Over, že `.env`, lokálne konfigurácie a osobné súbory IDE nie sú sledované; `.gitignore` povoľuje `.env.example`.
- [ ] Skontroluj príklady a testovacie fixtures; používaj syntetické poverenia a dokumenty.
- [ ] Rozhodni, či majú byť `AGENTS.md`, `.codex/`, pracovné poznámky a interné materiály súčasťou verejného repozitára.
- [ ] Skontroluj výstup `composer archive` a over, že obsahuje runtime zdroje a vynecháva vývojové súbory podľa `.gitattributes`.

## Vydanie cez GitHub Actions

Workflow vytvorí vydanie až po úspechu povinných jobov. Pri merge do predvolenej vetvy vyberie verziu podľa labelu pridruženého zlúčeného PR:

- [ ] Pri PR určenom na vydanie nastav presne jeden label `release:patch`, `release:minor` alebo `release:major`.
- [ ] Ak jeden push zahŕňa viac zlúčených PR, over, že ich labely zodpovedajú zmenám; workflow vyberie najvyšší požadovaný bump. Bez release labelu vydanie nevytvorí.
- [ ] Automatické vydanie zvýši najnovší tag v tvare `vMAJOR.MINOR.PATCH`; skontroluj, že predchádzajúce tagy používajú tento formát.
- [ ] Pri ručnom vydaní pushni existujúci tag začínajúci na `v`; workflow vytvorí GitHub Release s vygenerovanými poznámkami.

## Kvalita a distribúcia

- [ ] Skontroluj staged aj unstaged rozdiely a zahrň iba zámerné, dokončené zmeny.
- [ ] Porovnaj verejné API s posledným tagom.
- [ ] Skontroluj label `release:*` voči výsledku BC kontroly.
- [ ] Zosúlaď sekciu `Unreleased` v `CHANGELOG.md` s obsahom vydania.
- [ ] Zosúlaď verziu balíka, README, inštalačné pokyny a Composer metadata.
- [ ] Over, že Composer balík obsahuje potrebné runtime zdroje a vynecháva obsah, ktorý do distribúcie nepatrí.
- [ ] Spusti `composer validate --strict`, `composer check-style`, `composer check-format` a `composer test`.
- [ ] Over Laravel bridge v podporovanom prostredí a jadro na podporovaných verziách PHP podľa CI matice.
- [ ] Spusti validačné testy závislé od Javy s požadovanými Java/PHP funkciami.
- [ ] Skontroluj CI workflow a výsledok všetkých povinných jobov.
- [ ] Nainštaluj Composer archív do čistej skúšobnej aplikácie a over autoload, konfiguráciu aj prístup k validačným zdrojom.
- [ ] Over obsah verejného repozitára a distribučného archívu.
