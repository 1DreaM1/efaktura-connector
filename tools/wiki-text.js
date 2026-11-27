// Prezentačné zlepšenie čitateľnosti textu wiki pri generovaní HTML (zdrojové .md sa nemení):
// dlhé odseky, položky zoznamov a bunky tabuliek sa rozdelia po vetách na kratšie celky.
const ABBR = new Set(['napr', 'tzn', 'tzv', 'tj', 'resp', 'atď', 'atd', 'č', 'max', 'min', 'vr', 'vs', 'pozn', 'str', 'cca', 'príp', 'prip', 'ods', 'písm', 'pism', 'obr', 'tab', 'kap', 'ev', 'mgr', 'ing']);
const INLINE_OPEN = /^<(a|strong|em|code|span|b|i|kbd|abbr)(\s|>)/i;
const INLINE_CLOSE = /^<\/(a|strong|em|code|span|b|i|kbd|abbr)>/i;

const textLen = h => h.replace(/<[^>]+>/g, '').length;

/** Rozdelí HTML text na vety; nerozdeľuje vo vnútri inline značiek (kód, odkazy, zvýraznenie). */
function sentences(html) {
  const out = [];
  let depth = 0, start = 0, i = 0;
  while (i < html.length) {
    const ch = html[i];
    if (ch === '<') {
      const end = html.indexOf('>', i);
      if (end < 0) break;
      const tag = html.slice(i, end + 1);
      if (INLINE_OPEN.test(tag)) depth++;
      else if (INLINE_CLOSE.test(tag)) depth = Math.max(0, depth - 1);
      i = end + 1;
      continue;
    }
    if ((ch === '.' || ch === '!' || ch === '?') && depth === 0 && html[i + 1] === ' ') {
      const rest = html.slice(i + 2);
      const startsSentence = /^(<code>|<strong>|<em>|\p{Lu})/u.test(rest);
      if (startsSentence) {
        const word = (/([\p{L}]+)$/u.exec(html.slice(Math.max(0, i - 12), i)) || [])[1] || '';
        const prevChar = html[i - 1] || '';
        const tiny = word.length <= 1 && /\p{L}/u.test(prevChar);
        const digitBefore = /\d/.test(prevChar);
        if (!ABBR.has(word.toLowerCase()) && !tiny && !(digitBefore && ch === '.' && /^\d/.test(rest))) {
          out.push(html.slice(start, i + 1));
          start = i + 2;
          i += 2;
          continue;
        }
      }
    }
    i++;
  }
  out.push(html.slice(start));
  return out.map(s => s.trim()).filter(Boolean);
}

/** Zoskupí vety do celkov s približne `target` znakmi textu. */
function group(sents, target) {
  const groups = [];
  let cur = '';
  for (const s of sents) {
    if (cur && (textLen(cur) >= target || textLen(cur) + textLen(s) > target * 1.45)) { groups.push(cur); cur = ''; }
    cur += (cur ? ' ' : '') + s;
  }
  if (cur) groups.push(cur);
  return groups;
}

const BLOCK = /<(p|ul|ol|li|pre|table|div|details|h[1-6]|blockquote|br)\b/i;


/** Inline kód mimo <pre>: krátky sa nezalamuje (class nb), dlhý dostane povolené zlomy za oddeľovačmi. */
function tuneCode(html) {
  return html.split(/(<pre[\s\S]*?<\/pre>)/g).map((part, i) => {
    if (i % 2 === 1) return part;
    return part.replace(/<code>([^<]*)<\/code>/g, (m, inner) => {
      const txt = inner.replace(/&[a-z]+;/g, 'x');
      if (txt.length <= 38 && !/\s/.test(txt)) return '<code class="nb">' + inner + '</code>';
      return '<code>' + inner.replace(/(\/|\\|::|->|\.|_|,)(?=[^\s])/g, '$1<wbr>') + '</code>';
    });
  }).join('');
}

function improve(html) {
  // odseky
  html = html.replace(/<p>([\s\S]*?)<\/p>/g, (m, inner) => {
    if (textLen(inner) < 340 || BLOCK.test(inner)) return m;
    const g = group(sentences(inner), 230);
    return g.length < 2 ? m : g.map(x => '<p>' + x + '</p>').join('\n');
  });
  // bunky tabuliek a položky zoznamov (tesné zoznamy bez vnorených blokov)
  const lines = (m, open, inner, close, limit, target) => {
    if (textLen(inner) < limit || BLOCK.test(inner)) return m;
    const g = group(sentences(inner), target);
    return g.length < 2 ? m : open + g.join('<br>') + close;
  };
  html = html.replace(/(<td(?:\s[^>]*)?>)([\s\S]*?)(<\/td>)/g, (m, o, i, c) => lines(m, o, i, c, 230, 170));
  html = html.replace(/(<li>)([\s\S]*?)(<\/li>)/g, (m, o, i, c) => lines(m, o, i, c, 360, 260));
  // prázdne hodnoty (—) v kartách tabuliek sa zobrazia stlmene
  html = html.replace(/<td data-label="([^"]*)">—<\/td>/g, '<td class="na" data-label="$1">—</td>');
  return tuneCode(html);
}

module.exports = { improve, sentences, group };
