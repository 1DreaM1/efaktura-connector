(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  function $$(s, c) { return [].slice.call((c || doc).querySelectorAll(s)); }
  function esc(t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }

  /* ---------- theme ---------- */
  var modes = ['auto', 'light', 'dark'], icons = { auto: '◐', light: '☀', dark: '☾' };
  var mode = store('efk-theme') || 'auto';
  function applyTheme() {
    if (mode === 'auto') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', mode);
    var b = doc.getElementById('theme');
    if (b) { b.textContent = icons[mode]; b.title = 'Téma: ' + mode; b.setAttribute('aria-label', 'Téma: ' + mode); }
  }
  applyTheme();
  var tb = doc.getElementById('theme');
  if (tb) tb.addEventListener('click', function () {
    mode = modes[(modes.indexOf(mode) + 1) % 3]; store('efk-theme', mode); applyTheme();
  });

  /* ---------- mobile menu ---------- */
  var burger = doc.getElementById('burger'), menu = doc.getElementById('menu');
  if (burger && menu) {
    burger.addEventListener('click', function () {
      var o = menu.classList.toggle('open'); burger.setAttribute('aria-expanded', o);
    });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') { menu.classList.remove('open'); burger.setAttribute('aria-expanded', false); } });
  }

  /* ---------- progress ---------- */
  var bar = doc.getElementById('bar');
  if (bar) addEventListener('scroll', function () {
    var h = root.scrollHeight - root.clientHeight;
    bar.style.width = (h > 0 ? root.scrollTop / h * 100 : 0) + '%';
  }, { passive: true });

  /* ---------- reveal ---------- */
  var rvs = $$('.rv');
  rvs.forEach(function (el, i) { el.style.transitionDelay = ((i % 4) * 60) + 'ms'; });
  function reveal() {
    var vh = window.innerHeight || root.clientHeight, left = [];
    rvs.forEach(function (el) {
      if (el.getBoundingClientRect().top < vh * 0.94) el.classList.add('in'); else left.push(el);
    });
    rvs = left;
  }
  reveal();
  addEventListener('scroll', reveal, { passive: true });
  addEventListener('resize', reveal);
  addEventListener('load', reveal);

  /* ---------- cards spotlight ---------- */
  $$('.cell').forEach(function (c) {
    c.addEventListener('pointermove', function (e) {
      var r = c.getBoundingClientRect();
      c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  /* ---------- clipboard ---------- */
  function copyText(t, done) {
    function fallback() {
      var ta = doc.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0';
      doc.body.appendChild(ta); ta.select(); try { doc.execCommand('copy'); } catch (e) {} doc.body.removeChild(ta); done();
    }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done, fallback); else fallback();
  }
  var inst = doc.getElementById('copy');
  if (inst) {
    var cs = doc.getElementById('cs');
    var go = function () { copyText('composer require dream1/efaktura-connector:dev-main', function () { cs.textContent = 'skopírované ✓'; setTimeout(function () { cs.textContent = 'kopírovať'; }, 1800); }); };
    inst.addEventListener('click', go);
    inst.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
  }

  /* ---------- syntax highlight ---------- */
  var KW = 'namespace|use|final|class|function|public|private|protected|static|return|new|if|else|foreach|as|do|while|try|catch|throw|null|true|false|array|implements|require|echo|fn|void|int|string|self|instanceof';
  var phpRe = new RegExp([
    /(\/\/[^\n]*|\/\*[\s\S]*?\*\/)/,                 // 1 komentár
    /(<\?php)/,                                       // 2
    /('(?:\x5c.|[^'\x5c])*'|"(?:\x5c.|[^"\x5c])*")/,  // 3 reťazec
    /(\$[A-Za-z_]\w*)/,                               // 4 premenná
    /(->|::)([A-Za-z_]\w*)?/,                         // 5 operátor, 6 člen
    new RegExp('\\b(' + KW + ')\\b'),                 // 7 kľúčové slovo
    /\b([A-Z][A-Za-z0-9_]*)\b/,                       // 8 trieda / konštanta
    /([A-Za-z_]\w*)(?=\()/,                           // 9 funkcia
    /(\b\d[\d.]*\b)/,                                 // 10 číslo
    /(=>|\?\?|===|!==|==|!=|&&|\|\|)/                 // 11 operátory
  ].map(function (r) { return r.source; }).join('|'), 'g');
  function hlPhp(src) {
    var out = '', last = 0, m;
    phpRe.lastIndex = 0;
    while ((m = phpRe.exec(src))) {
      out += esc(src.slice(last, m.index));
      var end = m.index + m[0].length;
      if (m[5]) {
        out += '<span class="o">' + esc(m[5]) + '</span>';
        if (m[6]) out += '<span class="' + (src.charAt(end) === '(' ? 'f' : 'p') + '">' + esc(m[6]) + '</span>';
      } else {
        var cls = m[1] ? 'c' : m[2] ? 'k' : m[3] ? 's' : m[4] ? 'v' : m[7] ? 'k' : m[8] ? 'cl' : m[9] ? 'f' : m[10] ? 'n' : 'o';
        out += '<span class="' + cls + '">' + esc(m[0]) + '</span>';
      }
      last = end;
    }
    return out + esc(src.slice(last));
  }
  function hlEnv(src) {
    return src.split('\n').map(function (l) {
      if (/^\s*#/.test(l)) return '<span class="c">' + esc(l) + '</span>';
      var i = l.indexOf('=');
      return i < 0 ? esc(l) : '<span class="t">' + esc(l.slice(0, i)) + '</span>=<span class="s">' + esc(l.slice(i + 1)) + '</span>';
    }).join('\n');
  }
  $$('script.src').forEach(function (s) {
    var src = s.textContent.replace(/^\n/, '').replace(/\s+$/, '');
    var lang = s.dataset.lang || 'php';
    var box = doc.createElement('div'); box.className = 'code';
    var top = doc.createElement('div'); top.className = 'win-top';
    top.innerHTML = '<i></i><i></i><i></i><span class="fn"></span><button type="button" class="copy">kopírovať</button>';
    top.querySelector('.fn').textContent = s.dataset.file || '';
    var pre = doc.createElement('pre'); pre.tabIndex = 0;
    var hl = {};
    (s.dataset.hl || '').split(',').forEach(function (r) {
      var m = r.trim().match(/^(\d+)(?:-(\d+))?$/); if (!m) return;
      for (var i = +m[1]; i <= +(m[2] || m[1]); i++) hl[i] = 1;
    });
    var code = doc.createElement('code');
    code.innerHTML = src.split('\n').map(function (l, i) {
      return '<span class="ln' + (hl[i + 1] ? ' hl' : '') + '">' + (lang === 'env' ? hlEnv(l) : hlPhp(l)) + '</span>';
    }).join('');
    pre.appendChild(code); box.appendChild(top); box.appendChild(pre);
    var btn = top.querySelector('.copy');
    btn.addEventListener('click', function () { copyText(src, function () { btn.textContent = 'skopírované ✓'; setTimeout(function () { btn.textContent = 'kopírovať'; }, 1600); }); });
    s.parentNode.replaceChild(box, s);
  });
  $$('pre.inline[data-hl]').forEach(function (p) {
    var raw = p.textContent.replace(/\s+$/, '');
    var hl = {};
    (p.dataset.lines || '').split(',').forEach(function (r) {
      var m = r.trim().match(/^(\d+)(?:-(\d+))?$/); if (!m) return;
      for (var i = +m[1]; i <= +(m[2] || m[1]); i++) hl[i] = 1;
    });
    p.dataset.raw = raw; p.classList.add('nm');
    p.innerHTML = '<code>' + raw.split('\n').map(function (l, i) {
      return '<span class="ln' + (hl[i + 1] ? ' hl' : '') + '">' + hlPhp(l) + '</span>';
    }).join('') + '</code>';
  });
  $$('.win').forEach(function (w) {
    var top = w.querySelector('.win-top'); if (!top) return;
    var b = doc.createElement('button'); b.type = 'button'; b.className = 'copy'; b.textContent = 'kopírovať';
    b.addEventListener('click', function () {
      var pre = w.querySelector('[role=tabpanel]:not([hidden]) pre'); if (!pre) return;
      copyText(pre.dataset.raw || pre.textContent, function () { b.textContent = 'skopírované ✓'; setTimeout(function () { b.textContent = 'kopírovať'; }, 1600); });
    });
    top.appendChild(b);
  });

  /* ---------- tabs ---------- */
  $$('[data-tabs]').forEach(function (set) {
    function own(el) { return el.closest('[data-tabs]') === set; }
    var btns = $$('[role="tab"]', set).filter(own), panels = $$('[role="tabpanel"]', set).filter(own);
    function show(id) {
      btns.forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('aria-controls') === id); });
      panels.forEach(function (p) { p.hidden = p.id !== id; });
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('aria-controls')); }); });
    var hash = location.hash.slice(1);
    var pre = btns.filter(function (b) { return b.getAttribute('aria-controls') === hash; })[0] || btns[0];
    if (pre) show(pre.getAttribute('aria-controls'));
  });

  /* ---------- flowcharts ---------- */
  $$('.fbox').forEach(function (box) {
    var panel = box.querySelector('.fdetail');
    function pick(g) {
      $$('.nd.sel', box).forEach(function (x) { x.classList.remove('sel'); });
      g.classList.add('sel');
      panel.innerHTML = '';
      var b = doc.createElement('b'); b.textContent = g.getAttribute('data-t');
      var p = doc.createElement('p'); p.textContent = g.getAttribute('data-i') || '';
      panel.appendChild(b); panel.appendChild(p);
    }
    $$('.nd', box).forEach(function (g) {
      g.addEventListener('click', function () { pick(g); });
      g.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(g); } });
    });
    var first = box.querySelector('.nd'); if (first) pick(first);
  });

  /* ---------- table filter ---------- */
  var q = doc.getElementById('filter');
  if (q) {
    var rows = $$('#' + q.dataset.target + ' tbody tr'), empty = doc.getElementById('empty');
    q.addEventListener('input', function () {
      var v = q.value.trim().toLowerCase(), n = 0;
      rows.forEach(function (r) { var hit = !v || r.textContent.toLowerCase().indexOf(v) > -1; r.hidden = !hit; if (hit) n++; });
      if (empty) empty.style.display = n ? 'none' : 'block';
    });
  }

  /* ---------- open accordion from hash ---------- */
  if (location.hash) {
    var t = doc.getElementById(location.hash.slice(1));
    if (t && t.tagName === 'DETAILS') t.open = true;
  }
})();
