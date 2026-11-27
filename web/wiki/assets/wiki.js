/* Wiki 2 – správanie stránky: téma, navigácia, hľadanie, obsah, kód, diagramy. Upravuje sa ručne. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  function $$(s, c) { return [].slice.call((c || doc).querySelectorAll(s)); }
  function esc(t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function copyText(t, done) {
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).then(done, function () {}); return; }
    var a = doc.createElement('textarea'); a.value = t; a.style.position = 'fixed'; a.style.opacity = '0'; doc.body.appendChild(a); a.select();
    try { doc.execCommand('copy'); done(); } catch (e) {} doc.body.removeChild(a);
  }

  /* ---------- téma ---------- */
  var themeBtn = doc.getElementById('theme');
  if (themeBtn) themeBtn.addEventListener('click', function () {
    var cur = root.getAttribute('data-theme') || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    var next = cur === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('wiki-theme', next); } catch (e) {}
  });

  /* ---------- bočná navigácia na mobile ---------- */
  var side = doc.getElementById('side'), burger = doc.getElementById('burger'), scrim = doc.getElementById('scrim');
  function toggleSide(open) {
    if (!side) return;
    side.classList.toggle('open', open); if (scrim) scrim.classList.toggle('open', open);
    if (burger) burger.setAttribute('aria-expanded', open);
  }
  if (burger) burger.addEventListener('click', function () { toggleSide(!side.classList.contains('open')); });
  if (scrim) scrim.addEventListener('click', function () { toggleSide(false); });
  var cur = side && side.querySelector('[aria-current]');
  if (cur && side.scrollHeight > side.clientHeight) side.scrollTop = Math.max(0, cur.offsetTop - side.clientHeight / 3);

  /* ---------- obsah stránky ---------- */
  var tocBtn = doc.getElementById('toct'), toc = doc.getElementById('toc');
  if (tocBtn && toc) {
    try { if (localStorage.getItem('wiki-toc') === 'off') { toc.classList.add('off'); tocBtn.textContent = 'zobraziť'; tocBtn.setAttribute('aria-expanded', 'false'); } } catch (e) {}
    tocBtn.addEventListener('click', function () {
      var off = toc.classList.toggle('off');
      tocBtn.textContent = off ? 'zobraziť' : 'skryť'; tocBtn.setAttribute('aria-expanded', !off);
      try { localStorage.setItem('wiki-toc', off ? 'off' : 'on'); } catch (e) {}
    });
  }

  /* ---------- zvýraznenie aktuálnej sekcie v obsahu ---------- */
  if (toc && 'IntersectionObserver' in window) {
    var tmap = {}, tcur = null;
    $$('a', toc).forEach(function (a) { tmap[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting && tmap[e.target.id]) { if (tcur) tcur.classList.remove('on'); tcur = tmap[e.target.id]; tcur.classList.add('on'); }
      });
    }, { rootMargin: '-80px 0px -70% 0px' });
    $$('.body h2[id], .body h3[id]').forEach(function (h) { io.observe(h); });
  }

  /* ---------- hľadanie ---------- */
  var IDX = window.WIKI2_INDEX || [];
  function norm(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  IDX.forEach(function (p) {
    p.nt = norm(p.t);
    p.s.forEach(function (s) { s.push(norm(s[1]), norm(s[2])); }); /* [id, heading, text, nHeading, nText] */
  });
  function count(h, t) { var c = 0, i = -1; while ((i = h.indexOf(t, i + 1)) >= 0 && c < 6) c++; return c; }
  function search(q) {
    var toks = norm(q).split(/[^a-z0-9_.\\:$@-]+/).filter(Boolean);
    if (!toks.length) return [];
    var res = [];
    IDX.forEach(function (p) {
      var best = [], total = 0;
      p.s.forEach(function (s) {
        var sc = 0, ok = true;
        for (var i = 0; i < toks.length; i++) {
          var t = toks[i], inT = p.nt.indexOf(t) >= 0, inH = s[3].indexOf(t) >= 0, c = count(s[4], t);
          if (!inT && !inH && !c) { ok = false; break; }
          sc += (inT ? 8 : 0) + (inH ? 6 : 0) + c;
        }
        if (ok) { if (s[0] === '' ) sc += 2; best.push({ s: s, sc: sc }); }
      });
      if (!best.length) return;
      best.sort(function (a, b) { return b.sc - a.sc; });
      total = best[0].sc + (p.nt === norm(q) ? 30 : 0) + (p.nt.indexOf(toks[0]) === 0 ? 4 : 0);
      res.push({ p: p, total: total, hits: best.slice(0, 3) });
    });
    res.sort(function (a, b) { return b.total - a.total; });
    return { toks: toks, list: res };
  }
  function snippet(s, toks, len) {
    var text = s[2] || s[1], nt = s[4] || '', pos = -1;
    for (var i = 0; i < toks.length && pos < 0; i++) pos = nt.indexOf(toks[i]);
    var start = Math.max(0, pos - 60), out = text.slice(start, start + len);
    if (start > 0) out = '…' + out; if (start + len < text.length) out += '…';
    return mark(esc(out), toks);
  }
  function mark(html, toks) {
    toks.forEach(function (t) {
      if (t.length < 2) return;
      var re = new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
      html = html.replace(re, function (m, a, off, str) {
        var lt = str.lastIndexOf('<', off), gt = str.lastIndexOf('>', off);
        return lt > gt ? m : '<mark>' + a + '</mark>';
      });
    });
    return html;
  }
  function href(p, s) { return p.n + (s && s[0] ? '#' + s[0] : ''); }

  var q = doc.getElementById('q'), sug = doc.getElementById('sug'), active = -1;
  function closeSug() { if (sug) { sug.hidden = true; sug.innerHTML = ''; active = -1; } }
  if (q && sug) {
    q.addEventListener('input', function () {
      var v = q.value.trim(); if (v.length < 2) { closeSug(); return; }
      var r = search(v); active = -1;
      if (!r.list || !r.list.length) { sug.innerHTML = '<div class="none">Nič sa nenašlo.</div>'; sug.hidden = false; return; }
      sug.innerHTML = r.list.slice(0, 7).map(function (x) {
        var h = x.hits[0].s, isLead = h[0] === '';
        return '<a role="option" href="' + esc(href(x.p, h)) + '"><b>' + mark(esc(x.p.t), r.toks) + (isLead ? '' : ' › ' + mark(esc(h[1]), r.toks)) + '</b><span>' + snippet(h, r.toks, 110) + '</span></a>';
      }).join('') + '<a class="all" href="search.html?q=' + encodeURIComponent(v) + '">Zobraziť všetky výsledky (' + r.list.length + ')</a>';
      sug.hidden = false;
    });
    q.addEventListener('keydown', function (e) {
      var items = $$('a', sug);
      if (e.key === 'Escape') { closeSug(); q.blur(); }
      else if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && items.length) {
        e.preventDefault(); active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach(function (a, i) { a.classList.toggle('on', i === active); });
      } else if (e.key === 'Enter' && active >= 0 && items[active]) { e.preventDefault(); location.href = items[active].getAttribute('href'); }
    });
    doc.addEventListener('click', function (e) { if (!e.target.closest || !e.target.closest('.search')) closeSug(); });
  }
  doc.addEventListener('keydown', function (e) {
    var tag = (e.target && e.target.tagName) || '';
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag) && !e.ctrlKey && !e.metaKey && q) { e.preventDefault(); q.focus(); q.select(); }
  });

  var results = doc.getElementById('results');
  if (results) {
    var qs = new URLSearchParams(location.search).get('q') || '', q2 = doc.getElementById('q2');
    if (q2) q2.value = qs;
    if (qs.trim()) {
      var r = search(qs);
      if (!r.list || !r.list.length) results.innerHTML = '<p>Pre „' + esc(qs) + '“ sa nenašla žiadna stránka. Skúste kratší výraz alebo inú podobu slova.</p>';
      else results.innerHTML = '<p class="rcount">Nájdených stránok: <b>' + r.list.length + '</b></p><ul class="res">' + r.list.slice(0, 40).map(function (x) {
        return '<li><a class="rt" href="' + esc(x.p.n) + '">' + mark(esc(x.p.t), r.toks) + '</a> <span class="rg">· ' + esc(x.p.g) + '</span>' +
          x.hits.map(function (h) {
            return '<span class="rs">' + (h.s[0] ? '<a href="' + esc(href(x.p, h.s)) + '">§ ' + mark(esc(h.s[1]), r.toks) + '</a> – ' : '') + snippet(h.s, r.toks, 190) + '</span>';
          }).join('') + '</li>';
      }).join('') + '</ul>';
      doc.title = 'Hľadanie: ' + qs + ' – Wiki eFaktúra Connector';
    }
  }

  /* ---------- zvýrazňovanie syntaxe ---------- */
  var PHP_KW = 'abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|list|namespace|new|or|print|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield|self|parent';
  var RULES = {
    php: [
      ['c', '\\/\\*[\\s\\S]*?\\*\\/|\\/\\/[^\\n]*|#(?!\\[)[^\\n]*'],
      ['s', "'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\""],
      ['m', '<\\?php|\\?>'],
      ['v', '\\$[A-Za-z_]\\w*'],
      ['n', '\\b(?:0x[\\da-fA-F]+|\\d+(?:\\.\\d+)?)\\b'],
      ['k', '\\b(?:' + PHP_KW + ')\\b'],
      ['l', '\\b(?:true|false|null|TRUE|FALSE|NULL)\\b'],
      ['t', '\\b[A-Z][A-Za-z0-9_]*\\b(?!\\s*\\()|\\\\[A-Z][\\w\\\\]*'],
      ['f', '\\b[A-Za-z_]\\w*(?=\\s*\\()']
    ],
    json: [
      ['a', '"(?:[^"\\\\]|\\\\.)*"(?=\\s*:)'],
      ['s', '"(?:[^"\\\\]|\\\\.)*"'],
      ['n', '-?\\b\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b'],
      ['l', '\\b(?:true|false|null)\\b']
    ],
    xml: [
      ['c', '<!--[\\s\\S]*?-->'],
      ['m', '<\\?[\\s\\S]*?\\?>'],
      ['s', '"[^"]*"|\'[^\']*\''],
      ['t', '<\\/?[A-Za-z_][\\w:.-]*|\\/?>'],
      ['a', '\\b[A-Za-z_][\\w:.-]*(?=\\s*=)']
    ],
    sh: [
      ['c', '(?:^|\\s)#[^\\n]*'],
      ['s', "'[^']*'|\"(?:[^\"\\\\]|\\\\.)*\""],
      ['v', '\\$\\{?[A-Za-z_]\\w*\\}?'],
      ['a', '(?:^|\\s)--?[A-Za-z][\\w-]*'],
      ['k', '\\b(?:composer|php|java|git|cd|export|sudo|apt|npm|node|curl|vendor\\/bin\\/\\w+)\\b'],
      ['n', '\\b\\d+\\b']
    ],
    ini: [
      ['c', '^\\s*[#;][^\\n]*'],
      ['m', '^\\s*\\[[^\\]\\n]+\\]'],
      ['a', '^\\s*[A-Za-z_][\\w.\\-\\[\\]]*(?=\\s*=)'],
      ['s', '"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\''],
      ['n', '\\b\\d+(?:\\.\\d+)?\\b'],
      ['l', '\\b(?:true|false|null|on|off|yes|no)\\b']
    ],
    generic: [
      ['c', '\\/\\*[\\s\\S]*?\\*\\/|(?:^|\\s)#[^\\n]*|\\/\\/[^\\n]*'],
      ['s', '"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\''],
      ['n', '\\b\\d+(?:\\.\\d+)?\\b']
    ]
  };
  var ALIAS = { dotenv: 'ini', env: 'ini', bash: 'sh', shell: 'sh', console: 'sh', html: 'xml', apache: 'generic', text: null, txt: null };
  var compiled = {};
  function compile(lang) {
    if (compiled[lang]) return compiled[lang];
    var rules = RULES[lang];
    return compiled[lang] = { rules: rules, re: new RegExp(rules.map(function (r) { return '(' + r[1] + ')'; }).join('|'), 'gm') };
  }
  function highlight(src, lang) {
    lang = lang in ALIAS ? ALIAS[lang] : lang;
    if (!lang || !RULES[lang]) return null;
    var c = compile(lang), re = c.re, out = '', last = 0, m;
    re.lastIndex = 0;
    while ((m = re.exec(src))) {
      if (m[0] === '') { re.lastIndex++; continue; }
      var i = 1; while (i < m.length && m[i] === undefined) i++;
      var cls = c.rules[i - 1][0], txt = m[0], lead = '';
      if (cls === 'c' || cls === 'a') { var w = /^\s+/.exec(txt); if (w && lang === 'sh') { lead = w[0]; txt = txt.slice(lead.length); } }
      out += esc(src.slice(last, m.index)) + esc(lead) + '<span class="tk-' + cls + '">' + esc(txt) + '</span>';
      last = m.index + m[0].length;
    }
    return out + esc(src.slice(last));
  }

  $$('.codebox').forEach(function (box) {
    var code = box.querySelector('code'); if (!code) return;
    var lang = (code.dataset.lang || '').toLowerCase(), src = code.textContent;
    if (lang) box.setAttribute('data-lang', lang);
    var html = null;
    try { html = highlight(src.replace(/\n$/, ''), lang); } catch (e) {}
    if (html !== null) code.innerHTML = html;
    var b = doc.createElement('button');
    b.type = 'button'; b.className = 'copy'; b.textContent = 'kopírovať';
    b.addEventListener('click', function () {
      copyText(src, function () { b.textContent = 'skopírované ✓'; setTimeout(function () { b.textContent = 'kopírovať'; }, 1600); });
    });
    box.appendChild(b);
  });

  /* ---------- diagramy Mermaid ---------- */
  var script = doc.currentScript, url = script && script.getAttribute('data-mermaid');
  var nodes = $$('pre.mermaid');
  if (!url || !nodes.length) return;

  nodes.forEach(function (n, i) {
    n.setAttribute('data-src', n.textContent);
    var fig = doc.createElement('figure'); fig.className = 'diagram'; fig.style.margin = '1.2em 0';
    var bar = doc.createElement('div'); bar.className = 'dbar';
    bar.innerHTML = '<span class="dh">Diagram ' + (i + 1) + '</span><span class="dhint">posuňte do strán alebo zväčšite</span><button type="button" class="btn sm dz">⤢ Zväčšiť</button>';
    n.parentNode.insertBefore(fig, n); fig.appendChild(bar);
    var sc = doc.createElement('div'); sc.className = 'dscroll'; fig.appendChild(sc); sc.appendChild(n);
    bar.querySelector('.dz').addEventListener('click', function () { openViewer(n); });
  });

  function dark() {
    var t = root.getAttribute('data-theme');
    return t ? t === 'dark' : !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
  }
  var seq = 0;
  function renderNode(n) {
    var id = 'mmd' + (++seq);
    return window.mermaid.render(id, n.getAttribute('data-src')).then(function (r) {
      n.innerHTML = r.svg; n.setAttribute('data-processed', 'true'); n.classList.remove('failed'); n.classList.add('ready');
    }, function (e) {
      var el = doc.getElementById(id) || doc.getElementById('d' + id); if (el) el.remove();
      n.textContent = n.getAttribute('data-src'); n.classList.remove('ready'); n.classList.add('failed');
      n.setAttribute('title', 'Diagram sa nepodarilo vykresliť: ' + String(e && e.message || e).split(String.fromCharCode(10))[0]);
    });
  }
  function draw() {
    if (!window.mermaid) return;
    window.mermaid.initialize({
      startOnLoad: false, securityLevel: 'strict', theme: dark() ? 'dark' : 'default', suppressErrorRendering: true,
      fontFamily: 'Segoe UI, system-ui, sans-serif', fontSize: 15,
      flowchart: { useMaxWidth: false, htmlLabels: true, nodeSpacing: 36, rankSpacing: 46, padding: 12 },
      sequence: { useMaxWidth: false, actorMargin: 60, messageMargin: 38, wrap: false },
      state: { useMaxWidth: false }
    });
    nodes.reduce(function (chain, n) { return chain.then(function () { return renderNode(n); }); }, Promise.resolve());
  }
  var s = doc.createElement('script');
  s.src = url; s.async = true; s.onload = draw;
  doc.head.appendChild(s);
  if (themeBtn) themeBtn.addEventListener('click', function () { setTimeout(draw, 0); });

  /* ---------- zväčšený náhľad diagramu ---------- */
  var viewer;
  function openViewer(pre) {
    var svg = pre.querySelector('svg'); if (!svg) return;
    closeViewer();
    var vb = svg.viewBox && svg.viewBox.baseVal, w = vb && vb.width ? vb.width : svg.getBoundingClientRect().width, h = vb && vb.height ? vb.height : svg.getBoundingClientRect().height;
    var ov = doc.createElement('div'); ov.className = 'dview'; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', 'Zväčšený diagram');
    ov.innerHTML = '<div class="dvbar"><b>Diagram</b><span class="dvhint">koliesko = priblíženie · ťahanie = posun · Esc = zavrieť</span><span class="sp"></span><button type="button" class="btn sm" data-a="out" aria-label="Oddialiť">−</button><button type="button" class="btn sm" data-a="in" aria-label="Priblížiť">+</button><button type="button" class="btn sm" data-a="fit">Na šírku</button><button type="button" class="btn sm" data-a="all">Celý diagram</button><button type="button" class="btn sm" data-a="x">Zavrieť ✕</button></div><div class="dvstage"><div class="dvcanvas"></div></div>';
    var stage = ov.querySelector('.dvstage'), canvas = ov.querySelector('.dvcanvas');
    var clone = svg.cloneNode(true); clone.removeAttribute('style'); clone.setAttribute('width', w); clone.setAttribute('height', h);
    clone.style.maxWidth = 'none'; canvas.appendChild(clone);
    doc.body.appendChild(ov); doc.body.classList.add('noscroll');
    var k = 1, x = 0, y = 0;
    function apply() { canvas.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + k + ')'; }
    function fit(all) {
      var sw = stage.clientWidth, sh = stage.clientHeight;
      k = (all === true ? Math.min(sw / w, sh / h, 2.5) : Math.min(sw / w, 1.1)) * 0.96;
      x = (sw - w * k) / 2; y = all === true || h * k < sh ? (sh - h * k) / 2 : 16; apply();
    }
    function zoom(f, cx, cy) {
      var nk = Math.max(0.1, Math.min(6, k * f)); f = nk / k;
      if (cx === undefined) { cx = stage.clientWidth / 2; cy = stage.clientHeight / 2; }
      x = cx - (cx - x) * f; y = cy - (cy - y) * f; k = nk; apply();
    }
    fit();
    stage.addEventListener('wheel', function (e) {
      e.preventDefault(); var r = stage.getBoundingClientRect();
      zoom(e.deltaY < 0 ? 1.15 : 1 / 1.15, e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });
    var drag = null;
    stage.addEventListener('pointerdown', function (e) { drag = { x: e.clientX - x, y: e.clientY - y }; stage.setPointerCapture(e.pointerId); stage.classList.add('grab'); });
    stage.addEventListener('pointermove', function (e) { if (drag) { x = e.clientX - drag.x; y = e.clientY - drag.y; apply(); } });
    function end() { drag = null; stage.classList.remove('grab'); }
    stage.addEventListener('pointerup', end); stage.addEventListener('pointercancel', end);
    ov.addEventListener('click', function (e) {
      var a = e.target.getAttribute && e.target.getAttribute('data-a'); if (!a) return;
      if (a === 'x') closeViewer(); else if (a === 'in') zoom(1.25); else if (a === 'out') zoom(1 / 1.25); else if (a === 'fit') fit(); else if (a === 'all') fit(true);
    });
    viewer = { el: ov, onKey: function (e) { if (e.key === 'Escape') closeViewer(); else if (e.key === '+' || e.key === '=') zoom(1.25); else if (e.key === '-') zoom(1 / 1.25); }, onResize: function () { fit(); } };
    doc.addEventListener('keydown', viewer.onKey); addEventListener('resize', viewer.onResize);
    ov.querySelector('[data-a="x"]').focus();
  }
  function closeViewer() {
    if (!viewer) return;
    doc.removeEventListener('keydown', viewer.onKey); removeEventListener('resize', viewer.onResize);
    viewer.el.remove(); viewer = null; doc.body.classList.remove('noscroll');
  }
})();
