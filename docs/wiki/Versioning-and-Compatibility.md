# Verzovanie a kompatibilita

## Prvé vydanie

Prvé verejné vydanie je `0.1.0`. V sérii `0.x` môže minor vydanie obsahovať zmenu spätnej kompatibility; patch vydanie má zachovať kompatibilitu. Pred prvým vydaním neexistoval Git tag ani zverejnená kompatibilná verzia.

## Verejné a interné API

Verejné API tvoria typy v `Core` (okrem `Core\Configuration\Internal`), `Domain` a `Laravel` a ostatné typy výslovne označené `@api`. Ostatné typy `Infrastructure` a `Support` sú interné. Hostiteľské aplikácie majú používať verejné kontrakty a hodnoty, nie interné implementácie.

## Zmeny spätnej kompatibility

Za zmenu spätnej kompatibility sa považuje odstránenie alebo premenovanie verejnej triedy, metódy, konfiguračného kľúča či premennej prostredia. Rovnako sa ňou považuje zmena názvu služby v Laravel kontajneri alebo názvu Artisan príkazu. Odstránenie alebo premenovanie konfiguračného kľúča je nekompatibilná zmena; pridanie nového kľúča ňou nie je. Neznáme konfiguračné kľúče sa naďalej odmietajú.

Za zmenu spätnej kompatibility sa nepovažuje aktualizácia validačných zdrojov Peppol/CEN ani číselníkov, pretože môže zmeniť výsledok validácie. Nepovažuje sa za ňu ani pridanie metód do rozhraní určených na odber hostiteľom, pridanie hodnôt do enumov čítaných z odpovede poskytovateľa alebo zmena textu výnimky.

Konštruktory výsledkových modelov rozširujte iba pridaním voliteľných koncových parametrov. Existujúce pozičné argumenty a ich poradie zachovajte.

`UblDocumentWriter` nezaručuje identický XML výstup medzi verziami. Hostiteľ má uložiť výsledný `OutboundDocumentPayload`; pri opakovaní odoslania s rovnakým idempotency kľúčom má použiť uložený payload a znovu ho neprepočítavať.

Predvolené správanie retry, circuit breakera, backpressure a execution locku je súčasťou kontraktu. Hostiteľ ho môže zmeniť iba cez zdokumentované nastavenia a rozšíriteľné kontrakty.

## Kontrola pred vydaním

Verejné API sa v CI porovnáva s posledným tagom pri každom PR, pushi a tagu (pri sérii `0.x` len informatívne, od `1.0` blokujúco). Pred každým tagom skontrolujte výsledok tejto kontroly. Výsledok kontroly zosúlaďte s release labelmi a so sekciou `Unreleased` v `CHANGELOG.md`.
