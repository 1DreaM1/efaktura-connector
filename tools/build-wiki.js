// Generátor encyklopedickej wiki web/wiki/ zo zdrojov docs/wiki/*.md.
// Spustenie z koreňa repozitára: node tools/build-wiki.js
// Poradie a skupiny stránok sa berú z docs/wiki/_Sidebar.md. CSS a JS v web/wiki/assets sa upravujú ručne.
const fs = require('fs'), path = require('path'), cp = require('child_process');
const { marked } = require('./vendor/marked.umd.js');
const { improve, sentences, group } = require('./wiki-text.js');

const ROOT = path.resolve(__dirname, '..');
const SRC = ROOT + '/docs/wiki/';
const OUT = ROOT + '/web/wiki';
const REPO = 'https://github.com/1DreaM1/efaktura-connector';
const MERMAID = 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.min.js';

const escH = t => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const slugify = t => t.toLowerCase().replace(/<[^>]+>/g, '').replace(/&[a-z]+;/g, '').replace(/[^\p{L}\p{N}\s_-]/gu, '').trim().replace(/\s/g, '-');
const plain = h => h.replace(/<[^>]+>/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();
const outName = n => (n === 'Home' ? 'index' : n) + '.html';
const read = f => fs.readFileSync(f, 'utf8').replace(/^﻿/, '').replace(/\r\n/g, '\n');

/* ---------- štruktúra z _Sidebar.md ---------- */
const order = [];
{
  let group = 'Úvod';
  for (const line of read(SRC + '_Sidebar.md').split('\n')) {
    let m;
    if ((m = /^###\s+(.+)$/.exec(line))) group = m[1].trim();
    else if ((m = /^-\s+\[(.+?)\]\((.+?)\)/.exec(line))) order.push({ name: m[2], label: m[1], group: m[2] === 'Home' ? 'Úvod' : group });
  }
}
const names = new Set(fs.readdirSync(SRC).filter(f => /\.md$/.test(f) && f[0] !== '_').map(f => f.slice(0, -3)));
for (const n of names) if (!order.some(o => o.name === n)) throw new Error('wiki page missing in _Sidebar.md: ' + n);
for (const o of order) if (!names.has(o.name)) throw new Error('_Sidebar.md links to a missing page: ' + o.name);
const groups = [];
order.forEach(o => { let g = groups.find(x => x.title === o.group); if (!g) groups.push(g = { title: o.group, pages: [] }); g.pages.push(o); });

function fixHref(href) {
  if (/^([a-z][a-z0-9+.-]*:|#|\/\/)/i.test(href)) return href;
  const m = /^([^#?]*)([#?].*)?$/.exec(href), p = m[1], rest = m[2] || '';
  const name = p.replace(/\.md$/, '');
  if (names.has(name)) return outName(name) + rest;
  if (p.startsWith('../eFaktura/')) return '../../docs/' + p.slice(3) + rest;
  return href;
}

/* ---------- čitateľnosť: kratšie odseky a upozornenia (callouts) ---------- */
const ALERTS = {
  warn: { title: 'Pozor', icon: '!' },
  info: { title: 'Poznámka', icon: 'i' },
  tip: { title: 'Tip', icon: '★' }
};
const RULES = [
  ['warn', /^(Nikdy|Nepoužívajte|Neukladajte|Nelogujte|Neopakujte|Nevolajte|Neposielajte|Nevypisujte|Pozor|Upozornenie)\b/],
  ['warn', /\b(nesmie|nesmú|nikdy|nezaručuje|nepotvrdzuje)\b/i],
  ['warn', /\b(výsledok|stav)\b[^.]{0,40}\bneist/i],
  ['warn', /nie je (bezpečn|vhodn|automaticky)/i],
  ['info', /^(Poznámka|Pozn\.)/],
  ['info', /^Hostiteľská aplikácia (je |zodpoved|vlastní|rozhoduje|zabezpečuje|vyberá|stále|musí|overuje)/],
  ['info', /^(Konektor|Knižnica|Balík) (neukladá|nevykonáva|neurčuje|nemá|neposkytuje|nespúšťa|nerobí|nevolá)/],
  ['tip', /^(Odporúč|Preferujte|Použite|Vhodné je|Najlepšie|Najjednoduchší)/]
];
const textOf = h => plain(h);
function classify(sentence) {
  const t = textOf(sentence);
  if (t.length < 40) return null;
  for (const [kind, re] of RULES) if (re.test(t)) return kind;
  return null;
}
const BLOCKISH = /<(ul|ol|li|pre|table|div|details|h[1-6]|blockquote|br)\b/i;
function alertHtml(kind, inner) {
  const a = ALERTS[kind];
  return `<div class="alert ${kind}" role="note"><div class="ai" aria-hidden="true">${a.icon}</div><div class="ac"><div class="at">${a.title}</div><p>${inner}</p></div></div>`;
}
/** Rozdelí dlhé odseky po vetách (cca 2 vety) a dôležité vety vyberie do upozornení. */
function enrich(html) {
  return html.replace(/<p>([\s\S]*?)<\/p>/g, (m, inner) => {
    if (BLOCKISH.test(inner)) return m;
    const parts = sentences(inner);
    if (parts.length < 2 && !classify(inner)) return m;
    const out = []; let buf = [];
    const flush = () => { if (buf.length) { group(buf, 190).forEach(g => out.push('<p>' + g + '</p>')); buf = []; } };
    for (const s of parts) {
      const kind = classify(s);
      if (kind) { flush(); out.push(alertHtml(kind, s)); } else buf.push(s);
    }
    flush();
    return out.join('\n');
  });
}

/* ---------- renderovanie jednej stránky ---------- */
function render(name, md) {
  const seen = {}, heads = []; let mermaid = false;
  const renderer = new marked.Renderer();
  renderer.heading = function (tok) {
    const inner = this.parser.parseInline(tok.tokens);
    let id = slugify(tok.text); if (seen[id] !== undefined) id += '-' + (++seen[id]); else seen[id] = 0;
    if (tok.depth === 2 || tok.depth === 3) heads.push({ depth: tok.depth, id, text: plain(inner) });
    const edit = tok.depth === 2 ? `<span class="edit">[<a href="${REPO}/edit/main/docs/wiki/${name}.md" rel="noopener">upraviť zdroj</a>]</span>` : '';
    return `<h${tok.depth} id="${id}"><span class="mw-h">${inner}</span><a class="anchor" href="#${id}" aria-label="Odkaz na sekciu">¶</a>${edit}</h${tok.depth}>\n`;
  };
  renderer.link = function (tok) {
    const href = fixHref(tok.href), ext = /^https?:/i.test(href);
    return `<a href="${escH(href)}"${ext ? ' class="ext" rel="noopener"' : ''}${tok.title ? ` title="${escH(tok.title)}"` : ''}>${this.parser.parseInline(tok.tokens)}</a>`;
  };
  renderer.code = function (tok) {
    const lang = (tok.lang || '').split(/\s/)[0];
    if (lang === 'mermaid') { mermaid = true; return `<pre class="mermaid">${escH(tok.text)}</pre>\n`; }
    return `<div class="codebox"><pre tabindex="0"><code${lang ? ` class="language-${escH(lang)}" data-lang="${escH(lang)}"` : ''}>${escH(tok.text)}</code></pre></div>\n`;
  };
  renderer.blockquote = function (tok) {
    let body = this.parser.parse(tok.tokens), title = 'Poznámka';
    const m = /^<p><strong>([^<]+?):?<\/strong>:?\s*/.exec(body);
    if (m) { title = m[1]; body = body.replace(m[0], '<p>'); }
    return `<div class="alert info" role="note"><div class="ai" aria-hidden="true">i</div><div class="ac"><div class="at">${title}</div>${body}</div></div>\n`;
  };
  renderer.table = function (tok) {
    const cell = c => `<${c.header ? 'th' : 'td'}${c.align ? ` style="text-align:${c.align}"` : ''}>${this.parser.parseInline(c.tokens)}</${c.header ? 'th' : 'td'}>`;
    return `<div class="tw"><table class="wikitable"><thead><tr>${tok.header.map(cell).join('')}</tr></thead><tbody>${tok.rows.map(r => `<tr>${r.map(cell).join('')}</tr>`).join('')}</tbody></table></div>\n`;
  };
  const html = marked.parse(md, { renderer, gfm: true, async: false })
    .replace(/(<a\s[^>]*href=")([^"#]+?)(\.md)?([#"])/g, (m, a, h, md_, e) => (names.has(h) && !h.endsWith('.html')) ? a + outName(h) + e : m);
  return { html: improve(enrich(html)), heads, mermaid };
}

function lastEdit(name) {
  let iso = '';
  try { iso = cp.execSync(`git log -1 --format=%cs -- docs/wiki/${name}.md`, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim(); } catch (e) { /* bez gitu */ }
  if (!iso) iso = fs.statSync(SRC + name + '.md').mtime.toISOString().slice(0, 10);
  const [y, m, d] = iso.split('-').map(Number);
  return `${d}. ${m}. ${y}`;
}

const pages = order.map(o => {
  let src = read(SRC + o.name + '.md');
  src = src.replace(/<details>\s*<summary>Obsah stránky<\/summary>[\s\S]*?<\/details>\s*/, '');
  const h1 = /^#\s+(.+)$/m.exec(src);
  const title = h1 ? h1[1].replace(/[`*_]/g, '') : o.label;
  src = src.replace(/^#\s+.+\n+/, '');
  const r = render(o.name, src);
  const cut = r.html.search(/<h2[\s>]/);
  const lead = cut < 0 ? r.html : r.html.slice(0, cut), rest = cut < 0 ? '' : r.html.slice(cut);
  const leadText = plain(/<p>([\s\S]*?)<\/p>/.exec(lead) ? /<p>([\s\S]*?)<\/p>/.exec(lead)[1] : title);
  return Object.assign({}, o, { title, lead, rest, heads: r.heads, mermaid: r.mermaid, summary: leadText, edited: lastEdit(o.name), full: r.html });
});
const byName = {}; pages.forEach(p => { byName[p.name] = p; });

/* odkazy sem (spätné odkazy) */
const backlinks = {}; pages.forEach(p => { backlinks[p.name] = []; });
for (const p of pages) {
  const targets = new Set();
  p.full.replace(/href="([A-Za-z0-9-]+)\.html[#"]/g, (m, t) => { targets.add(t === 'index' ? 'Home' : t); return m; });
  targets.forEach(t => { if (t !== p.name && backlinks[t]) backlinks[t].push(p.name); });
}

/* ---------- šablóna ---------- */
const comp = JSON.parse(fs.readFileSync(ROOT + '/composer.json', 'utf8'));

function sidebar(active) {
  const link = (href, text, cur) => `<a href="${href}"${cur ? ' aria-current="page"' : ''}>${text}</a>`;
  return `<nav class="side" id="side" aria-label="Navigácia wiki">
<div class="portal"><h3>Navigácia</h3>${link('index.html', 'Hlavná stránka', active === 'Home')}${link('vsetky-stranky.html', 'Všetky stránky', active === '@all')}${link('search.html', 'Hľadať', active === '@search')}</div>
${groups.filter(g => g.title !== 'Úvod').map(g => `<div class="portal"><h3>${escH(g.title)}</h3>${g.pages.map(p => link(outName(p.name), escH(p.label), active === p.name)).join('')}</div>`).join('\n')}
<div class="portal"><h3>Nástroje</h3><a href="${REPO}" rel="noopener">Repozitár na GitHube ↗</a><a href="../../docs/eFaktura/api/openapi.json">OpenAPI špecifikácia</a><a href="../index.html">Web balíka</a></div>
</nav>`;
}

function layout(file, title, desc, active, inner, extra) {
  extra = extra || {};
  const tabs = extra.tabs || '';
  return `<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="${escH(desc)}">
<title>${escH(title)} – Wiki eFaktúra Connector</title>
<script>try{var t=localStorage.getItem('wiki-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="assets/wiki.css">
</head>
<body>
<a class="skip" href="#content">Preskočiť na obsah</a>
<header class="hd">
  <button class="hbtn burger" id="burger" type="button" aria-label="Navigácia" aria-expanded="false" aria-controls="side">☰</button>
  <a class="brand" href="index.html"><span class="emb">€</span><span class="bt"><b>Wiki</b><small>Konektor eFaktúr</small></span></a>
  <form class="search" action="search.html" role="search" autocomplete="off">
    <input type="search" name="q" id="q" placeholder="Hľadať vo wiki ( / )" aria-label="Hľadať vo wiki" autocomplete="off">
    <button type="submit" aria-label="Hľadať">⌕</button>
    <div class="sug" id="sug" role="listbox" hidden></div>
  </form>
  <div class="hr"><button class="hbtn" id="theme" type="button" aria-label="Zmeniť tému">◐</button><a class="hlink" href="../index.html">Web balíka</a></div>
</header>
<div class="shell">
${sidebar(active)}
<div class="scrim" id="scrim"></div>
<main class="page" id="main">
${tabs}
<article id="content">
${inner}
</article>
<footer class="ft">${extra.footer || ''}<p class="lic">Wiki je súčasťou repozitára <a href="${REPO}" rel="noopener">${escH(comp.name)}</a> a šíri sa pod licenciou ${escH(comp.license || 'MIT')}. Stránky sa generujú zo zdrojov <code>docs/wiki</code> príkazom <code>node tools/build-wiki.js</code>.</p></footer>
</main>
</div>
<script src="assets/search-index.js"></script>
<script src="assets/wiki.js"${extra.mermaid ? ` data-mermaid="${MERMAID}"` : ''}></script>
</body>
</html>
`;
}

function tocBox(heads) {
  if (heads.length < 3) return '';
  let a = 0, b = 0, out = '';
  heads.forEach(h => {
    if (h.depth === 2) { a++; b = 0; } else { if (!a) a = 1; b++; }
    const num = h.depth === 2 ? a : a + '.' + b;
    out += `<li class="t${h.depth}"><a href="#${h.id}"><span class="n">${num}</span> <span class="x">${escH(h.text)}</span></a></li>`;
  });
  return `<nav class="toc" id="toc" aria-label="Obsah stránky"><div class="toch"><b>Obsah</b> <button type="button" id="toct" aria-expanded="true">skryť</button></div><ol>${out}</ol></nav>`;
}

function infobox() {
  const php = (comp.require && comp.require.php) || '';
  const row = (k, v) => `<tr><th scope="row">${k}</th><td>${v}</td></tr>`;
  return `<aside class="infobox" aria-label="Základné údaje"><div class="ib-t">Konektor eFaktúr</div><div class="ib-s">PHP knižnica pre slovenskú e-fakturáciu</div><table>
${row('Balík', `<code>${escH(comp.name)}</code>`)}
${row('Jazyk', 'PHP ' + escH(php.replace(/^[\^>=~\s]+/, '') + (/^[\^>]/.test(php) ? '+' : '')))}
${row('Licencia', escH(comp.license || 'MIT'))}
${row('Rozhrania', 'SAPI-SK, Peppol Gateway, Integrator API')}
${row('Formát dokladov', 'UBL 2.1 (Invoice, CreditNote)')}
${row('Voliteľne', 'Laravel bridge')}
${row('Repozitár', `<a class="ext" href="${REPO}" rel="noopener">1DreaM1/efaktura-connector</a>`)}
</table></aside>`;
}

function pageHtml(p) {
  const i = order.findIndex(o => o.name === p.name), prev = order[i - 1], next = order[i + 1];
  const siblings = groups.find(g => g.title === p.group).pages.filter(x => x.name !== p.name);
  const see = [...new Set([prev, next].filter(Boolean).concat(siblings.slice(0, 4)))].filter(x => x.name !== p.name);
  const back = backlinks[p.name];
  const li = x => `<li><a href="${outName(x.name || x)}">${escH(x.label || byName[x].title)}</a></li>`;
  const footer = `<div class="cats"><b>Kategória:</b> <a href="vsetky-stranky.html#g-${slugify(p.group)}">${escH(p.group)}</a></div>
<div class="meta">Naposledy upravené: ${p.edited} · <a href="${REPO}/blob/main/docs/wiki/${p.name}.md" rel="noopener">zdrojový súbor</a> · <a href="${REPO}/commits/main/docs/wiki/${p.name}.md" rel="noopener">história</a></div>`;
  const nav = `<div class="pn">${prev ? `<a href="${outName(prev.name)}"><small>← Predchádzajúca</small><b>${escH(prev.label)}</b></a>` : '<span></span>'}${next ? `<a class="nx" href="${outName(next.name)}"><small>Ďalšia →</small><b>${escH(next.label)}</b></a>` : ''}</div>`;
  const alsoBox = `<section class="also"><h2 class="plain">Pozri aj</h2><ul>${see.slice(0, 6).map(li).join('')}</ul>${back.length ? `<h2 class="plain">Odkazuje sem</h2><ul>${back.slice(0, 8).map(li).join('')}</ul>` : ''}</section>`;
  const tabs = `<div class="tabs"><div class="tl"><a class="on" href="${outName(p.name)}">Stránka</a><a href="vsetky-stranky.html">Všetky stránky</a></div><div class="tr"><a href="${REPO}/edit/main/docs/wiki/${p.name}.md" rel="noopener">Upraviť zdroj</a><a href="${REPO}/commits/main/docs/wiki/${p.name}.md" rel="noopener">História</a></div></div>`;
  const words = plain(p.full).split(/\s+/).length, mins = Math.max(1, Math.round(words / 190));
  const crumbs = `<a href="index.html">Wiki</a><i>›</i><a href="vsetky-stranky.html#g-${slugify(p.group)}">${escH(p.group)}</a>`;
  const inner = `<div class="crumbs">${crumbs}</div><h1 class="title">${escH(p.title)}</h1><div class="sub"><span class="chip">Čítanie ≈ ${mins} min</span><span class="chip">${p.heads.filter(h => h.depth === 2).length} sekcií</span><span class="chip">Upravené ${p.edited}</span></div>
<div class="pagegrid"><div class="body">${p.lead.replace(/^<p>/, '<p class="lead">')}${p.rest}</div><aside class="rail">${p.name === 'Home' ? infobox() : ''}${tocBox(p.heads)}</aside></div>
${nav}${alsoBox}`;
  return layout(outName(p.name), p.title, p.summary.slice(0, 220), p.name, inner, { footer, mermaid: p.mermaid, tabs });
}

/* ---------- zápis ---------- */
fs.mkdirSync(OUT + '/assets', { recursive: true });
for (const p of pages) {
  fs.writeFileSync(path.join(OUT, outName(p.name)), pageHtml(p));
  console.log('wrote wiki/' + outName(p.name));
}

// Všetky stránky
{
  const sorted = pages.slice().sort((a, b) => a.title.localeCompare(b.title, 'sk'));
  const card = e => { const p = byName[e.name]; return `<li><a href="${outName(p.name)}">${escH(p.title)}<span>${escH(p.summary.length > 170 ? p.summary.slice(0, 167).replace(/\s+\S*$/, '') + '…' : p.summary)}</span></a></li>`; };
  const inner = `<h1 class="title">Všetky stránky</h1><div class="sub">Z wiki Konektora eFaktúr</div><div class="body">
<p>Wiki má <b>${pages.length}</b> stránok rozdelených do ${groups.length} kategórií. Ak hľadáte konkrétnu triedu, endpoint alebo konfiguračný kľúč, použite vyhľadávanie v hornej lište.</p>
${groups.map(g => `<h2 id="g-${slugify(g.title)}"><span class="mw-h">${escH(g.title)}</span></h2><ul class="plist">${g.pages.map(card).join('')}</ul>`).join('\n')}
<h2 id="abecedne"><span class="mw-h">Abecedný zoznam</span></h2>
<ul class="alpha">${sorted.map(p => `<li><a href="${outName(p.name)}">${escH(p.title)}</a></li>`).join('')}</ul></div>`;
  fs.writeFileSync(path.join(OUT, 'vsetky-stranky.html'), layout('vsetky-stranky.html', 'Všetky stránky', 'Zoznam všetkých stránok wiki Konektora eFaktúr.', '@all', inner, {}));
  console.log('wrote wiki/vsetky-stranky.html');
}

// Vyhľadávanie
{
  const inner = `<h1 class="title">Hľadanie</h1><div class="sub">Z wiki Konektora eFaktúr</div><div class="body">
<form class="bigsearch" action="search.html" role="search"><input type="search" name="q" id="q2" placeholder="Zadajte hľadaný výraz" aria-label="Hľadaný výraz" autocomplete="off"><button type="submit" class="btn">Hľadať</button></form>
<div id="results" aria-live="polite"><p class="hint">Hľadá sa v názvoch stránok, nadpisoch sekcií aj v texte. Diakritika sa pri hľadaní ignoruje.</p></div></div>`;
  fs.writeFileSync(path.join(OUT, 'search.html'), layout('search.html', 'Hľadanie', 'Vyhľadávanie vo wiki Konektora eFaktúr.', '@search', inner, {}));
  console.log('wrote wiki/search.html');

  const sections = html => {
    const parts = html.split(/(?=<h[23] id=")/), out = [];
    parts.forEach((part, i) => {
      const m = /^<h[23] id="([^"]+)"/.exec(part);
      const heading = m ? plain(/<span class="mw-h">([\s\S]*?)<\/span>/.exec(part)[1]) : '';
      const text = plain(part.replace(/<div class="at">[^<]*<\/div>/g, '').replace(/<span class="edit">[\s\S]*?<\/span>/g, '').replace(/<a class="anchor"[^>]*>.*?<\/a>/g, '')).slice(0, 3500);
      if (m || text) out.push([m ? m[1] : '', heading, m ? text.replace(heading, '').trim() : text]);
    });
    return out;
  };
  const index = pages.map(p => ({ n: outName(p.name), t: p.title, g: p.group, s: sections(p.full) }));
  fs.writeFileSync(OUT + '/assets/search-index.js', '/* Generované: node tools/build-wiki.js */\nwindow.WIKI2_INDEX=' + JSON.stringify(index) + ';\n');
  console.log('wrote wiki/assets/search-index.js');
}
