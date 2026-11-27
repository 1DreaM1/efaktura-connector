// Generátor statickej stránky web/ (HTML). Spustenie z koreňa repozitára: node tools/build-site.js
// Zdroje textov: tools/site-snippets.html (ukážky kódu). CSS a JS v web/assets sa upravujú ručne.
const fs = require('fs'), path = require('path');
const ROOT = path.resolve(__dirname, '..');
const OUT = ROOT + '/web';

/* ---------- code snippets ---------- */
const v1 = fs.readFileSync(ROOT + '/tools/site-snippets.html', 'utf8');
const dec = t => t.replace(/<[^>]+>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, '&');
const SN = [];
{ const re = /<pre\b[^>]*>([\s\S]*?)<\/pre>/g; let m; while ((m = re.exec(v1))) SN.push(dec(m[1]).replace(/\s+$/, '').replace(/^ (\$result)/m, '$1')); }
if (SN.length !== 11) throw new Error('snippets ' + SN.length);
SN.forEach((t, i) => { SN[i] = t.split("UnitCodeList::resolve('ks')").join("UnitCodeList::resolve('H87')").replace(/new UblDraftLine\(\s*'1'/g,"new UblDraftLine(new UblDraftIdentifier('1')"); });
const code = (i, file, lang) => `<script type="text/plain" class="src" data-file="${file}"${lang ? ` data-lang="${lang}"` : ''}>\n${SN[i]}\n</script>`;

/* ---------- helpers ---------- */
const PAGES = [
  ['index.html', 'Prehľad'], ['architektura.html', 'Architektúra'], ['toky.html', 'Toky dokladov'], ['ukazky.html', 'Ukážky kódu'],
  ['validacia.html', 'Validácia'], ['api.html', 'API'], ['integrator.html', 'Integrator'], ['nastavenie.html', 'Nastavenie'], ['prevadzka.html', 'Prevádzka'],
  ['wiki/index.html', 'Wiki'],
];
const flow = (steps, n) => `<div class="flow" style="--n:${n || steps.length}">${steps.map((s, i) => `<div class="step rv"><i>${i + 1}</i>${s[3] ? `<em>${s[3]}</em>` : ''}<b>${s[0]}</b><small>${s[1]}${s[2] ? ' ' + s[2] : ''}</small></div>`).join('')}</div>`;
const wb = t => (t.indexOf(" ") > -1 || t.length < 22) ? t : t.replace(/([a-z])(?=[A-Z])/g, "$1<wbr>");
const cells = (arr, cls) => `<div class="grid ${cls || 'g3'}">${arr.map(c => `<${c.href ? 'a href="' + c.href + '"' : 'div'} class="cell rv">${c.ico ? `<div class="ico ${c.c || ''}">${c.ico}</div>` : ''}${c.top ? `<small class="top">${c.top}</small>` : ''}<h3>${wb(c.t)}</h3><p>${c.p}</p>${c.ul ? `<ul>${c.ul.map(x => `<li>${x}</li>`).join('')}</ul>` : ''}${c.href ? `<span class="more">${c.more || 'Viac →'}</span>` : ''}</${c.href ? 'a' : 'div'}>`).join('')}</div>`;
const table = (head, rows, id, cls) => `<div class="tw"><table${id ? ` id="${id}"` : ''}${cls ? ` class="${cls}"` : ''}><thead><tr>${head.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
const opRows = rows => rows.map(r => [`<span class="m ${r[0]}">${r[0]}</span>`, `<span class="path">${r[1]}</span>`, r[2]]);
const head = (kick, h2, lead, center) => `<div class="${center ? 'head-c' : ''}"><span class="kick">${kick}</span><h2>${h2}</h2>${lead ? `<p class="lead">${lead}</p>` : ''}</div>`;
const acc = (id, title, tag, body, open) => `<details class="acc rv" id="${id}"${open ? ' open' : ''}><summary>${title}${tag ? ` <span class="tag">${tag}</span>` : ''}</summary><div class="body">${body}</div></details>`;
const note = (t, c) => `<div class="note ${c || ''} rv"><span>${t}</span></div>`;
const final = (h, p, links) => `<div class="final rv"><h2>${h}</h2><p class="lead">${p}</p><div class="links">${links.map((l, i) => `<a class="btn${i ? '' : ' pri'}" href="${l[0]}">${l[1]}</a>`).join('')}</div></div>`;
const WIKI = 'wiki/';

function layout(file, title, desc, body, active, base, customPager) {
  base = base || '';
  const idx = PAGES.findIndex(p => p[0] === file);
  const prev = PAGES[idx - 1], next = PAGES[idx + 1];
  const pager = customPager !== undefined ? customPager : idx >= 0 && file !== 'index.html' ? `<div class="pager">${prev ? `<a href="${base}${prev[0]}"><small>← Predchádzajúca</small><b>${prev[1]}</b></a>` : '<span></span>'}${next ? `<a class="nx" href="${base}${next[0]}"><small>Ďalšia →</small><b>${next[1]}</b></a>` : ''}</div>` : '';
  return `<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#fafafc">
<meta name="description" content="${desc}">
<title>${title} · eFaktúra Connector</title>
<script>try{var t=localStorage.getItem('efk-theme');if(t&&t!=='auto')document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="${base}assets/style.css">
</head>
<body>
<a class="skip" href="#main">Preskočiť na obsah</a>
<div class="progress" id="bar"></div>
<header class="site">
  <div class="wrap nav">
    <a class="logo" href="${base}index.html"><i>€</i>eFaktúra <span>Connector</span></a>
    <nav class="menu" id="menu" aria-label="Hlavná navigácia">${PAGES.map(p => `<a href="${base}${p[0]}"${p[0] === active ? ' aria-current="page"' : ''}>${p[1]}</a>`).join('')}</nav>
    <div class="right">
      <button class="icon-btn" id="theme" type="button" aria-label="Téma">◐</button>
      <a class="btn pri sm hide-s" href="https://github.com/1DreaM1/efaktura-connector">GitHub ↗</a>
      <button class="icon-btn burger" id="burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="menu">☰</button>
    </div>
  </div>
</header>
<main id="main">
${body}
<div class="wrap">${pager}</div>
</main>
<footer class="site">
  <div class="wrap">
    <div class="fgrid">
      <div><a class="logo" href="${base}index.html"><i>€</i>eFaktúra <span>Connector</span></a><p>Frameworkovo nezávislá PHP knižnica pre SAPI-SK, Peppol Gateway a Integrator API. MIT licencia.</p></div>
      <div><h5>Produkt</h5><a href="${base}architektura.html">Architektúra</a><a href="${base}toky.html">Toky dokladov</a><a href="${base}ukazky.html">Ukážky kódu</a><a href="${base}validacia.html">Validácia</a></div>
      <div><h5>Referencia</h5><a href="${base}api.html">API operácie</a><a href="${base}integrator.html">Integrator</a><a href="${base}nastavenie.html">Nastavenie</a><a href="${base}prevadzka.html">Prevádzka</a></div>
      <div><h5>Zdroje</h5><a href="${base}dokumentacia.html">Dokumentácia</a><a href="https://github.com/1DreaM1/efaktura-connector">GitHub</a><a href="${base}wiki/index.html">Wiki</a><a href="${base}../LICENSE">Licencia MIT</a></div>
    </div>
    <div class="fbot"><span>© eFaktúra Connector · MIT licencia</span><span>Statická stránka · CSS a JavaScript v priečinku assets</span></div>
  </div>
</footer>
<script src="${base}assets/app.js"></script>
</body>
</html>
`;
}
const phead = (crumb, h1, lead, extra) => `<div class="phead"><div class="mesh"><div class="dots"></div></div><div class="wrap"><div class="crumbs"><a href="index.html">Prehľad</a> / ${crumb}</div><span class="kick">${crumb}</span><h1>${h1}</h1><p class="sub">${lead}</p>${extra || ''}</div></div>`;
const toc = items => `<div class="toc">${items.map(i => `<a href="#${i[0]}">${i[1]}</a>`).join('')}</div>`;
const W = b => `<div class="wrap">${b}</div>`;
const sec = (id, inner, cls) => `<section${id ? ` id="${id}"` : ''}${cls ? ` class="${cls}"` : ''}>${inner}</section>`;

const pages = {};

/* ================= INDEX ================= */
pages['index.html'] = ['Prehľad', 'Frameworkovo nezávislá PHP knižnica pre SAPI-SK, Peppol Gateway a Integrator API s UBL 2.1 a lokálnou validáciou.', () => `
<div class="hero">
  <div class="mesh"><div class="dots"></div></div>
  <div class="wrap">
    <span class="badge"><b>DataHosting API</b> SAPI-SK + Peppol + Integrator</span>
    <h1>Fakturácia <span class="gt">bez integračného chaosu.</span></h1>
    <p class="sub">Nezávislá PHP knižnica pre SAPI-SK a Peppol Gateway. Podporuje Peppol BIS Billing 3.0, čítanie a zápis faktúr a dobropisov UBL 2.1 aj lokálnu kontrolu podľa EN 16931 a slovenských pravidiel. Obchodné spracovanie a ukladanie údajov zabezpečuje hostiteľská aplikácia.</p>
    <div class="cta"><a class="btn pri" href="architektura.html">Preskúmať možnosti →</a><a class="btn" href="api.html">Pozrieť API</a></div>
    <div class="install" id="copy" role="button" tabindex="0" title="Kliknutím skopírujete"><code><span class="d">$</span> composer require dream1/efaktura-connector:dev-main</code><em id="cs">kopírovať</em></div><small style="display:block;margin-top:8px;opacity:.7">Najprv pridajte GitHub VCS repozitár do composer.json (pozri Nastavenie).</small>
    <div class="pills"><span>Peppol BIS Billing 3.0</span><span>UBL 2.1 · Invoice / CreditNote</span><span>SAPI-SK + Peppol Gateway</span><span>PHP 7.4+ · Laravel voliteľný</span></div>

    <div class="pipe rv"><div><b>Váš doklad</b><small>UBL 2.1 · Invoice / CreditNote</small></div><div><b>eFaktúra Connector</b><small>OAuth · transport · typované API</small></div><div><b>SAPI + Peppol Gateway</b><small>odoslanie · stav · príjem</small></div></div>

    <div class="win" data-tabs>
      <div class="win-top"><i></i><i></i><i></i>
        <div class="dtabs" role="tablist">
          <button role="tab" aria-selected="true" aria-controls="h1p">príprava a kontroly</button>
          <button role="tab" aria-selected="false" aria-controls="h2p">odoslanie</button>
          <button role="tab" aria-selected="false" aria-controls="h3p">príjem</button>
        </div>
      </div>
      <div role="tabpanel" id="h1p"><pre class="inline" data-hl data-lines="5-7" tabindex="0">// 1. lokálna príprava (bez volania siete)
$payload = $drafts->preparePayload($sender, $receiver, $draft, $createdAt);

// 2. voliteľné kontroly: každá je samostatné volanie API
$client->recipientCapabilityCheck()->check($payload);
$client->providerDocumentValidationCheck()
       ->check(new ValidateDocumentRequest($sender, $payload));</pre></div>
      <div role="tabpanel" id="h2p" hidden><pre class="inline" data-hl data-lines="2" tabindex="0">// kľúč uložte spolu s draftom; pri opakovaní použite ten istý
$result = $gateway->send(new SendDocumentRequest($sender, $payload, $key));

echo $result->status()->value();   // ACCEPTED = technické prijatie, nie doručenie

// Pri neistom výsledku zopakujte presný payload s tým istým kľúčom.</pre></div>
      <div role="tabpanel" id="h3p" hidden><pre class="inline" data-hl data-lines="8" tabindex="0">$page = $inbox->getInboxPage(new ListReceivedDocumentsRequest($participant, $pageToken));

foreach ($page->documents() as $metadata) {
    $reference = new ReceivedDocumentReference($participant, $metadata->documentId(), $context);
    $payload   = $inbox->getDocumentPayload($reference);

    // uložte idempotentne ...
    $inbox->acknowledge($reference);   // až po úspešnom uložení
}</pre></div>
    </div>

    <div class="stats rv">
      <div><strong class="gt">3</strong><b>skupiny API</b><span>SAPI-SK · Gateway · Integrator</span></div>
      <div><strong class="gt">28</strong><b>implementovaných operácií</b><span>6 SAPI · 12 Gateway · 10 Integrator</span></div>
      <div><strong class="gt">3</strong><b>základné vrstvy</b><span>Core · Domain · Infrastructure</span></div>
      <div><strong class="gt">7.4+</strong><b>PHP bez frameworku</b><span>Integrácia Laravelu je voliteľná</span></div>
    </div>
  </div>
</div>

<div class="wrap">
${sec('moznosti', head('01 — 06 / Možnosti', 'Jedna knižnica. <span class="gt">Technické kroky e&#8209;faktúry.</span>', 'Konektor poskytuje samostatné operácie a ich výsledky. Hostiteľská aplikácia riadi obchodný proces, ukladanie údajov, plánovanie, používateľské správy aj prijímanie webhookov cez HTTP.', true) + cells([
  { ico: '⇄', t: 'Odosielanie a vzdialená kontrola', p: 'Posiela obsah UBL cez SAPI. Pred odoslaním možno samostatne overiť podporu príjemcu v sieti Peppol a zavolať validačnú službu Gateway.', href: 'toky.html#fl-send', more: 'Tok odoslania →' },
  { ico: '✉', c: 'b', t: 'Doručená pošta', p: 'Načítava doručenú poštu po stránkach, sťahuje XML a umožňuje technicky potvrdiť prevzatie prijatého dokladu.', href: 'toky.html#fl-inbox', more: 'Tok príjmu →' },
  { ico: '◎', c: 'g', t: 'Stav a správy MLS', p: 'Číta stav odoslaných dokladov a správy MLS vrátane obsahu konkrétneho potvrdenia.', href: 'api.html#gateway', more: 'Operácie Gateway →' },
  { ico: '⚑', c: 'o', t: 'Webhooky', p: 'Registruje, mení, stránkuje a odstraňuje webhooky a spracúva obálku udalosti. HTTP prijímanie webhookov a overenie podpisu zabezpečuje hostiteľská aplikácia.', href: 'toky.html#fl-webhook', more: 'Tok webhookov →' },
  { ico: '❖', t: 'Čítanie a zápis UBL', p: 'Bezpečne načíta faktúru alebo dobropis do doménových hodnôt a zapíše nemenný návrh dokladu do UBL 2.1 XML. Pribalené číselníky poskytujú typované lookupy.', href: 'ukazky.html', more: 'Ukážky kódu →' },
  { ico: '✓', c: 'b', t: 'Lokálna validácia', p: 'Kontroluje UBL 2.1 XSD, BR-CO-25 a CEN EN 16931 1.3.17 spolu s Peppol BIS Billing 3.0.22 vrátane slovenských pravidiel SK-R-001 až SK-R-007.', href: 'validacia.html', more: 'Validácia →' },
]))}

${sec('hranica', head('Hranica zodpovednosti', 'Knižnica robí operácie. <span class="gt">Vy robíte rozhodnutia.</span>', '', true) + `<div class="grid g2">
<div class="cell rv"><h3>Konektor poskytuje</h3><ul><li>Typované kontrakty a výsledky operácií</li><li>OAuth, transport, retry a istič obvodu</li><li>Čítanie a zápis UBL 2.1, lokálnu validáciu</li><li>Analyzátor obálky webhookov</li></ul></div>
<div class="cell rv"><h3>Hostiteľská aplikácia rieši</h3><ul><li>Obchodný proces a schvaľovanie</li><li>Databázu, fronty úloh a plánovač</li><li>HTTP koncový bod pre webhooky a overenie podpisu</li><li>Používateľské správy a preklad chýb</li></ul></div></div>`)}

${sec('rozhrania', head('Rozhrania', 'Tri API. <span class="gt">Jeden konzistentný model.</span>', 'Konektor implementuje API spoločnosti Datahosting: SAPI-SK, Peppol Gateway API v1 a desať operácií samostatného Integrator API.', true) + cells([
  { top: 'OAuth · odosielanie · doručená pošta', t: 'SAPI-SK', p: '6 operácií. OAuth token pre SAPI aj Gateway, odoslanie dokladu, doručená pošta a technické potvrdenie.', href: 'api.html#sapi', more: 'Zoznam operácií →' },
  { top: 'Zistenie podpory · stav · webhooky', t: 'Peppol Gateway', p: '12 operácií. Discovery príjemcu, vzdialená validácia, dávkové odoslanie, stav odoslania a MLS, správa webhookov.', href: 'api.html#gateway', more: 'Zoznam operácií →' },
  { top: 'Klienti · účastníci · súhrny · PDS', t: 'Integrator API', p: '10 operácií. OAuth klienti, prístupy participantov, aktivácie, mesačné súhrny a PDS subjects.', href: 'integrator.html', more: 'Správa integrátora →' },
]))}

${final('Od inštalácie k prvej faktúre <span style="color:#c9b6ff">za večer.</span>', 'Prejdite si toky dokladov, ukážky kódu pre Laravel aj čisté PHP a nastavenie prostredia.', [['nastavenie.html', 'Nastavenie →'], ['ukazky.html', 'Ukážky kódu'], ['toky.html', 'Toky dokladov'], ['dokumentacia.html', 'Dokumentácia']])}
</div>`];

/* ================= ARCHITEKTURA ================= */
pages['architektura.html'] = ['Architektúra', 'Vrstvy Core, Domain a Infrastructure, možnosti použitia, štruktúra balíka a identita balíka.', () => `
${phead('Architektúra', 'Rozdelené zodpovednosti. <span class="gt">Jasné integračné body.</span>', 'Aplikačný kód závisí od kontraktov. Konkrétny transport a API poskytovateľa patria do infraštruktúry; možno ich nahradiť alebo oddeliť pri testovaní.', toc([['vrstvy', 'Vrstvy'], ['pouzitie', 'Možnosti použitia'], ['struktura', 'Štruktúra balíka'], ['identita', 'Identita balíka']]))}
<div class="wrap">
${sec('vrstvy', head('Návrh systému', 'Tri vrstvy, <span class="gt">jeden smer závislostí.</span>') + cells([
  { top: '01 · Rozhrania', t: 'Core', p: 'Stabilné rozhrania a typované vstupy a výstupy pre pracovný postup hostiteľa.', ul: ['Odosielanie, doručená pošta, sledovanie, zistenie podpory', 'Konfigurácia a výnimky', 'Modely webhookov a validácie'] },
  { top: '02 · Hodnoty', t: 'Domain', p: 'Modely dokladov a účastníkov bez znalosti HTTP alebo Laravelu.', ul: ['Účastník a stavy dokladov', 'Hodnoty UBL a návrhy dokladov', 'Limity a číselníky'] },
  { top: '03 · Implementácia', t: 'Infrastructure', p: 'Adaptéry poskytovateľa, koncové body, tokeny, transport a validačné nástroje.', ul: ['SAPI + Peppol Gateway API', 'Opakovanie HTTP požiadaviek, istič obvodu, logovanie', 'UBL čítačka, zapisovač a validátory'] },
]) + `<div class="cell s3 rv" style="margin-top:16px"><small class="top">Voliteľná integrácia</small><h3>Laravel</h3><p>Composer navrhuje <code>illuminate/support</code> v rozsahu 8.83–13. CI testuje bridge na Laravel 13 s PHP 8.4. Service provider zverejní konfiguráciu a sprístupní šifrovanú vyrovnávaciu pamäť tokenov aj koordináciu cez atomické zámky Laravel Cache. Jadro Laravel nevyžaduje; jeho PHP 7.4 kompatibilitu kontroluje hlavná testovacia matica. Metriky možno doplniť cez <code>ConnectorMetricsInterface</code>.</p></div>`)}

${sec('pouzitie', head('Možnosti použitia', 'Zapojte len tie časti, <span class="gt">ktoré potrebujete.</span>', 'Konektor môže obslúžiť celý technický tok dokladu alebo len lokálnu prácu s UBL. Integráciu si poskladáte podľa aplikácie.') + cells([
  { top: '01 · Laravel bridge', t: 'Aplikácia s kontajnerom služieb', p: 'Laravel service provider sprístupní predvolené API a UBL služby. Pri viacerých OAuth profiloch vyberte službu cez <code>SapiClientRegistryInterface</code>; pri povereniach v modeli zákazníka použite <code>TenantEFakturaClientResolver</code>. <code>default_client</code> určuje priame bindingy.' },
  { top: '02 · Samostatné PHP', t: 'Integrácia bez frameworku', p: 'Používajte kontrakty Core a zostavte prenos ručne. Jadro nevyžaduje <code>illuminate/support</code>; stabilná <code>ConnectorFactory</code> zostaví klientov a cez <code>withExecutorOptions()</code>, <code>withTokenRefreshLock()</code>, <code>withMetrics()</code> a <code>withClock()</code> pridáte logovanie, koordináciu obnovy tokenu, metriky či vlastné hodiny.' },
  { top: '03 · Viac účastníkov', t: 'Organizácie a rôzne firmy', p: 'Pri načítaní doručenej pošty získajte ID príjemcu aktuálnej organizácie a rovnaký objekt <code>Participant</code> použite pri zozname, stiahnutí aj potvrdení. Participant sa odovzdáva osobitne, nezávisle od vybraného OAuth profilu.' },
  { top: '04 · Lokálne UBL', t: 'Nástroje bez volania API', p: 'Čítajte XML faktúry a dobropisu, zostavujte návrhy dokladov a spúšťajte lokálne kontroly bez sieťového volania. Java je potrebná iba pre Schematron.' },
  { top: '05 · Testy hostiteľa', t: 'Offline klient bez poskytovateľa', p: '<code>FakeSapiClient</code> a <code>FakeDocumentSubmissionGateway</code> z menného priestoru <code>Testing</code> nahrávajú odoslania, vracajú výsledky a zlyhania z fronty a pri rovnakom idempotency key vrátia pôvodný výsledok. Nepotrebujú poverenia ani sieť.' },
], 'g2') + note('Vlastné fronty úloh, plánovač, databázu, obchodné schvaľovanie a HTTP koncový bod na prijímanie webhookov zabezpečuje hostiteľská aplikácia.', 'info'))}

${sec('struktura', head('Repozitár', 'Kde čo <span class="gt">patrí.</span>', 'Modul oddeľuje verejné kontrakty, hodnoty a konkrétne integrácie. Nová logika poskytovateľa patrí do infraštruktúry, nie do doménových modelov.') + cells([
  { top: 'src/Core/', t: 'Kontrakty a modely API', p: 'Rozhrania, konfigurácia, dátové objekty a typované chyby pre aplikačnú vrstvu.' },
  { top: 'src/Domain/', t: 'Hodnoty a pravidlá', p: 'Účastníci, stavy, doklady UBL, návrhy dokladov a číselníky bez závislostí od HTTP.' },
  { top: 'src/Infrastructure/', t: 'Adaptéry poskytovateľa a prostredia', p: 'Koncové body, OAuth, prenos, mapovanie API, čítanie a zápis XML aj validátory.' },
  { top: 'src/Testing/', t: 'Offline pomôcky pre testy', p: '<code>FakeSapiClient</code> a <code>FakeDocumentSubmissionGateway</code> pre testy hostiteľskej aplikácie bez poskytovateľa.' },
  { top: 'integrations/laravel/', t: 'Voliteľná integrácia Laravelu', p: 'Väzby v Laraveli, poskytovateľ služieb a šifrovaná vyrovnávacia pamäť tokenov.' },
  { top: 'resources/validation/', t: 'Verziované podklady', p: 'UBL XSD, Schematron pravidlá, Saxon HE a licenčné oznámenia.' },
  { top: 'resources/code-lists/', t: 'Referenčné číselníky', p: 'Krajiny, ISO 4217 meny, UN/ECE jednotky, EAS a participant schémy aj 13 číselníkov Peppol Billing 2026-Q4-MR. Voliteľné aliasy jednotiek (<code>ks</code>, <code>kg</code>, <code>hod</code>) prevedie <code>UnitCodeList::unit()</code> na kódy UN/ECE.' },
]) + `<div class="grid g2" style="margin-top:16px"><div class="cell rv"><small class="top">tests/</small><h3>Hranice a správanie</h3><p>Jednotkové a integračné testy, testovací prenos a architektonické kontroly závislostí.</p></div><div class="cell rv"><small class="top">config/efaktura-connector.php</small><h3>Predvolená konfigurácia</h3><p>Predvolená konfigurácia Laravelu. Composer PSR-4 mapuje <code>DreaM1\\EfakturaConnector\\</code> na <code>src/</code>; Laravel prefix smeruje do <code>integrations/laravel/src/</code>.</p></div></div>
<p class="muted rv" style="margin-top:18px">Podrobný strom a odporúčania na rozširovanie sú v <a href="${WIKI}Project-Structure.html" style="color:var(--p1);font-weight:600">dokumentácii štruktúry balíka ↗</a>.</p>`)}

${sec('identita', head('Composer · PSR-4 · LICENSE', 'Identita <span class="gt">balíka.</span>', 'Údaje z aktuálneho <code>composer.json</code> a súboru licencie.') + `<div class="kv rv"><div><b>Balík Composer</b><span><code>dream1/efaktura-connector</code></span></div><div><b>Priestor názvov jadra</b><span><code>DreaM1\\EfakturaConnector\\</code></span></div><div><b>Priestor názvov integrácie Laravelu</b><span><code>DreaM1\\EfakturaConnector\\Laravel\\</code></span></div><div><b>Licencia zdrojového kódu</b><span><a href="../LICENSE" style="color:var(--p1);font-weight:600">Licencia MIT ↗</a> · Validačné zdroje tretích strán majú samostatné licenčné oznámenia.</span></div></div>`)}
</div>`];

/* ================= FLOWCHART RENDERER ================= */
const FC = { CW: 272, RH: 134, NW: 210, PX: 44, PY: 34 };
const wrapText = (t, n) => {
  const toks = [];
  t.split(' ').forEach(w => { if (w.length <= n) toks.push([' ', w]); else w.split(/(?=[A-Z])/).forEach((p, i) => toks.push([i ? '' : ' ', p])); });
  const out = []; let cur = '';
  toks.forEach(([j, w]) => { const nx = cur ? cur + j + w : w; if (nx.length > n && cur) { out.push(cur); cur = w; } else cur = nx; });
  if (cur) out.push(cur); return out;
};
const attr = s => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const xml = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
function flowchart(nodes, edges) {
  const byId = {}; let maxCol = 0, maxRow = 0, hasLoop = false;
  nodes.forEach(n => {
    n.lines = wrapText(n.t, n.k === 'd' ? 16 : (['ok', 'err', 'warn'].includes(n.k) ? 21 : 26));
    n.w = n.k === 'd' ? FC.NW + 16 : FC.NW;
    n.h = n.k === 'd' ? Math.max(96, 54 + n.lines.length * 17) : Math.max(54, 24 + n.lines.length * 17);
    n.cx = FC.PX + FC.NW / 2 + n.col * FC.CW; n.cy = FC.PY + 50 + n.row * FC.RH;
    byId[n.id] = n; maxCol = Math.max(maxCol, n.col); maxRow = Math.max(maxRow, n.row);
  });
  edges.forEach(e => { if (e.loop) hasLoop = true; });
  const maxRight = FC.PX + FC.NW / 2 + maxCol * FC.CW + FC.NW / 2 + 10;
  const W = maxRight + FC.PX + (hasLoop ? 60 : 0), H = FC.PY * 2 + 100 + maxRow * FC.RH;
  const anchor = (n, s) => s === 't' ? [n.cx, n.cy - n.h / 2] : s === 'b' ? [n.cx, n.cy + n.h / 2] : s === 'l' ? [n.cx - n.w / 2, n.cy] : [n.cx + n.w / 2, n.cy];
  const dir = { t: [0, -1], b: [0, 1], l: [-1, 0], r: [1, 0] };
  const G = 22;
  function rounded(pts, r) {
    let d = `M${pts[0][0]} ${pts[0][1]}`;
    for (let i = 1; i < pts.length - 1; i++) {
      const [x0, y0] = pts[i - 1], [x1, y1] = pts[i], [x2, y2] = pts[i + 1];
      const l1 = Math.hypot(x1 - x0, y1 - y0), l2 = Math.hypot(x2 - x1, y2 - y1), rr = Math.min(r, l1 / 2, l2 / 2);
      if (!rr) { d += ` L${x1} ${y1}`; continue; }
      const ax = x1 - (x1 - x0) / l1 * rr, ay = y1 - (y1 - y0) / l1 * rr, bx = x1 + (x2 - x1) / l2 * rr, by = y1 + (y2 - y1) / l2 * rr;
      d += ` L${ax} ${ay} Q${x1} ${y1} ${bx} ${by}`;
    }
    const L = pts[pts.length - 1]; return d + ` L${L[0]} ${L[1]}`;
  }
  let svgE = '', svgL = '';
  edges.forEach(e => {
    const a = byId[e.a], b = byId[e.b];
    let s = e.s, t = e.e;
    if (!s) {
      if (a.col === b.col) { s = b.row > a.row ? 'b' : 't'; t = b.row > a.row ? 't' : 'b'; }
      else if (a.row === b.row) { s = b.col > a.col ? 'r' : 'l'; t = b.col > a.col ? 'l' : 'r'; }
      else { s = 'b'; t = 't'; }
    }
    const P = anchor(a, s), Q = anchor(b, t), ds = dir[s], dt = dir[t];
    const p1 = [P[0] + ds[0] * G, P[1] + ds[1] * G], p2 = [Q[0] + dt[0] * G, Q[1] + dt[1] * G];
    let pts;
    const vS = ds[0] === 0, vT = dt[0] === 0;
    if (vS && vT) { if (p1[0] === p2[0]) pts = [P, Q]; else { const my = (p1[1] + p2[1]) / 2; pts = [P, p1, [p1[0], my], [p2[0], my], p2, Q]; } }
    else if (!vS && !vT) { if (p1[1] === p2[1] && !e.loop) pts = [P, Q]; else { const mx = e.loop ? maxRight + 24 : (p1[0] + p2[0]) / 2; pts = [P, p1, [mx, p1[1]], [mx, p2[1]], p2, Q]; } }
    else if (vS) pts = [P, p1, [p1[0], p2[1]], p2, Q];
    else pts = [P, p1, [p2[0], p1[1]], p2, Q];
    // remove consecutive duplicates
    pts = pts.filter((p, i) => i === 0 || p[0] !== pts[i - 1][0] || p[1] !== pts[i - 1][1]);
    const cls = b.k === 'ok' ? 'ok' : b.k === 'err' ? 'err' : b.k === 'warn' ? 'warn' : 'n';
    svgE += `<path class="ed e-${cls}" d="${rounded(pts, 10)}" marker-end="url(#mk-${cls})"/>`;
    if (e.l) {
      let best = 0, bi = 0; for (let i = 0; i < pts.length - 1; i++) { const l = Math.hypot(pts[i + 1][0] - pts[i][0], pts[i + 1][1] - pts[i][1]); if (l > best) { best = l; bi = i; } }
      const lx = (pts[bi][0] + pts[bi + 1][0]) / 2, ly = (pts[bi][1] + pts[bi + 1][1]) / 2, w = e.l.length * 6.6 + 14;
      svgL += `<g class="lb"><rect x="${lx - w / 2}" y="${ly - 10}" width="${w}" height="20" rx="10"/><text x="${lx}" y="${ly + 4}" text-anchor="middle">${xml(e.l)}</text></g>`;
    }
  });
  let svgN = '';
  nodes.forEach(n => {
    const x = n.cx - n.w / 2, y = n.cy - n.h / 2;
    let shape;
    if (n.k === 'd') shape = `<path class="sh" d="M${n.cx} ${y} L${x + n.w} ${n.cy} L${n.cx} ${y + n.h} L${x} ${n.cy} Z"/>`;
    else shape = `<rect class="sh" x="${x}" y="${y}" width="${n.w}" height="${n.h}" rx="${['ok', 'err', 'warn', 's'].includes(n.k) ? n.h / 2 : 14}"/>`;
    const ty = n.cy - (n.lines.length - 1) * 8.5 + 4.5;
    const txt = `<text text-anchor="middle" x="${n.cx}" y="${ty}">${n.lines.map((l, i) => `<tspan x="${n.cx}" dy="${i ? 17 : 0}">${xml(l)}</tspan>`).join('')}</text>`;
    svgN += `<g class="nd k-${n.k}" tabindex="0" role="button" data-t="${attr(n.t)}" data-i="${attr(n.info || '')}" data-k="${n.k}">${shape}${txt}</g>`;
  });
  const mk = (id) => `<marker id="mk-${id}" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path class="mk-${id}" d="M1 1.5 L9 5 L1 8.5 Z"/></marker>`;
  return `<div class="fc-wrap"><svg class="fc" viewBox="0 0 ${W} ${H}" style="min-width:${Math.round(W * .8)}px" role="img" aria-label="Vývojový diagram"><defs>${['n', 'ok', 'err', 'warn'].map(mk).join('')}</defs>${svgE}${svgL}${svgN}</svg></div>`;
}
const LEGEND = `<div class="legend"><span class="k-c">Konektor</span><span class="k-h">Hostiteľ</span><span class="k-a">API poskytovateľa</span><span class="k-d">Rozhodnutie</span><span class="k-ok">Úspech</span><span class="k-warn">Neistý výsledok</span><span class="k-err">Chyba</span></div>`;
const N = (id, col, row, k, t, info) => ({ id, col, row, k, t, info });
const E = (a, b, l, o) => Object.assign({ a, b, l }, o || {});
const fchart = (id, intro, nodes, edges, tail) => `<div role="tabpanel" id="${id}" class="fpanel" hidden><p class="fintro">${intro}</p>${LEGEND}<div class="fbox">${flowchart(nodes, edges)}<div class="fdetail" aria-live="polite"><b>Detail kroku</b><p>Kliknite na ľubovoľný krok diagramu.</p></div></div>${tail ? note(tail, 'info') : ''}</div>`;

const D_SEND = fchart('fl-send', 'Hostiteľ vždy rozhoduje, ktoré kontroly pred odoslaním spustí. Konektor ich nevolá automaticky a odosielacia požiadavka POST sa po neistej chybe sama neopakuje.', [
  N('s1', 1, 0, 'h', 'Uložiť draft, čas a UUID', 'Pred prvým pokusom si hostiteľ uloží návrh dokladu, čas vytvorenia (UTC) a Idempotency-Key. Pri opakovaní sa používajú rovnaké hodnoty, nikdy nové.'),
  N('s2', 1, 1, 'c', 'preparePayload()', 'Čisto lokálny krok: zapíše draft do UBL 2.1 XML a vytvorí OutboundDocumentPayload (metadáta, XML, voliteľný SHA-256). Bez volania siete.'),
  N('s3', 1, 2, 'd', 'Spustiť predbežné kontroly?', 'Rozhodnutie hostiteľa. Konektor nespúšťa Discovery ani vzdialenú validáciu implicitne; send() ich nevolá.'),
  N('s4', 0, 3, 'a', 'Discovery a validácia v Gateway', 'POST /v1/discovery/receives a POST /v1/validate-document. Každé je samostatné volanie a vzdialená validácia môže používať inú verziu pravidiel ako lokálna.'),
  N('s5', 0, 4, 'd', 'Doklad zamietnutý?', 'Kontrola dostala úspešnú odpoveď, ktorá dokument zamietla. Nie je to HTTP chyba.'),
  N('s6', 0, 5, 'err', 'DocumentPreflightException', 'Abstraktný základ: RecipientUnsupportedException (príjemca nepodporuje typ dokladu) alebo DocumentRejectedByProviderException (validácia Gateway zamietla). Doklad sa neodosiela; hostiteľ ho opraví a rozhodne o ďalšom postupe.'),
  N('s7', 1, 5, 'c', 'send() · POST /sapi/document/send', 'Odoslanie cez SAPI s Participant a stabilným Idempotency-Key. Pri aktívnom cooldowne po 429 vznikne RequestDeferredException ešte pred sieťou.'),
  N('s8', 1, 6, 'd', 'Odpoveď SAPI', 'Výsledok sa rozlíši podľa odpovede alebo typu výnimky.'),
  N('s9', 0, 7, 'ok', 'DocumentSubmissionResult', 'Úspešná odpoveď SAPI: providerDocumentId, timestamp a status() ACCEPTED alebo REJECTED podľa odpovede API. ACCEPTED je technické prijatie požiadavky, nie doručenie príjemcovi.'),
  N('s10', 1, 7, 'err', 'ProviderErrorResponseException', 'HTTP stav mimo 2xx. Uložte stav HTTP; telo môže obsahovať citlivé údaje. HTTP chyba nie je automaticky obchodné zamietnutie.'),
  N('s11', 2, 7, 'warn', 'Výsledok je neistý', 'ProviderConnectionException alebo InvalidProviderResponseException (neplatná 2xx). Poskytovateľ mohol doklad prijať, preto nevytvárajte nový kľúč.'),
  N('s12', 2, 8, 'h', 'Zosúladiť stav', 'Hostiteľ doklad označí na zosúladenie, overí stav a prípadne zopakuje presne ten istý payload s tým istým Idempotency-Key.'),
  N('s13', 0, 8, 'h', 'Uložiť výsledok', 'Hostiteľ uloží providerDocumentId a stav vo vlastnej databáze.'),
  N('s14', 0, 9, 'a', 'Tracking alebo webhook', 'Stav doručenia a správy MLS cez Peppol Gateway (GET /v1/sent-documents/{id}) alebo webhooky. Samostatné volanie.'),
], [E('s1', 's2'), E('s2', 's3'), E('s3', 's4', 'áno'), E('s3', 's7', 'nie'), E('s4', 's5'), E('s5', 's6', 'áno'), E('s5', 's7', 'nie', { s: 'r', e: 't' }), E('s7', 's8'), E('s8', 's9', '2xx', { s: 'l', e: 't' }), E('s8', 's10', 'HTTP chyba'), E('s8', 's11', 'výpadok', { s: 'r', e: 't' }), E('s11', 's12'), E('s12', 's7', 'rovnaký kľúč', { s: 'r', e: 'r', loop: true }), E('s9', 's13'), E('s13', 's14')],
  'Prijatie požiadavky SAPI nie je potvrdením doručenia príjemcovi. Dávkové odoslanie cez Gateway (<code>POST /v1/send-documents</code>) má vlastný <code>Idempotency-Key</code> a rovnaké pravidlá.');

const D_INBOX = fchart('fl-inbox', 'Najprv sa spracujú všetky doklady jednej stránky. Až potom sa podľa nextPageToken() rozhodne, či sa načíta ďalšia stránka. Potvrdzuje sa až po bezpečnom uložení.', [
  N('i1', 1, 0, 'h', 'Participant a prvá stránka', 'Participant z identity aktuálnej organizácie (nie z globálneho .env). Prvé volanie ide bez tokenu (pageToken = null). Rovnaký Participant použite pri zozname, stiahnutí aj potvrdení.'),
  N('i2', 1, 1, 'c', 'getInboxPage(participant, token)', 'GET /sapi/document/receive: Participant, voliteľný pageToken a limit stránky (1 až 100, napr. 50). Vráti metadáta dokladov od najstarších a nextPageToken. Token je nepriehľadný kurzor; odovzdajte ho nezmenený.'),
  N('i3', 1, 2, 'd', 'Zostáva doklad na stránke?', 'Vnútorný cyklus: foreach cez $page->documents(). Pri prázdnej stránke sa rovno prejde na kontrolu nextPageToken().'),
  N('i4', 1, 3, 'd', 'receiver = účastník?', 'Skontrolujte $metadata->receiver() voči účastníkovi organizácie. Samotné metadáta doručenej pošty nestačia.'),
  N('i5', 1, 4, 'c', 'getDocumentPayload()', 'GET /sapi/document/receive/{id}: stiahne metadáta, XML payload a payloadFormat.'),
  N('i6', 1, 5, 'c', 'UblDocumentReader::read()', 'Bezpečne načíta UBL do doménových hodnôt. Nespúšťa XSD ani Schematron validáciu.'),
  N('i7', 1, 6, 'h', 'Uložiť idempotentne', 'Hostiteľ uloží obsah a výsledok (napr. storeIfAbsent alebo updateOrCreate v transakcii). Ak uloženie zlyhá, výnimka preruší spracovanie a potvrdenie sa nevykoná.'),
  N('i8', 1, 7, 'c', 'acknowledge()', 'POST /sapi/document/receive/{id}/acknowledge: technické prevzatie, volá sa až po úspešnom uložení. Neznamená obchodné schválenie faktúry.'),
  N('i9', 0, 2, 'd', 'nextPageToken() ≠ null?', 'Vyhodnotí sa po spracovaní všetkých dokladov stránky, nad stránkou vrátenou z getInboxPage(). Podmienka while ($pageToken !== null). Pokračujte vždy až po null.'),
  N('i10', 0, 3, 'ok', 'Hotovo', 'Doručená pošta je vyčerpaná.'),
  N('i11', 2, 3, 'err', 'Výnimka · spracovanie sa preruší', 'Doklad nepatrí do tejto doručenej pošty (UnexpectedValueException v ukážke). Spracovanie sa preruší a nič sa nepotvrdí.'),
  N('i12', 2, 6, 'warn', 'Výnimka · bez potvrdenia', 'Uloženie alebo čítanie zlyhalo. Doklad sa nepotvrdí, takže ho možno spracovať znova pri ďalšom behu; spracovanie preto musí byť idempotentné.'),
], [E('i1', 'i2'), E('i2', 'i3'), E('i3', 'i4', 'áno'), E('i3', 'i9', 'nie', { s: 'l', e: 'r' }), E('i9', 'i2', 'áno · ďalšia stránka', { s: 't', e: 'l' }), E('i9', 'i10', 'nie'), E('i4', 'i5', 'áno'), E('i4', 'i11', 'nie'), E('i5', 'i6'), E('i6', 'i7'), E('i7', 'i8', 'uložené'), E('i7', 'i12', 'chyba'), E('i8', 'i3', 'ďalší doklad', { s: 'r', e: 'r', loop: true })]);

const D_HOOK = fchart('fl-webhook', 'Registrácia ide cez Gateway API. Callback prijíma vlastná HTTP route hostiteľa; konektor nie je receiver a podpis neoveruje.', [
  N('w1', 1, 0, 'c', 'webhookRegistry()->register()', 'POST /v1/webhooks: hostiteľ vytvorí typovaný request a vyberie participanta.'),
  N('w2', 1, 1, 'a', 'Gateway: webhook + secret', 'Poskytovateľ vráti registráciu a signing secret.'),
  N('w3', 1, 2, 'h', 'Uložiť secret mimo logov', 'Tajomstvo uchovávajte bezpečne a nikdy ho nezapisujte do logov.'),
  N('w4', 1, 3, 'a', 'Callback na route hostiteľa', 'HTTP server a prijatie požiadavky patria hostiteľovi. Konektor nehostuje HTTP route.'),
  N('w5', 1, 4, 'd', 'Podpis a čerstvosť platné?', 'Overuje hostiteľ nad surovým telom: sám, alebo voliteľným pomocníkom PeppolWebhookEventFactory::fromSignedJson() (pri rotácii secretu fromSignedJsonWithAnySecret() so zoznamom tajomstiev) s explicitne dodaným verifikátorom (napr. HMAC-SHA256) a voliteľnou toleranciou časovej pečiatky. Výber tajomstva, politika čerstvosti a replay patria hostiteľovi. Neúspech: InvalidWebhookPayloadException.'),
  N('w6', 1, 5, 'd', 'Telo ≤ 1 MiB a obálka platná?', 'Surové telo nad 1 MiB sa odmietne pred dekódovaním; JSON musí byť objekt s platnou obálkou a objektom data (WebhookEventPayload::fromJson). Neúspech: InvalidWebhookPayloadException. Pole data ostáva nezmenený payload od poskytovateľa.'),
  N('w7', 1, 6, 'd', 'Udalosť už spracovaná?', 'Webhook aj polling môžu oznámiť ten istý stav. Deduplikácia patrí hostiteľovi.'),
  N('w8', 1, 7, 'h', 'Trvalo uložiť / do fronty', 'Event store a fronta patria hostiteľovi. Konektor ich nespravuje.'),
  N('w9', 1, 8, 'ok', 'Vrátiť úspešný HTTP status', 'Až po trvalom uložení udalosti vráťte 2xx.'),
  N('w10', 0, 4, 'err', 'Odmietnuť · nespracovať', 'Neautentifikované doručenie sa nesmie spracovať.'),
  N('w11', 0, 5, 'err', 'InvalidWebhookPayloadException', 'Neplatné alebo príliš veľké telo, resp. neplatná obálka. Doručenie neprijímajte.'),
  N('w12', 0, 6, 'ok', 'Ignorovať · vrátiť 2xx', 'Duplicitná udalosť: potvrďte ju bez ďalšieho spracovania.'),
], [E('w1', 'w2'), E('w2', 'w3'), E('w3', 'w4'), E('w4', 'w5'), E('w5', 'w10', 'nie'), E('w5', 'w6', 'áno'), E('w6', 'w11', 'nie'), E('w6', 'w7', 'áno'), E('w7', 'w12', 'áno'), E('w7', 'w8', 'nie'), E('w8', 'w9')]);

const D_HTTP = fchart('fl-http', 'Istič sa vyhodnotí raz za operáciu, cooldown pred každým pokusom. Retry vrstva opakuje iba bezpečné operácie a iba pri povolených výsledkoch.', [
  N('t1', 1, 0, 'c', 'HttpExchangeRequest', 'Typovaná metóda, cesta, hlavičky, telo a retry safety (ApiRequest opakuje predvolene iba GET).'),
  N('t2', 1, 1, 'd', 'Istič otvorený?', 'Istič obklopuje retry vrstvu a hodnotí celú operáciu, nie jednotlivé pokusy. Po 5 neúspešných operáciách za 60 s sa otvorí na 30 s; potom povolí jednu skúšobnú požiadavku. HTTP 429 a RequestDeferredException sú neutrálne.'),
  N('t3', 1, 2, 'd', 'Aktívny cooldown?', 'Backpressure kontroluje cooldown pred každým pokusom, aj pred opakovaním po čakaní. Cooldown zapisuje každá odpoveď 429 podľa Retry-After (alebo predvolene 1 s); požiadavka v rovnakom scope sa odloží bez sieťového volania.'),
  N('t4', 1, 3, 'a', 'cURL cez HTTPS', 'Overí TLS, použije timeouty a číta telo do nakonfigurovaného limitu (16 MiB, najviac 128 MiB).'),
  N('t5', 1, 4, 'd', 'Odpoveď?', 'Rozhodne sa podľa stavu a veľkosti odpovede.'),
  N('t6', 1, 5, 'd', 'Opakovateľné a zostali pokusy?', 'Opakuje sa iba retry-safe požiadavka (predvolene GET) a iba chyba spojenia alebo 5xx/429 s retryable: true v chybovej obálke; ostatné chyby 4xx sa vrátia hneď. Predvolene 3 pokusy celkovo. POST odoslania dokladu sa neopakuje. Ak Retry-After presiahne inline limit, odpoveď sa vráti hostiteľovi.'),
  N('t7', 0, 4, 'ok', '2xx → typovaný výsledok', 'ApiConnector skontroluje status, dekóduje JSON/XML a endpoint ho namapuje na Core model. Neplatné telo úspešnej odpovede vyvolá InvalidProviderResponseException.'),
  N('t8', 2, 4, 'err', 'ProviderResponseTooLargeException', 'Po prekročení limitu sa prenos zastaví. Automatické opakovanie sa nevykoná.'),
  N('t9', 0, 2, 'warn', 'RequestDeferredException', 'Požiadavka sa neodoslala. Termín je v retryAt(); plánovanie patrí hostiteľovi.'),
  N('t10', 0, 1, 'err', 'ProviderConnectionException', 'Vyčerpané pokusy, alebo jej podtrieda CircuitBreakerOpenException pri otvorenom ističi (pred retry vrstvou, bez opakovania). Pri chybe siete nemusí byť známe, či poskytovateľ požiadavku prijal.'),
  N('t11', 1, 6, 'warn', 'Výnimka hostiteľovi', 'ProviderErrorResponseException alebo ProviderConnectionException. Po nejednoznačnej chybe odosielacieho POST najprv zosúlaďte stav.'),
], [E('t1', 't2'), E('t2', 't10', 'áno'), E('t2', 't3', 'nie'), E('t3', 't9', 'áno'), E('t3', 't4', 'nie'), E('t4', 't5'), E('t5', 't7', '2xx'), E('t5', 't8', 'nad limit'), E('t5', 't6', 'chyba spojenia / 4xx / 5xx'), E('t6', 't3', 'pauza 500 ms → 1 s', { s: 'r', e: 'r', loop: true }), E('t6', 't11', 'nie')]);

const D_OAUTH = fchart('fl-oauth', 'SAPI vydáva OAuth token pre SAPI aj Peppol Gateway. Token cache je viazaná na server, OAuth klienta a scope; Participant sa posiela pri každej operácii.', [
  N('o1', 1, 0, 'h', 'Operácia s Participant', 'Hostiteľ volá Core kontrakt s typovanými vstupmi a Participant pre konkrétnu operáciu.'),
  N('o2', 1, 1, 'c', 'AuthenticatedRequestSender', 'SapiTokenProvider vráti platný token z pamäte alebo cache.'),
  N('o3', 1, 2, 'd', 'Platný token v cache?', 'Predvolená cache drží token len počas procesu; v produkcii dodajte vlastnú SapiTokenCacheInterface.'),
  N('o4', 2, 2, 'a', 'POST /sapi/auth/token', 'Client credentials cez obyčajný transport (bez autentifikačného senderu).'),
  N('o5', 2, 3, 'c', 'Uložiť token do cache', 'Token sa uloží podľa servera, OAuth klienta a scope.'),
  N('o6', 1, 4, 'c', 'Request s Bearer tokenom', 'Transport doplní base_url a pripojí Authorization hlavičku.'),
  N('o7', 1, 5, 'a', 'SAPI alebo Gateway', 'Poskytovateľ vráti status, hlavičky a prípadné telo.'),
  N('o8', 1, 6, 'd', 'Odpoveď 401?', 'Sender zahodí zamietnutý token a raz zopakuje pôvodný request s novým tokenom. Čerstvý token, ktorý medzitým získal iný worker, ostane v cache. Tento krok je oddelený od HTTP retry.'),
  N('o9', 0, 6, 'c', 'Zahodiť token · obnoviť raz', 'Ak 401 príde aj po obnove, výsledkom je ProviderErrorResponseException.'),
  N('o10', 1, 7, 'ok', 'Typovaný výsledok', 'Odpoveď provideru sa továrňou (<code>*Factory</code>) namapuje priamo na Core model alebo vznikne typovaná výnimka.'),
], [E('o1', 'o2'), E('o2', 'o3'), E('o3', 'o6', 'áno'), E('o3', 'o4', 'nie'), E('o4', 'o5'), E('o5', 'o6', null, { s: 'b', e: 'r' }), E('o6', 'o7'), E('o7', 'o8'), E('o8', 'o9', '401'), E('o8', 'o10', 'iný stav'), E('o9', 'o3', 'jedno obnovenie', { s: 't', e: 'l' })]);

const D_UBL = fchart('fl-ubl', 'Zápis, čítanie a validácia sa spúšťajú samostatne. Zápis nič nevaliduje a čítanie nie je biznis validácia.', [
  N('u1', 1, 0, 'h', 'Zostaviť UblDocumentDraft', 'Hostiteľ pripraví strany, riadky a súčty. Zapisovač sumy nepočíta a biznis hodnoty nevymýšľa.'),
  N('u2', 1, 1, 'c', 'UblDocumentWriter::write()', 'Skladá Invoice alebo CreditNote v poradí schémy a vráti UTF-8 UBL 2.1 XML. Voliteľne predtým PeppolBillingDraftRules.'),
  N('u3', 1, 2, 'c', 'UblXmlDocumentLoader', 'Kontrola veľkosti, DTD/ENTITY, bezpečného parsovania a podporovaného rootu.'),
  N('u4', 1, 3, 'd', 'Spustiť validáciu?', 'Rozhodnutie hostiteľa. Čítačka validátory nespúšťa.'),
  N('u5', 1, 4, 'c', 'UBL XSD', 'Štruktúra dokumentu proti pribaleným UBL 2.1 schémam cez libxml.'),
  N('u6', 1, 5, 'c', 'BR-CO-25', 'Doplnková kontrola splatnosti alebo platobných podmienok.'),
  N('u7', 1, 6, 'c', 'CEN + Peppol Schematron', 'EN 16931 1.3.17 a Peppol BIS Billing 3.0.22 vrátane SK-R-001 až SK-R-007. Java 11+, Saxon HE.'),
  N('u8', 1, 7, 'd', 'Java a zdroje dostupné?', 'Pipeline vykoná všetky kroky aj po chybe skoršieho kroku. Chýbajúca Java alebo zdroje sú infraštruktúrna výnimka, nie nález.'),
  N('u9', 1, 8, 'd', 'Nález typu error?', 'warning a info samy osebe isValid() neblokujú; error a vynechané nálezy áno.'),
  N('u10', 1, 9, 'ok', 'isValid() = true', 'LocalUblValidationResult obsahuje stav, trvanie a verzie pravidiel jednotlivých krokov.'),
  N('u11', 0, 3, 'ok', 'Len čítanie', 'UblDocumentReader načíta doménové hodnoty bez validácie.'),
  N('u12', 2, 2, 'err', 'UblValidationException', 'Abstraktný predok chýb: InvalidXmlException (neplatné alebo nebezpečné XML: veľkosť, DTD/ENTITY) alebo UnsupportedDocumentTypeException (koreň nie je Invoice ani CreditNote).'),
  N('u13', 2, 7, 'err', 'LocalValidationInfrastructureException', 'Lokálnu kontrolu nebolo možné spustiť. Nehovorí nič o platnosti dokladu.'),
  N('u14', 0, 8, 'err', 'isValid() = false', 'Hostiteľ rozhodne o oprave dokladu.'),
], [E('u1', 'u2'), E('u2', 'u3'), E('u3', 'u12', 'chyba'), E('u3', 'u4'), E('u4', 'u11', 'nie'), E('u4', 'u5', 'áno'), E('u5', 'u6'), E('u6', 'u7'), E('u7', 'u8'), E('u8', 'u13', 'nie'), E('u8', 'u9', 'áno'), E('u9', 'u14', 'áno'), E('u9', 'u10', 'nie')],
  'Vzdialená validácia cez Gateway (<code>POST /v1/validate-document</code>) je samostatné volanie a môže používať inú verziu pravidiel.');

/* ================= TOKY ================= */
pages['toky.html'] = ['Toky dokladov', 'Interaktívne vývojové diagramy: odoslanie, príjem, webhooky, HTTP požiadavka, OAuth a lokálna validácia UBL.', () => `
${phead('Toky dokladov', 'Každá vetva <span class="gt">na jednom obrázku.</span>', 'Konektor poskytuje jednotlivé kroky a výsledky. Vývojové diagramy ukazujú, kde rozhoduje hostiteľ, kde pracuje konektor a ako sa vetví ACCEPTED, chyba alebo neistý výsledok. Kliknutím na krok zobrazíte detail.', toc([['diagramy', 'Diagramy'], ['ucastnik', 'Pravidlá účastníka']]))}
<div class="wrap">
${sec('diagramy', `<div data-tabs>
<div class="seg" role="tablist"><button role="tab" aria-selected="true" aria-controls="fl-send">Odoslanie</button><button role="tab" aria-selected="false" aria-controls="fl-inbox">Príjem</button><button role="tab" aria-selected="false" aria-controls="fl-webhook">Webhooky</button><button role="tab" aria-selected="false" aria-controls="fl-http">HTTP požiadavka</button><button role="tab" aria-selected="false" aria-controls="fl-oauth">OAuth token</button><button role="tab" aria-selected="false" aria-controls="fl-ubl">UBL a validácia</button></div>
${D_SEND}${D_INBOX}${D_HOOK}${D_HTTP}${D_OAUTH}${D_UBL}
</div>`, 'tight')}
${sec('ucastnik', `<div class="flowhead"><h3>Účastník pri príjme dokladov</h3><span class="tag">3 pravidlá</span></div>` + cells([
  { top: '01', t: 'Načítajte príjemcu organizácie', p: 'Pri načítaní doručenej pošty vytvorte <code>Participant</code> z identity aktuálnej organizácie; neukladajte ju do globálneho <code>.env</code>.' },
  { top: '02', t: 'Overte vlastníctvo dokladu', p: 'Skontrolujte <code>$metadata->receiver()</code> voči účastníkovi organizácie; pri nezhode doklad nespracujte.' },
  { top: '03', t: 'Uložte a až potom potvrďte', p: 'Spracovanie robte idempotentne, stránkujte cez <code>nextPageToken()</code> a potvrďte až po bezpečnom uložení.' },
]), 'tight')}
</div>`];

/* ================= UKAZKY (krátke, cielené) ================= */
const codeS = (text, file, hl, lang) => `<script type="text/plain" class="src" data-file="${file}"${hl ? ` data-hl="${hl}"` : ''}${lang ? ` data-lang="${lang}"` : ''}>\n${text}\n</script>`;
const S = {};
S.setup = [`$factory = new ConnectorFactory();   // vlastná token cache: new ConnectorFactory($runtime, $tokenCache)

$client = $factory->createSapiClient('billing', SapiClientConfiguration::fromArray([
    'base_url'      => getenv('EFAKTURA_BASE_URL'),
    'client_id'     => getenv('EFAKTURA_CLIENT_ID'),
    'client_secret' => getenv('EFAKTURA_CLIENT_SECRET'),
    'client_scope'  => SapiClientConfiguration::SAPI_SCOPE_DOCUMENT_SEND . ' ' . SapiClientConfiguration::SAPI_SCOPE_DOCUMENT_RECEIVE,
]));

// Klient poskytuje hotové služby. Účastníka (Participant) zadávate pri každej operácii.
$drafts   = $client->ublDraftSubmission();
$gateway  = $client->documentSubmissionGateway();
$inbox    = $client->receivedDocumentProvider();
$tracking = $client->documentTrackingProvider();`, 'app/bootstrap.php', '3-8,11-14'];
const ublBody = ind => `$country  = CountryCodeList::resolve(CountryCodeList::SLOVAKIA);
$currency = CurrencyCodeList::resolve('EUR');
$unit     = UnitCodeList::resolve('ks');

$supplier = (new UblDraftParty(
    UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345676'),
    'Ukážkový dodávateľ s.r.o.',
    $country
))->withAddress((new UblDraftAddress($country))->withLines('Hlavná 1')->withLocality('Bratislava', '811 01'));

$customer = (new UblDraftParty(
    UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345687'),
    'Ukážkový odberateľ s.r.o.',
    $country
))->withAddress((new UblDraftAddress($country))->withLines('Druhá 2')->withLocality('Košice', '040 01'));

// Sumy pripravuje aplikácia; zapisovač ich nepočíta.
$vat    = new UblDraftTaxCategory(TaxCategoryCode::fromString(TaxCategoryCode::STANDARD), UblDecimal::fromString('23'));
$line   = new UblDraftLine(new UblDraftIdentifier('1'), UblDecimal::fromString('2.0000'), $unit, UblDecimal::fromString('100.00'), 'Konzultačná služba', $vat, UblDecimal::fromString('50.00'));
$totals = new UblDraftTotals(
    UblDecimal::fromString('100.00'), UblDecimal::fromString('100.00'), UblDecimal::fromString('123.00'),
    null, UblDecimal::fromString('123.00'), UblDecimal::fromString('23.00'),
    [new UblDraftTaxSubtotal(UblDecimal::fromString('100.00'), UblDecimal::fromString('23.00'), $vat)]
);

$draft = (new UblDocumentDraft(
    UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
    UblDocumentNumber::fromString('FA-2026-001'),
    LocalDate::fromString('2026-11-22'),
    $currency, $supplier, $customer, $totals
))->withLines([$line])->withDates(UblDraftDates::none()->withDueDate(LocalDate::fromString('2026-12-22')));
`;
S.ubl = [`$country  = CountryCodeList::resolve(CountryCodeList::SLOVAKIA);
$currency = CurrencyCodeList::resolve('EUR');
$unit     = UnitCodeList::resolve('H87');

$supplier = (new UblDraftParty(
    UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345676'),
    'Ukážkový dodávateľ s.r.o.',
    $country
))->withAddress(
    (new UblDraftAddress($country))
        ->withLines('Hlavná 1')
        ->withLocality('Bratislava', '811 01')
);

$customer = (new UblDraftParty(
    UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345687'),
    'Ukážkový odberateľ s.r.o.',
    $country
))->withAddress(
    (new UblDraftAddress($country))
        ->withLines('Druhá 2')
        ->withLocality('Košice', '040 01')
);

// Sumy pripravuje aplikácia; zapisovač ich nepočíta.
$vat = new UblDraftTaxCategory(
    TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
    UblDecimal::fromString('23')
);

$line = new UblDraftLine(
    new UblDraftIdentifier('1'),
    UblDecimal::fromString('2.0000'),
    $unit,
    UblDecimal::fromString('100.00'),
    'Konzultačná služba',
    $vat,
    UblDecimal::fromString('50.00')
);

$totals = new UblDraftTotals(
    UblDecimal::fromString('100.00'),
    UblDecimal::fromString('100.00'),
    UblDecimal::fromString('123.00'),
    null,
    UblDecimal::fromString('123.00'),
    UblDecimal::fromString('23.00'),
    [
        new UblDraftTaxSubtotal(
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString('23.00'),
            $vat
        ),
    ]
);

$draft = (new UblDocumentDraft(
    UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
    UblDocumentNumber::fromString('FA-2026-001'),
    LocalDate::fromString('2026-11-22'),
    $currency,
    $supplier,
    $customer,
    $totals
))
    ->withLines([$line])
    ->withDates(UblDraftDates::none()->withDueDate(LocalDate::fromString('2026-12-22')));

$xml = (new UblDocumentWriter())->write($draft);   // bez validácie XSD či Peppol`, 'app/ubl/write-invoice.php', '25-29,57-67,69'];
S.send = [`$sender    = Participant::parse('0245:2012345676');
$receiver  = Participant::parse('0245:2012345687');
$createdAt = Timestamp::fromDateTimeImmutable(
    new DateTimeImmutable('now', new DateTimeZone('UTC'))
);
$key = IdempotencyKeyGenerator::generate();   // ulož ho spolu s draftom

$payload = $drafts->preparePayload($sender, $receiver, $draft, $createdAt);

// Voliteľné kontroly: každá je samostatné volanie API.
$client->recipientCapabilityCheck()->check($payload);
$client->providerDocumentValidationCheck()->check(
    new ValidateDocumentRequest($sender, $payload)
);

$result = $gateway->send(new SendDocumentRequest($sender, $payload, $key));

echo $result->status()->value();                // ACCEPTED = technické prijatie
echo $result->providerDocumentId()->value();`, 'app/send-invoice.php', '8,16'];
S.batch = [`$request = new SendDocumentsRequest(
    $sender,
    [
        new OutboundGatewayDocument(XmlPayloadFormat::UBL_XML, $invoiceXml, $receiver),
        new OutboundGatewayDocument(XmlPayloadFormat::SBDH_XML, $sbdhXml),
    ],
    IdempotencyKeyGenerator::generate()
);

$result = $client->peppolGatewayDocumentSubmission()->send($request);

$result->count();                      // počet prijatých dokumentov
$result->identifiers()[0]->value();    // identifikátor v Gateway`, 'app/send-batch.php', '10'];
S.err = [`try {
    $result = $gateway->send(new SendDocumentRequest($sender, $payload, $key));
    $attempt->recordProviderResult($result);

} catch (RequestDeferredException $e) {
    // Aktívny cooldown po 429. Požiadavka sa vôbec neodoslala.
    $attempt->markDeferred($e->retryAt()->unixTimestamp());

} catch (ProviderConnectionException | InvalidProviderResponseException $e) {
    // Výsledok môže byť neistý: najprv zosúladiť, potom zopakovať s TÝM ISTÝM kľúčom.
    $attempt->markForReconciliation();

} catch (ProviderErrorResponseException $e) {
    $attempt->markProviderError($e->rawStatusCode());   // telo chyby nelogovať
}`, 'app/send-safely.php', '5-7,9-11'];
S.inbox = [`$ublReader   = new UblDocumentReader(new UblXmlDocumentLoader());
$participant = Participant::parse('0245:1234567890');
$pageToken   = null;

do {
    $page = $inbox->getInboxPage(new ListReceivedDocumentsRequest(
        $participant,
        $pageToken,
        PageSize::fromInt(50)
    ));
    $pageToken = $page->nextPageToken();

    foreach ($page->documents() as $metadata) {
        if (!$metadata->receiver()->equals($participant)) {
            throw new UnexpectedValueException('Doklad nepatrí do tejto schránky.');
        }

        $reference = new ReceivedDocumentReference(
            $participant,
            $metadata->documentId(),
            new DocumentRequestContext(
                $db->resolveId($metadata->documentId()->value()),
                $metadata->documentId()->value()
            )
        );

        $document = $inbox->getDocumentPayload($reference);
        $ubl      = $ublReader->read($document->xml());     // len načítanie, bez validácie

        $db->storeIfAbsent($participant, $metadata, $ubl);  // idempotentne
        $inbox->acknowledge($reference);                    // až po úspešnom uložení
    }
} while ($pageToken !== null);`, 'app/receive-inbox.php', '14-16,30-31'];
S.lvSend = [`public function __invoke(
    SubmissionAttempt $attempt,
    UblDraftSubmissionInterface $drafts,
    RecipientCapabilityCheckInterface $recipient,
    ProviderDocumentValidationCheckInterface $validation,
    DocumentSubmissionGatewayInterface $gateway
): JsonResponse {
    $sender   = Participant::parse($attempt->senderParticipantId());
    $receiver = Participant::parse($attempt->receiverParticipantId());

    $payload = $drafts->preparePayload(
        $sender,
        $receiver,
        $attempt->draft(),
        $attempt->creationDateTime()
    );

    // Samostatné volania API; send() ich nespúšťa automaticky.
    $recipient->check($payload);
    $validation->check(new ValidateDocumentRequest($sender, $payload));

    $result = $gateway->send(new SendDocumentRequest(
        $sender,
        $payload,
        IdempotencyKey::fromString($attempt->idempotencyKey())   // uložené UUID
    ));
    $attempt->recordProviderResult($result);

    return response()->json([
        'status'               => $result->status()->value(),
        'provider_document_id' => $result->providerDocumentId()->value(),
    ]);
}`, 'app/Http/Controllers/SubmitInvoiceController.php', '2-6,18-20,22-26'];
S.lvErr = [`try {
    $result = $gateway->send(new SendDocumentRequest($sender, $payload, $key));
    $attempt->recordProviderResult($result);

    return response()->json(['status' => $result->status()->value()]);
} catch (RequestDeferredException $e) {
    $attempt->markDeferred($e->retryAt()->unixTimestamp());
    return response()->json(['status' => 'odlozene'], 429);
} catch (ProviderConnectionException | InvalidProviderResponseException $e) {
    $attempt->markForReconciliation();                    // výsledok môže byť neistý
    return response()->json(['status' => 'potrebne_zosuladenie'], 202);
} catch (ProviderErrorResponseException $e) {
    $attempt->markProviderError($e->rawStatusCode());
    Log::warning('SAPI vrátilo chybu.', ['status' => $e->rawStatusCode()]);   // bez tela odpovede
    return response()->json(['status' => 'chyba_poskytovatela'], 502);
}`, 'app/Http/Controllers/SubmitInvoiceController.php', '6-8,9-11'];
S.lvIn = [`public function __invoke(
    Request $request,
    Tenant $tenant,
    ReceivedDocumentProviderInterface $inbox,
    UblDocumentReaderInterface $reader
): JsonResponse {
    $participant = Participant::parse($tenant->peppol_participant_id);   // z organizácie, nie z .env

    $pageToken = $request->filled('page_token')
        ? PageToken::fromString($request->input('page_token'))
        : null;

    $page = $inbox->getInboxPage(new ListReceivedDocumentsRequest(
        $participant,
        $pageToken,
        PageSize::fromInt(50)
    ));

    foreach ($page->documents() as $metadata) {
        if (!$metadata->receiver()->equals($participant)) {
            throw new UnexpectedValueException('Doklad nepatrí do tejto schránky.');
        }

        $reference = new ReceivedDocumentReference(
            $participant,
            $metadata->documentId(),
            new DocumentRequestContext('received:' . $metadata->documentId()->value(), null)
        );
        $payload = $inbox->getDocumentPayload($reference);
        $ubl     = $reader->read($payload->xml());

        DB::transaction(function () use ($participant, $metadata, $payload) {
            ReceivedInvoice::updateOrCreate(
                [
                    'receiver_participant_id' => $participant->identifier(),
                    'provider_document_id'    => $metadata->documentId()->value(),
                ],
                [
                    'sender_participant_id' => $metadata->sender()->identifier(),
                    'document_xml'          => $payload->xml(),
                ]
            );
        });

        $inbox->acknowledge($reference);   // až po dokončení transakcie
    }

    return response()->json([
        'next_page_token' => optional($page->nextPageToken())->value(),
    ]);
}`, 'app/Http/Controllers/ReceiveInboxController.php', '7,20-22,32-43,45'];
S.lvUbl = [`public function __invoke(UblDocumentWriterInterface $writer): Response
{
    $country  = CountryCodeList::resolve(CountryCodeList::SLOVAKIA);
    $currency = CurrencyCodeList::resolve('EUR');
    $unit     = UnitCodeList::resolve('H87');

    $supplier = (new UblDraftParty(
        UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345676'),
        'Ukážkový dodávateľ s.r.o.',
        $country
    ))->withAddress(
        (new UblDraftAddress($country))
            ->withLines('Hlavná 1')
            ->withLocality('Bratislava', '811 01')
    );

    $customer = (new UblDraftParty(
        UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345687'),
        'Ukážkový odberateľ s.r.o.',
        $country
    ))->withAddress(
        (new UblDraftAddress($country))
            ->withLines('Druhá 2')
            ->withLocality('Košice', '040 01')
    );

    // Sumy pripravuje aplikácia; zapisovač ich nepočíta.
    $vat = new UblDraftTaxCategory(
        TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
        UblDecimal::fromString('23')
    );

    $line = new UblDraftLine(
        new UblDraftIdentifier('1'),
        UblDecimal::fromString('2.0000'),
        $unit,
        UblDecimal::fromString('100.00'),
        'Konzultačná služba',
        $vat,
        UblDecimal::fromString('50.00')
    );

    $totals = new UblDraftTotals(
        UblDecimal::fromString('100.00'),
        UblDecimal::fromString('100.00'),
        UblDecimal::fromString('123.00'),
        null,
        UblDecimal::fromString('123.00'),
        UblDecimal::fromString('23.00'),
        [
            new UblDraftTaxSubtotal(
                UblDecimal::fromString('100.00'),
                UblDecimal::fromString('23.00'),
                $vat
            ),
        ]
    );

    $draft = (new UblDocumentDraft(
        UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
        UblDocumentNumber::fromString('FA-2026-001'),
        LocalDate::fromString('2026-11-22'),
        $currency,
        $supplier,
        $customer,
        $totals
    ))
        ->withLines([$line])
        ->withDates(UblDraftDates::none()->withDueDate(LocalDate::fromString('2026-12-22')));

    return response($writer->write($draft))
        ->header('Content-Type', 'application/xml; charset=UTF-8');
}`, 'app/Http/Controllers/InvoiceXmlController.php', null];
S.lvProfiles = [`// Viac OAuth profilov: aliasy v config/efaktura-connector.php (clients.<alias>)
$client = app(SapiClientRegistryInterface::class)->client('tenant-a');

$result = $client->documentSubmissionGateway()->send(new SendDocumentRequest($sender, $payload, $key));

// Priame bindingy (injekcia rozhraní) používajú profil z default_client.`, 'app/Services/BillingService.php', '2'];

const exn = (id, title, p, blocks, notes, after, full) => `<div role="tabpanel" id="${id}" class="ex" hidden><h3>${title}</h3><p>${p}</p>${notes ? `<ul class="list" style="max-width:780px;margin-top:14px">${notes.map(n => `<li>${n}</li>`).join('')}</ul>` : ''}${blocks.map(b => codeS(b[0], b[1], b[2], b[3])).join('')}${after ? `<div class="note info"><span>${after}</span></div>` : ''}${full ? `<details class="acc" style="margin-top:14px"><summary>Celý súbor s importmi a plným zostavením</summary><div class="body">${codeS(full[0], full[1])}</div></details>` : ''}</div>`;
const subtabs = list => `<div class="subtabs" role="tablist">${list.map((l, i) => `<button role="tab" aria-selected="${i === 0}" aria-controls="${l[0]}">${l[1]}</button>`).join('')}</div>`;
const UBL_FULL = [SN[5], 'app/ubl/write-invoice.php'];

pages['ukazky.html'] = ['Ukážky kódu', 'Krátke a cielené ukážky pre Laravel aj čisté PHP: zapojenie, odoslanie, chyby, príjem a vytvorenie UBL.', () => `
${phead('Ukážky kódu', 'Menej riadkov. <span class="gt">Jasný zámer.</span>', 'Každá ukážka obsahuje len podstatné kroky; importy a obvyklý kontext sú vynechané. Zvýraznené riadky označujú miesta, kde sa rozhoduje o idempotencii, kontrolách a potvrdení.')}
<div class="wrap">
<section class="tight">
<div data-tabs>
  <div class="seg" role="tablist"><button role="tab" aria-selected="true" aria-controls="g-php">Čisté PHP</button><button role="tab" aria-selected="false" aria-controls="g-lv">Laravel</button></div>

  <div role="tabpanel" id="g-php"><div data-tabs>
    ${subtabs([['ph-boot', 'Zapojenie'], ['ph-ubl', 'Vytvorenie UBL'], ['ph-send', 'Odoslanie'], ['ph-batch', 'Dávka cez Gateway'], ['ph-err', 'Chyby'], ['ph-in', 'Príjem']])}
    ${exn('ph-boot', 'Zapojenie jedným klientom', '<code>ConnectorFactory</code> vytvorí HTTP stack, OAuth a všetky služby. Vytvorený klient uchovajte a používajte opakovane.', [S.setup], null, 'Predvolená token cache je len procesová. Pri viacerých workeroch odovzdajte vlastnú <code>SapiTokenCacheInterface</code>, ktorá token bezpečne uloží.')}
    ${exn('ph-ubl', 'Faktúra z draftu na XML', 'Zostavíte nemenný návrh a zapisovač ho serializuje do UBL 2.1. Nič sa pritom nevaliduje a nepočíta.', [S.ubl], null, 'Syntetické identifikátory slúžia len na príklad. Schéma 0245 je produkčná; v Sandboxe použite 9915 v transportných Participant ID aj v UBL <code>EndpointID/@schemeID</code>.', UBL_FULL)}
    ${exn('ph-send', 'Odoslanie s uloženým kľúčom', 'Kontroly pred odoslaním sú voliteľné a samostatné. Kľúč a draft uložte pred prvým pokusom.', [S.send], null, '<code>send()</code> nespúšťa zisťovanie podpory ani validáciu. <b>ACCEPTED</b> je len technické prijatie; stav doručenia a MLS čítajte samostatne.')}
    ${exn('ph-batch', 'Dávkové odoslanie cez Gateway', 'Viac dokumentov v jednom volaní, s vlastným Idempotency-Key. Najviac 100 dokumentov, každý do 10 MB.', [S.batch], null, 'Pre <code>UBL_XML</code> je <code>receiverParticipantId</code> povinné, pre <code>SBDH_XML</code> voliteľné.')}
    ${exn('ph-err', 'Odloženie vs. neistý výsledok', 'Typ výnimky hovorí, či poskytovateľ odpovedal. Pri neistom odoslaní najprv zosúlaďte stav.', [S.err], null, '<code>$attempt</code> je záznam hostiteľskej aplikácie s pôvodným draftom, časom a UUID. Telo HTTP chyby môže obsahovať citlivé údaje, preto ho nelogujte.')}
    ${exn('ph-in', 'Načítať, uložiť, potvrdiť', 'Rovnakého účastníka použite pri zozname, stiahnutí aj potvrdení. Potvrdzujte až po úspešnom uložení.', [S.inbox], null, 'Ak čítanie alebo uloženie zlyhá, potvrdenie sa nevykoná a doklad možno spracovať znova. Čítačka nenahrádza XSD ani Peppol validáciu.')}
  </div></div>

  <div role="tabpanel" id="g-lv" hidden><div data-tabs>
    ${subtabs([['lv-send', 'Odoslanie'], ['lv-err', 'Chyby'], ['lv-in', 'Príjem'], ['lv-ubl', 'Vytvorenie UBL'], ['lv-prof', 'Viac profilov']])}
    ${exn('lv-send', 'Odoslanie v kontroléri', 'Služby dodá kontajner cez injekciu do metódy. Kontrolér pripraví payload, spustí kontroly a odošle s uloženým UUID.', [S.lvSend], null, '<code>SubmissionAttempt</code> je model vašej aplikácie (ID účastníkov, draft, čas UTC, UUID). Trasa: <code>Route::post(\'/submission-attempts/{attempt}/send\', SubmitInvoiceController::class);</code>')}
    ${exn('lv-err', 'Bezpečné odpovede na chyby', 'Oddeľte chyby pred odoslaním od chýb, pri ktorých už mohol poskytovateľ doklad prijať.', [S.lvErr], ['<code>DocumentPreflightException</code>: kontrola zamietla doklad ešte pred <code>send()</code>.', '<code>RequestDeferredException</code>: nič sa neodoslalo, naplánujte úlohu podľa <code>retryAt()</code>.', '<code>ProviderConnectionException</code> a neplatná 2xx: výsledok môže byť neistý.'], 'Pri opakovaní zachovajte pôvodný draft, čas a UUID. HTTP chyba nie je automaticky obchodné zamietnutie.')}
    ${exn('lv-in', 'Príjem organizácie', 'Identitu príjemcu načítajte pre konkrétnu organizáciu. Uložte v transakcii a až potom potvrďte.', [S.lvIn], null, '<code>Tenant</code> a <code>ReceivedInvoice</code> patria hostiteľskej aplikácii. Ukážka spracuje jednu stránku s limitom 50; pri väčšom objeme ju spúšťajte vo fronte úloh.')}
    ${exn('lv-ubl', 'UBL ako XML odpoveď', 'Zapisovač príde z kontajnera. Kontrolér zostaví draft a vráti XML s typom obsahu.', [S.lvUbl], null, 'Pred odoslaním spustite samostatnú validáciu XML.', UBL_FULL)}
    ${exn('lv-prof', 'Viac OAuth profilov', 'Pri viacerých firmách alebo prostrediach vyberte klienta podľa aliasu.', [S.lvProfiles], null, 'Pri povereniach uložených v modeli zákazníka použite <code>TenantEFakturaClientResolver</code>.')}
  </div></div>
</div>
</section>
</div>`];

/* ================= VALIDACIA ================= */
pages['validacia.html'] = ['Validácia', 'UBL 2.1, XSD, Schematron CEN EN 16931 a Peppol BIS Billing 3.0.22, slovenské pravidlá SK-R-001 až SK-R-007.', () => `
${phead('UBL a validácia', 'Validácia na <span class="gt">správnej hranici.</span>', 'Cieľovým profilom je Peppol BIS Billing 3.0 pre UBL 2.1 Invoice a CreditNote. Predvolená pipeline v Laraveli spúšťa UBL XSD, pravidlá hlavičky a CEN EN 16931 1.3.17 + Peppol BIS Billing 3.0.22 Schematron vrátane slovenských pravidiel. Čítanie XML validáciu nespúšťa; kontrola cez Gateway je samostatná a môže používať inú verziu pravidiel.', toc([['vrstvy', 'Vrstvy'], ['kontroly', 'Prehľad kontrol'], ['vysledok', 'Výsledok'], ['pipeline', 'Pipeline']]))}
<div class="wrap">
${sec('vrstvy', `<div class="grid g4" style="margin-top:0">
<div class="cell rv"><span class="num">01</span><h4>Čítanie UBL</h4><p>Bezpečný XML parser. Nie je to biznis validácia.</p></div>
<div class="cell rv"><span class="num">02</span><h4>XSD</h4><p>Verziované schémy UBL 2.1 cez libxml.</p></div>
<div class="cell rv"><span class="num">03</span><h4>Schematron</h4><p>CEN a Peppol pravidlá cez Saxon (Java 11+).</p></div>
<div class="cell rv"><span class="num">04</span><h4>Gateway</h4><p>Voliteľná vzdialená kontrola; samostatné volanie.</p></div></div>`, 'tight')}

${sec('kontroly', head('Prehľad', 'Čo sa overuje <span class="gt">a ako.</span>') + table(['Kontrola', 'Čo overuje', 'Požiadavky / výsledok'], [
  ['Bezpečné načítanie', 'Veľkosť XML, DTD/ENTITY a koreň Invoice alebo CreditNote.', 'Lokálne; bez Javy. Nevykonáva obchodnú validáciu.'],
  ['UBL XSD', 'Štruktúru proti pribaleným UBL 2.1 schémam.', 'libxml; hlási typované XSD problémy.'],
  ['CEN + Peppol Schematron', 'CEN/EN 16931 1.3.17 a Peppol BIS Billing 3.0.22 (2026-Q4-MR, november 2026).', 'Java 11+, Saxon HE, povolené <code>proc_open</code> a <code>flock</code>; predvolený limit sú 2 súbežné validácie. Workery na rovnakom hoste zdieľajú sloty pri rovnakom adresári zámkov; pracovný adresár rozsah limitu nemení.'],
  ['Slovenské pravidlá', 'Pravidlá Peppol SK-R-001 až SK-R-007 v Schematrone vydania 2026-Q4-MR.', 'Spúšťa ich predvolený Peppol Schematron krok. Samostatný PHP stage je určený pre vlastnú pipeline, ktorý tieto pravidlá v Schematrone nemá.'],
  ['Vzdialená validácia', 'Kontrola obsahu dokladu službou Peppol Gateway.', 'Sieť + OAuth; pravidlá sa môžu líšiť od pribalených verzií.'],
  ['Základné pravidlá hlavičky', 'Vybrané fakturačné pravidlá Peppol nad načítanou hlavičkou UBL (BR-CO-25).', 'Lokálne; bez Javy a bez XSD.'],
  ['Kontrola návrhu dokladu', 'Vybrané požiadavky dostupné pred zápisom UBL XML.', 'Voliteľné samostatné volanie; zapisovač ani odoslanie ho nespúšťajú.'],
]))}

${sec('vysledok', head('Výsledok', 'Závažnosť nálezov <span class="gt">a platnosť.</span>', 'Nálezy majú závažnosť <code>error</code>, <code>warning</code> alebo <code>info</code>. Upozornenia a informácie samy osebe neblokujú <code>isValid()</code>; vynechané nálezy áno.') + `<div class="sev rv"><span class="e">error: dokument je neplatný</span><span class="w">warning: stále platný</span><span class="i">info: stále platný</span><span class="e">vynechané nálezy: dokument je neplatný</span></div>` + note('Lokálne zdroje sú súčasťou balíka v <code>resources/validation</code>. Integrácia Laravelu umožňuje nastaviť poradie krokov cez <code>laravel.validation.stages</code> a pridať vlastnú implementáciu <code>UblValidationStageInterface</code>. Pred zápisom možno samostatne zavolať <code>PeppolBillingDraftRulesInterface</code>. Cesty k zdrojom, pracovný adresár, spustiteľný súbor Java a limity validácie možno upraviť v zverejnenej konfigurácii. Samotná čítačka môže vrátiť doklad bez automatického spustenia validátorov.', 'info'))}

${sec('pipeline', head('Pipeline v detailoch', 'Tri samostatné <span class="gt">toky.</span>') + acc('p-write', 'Zápis návrhu', 'WRITE', '<p>Zapisovač serializuje dodaný draft a biznis hodnoty nevymýšľa ani výsledok nevaliduje.</p>' + flow([['UblDocumentDraft', 'Doménové hodnoty, strany, riadky a súčty.', '', 'Vstup'], ['Draft preflight', 'PeppolBillingDraftRules kontroluje iba údaje dostupné v návrhu.', '', 'Voliteľne'], ['UblDocumentWriter', 'Skladá Invoice alebo CreditNote v poradí schémy.', '', 'Zápis'], ['UBL 2.1 XML', 'UTF-8 XML; writer sám nevykonáva obchodnú validáciu.', '', 'Výstup']], 4), true) + acc('p-read', 'Načítanie a spoločný bod', 'READ', '<p>Zdieľaným bodom čítania a kontrol je bezpečne načítaný <code>UblXmlDocument</code>.</p>' + flow([['XML z writeru alebo systému', 'Hostiteľ odovzdá obsah explicitne.', '', 'Zdroj'], ['UblXmlDocumentLoader', 'Kontrola veľkosti, DTD, bezpečného parsovania a podporovaného rootu.', '', 'Bezpečnosť'], ['UblXmlDocument', 'UBL 2.1 Invoice alebo CreditNote s pôvodným XML.', '', 'Model XML'], ['UblDocumentReader', 'Header reader + content reader načítajú doménové hodnoty.', '', 'Čítanie'], ['UblDocument', 'Čitateľný model hlavičky, strán a obsahu.', '', 'Model']], 5)) + acc('p-val', 'Predvolená lokálna pipeline', 'LocalUblValidator', '<p>Ten istý parsovaný obsah môže hostiteľ zdieľať medzi krokmi.</p>' + flow([['UBL XSD', 'Štruktúra dokumentu cez libxml.', '', 'Krok 1'], ['BR-CO-25 header', 'Doplnková kontrola splatnosti alebo platobných podmienok.', '', 'Krok 2'], ['CEN + Peppol Schematron', 'Java/Saxon spustí pravidlá a vytvorí reporty SVRL.', '', 'Krok 3'], ['Normalizované nálezy', 'Kroky pridajú stav, trvanie a dostupné verzie pravidiel.', '', 'Mapovanie'], ['LocalUblValidationResult', 'Spoločný výsledok všetkých nakonfigurovaných krokov.', '', 'Výsledok']], 5) + note('Pipeline vykoná všetky nakonfigurované kroky v poradí aj vtedy, keď skorší krok našiel chyby. Chýbajúca Java, zdroje alebo procesná chyba vyvolá infraštruktúrnu výnimku (<code>LocalValidationInfrastructureException</code>); nejde o nález proti dokumentu. Laravel predvolene používa poradie XSD → BR-CO-25 → CEN/Peppol Schematron. Remote check <code>POST /v1/validate-document</code> vyžaduje samostatné volanie.', 'info')))}
</div>`];

/* ================= API ================= */
const SAPI = [['POST', '/sapi/auth/token', 'OAuth token získaný pomocou klientskych poverení.'], ['GET', '/sapi/auth/token/status', 'Stav aktuálneho tokenu a odporúčanie jeho obnovy.'], ['POST', '/sapi/document/send', 'Odoslanie UBL dokumentu.'], ['GET', '/sapi/document/receive', 'Stránka doručenej pošty.'], ['GET', '/sapi/document/receive/{documentId}', 'Stiahnutie XML prijatého dokladu.'], ['POST', '/sapi/document/receive/{documentId}/acknowledge', 'Technické potvrdenie prevzatia.']];
const GW = [['POST', '/v1/discovery/receives', 'Podpora príjemcu pre typ a proces.'], ['POST', '/v1/validate-document', 'Vzdialená validácia obsahu dokladu.'], ['POST', '/v1/send-documents', 'Dávkové odoslanie dokumentov cez Gateway s Idempotency-Key (najviac 100 dokumentov).'], ['GET', '/v1/participants/{participantId}', 'Profil a zverejnené schopnosti účastníka.'], ['GET', '/v1/sent-documents/{identifier}', 'Stav odoslaného dokumentu.'], ['GET', '/v1/sent-documents/{identifier}/message-level-statuses', 'Zoznam MLS správ.'], ['GET', '/v1/sent-documents/{identifier}/message-level-statuses/{statusIdentifier}/content', 'Obsah jednej MLS správy.'], ['POST', '/v1/webhooks', 'Registrácia webhooku.'], ['GET', '/v1/webhooks', 'Stránkovaný zoznam webhookov.'], ['GET', '/v1/webhooks/{webhookId}', 'Detail webhooku.'], ['PATCH', '/v1/webhooks/{webhookId}', 'Úprava webhooku.'], ['DELETE', '/v1/webhooks/{webhookId}', 'Odstránenie webhooku.']];
const INT = [['GET', '/v1/integrators/{integratorId}/clients', 'Stránkovaný zoznam OAuth klientov.'], ['POST', '/v1/integrators/{integratorId}/clients', 'Vytvorenie OAuth klienta.'], ['GET', '/v1/integrators/{integratorId}/clients/{clientId}', 'Detail OAuth klienta.'], ['DELETE', '/v1/integrators/{integratorId}/clients/{clientId}', 'Odstránenie OAuth klienta.'], ['PUT', '/v1/integrators/{integratorId}/clients/{clientId}/participants', 'Náhrada participant oprávnení klienta.'], ['PUT', '/v1/integrators/{integratorId}/clients/{clientId}/secret', 'Rotácia OAuth secretu.'], ['GET', '/v1/integrators/{integratorId}/participants', 'Stránkovaný zoznam participantov.'], ['GET', '/v1/integrators/{integratorId}/document-summary', 'Mesačný súhrn počtov dokladov.'], ['POST', '/v1/integrators/{integratorId}/participants/{participantId}/activations', 'Požiadavka na aktiváciu participant kontraktu.'], ['POST', '/v1/integrators/{integratorId}/pds/subjects', 'Odoslanie dávky PDS subjects s podpisovým poverením.']];
const H3 = ['HTTP', 'Cesta', 'Účel'];
pages['api.html'] = ['API', 'Zoznam 28 implementovaných operácií: SAPI-SK, Peppol Gateway a Integrator API.', () => `
${phead('API poskytovateľa', 'Tri rozhrania, <span class="gt">typované výsledky.</span>', 'Konektor implementuje API spoločnosti Datahosting podľa jej SAPI-SK API, Peppol Gateway API v1 a desiatich operácií samostatného Integrator API. Hostiteľská aplikácia nastavuje adresu prostredia.', `<div class="stats" style="margin-top:36px;max-width:640px"><div><strong class="gt">6</strong><b>SAPI-SK</b></div><div><strong class="gt">12</strong><b>Gateway</b></div><div><strong class="gt">10</strong><b>Integrator</b></div><div><strong class="gt">28</strong><b>spolu</b></div></div>`)}
<div class="wrap">
<section class="tight">
<div data-tabs>
  <div class="seg" role="tablist"><button role="tab" aria-selected="true" aria-controls="sapi">SAPI-SK · 6</button><button role="tab" aria-selected="false" aria-controls="gateway">Peppol Gateway · 12</button><button role="tab" aria-selected="false" aria-controls="integrator">Integrator API · 10</button></div>
  <div role="tabpanel" id="sapi"><p class="lead" style="margin-top:22px">OAuth, odosielanie, doručená pošta.</p>${table(H3, opRows(SAPI))}</div>
  <div role="tabpanel" id="gateway" hidden><p class="lead" style="margin-top:22px">Zistenie podpory, stav, webhooky.</p>${table(H3, opRows(GW))}</div>
  <div role="tabpanel" id="integrator" hidden><p class="lead" style="margin-top:22px">Klienti, účastníci, súhrny a PDS. <a href="integrator.html" style="color:var(--p1);font-weight:600">Správa integrátora →</a></p>${table(H3, opRows(INT))}</div>
</div>
${note('Počty označujú implementované operácie vrstvy Infrastructure. OAuth token endpoint <code>POST /sapi/auth/token</code> používa konektor interne. Dávkové odoslanie <code>POST /v1/send-documents</code> prijíma najviac 100 dokumentov (každý do 10 MB; typ <code>UBL_XML</code> alebo <code>SBDH_XML</code>). Detail webhooku <code>GET /v1/webhooks/{webhookId}</code> je dostupný cez Core kontrakt <code>WebhookRegistryInterface::get()</code>.', 'info')}
<div class="links" style="justify-content:flex-start;margin-top:26px"><a class="btn" href="${WIKI}API-Reference.html">Referenčné cesty a typy ↗</a><a class="btn" href="../docs/eFaktura/api/openapi.json">OpenAPI kontrakt ↗</a></div>
</section>
</div>`];

/* ================= INTEGRATOR ================= */
pages['integrator.html'] = ['Integrator', 'Integrator API: OAuth klienti, prístupy účastníkov, aktivácie, mesačné súhrny a PDS subjects.', () => `
${phead('Správa integrátora', 'Od registrácie klienta <span class="gt">po prístup účastníka.</span>', 'Integrator API slúži na správu OAuth klientov a ich účastníkov. Je to samostatná administratívna vrstva: vydaný klientsky secret sa používa pri neskoršom získaní OAuth tokenu pre SAPI a Peppol Gateway.', toc([['autorizacia', 'Autorizácia'], ['proces', 'Typický proces'], ['metody', 'Metódy']]))}
<div class="wrap">
${sec('autorizacia', head('Samostatný klient a prístup', 'Jeden integrátor. <span class="gt">Viac klientov a účastníkov.</span>', 'Hostiteľská aplikácia používa pri správe klientov hlavičku <code>X-Integrator-Token</code>. Účastník a jeho oprávnenia sa spravujú cez Integrator API; samotné fakturačné volania potom používajú OAuth klienta a samostatný objekt <code>Participant</code>.') + cells([
  { top: 'Správa účtov', t: 'X-Integrator-Token', p: 'Autorizuje bežné volania Integrator API.' },
  { top: 'Volania SAPI', t: 'OAuth client ID + secret', p: 'Klient získa Bearer token cez SAPI. Jeho secret príde iba pri vytvorení alebo rotácii.' },
  { top: 'Odoslanie PDS', t: 'X-PDS-Secret', p: 'Samostatné podpisové poverenie pre dávku PDS subjects.' },
]) + note('<b>Nastavenie:</b> Laravel používa sekciu <code>integrator_api</code> a premenné <code>EFAKTURA_INTEGRATOR_BASE_URL</code>, <code>EFAKTURA_INTEGRATOR_ID</code> a <code>EFAKTURA_INTEGRATOR_API_TOKEN</code>. Bez Laravelu vytvorte klienta cez <code>IntegratorAccountClientFactory</code>. Použite URL a poverenia pre rovnaké prostredie.', 'info'), 'tight')}

${sec('proces', head('Typický proces', 'Založte klienta, <span class="gt">potom aktivujte jeho prístup.</span>', 'Každý krok je samostatná operácia. Hostiteľ ukladá výsledky, secret store a stav onboardingu vo vlastnej aplikácii.') + flow([['createClient()', 'POST klienta s hlavičkou X-Integrator-Token a stabilným Idempotency-Key.', '', 'Vytvorenie'], ['Uložte client ID a secret', 'Secret z odpovede bezpečne uložte hneď; detail klienta ho neskôr nevráti.', '', 'Poverenia'], ['Nastavte zoznam účastníkov', 'replaceClientParticipantAccess() nahrádza celý zoznam oprávnení klienta.', '', 'Oprávnenia'], ['Vyžiadajte kontrakt', 'requestParticipantActivation() s režimom SEND_ONLY alebo SEND_AND_RECEIVE.', '', 'Aktivácia'], ['Volajte SAPI a Gateway', 'Klient získa OAuth token cez SAPI; Participant sa posiela pri konkrétnej operácii.', '', 'Prevádzka']], 5) + note('Rotácia secretu vráti novú jednorazovú hodnotu a automaticky sa neopakuje. Pri nejednoznačnom výsledku si stav overte pred ďalšou rotáciou. Poverenia integrátora, OAuth klienta a PDS nikdy nezapisujte do aplikačných logov.', 'warn'))}

${sec('metody', head('Operácie', 'Štyri skupiny <span class="gt">metód.</span>') + cells([
  { top: '1. OAuth klienti', t: 'Správa klientov', p: '<code>listClients()</code>, <code>createClient()</code>, <code>getClient()</code>, <code>rotateClientSecret()</code> a <code>deleteClient()</code>. Vytvorenie používa idempotency kľúč. Secret sa vracia iba pri vytvorení alebo rotácii.' },
  { top: '2. Účastníci a prístupy', t: 'Participanti', p: '<code>listParticipants()</code>, <code>replaceClientParticipantAccess()</code> a <code>requestParticipantActivation()</code>. Pri nahradení pošlite celý požadovaný zoznam; aktiváciu vytvorte s trvalým idempotency UUID.' },
  { top: '3. Mesačný prehľad', t: 'Súhrn dokladov', p: '<code>getDocumentSummary()</code> vracia počty dokladov pre mesiac vo formáte <code>yyyyMM</code>. Zoznamy klientov a účastníkov sa stránkujú pomocou pokračovacieho tokenu.' },
  { top: '4. PDS subjects', t: 'Dávka PDS', p: '<code>submitPdsSubjects()</code> odošle typovanú dávku a použije samostatné podpisové poverenie v <code>X-PDS-Secret</code>. Ide o oddelený tok; Integrator API token sa do PDS požiadavky neposiela.' },
], 'g2') + `<div class="links" style="justify-content:flex-start;margin-top:26px"><a class="btn" href="${WIKI}Integrator-API.html">Nastavenie, PHP príklady a bezpečnosť ↗</a><a class="btn" href="api.html#integrator">Zoznam operácií</a></div>`)}
</div>`];

/* ================= NASTAVENIE ================= */
const ENV = [
  ['EFAKTURA_BASE_URL', 'HTTPS adresa poskytovateľa'], ['EFAKTURA_CLIENT_ID', 'OAuth klient'], ['EFAKTURA_CLIENT_SECRET', 'úložisko tajomstiev / premenná prostredia'], ['EFAKTURA_CLIENT_SCOPE', 'document:send document:receive'], ['EFAKTURA_INTEGRATOR_BASE_URL', 'adresa Integrator API; predvolene EFAKTURA_BASE_URL'], ['EFAKTURA_INTEGRATOR_ID', 'ID integrátora'], ['EFAKTURA_INTEGRATOR_API_TOKEN', 'token Integrator API (tajomstvo)'], ['EFAKTURA_DEFAULT_CLIENT', 'alias profilu pre priame Laravel bindingy'],
  ['EFAKTURA_HTTP_CONNECT_TIMEOUT_SECONDS', 'časový limit pripojenia; predvolene 10 s'], ['EFAKTURA_HTTP_REQUEST_TIMEOUT_SECONDS', 'časový limit cURL; predvolene 45 s'], ['EFAKTURA_HTTP_RESPONSE_MAX_BYTES', 'limit odpovede; 16 MiB, najviac 128 MiB'],
  ['EFAKTURA_HTTP_REQUEST_MAX_BYTES', 'limit tela požiadavky; predvolene 100 MiB'], ['EFAKTURA_HTTP_RETRY_ENABLED', 'opakovanie požiadaviek; predvolene zapnuté'], ['EFAKTURA_HTTP_RETRY_MAX_ATTEMPTS', 'celkové pokusy vrátane prvého; predvolene 3'], ['EFAKTURA_HTTP_RETRY_BASE_DELAY_MS', 'počiatočné oneskorenie; predvolene 500 ms'], ['EFAKTURA_HTTP_RETRY_MAX_DELAY_MS', 'horná hranica exponenciálneho oneskorenia; predvolene 30 s'], ['EFAKTURA_HTTP_RETRY_MAX_INLINE_DELAY_MS', 'najdlhšie čakanie po HTTP odpovedi; predvolene 1 s'],
  ['EFAKTURA_CIRCUIT_BREAKER_ENABLED', 'zapnutie ističa; predvolene zapnuté'], ['EFAKTURA_HTTP_BACKPRESSURE_ENABLED', 'zapnutie cooldownu po 429; predvolene zapnuté'], ['EFAKTURA_HTTP_EXECUTION_LOCK_ENABLED', 'zapnutie HTTP zámku (pri zapnutom backpressure); predvolene zapnuté'], ['EFAKTURA_HTTP_LOGGING_ENABLED', 'logovanie HTTP výmen v Laraveli; predvolene zapnuté'], ['EFAKTURA_HTTP_METRICS_ENABLED', 'metriky HTTP, token cache a Schematron v Laraveli; predvolene zapnuté'], ['EFAKTURA_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 'prahový počet zlyhaní; predvolene 5'], ['EFAKTURA_CIRCUIT_BREAKER_FAILURE_WINDOW_SECONDS', 'časové okno zlyhaní; predvolene 60 s'], ['EFAKTURA_CIRCUIT_BREAKER_OPEN_SECONDS', 'dĺžka otvorenia ističa; predvolene 30 s'], ['EFAKTURA_HTTP_RATE_LIMIT_FALLBACK_COOLDOWN_SECONDS', 'prestávka po 429 bez použiteľného Retry-After; predvolene 1 s'],
  ['EFAKTURA_SCHEMATRON_PROCESS_TIMEOUT_SECONDS', 'limit Saxon procesu; predvolene 60 s'], ['EFAKTURA_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS', 'limit lokálnych validácií; predvolene 2'], ['EFAKTURA_VALIDATION_MAX_ISSUES', 'počet vrátených nálezov; predvolene 8'], ['EFAKTURA_SCHEMATRON_MAX_REPORT_BYTES', 'limit Schematron reportu; predvolene 32 MiB'], ['EFAKTURA_SCHEMATRON_MAX_ERROR_OUTPUT_BYTES', 'limit chybového výstupu procesu; predvolene 64 KiB'], ['EFAKTURA_SCHEMATRON_MAX_ERROR_DETAIL_CHARACTERS', 'dĺžka detailu chyby; predvolene 512 znakov'], ['EFAKTURA_SCHEMATRON_JAVA_MAX_HEAP_MB', 'limit haldy Javy v MB (64 až 65536); predvolene určuje JVM'], ['EFAKTURA_JAVA_BINARY', 'spustiteľný súbor Java; JAVA_HOME/bin, potom PATH'], ['EFAKTURA_VALIDATION_RESOURCE_DIRECTORY', 'voliteľný adresár validačných zdrojov'], ['EFAKTURA_VALIDATION_WORKING_DIRECTORY', 'voliteľný pracovný adresár validácie'], ['EFAKTURA_CACHE_STORE', 'spoločný Laravel cache store pre token, cooldown a istič'], ['EFAKTURA_PDS_SIGNING_SECRET', 'tajomstvo podpisu PDS pre Integrator API'],
  ['EFAKTURA_HTTP_EXECUTION_LOCK_DRIVER', 'predvolene cache; file použije lokálny súborový zámok'], ['EFAKTURA_HTTP_EXECUTION_LOCK_STORE', 'voliteľný pomenovaný Laravel cache store'], ['EFAKTURA_HTTP_EXECUTION_LOCK_LEASE_SECONDS', 'platnosť zámku; bez hodnoty sa odvodí z timeoutu jedného pokusu + 5 s (50 s pri predvolených 45 s); výslovná hodnota nesmie byť kratšia'], ['EFAKTURA_HTTP_EXECUTION_LOCK_WAIT_SECONDS', 'čakanie na zámok; predvolene 120 s'], ['EFAKTURA_HTTP_EXECUTION_LOCK_DIRECTORY', 'adresár HTTP zámkov iba pri driver=file'], ['EFAKTURA_SCHEMATRON_LOCK_DIRECTORY', 'voliteľný adresár zámkov súbežnej validácie'], ['APP_KEY', 'šifrovanie vyrovnávacej pamäte tokenov'],
];
pages['nastavenie.html'] = ['Nastavenie', 'Požiadavky, inštalácia, premenné prostredia EFAKTURA_* a konfigurácia konektora.', () => `
${phead('Nastavenie', 'Pripravte <span class="gt">prostredie.</span>', 'Základný transport potrebuje PHP a rozšírenia uvedené v Composer manifeste. Java je potrebná iba pri lokálnej Schematron validácii.', toc([['poziadavky', 'Požiadavky'], ['kroky', 'Prvé kroky'], ['env', 'Premenné prostredia'], ['konfig', 'Konfigurácia']]))}
<div class="wrap">
${sec('poziadavky', head('Prevádzkové prostredie', 'Čo je <span class="gt">potrebné.</span>') + `<div class="grid g2"><div class="cell rv"><h3>Jadro</h3><ul><li>PHP 7.4 alebo novšie</li><li>Rozšírenia: cURL, ctype, DOM, hash, JSON, libxml, mbstring</li><li>Validačné schémy a pravidlá sú pribalené; aplikácia ich nemusí kopírovať</li></ul></div><div class="cell rv"><h3>Voliteľné</h3><ul><li>Java 11+ a povolené <code>proc_open</code> iba pre Schematron</li><li><code>flock</code> a zapisovateľný dočasný adresár na súborovom systéme so zámkami pre Schematron alebo HTTP <code>driver=file</code>. Laravel štandardne používa atomické cache zámky</li><li>Laravel: <code>illuminate/support</code> 8.83–13. CI testuje bridge na Laravel 13/PHP 8.4 a jadro od PHP 7.4</li></ul></div></div>`, 'tight')}

${sec('kroky', head('Prvé kroky', 'Tri kroky <span class="gt">k spusteniu.</span>') + flow([['Nainštalujte balík', 'Pridajte GitHub VCS repozitár do composer.json a vyžiadajte dream1/efaktura-connector:dev-main; pozrite si inštalačný návod.', '', '01'], ['Nastavte hostiteľskú aplikáciu', 'V Laraveli zverejnite konfiguračný súbor cez vendor:publish --tag=efaktura-connector-config; bez frameworku odovzdajte typovanú konfiguráciu a zostavte služby hostiteľa.', '', '02'], ['Priraďte údaje k operácii', 'Tajomstvá OAuth uložte medzi premenné prostredia alebo do bezpečného úložiska tajomstiev. Účastníka zadávajte podľa konkrétnej organizácie a operácie.', '', '03']], 3) + `<p class="muted rv" style="margin-top:16px"><a href="${WIKI}Installation.html" style="color:var(--p1);font-weight:600">Inštalačný návod ↗</a></p>`)}

${sec('env', head('Najčastejšie premenné', 'Premenné <span class="gt">EFAKTURA_*.</span>', 'Integrácia Laravelu ich načíta cez zverejnený konfiguračný súbor; jadro bez Laravelu samo <code>.env</code> nečíta a hodnoty prijíma cez typovanú konfiguráciu hostiteľa.') + `<div class="search rv"><input id="filter" data-target="envtbl" type="search" placeholder="Hľadať premennú alebo popis…" aria-label="Hľadať premennú"></div>` + table(['Premenná', 'Význam'], ENV.map(r => [`<code>${r[0]}</code>`, r[1]]), 'envtbl', 'var') + `<div class="empty" id="empty">Nič sa nenašlo.</div>` + note('Ostatné limity opakovania, ističa obvodu, riadenia preťaženia a Schematronu možno nastaviť v zverejnenom konfiguračnom súbore. Účastník sa zadáva pri konkrétnej operácii, nie v globálnych nastaveniach. Integrator API používa samostatný token a klienta zostaveného cez factory, nie SAPI env profily. Laravel šifruje vyrovnávaciu pamäť tokenov pomocou <code>APP_KEY</code>; zdieľaný istič obvodu a riadenie preťaženia potrebujú vyrovnávaciu pamäť s atómovými zámkami.', 'info'))}

${sec('konfig', head('Čo sa dá nastaviť?', 'Konfigurácia <span class="gt">konektora.</span>', 'Laravel: <code>config/efaktura-connector.php</code> · bez Laravelu: typovaná konfigurácia a ručné zostavenie služieb.') + cells([
  { top: '01 · Pripojenie', t: 'Pomenovaní OAuth klienti', p: 'Každý profil <code>clients.&lt;alias&gt;</code> má vlastnú <code>base_url</code> (napríklad test aj produkcia naraz), <code>client_id</code>, <code>client_secret</code> a <code>client_scope</code>; <code>base_url</code> na najvyššej úrovni sa odmietne. Každý alias má oddelený token provider; <code>default_client</code> vyberá služby pre priame Laravel bindingy. Tajomstvá ukladajte do bezpečného úložiska.' },
  { top: '02 · Prevádzka', t: 'Časové limity a odolnosť', p: '<code>runtime.http</code>, <code>retry</code>, <code>circuit_breaker</code> a <code>backpressure</code> upravujú časové limity, zapnutie jednotlivých vrstiev (<code>enabled</code>, <code>execution_lock</code>), maximálnu veľkosť odpovede (predvolene 16 MiB), bezpečné opakovanie a reakcie na výpadky. <code>validation</code> a <code>schematron</code> nastavujú limity nálezov, procesov a výstupov.' },
  { top: '03 · Integrácia Laravelu', t: 'Prenos a validácia', p: 'Predvolený vykonávač používa nové pripojenie cURL pri každom volaní; pre dlhobežiace procesy možno zvoliť <code>PooledCurlRequestExecutor</code>. V <code>laravel.request_execution</code> nastavíte vykonávač, logovanie a metriky, v <code>laravel.validation.stages</code> postup validačných krokov a cez <code>laravel.services</code> vlastné väzby. Zapnutie ističa, cooldownu a zámku je v <code>runtime.*.enabled</code>. Po nasadení overí nastavenie príkaz <code>php artisan efaktura:check</code> (profil, <code>APP_KEY</code>, cache zámky, validačné zdroje a Java).' },
]) + `<h3 class="sec">Premenné prostredia · testovací vzor</h3>${code(9, '.env', 'env')}${note('Tieto OAuth premenné nastavia profil <code>default</code>. Ďalšie profily pridajte do mapy <code>clients</code> v Laravel konfigurácii. Vyberte ich cez <code>app(SapiClientRegistryInterface::class)->client(\'tenant-a\')</code>; priame service bindingy používajú <code>default_client</code>.', 'info')}<h3 class="sec">Proces fronty úloh</h3><p class="muted" style="margin:0">Pre dlhobežiace procesy fronty úloh alebo procesy Octane. Nastavenie patrí do zverejneného súboru <code>config/efaktura-connector.php</code>.</p>${code(10, 'config/efaktura-connector.php')}<div class="links" style="justify-content:flex-start;margin-top:26px"><a class="btn" href="${WIKI}Configuration.html">Podrobná konfigurácia ↗</a><a class="btn" href="${WIKI}Laravel.html">Integrácia Laravelu a vlastné väzby ↗</a><a class="btn" href="${WIKI}Integrator-API.html">Integrator API ↗</a></div>`)}
</div>`];

/* ================= PREVADZKA ================= */
pages['prevadzka.html'] = ['Prevádzka', 'Výnimky, opakovanie požiadaviek, istič obvodu, koordinácia a bezpečnosť citlivých údajov.', () => `
${phead('Prevádzka', 'Rozlíšte odmietnutie <span class="gt">od zlyhania.</span>', 'Typ výnimky vysvetľuje, čo sa stalo a či poskytovateľ odpovedal. Hostiteľ rozhoduje o preklade, ukladaní a ďalšom pracovnom postupe.', toc([['vynimky', 'Výnimky'], ['retry', 'Opakovanie'], ['klasifikator', 'Klasifikácia zlyhaní'], ['istic', 'Istič obvodu']]))}
<div class="wrap">
${sec('vynimky', head('Typy výnimiek', 'Čo ktorá <span class="gt">znamená.</span>', 'Správy výnimiek sú technická angličtina pre diagnostiku; knižnica ich nemení na lokalizované texty pre používateľa.') + cells([
  { t: 'ProviderErrorResponseException', p: 'Poskytovateľ odpovedal stavovým kódom mimo rozsahu 2xx. K dispozícii sú stav, hlavičky, pôvodné telo a podľa formátu aj parsovaná obálka cez <code>errorEnvelope()</code>. Typovaná kategória rozpozná AUTH, VALIDATION, PROCESSING, TEMPORARY a PERMANENT; neznáme hodnoty sa zachovajú.' },
  { t: 'ProviderConnectionException', p: 'Chyba spojenia, vyčerpaný počet opakovaní alebo otvorený istič (podtrieda <code>CircuitBreakerOpenException</code>). Pri chybe siete nemusí byť známe, či poskytovateľ požiadavku prijal.' },
  { t: 'RequestCoordinationException', p: 'Konfigurovaný HTTP zámok sa nepodarilo vytvoriť alebo získať; chránená požiadavka sa nevykonala.' },
  { t: 'DocumentPreflightException', p: 'Kontrola dostala úspešnú odpoveď, ktorá dokument zamietla. Nie je to HTTP chyba.' },
  { t: 'InvalidProviderResponseException', p: 'Prišla 2xx odpoveď, ale telo nezodpovedá deklarovanej API schéme.' },
  { t: 'ProviderResponseTooLargeException', p: 'Telo odpovede prekročilo nakonfigurovaný limit (predvolene 16 MiB); prenos sa zastaví a automatické opakovanie sa nevykoná.' },
  { t: 'UblValidationException', p: 'Abstraktný predok chýb pri načítaní XML, podpore typu UBL, XSD, Schematron alebo základných fakturačných pravidlách.' },
  { t: 'LocalValidationInfrastructureException', p: 'Lokálnu kontrolu nebolo možné spustiť kvôli Jave, Saxonu, procesu alebo validačným zdrojom. Nehovorí nič o platnosti dokladu.' },
  { t: 'RequestDeferredException', p: 'Požiadavka bola odložená pred odoslaním (aktívny cooldown po 429). Termín sprístupňuje <code>retryAt()</code>.' },
  { t: 'TokenRequestException', p: 'Zlyhalo samotné získanie OAuth tokenu; pôvodná príčina je v <code>getPrevious()</code>. Odloženie cez backpressure sa neobalí a vyhodí sa priamo ako <code>RequestDeferredException</code>.' },
  { t: 'RequestEncodingException', p: 'Telo požiadavky sa nedá zakódovať. Volanie sa ešte neodoslalo.' },
  { t: 'RequestBodyTooLargeException', p: 'Serializované telo dávky prekročilo lokálny limit (<code>maxBytes()</code>); HTTP volanie sa nespustilo.' },
  { t: 'ConfigurationException', p: 'Chýbajúca alebo neplatná konfigurácia, napríklad URL, poverenia alebo neznámy kľúč.' },
], 'g3'), 'tight')}

${sec('retry', head('Opakovanie a neistá požiadavka', 'Čo sa opakuje <span class="gt">a čo nie.</span>') + `<div class="grid g3">
<div class="cell rv"><span class="num">3</span><h4>pokusy celkovo</h4><p>Pri chybe spojenia čaká exponenciálne (500 ms, potom 1 s).</p></div>
<div class="cell rv"><span class="num">1 s</span><h4>najviac inline čakanie</h4><p>Po HTTP odpovedi čaká synchrónne najviac 1 s.</p></div>
<div class="cell rv"><span class="num">POST</span><h4>odosielanie sa neopakuje</h4><p>Po nejednoznačnej chybe sa odosielacia požiadavka POST na SAPI automaticky neopakuje.</p></div></div>` + `<ul class="list rv" style="max-width:820px;margin-top:28px"><li>Odpovede 5xx/429 sa opakujú iba pri bezpečnej požiadavke s rozpoznanou chybovou obálkou <code>retryable: true</code>.</li><li>Ak platná hlavička <code>Retry-After</code> vyžaduje dlhšie čakanie, odpoveď sa vráti hostiteľovi a výnimka sprístupní termín cez <code>retryAt()</code>.</li><li>Konečná odpoveď 429 nastaví prestávku podľa <code>Retry-After</code> alebo na predvolenú 1 s; počas aktívnej prestávky pre rovnaký transportný scope sa požiadavka odloží cez <code>RequestDeferredException</code> ešte pred odoslaním.</li><li>SAPI/Gateway scope vychádza z OAuth profilu, Integrator scope z URL, ID integratora a tokenu; pri požiadavke bez explicitného scope sa použije hash <code>Authorization</code>.</li></ul>` + note('<b>Neistý výsledok:</b> uložte payload a idempotency key a pri opakovaní použite presne ten istý. Nezapisujte nespracované telo chyby ani XML do logov.', 'warn'))}

${sec('klasifikator', head('Rozhodnutie z jedného miesta', 'Klasifikácia <span class="gt">zlyhaní.</span>', '<code>FailureClassifier::classify()</code> vráti <code>FailureAssessment</code>, takže fronta alebo plánovač nemusí skúmať triedy výnimiek. Je to čistá funkcia: nič neposiela a o postupe rozhoduje hostiteľ.') + table(['Metóda', 'Význam'], [
  ['<code>category()</code>', 'Jedna z kategórií: <code>authentication</code>, <code>deferred</code>, <code>transient</code>, <code>rejected</code>, <code>invalid_response</code>, <code>invalid_input</code>, <code>configuration</code>, <code>internal</code>.'],
  ['<code>isRetryable()</code>', 'Zopakovanie rovnakej operácie môže uspieť bez zmeny vstupu alebo konfigurácie.'],
  ['<code>isOutcomeUnknown()</code>', 'Poskytovateľ mohol požiadavku spracovať bez toho, aby hostiteľ poznal výsledok (sieť, 5xx, 408, nečitateľná 2xx).'],
  ['<code>isSafeToRepeatBlindly()</code>', 'Retryable a požiadavka určite neodišla; iba vtedy je opakovanie bez rizika duplicity.'],
  ['<code>needsAdministratorAttention()</code>', 'Chybné poverenia, scope alebo konfigurácia; opakovanie nepomôže.'],
  ['<code>retryAt()</code>', 'Najskorší odporúčaný čas z <code>Retry-After</code> alebo z lokálneho backpressure.'],
]) + note('<code>isRetryable()</code> neznamená opakovať naslepo. Pri odoslaní dokladu zopakujte s rovnakým payloadom a idempotency key, alebo najprv skontrolujte stav cez tracking.', 'warn') + `<div class="links" style="justify-content:flex-start;margin-top:26px"><a class="btn" href="${WIKI}Errors-Retry-and-Idempotency.html">Chyby, retry a idempotencia ↗</a></div>`)}
${sec('istic', head('Istič obvodu a koordinácia', 'Ochrana pred <span class="gt">kaskádou výpadkov.</span>') + `<div class="grid g3">
<div class="cell rv"><span class="num">5</span><h4>neúspešných operácií</h4><p>Operácia, ktorá po všetkých pokusoch skončí chybou spojenia alebo 5xx, v priebehu 60 sekúnd. HTTP 429 sa za zlyhanie nepočíta.</p></div>
<div class="cell rv"><span class="num">30 s</span><h4>otvorený istič</h4><p>Potom povolí jednu skúšobnú požiadavku.</p></div>
<div class="cell rv"><span class="num">120 s</span><h4>čakanie na zámok</h4><p>Platnosť zámku sa bez nastavenia odvodí z timeoutu jedného pokusu plus 5 s (50 s pri predvolených 45 s); výslovne zadaná hodnota nesmie byť kratšia. Ak zámok nemožno získať, požiadavka sa neodošle (RequestCoordinationException).</p></div></div>` + `<ul class="list rv" style="max-width:820px;margin-top:28px"><li>Laravel Cache zdieľa cooldown a stav ističa medzi procesmi, ak store podporuje atómové zámky. Pri výpadku cache istič prejde na stav procesu a cooldown sa nemusí uložiť.</li><li>Predvolený Laravel HTTP zámok používa <code>Cache::lock()</code> a chráni kontrolu cooldownu a jeden HTTP pokus; pred čakaním retry vrstvy sa uvoľní.</li><li>Samostatné použitie Core a Laravel nastavenie <code>driver=file</code> používajú <code>flock</code>; ten koordinuje iba procesy s rovnakou kanonickou cestou aplikácie a OS používateľom na tom istom hostiteľovi. Ostatné rozsahy sa neblokujú.</li></ul>` + note('Telo odpovede poskytovateľa, OAuth poverenia a XML dokumenty považujte za citlivé údaje. Logovanie používa samostatnú sanitizáciu a nesmie zapisovať surové poverenia ani obsah dokladu.', 'info') + `<div class="links" style="justify-content:flex-start;margin-top:26px"><a class="btn" href="${WIKI}Errors-Retry-and-Idempotency.html">Chyby, retry a idempotencia ↗</a><a class="btn" href="${WIKI}Transport-and-Authentication.html">Transport a autentifikácia ↗</a><a class="btn" href="${WIKI}Troubleshooting.html">Riešenie problémov ↗</a></div>`)}
</div>`];

/* ================= DOKUMENTACIA ================= */
const DOCS = [["Prehľad","Dokumentácia balíka","Home.md"],["Začíname","Inštalácia","Installation.md"],["Začíname","Konfigurácia","Configuration.md"],["Začíname","Transport a autentifikácia","Transport-and-Authentication.md"],["Laravel","Laravel bridge","Laravel.md"],["Laravel","OAuth poverenia zákazníka","Tenant-Clients-in-Laravel.md"],["Doklady","Praktické scenáre a diagramy","Practical-Scenarios.md"],["Doklady","Čítanie a zápis UBL","UBL-Documents.md"],["Doklady","Lokálna validácia","Validation.md"],["Doklady","Odosielanie dokladov","Sending-Documents.md"],["Doklady","Príjem dokladov","Receiving-Documents.md"],["Doklady","Stav, Discovery a webhooky","Tracking-and-Webhooks.md"],["Referencia","Prehľad API","API-Reference.md"],["Referencia","Integrator API","Integrator-API.md"],["Referencia","Verejné PHP API","Public-API.md"],["Referencia","Graf toku API requestov","Request-Flow.md"],["Referencia","Tok dát a triedy","Data-Flow-and-Classes.md"],["Referencia","Štruktúra balíka","Project-Structure.md"],["Referencia","API špecifikácie a pokrytie","Source-Alignment.md"],["Referencia","Výnimky, opakovanie a idempotencia","Errors-Retry-and-Idempotency.md"],["Prevádzka","Architektúra a testovanie","Architecture-and-Testing.md"],["Prevádzka","Riešenie problémov","Troubleshooting.md"]];
pages['dokumentacia.html'] = ['Dokumentácia', 'Odkazy na podrobnú dokumentáciu v repozitári: wiki, API referencia, OpenAPI a validačné zdroje.', () => `
${phead('Dokumentácia', 'Pokračujte <span class="gt">podľa témy.</span>', 'Táto stránka ponúka stručný prehľad modulu. Podrobné príklady a prevádzkové poznámky sú v lokálnej dokumentácii repozitára.')}
<div class="wrap">
<section class="tight">
<div class="grid g4" style="margin-top:0">${DOCS.map(d => `<a class="cell rv" href="wiki/${d[2] === 'Home.md' ? 'index' : d[2].slice(0, -3)}.html"><small class="top">${d[0]}</small><h4>${d[1]}</h4><span class="more">Otvoriť ↗</span></a>`).join('')}
<a class="cell rv" href="../resources/validation/README.md"><small class="top">Údržba</small><h4>Validačné zdroje</h4><span class="more">Otvoriť ↗</span></a>
<a class="cell rv" href="../docs/eFaktura/api/openapi.json"><small class="top">Kontrakt</small><h4>OpenAPI</h4><span class="more">Otvoriť ↗</span></a></div>
${note('Témy sa otvárajú ako HTML stránky v sekcii <a href="wiki/index.html">Wiki</a>, ktorú generuje <code>tools/build-wiki.js</code> zo zdrojov v <code>docs/wiki</code>. Odkazy na validačné zdroje a OpenAPI mieria na súbory v repozitári.', 'info')}
</section>
</div>`];

/* ---------- write ---------- */
fs.mkdirSync(OUT, { recursive: true });
for (const [file, [title, desc, fn]] of Object.entries(pages)) {
  const active = file === 'dokumentacia.html' ? '' : file;
  fs.writeFileSync(path.join(OUT, file), layout(file, title, desc, fn(), active));
  console.log('wrote', file);
}
