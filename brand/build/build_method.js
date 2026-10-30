// Builds "The Praxis Method" guide, the PDF the website's guide form emails, in English and French:
//   praxis_core/resources/pdf/the-praxis-method-en.pdf and -fr.pdf  (A4, printed by headless Edge)
// Sources: brand/method/the-praxis-method.{en,fr}.html and method.css; logo from brand/kit; fonts from
// method_fonts.py (static instances, so the PDF embeds real fonts with selectable text).
// Needs Node, Python with fontTools, and Microsoft Edge (as build_book.js).
//   node brand/build/build_method.js            both languages
//   node brand/build/build_method.js fr         one language
// To look at the pages: node brand/build/pdf_sheet.js 1 6 3 600 praxis_core/resources/pdf/the-praxis-method-en.pdf
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..', '..');
const BRAND = path.join(ROOT, 'brand');
const KIT = path.join(BRAND, 'kit');
const SRC = path.join(BRAND, 'method');
const RENDER = path.join(BRAND, 'build', '_render');
const OUT = path.join(ROOT, 'praxis_core', 'resources', 'pdf');
const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const LANGS = process.argv[2] ? [process.argv[2]] : ['en', 'fr'];
const PAGES = 11; // one <section class="page"> per printed page; the contents list on page 2 counts on it
const MAX_BYTES = 2 * 1024 * 1024;

// ---------- fonts ----------
const FACES = [
  ['Jakarta', 400, 'jakarta-400'], ['Jakarta', 500, 'jakarta-500'], ['Jakarta', 600, 'jakarta-600'],
  ['Newsreader Display', 400, 'newsreader-display-400'],
  ['Newsreader Title', 400, 'newsreader-title-400'], ['Newsreader Title', 500, 'newsreader-title-500'],
  ['Newsreader Text', 500, 'newsreader-text-500'],
];
const fontCss = FACES.map(([family, weight, file]) =>
  `@font-face{font-family:"${family}";src:url("fonts/${file}.ttf") format("truetype");font-weight:${weight};font-style:normal;font-display:block}`).join('\n');

// ---------- logo, bar stack and icons ----------
let svgSeq = 0;
// The kit's SVGs, with ids made unique for each copy on the page
const logo = name => {
  const k = `u${++svgSeq}_`;
  return fs.readFileSync(path.join(KIT, 'logo', `jbs-praxis-${name}.svg`), 'utf8').trim()
    .replace(/\sid="/g, ` id="${k}`).replace(/href="#/g, `href="#${k}`).replace(/url\(#/g, `url(#${k}`)
    .replace('<svg ', '<svg aria-hidden="true" focusable="false" ');
};

// The Praxis rule's bar stack (docs/BRAND.md section 9), as build_book.js draws it: white and Bright Teal on Ink
function barStack(w = 640, h = 12, gap = 13, dark = '#FFFFFF', light = '#0BB8C4') {
  const off = h * 1.6;
  const bars = [0, 1, 2].map(k => {
    const y = k * (h + gap), x0 = k * off * 1.6, x1 = w - x0;
    return `<polygon points="${x0},${y} ${x1},${y} ${x1 - off},${y + h} ${x0 + off},${y + h}"/>`;
  }).join('');
  const H = 3 * h + 2 * gap, id = 'bs' + (++svgSeq);
  return `<svg viewBox="0 0 ${w} ${H}" aria-hidden="true"><defs>
    <clipPath id="${id}l"><rect width="${w / 2}" height="${H}"/></clipPath><clipPath id="${id}r"><rect x="${w / 2}" width="${w / 2}" height="${H}"/></clipPath></defs>
    <g clip-path="url(#${id}l)" fill="${dark}">${bars}</g><g clip-path="url(#${id}r)" fill="${light}">${bars}</g></svg>`;
}

// Lucide icons (the site's set, praxis_site/package.json), 1.5 stroke as BRAND.md section 9
const ICONS = {
  'check': '<path d="M20 6 9 17l-5-5"/>',
  'landmark': '<path d="M10 18v-7"/><path d="M11.119 2.205a2 2 0 0 1 1.762 0l7.84 3.846A.5.5 0 0 1 20.5 7h-17a.5.5 0 0 1-.22-.949z"/><path d="M14 18v-7"/><path d="M18 18v-7"/><path d="M3 22h18"/><path d="M6 18v-7"/>',
  'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>',
  'truck': '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
  'shield-check': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  'message-square-text': '<path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"/><path d="M7 11h10"/><path d="M7 15h6"/><path d="M7 7h8"/>',
  'key-round': '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>',
  'scroll-text': '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>',
  'split': '<path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"/><path d="m15 9 6-6"/>',
  'lock': '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  'user-round-check': '<path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="m16 19 2 2 4-4"/>',
};
const icon = name => {
  if (!ICONS[name]) throw new Error(`unknown icon ${name}`);
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ICONS[name]}</svg>`;
};

// ---------- checks on the words (docs/BRAND.md sections 1, 4 and 5; owner rules for the guide) ----------
const text = html => html.replace(/<!--[\s\S]*?-->/g, ' ').replace(/<(style|script)[\s\S]*?<\/\1>/gi, ' ')
  .replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, '\u00A0').replace(/&amp;/g, '&');
const BANNED = [/predictive/i, /™/, /\bcertified\b/i, /fidson/i, /petite cour/i, /\bLPC\b/, /\bEts\./, /github/i,
  /tomblakeasaah/i, /horizon segment/i, /deterministic|forensic|immutable|seamless|robust|cutting-edge|world-class|leverage|empower|unlock|supercharge/i,
  /[₦$€£]\s?\d|\d\s?(FCFA|naira)/i];
function lint(lang, html) {
  const t = text(html), problems = [];
  BANNED.forEach(re => { const m = t.match(re); if (m) problems.push(`"${m[0]}" is not allowed`); });
  if (/'/.test(t)) problems.push('straight apostrophe: use ’');
  if (lang === 'en') {
    // Documents write contractions out (BRAND.md section 4)
    const c = t.match(/\b\w+’(t|ll|re|ve|d)\b/); if (c) problems.push(`contraction "${c[0]}": write it out`);
  } else {
    // French: U+202F before ; ? ! and inside « », U+00A0 before : (as the brand book)
    const bad = [[/[^\u202F]([;?!])(?=\s|$)/, 'U+202F before $1'], [/[^\u00A0]:(?=\s)/, 'U+00A0 before :'],
      [/«(?!\u202F)/, 'U+202F after «'], [/(?<!\u202F)»/, 'U+202F before »']];
    bad.forEach(([re, msg]) => { const m = t.match(re); if (m) problems.push(`${msg.replace('$1', m[1] || '')} near "${t.slice(Math.max(0, m.index - 30), m.index + 3).trim()}"`); });
  }
  if (problems.length) throw new Error(`${lang}: ${problems.join('; ')}`);
}

// ---------- layout check (runs in Edge before it prints; only in the render copy) ----------
// Each page is a fixed A4 box that hides what does not fit, so text that runs too long would be cut off quietly.
// This script finds text that leaves its page, runs into the band or folio at the foot, or is wider than its box,
// and adds a last page listing it; the page count check below then fails the build. It waits for the fonts, or it
// would measure the fallbacks.
const LAYOUT_CHECK = `<script>
document.fonts.ready.then(() => {
  const mm = 96 / 25.4, bad = [];
  document.querySelectorAll('.page').forEach((page, i) => {
    const box = page.getBoundingClientRect();
    const foot = page.querySelector(':scope > .outcome') || page.querySelector(':scope > .folio');
    const limit = foot ? foot.getBoundingClientRect().top - 4 * mm : box.bottom - 15 * mm;
    page.querySelectorAll('p, li, h1, h2, h3, a, .stage, .glance > div').forEach(el => {
      if (foot && foot.contains(el)) return;
      const r = el.getBoundingClientRect();
      const where = 'page ' + (i + 1) + ': "' + el.textContent.trim().slice(0, 50) + '"';
      if (r.height && (r.bottom > limit || r.right > box.right - 15 * mm))
        bad.push(where + ' ends ' + ((r.bottom - limit) / mm).toFixed(1) + ' mm too low');
      // A word too long for its box (a stage name in a table cell) spills sideways into the next one
      if (el.clientWidth && el.scrollWidth > el.clientWidth + 1)
        bad.push(where + ' is ' + ((el.scrollWidth - el.clientWidth) / mm).toFixed(1) + ' mm too wide');
    });
  });
  if (bad.length) document.body.insertAdjacentHTML('beforeend',
    '<section class="page"><h2>Layout check failed</h2><p>' + bad.join('</p><p>') + '</p></section>');
});
</script>`;

// ---------- build ----------
function page(lang) {
  const css = fs.readFileSync(path.join(SRC, 'method.css'), 'utf8');
  let html = fs.readFileSync(path.join(SRC, `the-praxis-method.${lang}.html`), 'utf8').replace(/\r\n/g, '\n');
  lint(lang, html);
  const sections = (html.match(/<section class="page/g) || []).length;
  if (sections !== PAGES) throw new Error(`${lang}: ${sections} pages in the source, expected ${PAGES}`);
  return html
    .replace('<link rel="stylesheet" href="method.css">', `<style>\n${fontCss}\n${css}</style>`)
    .replace(/<!--logo ([a-z-]+)-->/g, (_, n) => logo(n))
    .replace(/<!--bars-->/g, () => barStack())
    .replace(/<!--icon ([a-z-]+)-->/g, (_, n) => icon(n))
    .replace('</body>', `${LAYOUT_CHECK}\n</body>`);
}

// msedge.exe can hand the job to a background process and return at once, so wait until the file is written
function waitForFile(file, ms = 120000) {
  const sleep = t => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, t);
  let last = -1;
  for (const end = Date.now() + ms; Date.now() < end; sleep(500)) {
    const size = fs.existsSync(file) ? fs.statSync(file).size : -1;
    if (size > 0 && size === last) return;
    last = size;
  }
  throw new Error(`${path.relative(ROOT, file)} was not written`);
}

function pdfFacts(file) {
  const raw = fs.readFileSync(file, 'latin1');
  return {
    pages: (raw.match(/\/Type\s*\/Page[^s]/g) || []).length,
    type3: (raw.match(/\/Subtype\s*\/Type3/g) || []).length,
    fonts: [...new Set((raw.match(/\/BaseFont\s*\/[A-Z]{6}\+([^\s/]+)/g) || []).map(f => f.replace(/.*\+/, '')))].sort(),
  };
}

fs.mkdirSync(RENDER, { recursive: true });
fs.mkdirSync(OUT, { recursive: true });
execFileSync('python', [path.join(__dirname, 'method_fonts.py'), path.join(RENDER, 'fonts')], { stdio: 'inherit' });

for (const lang of LANGS) {
  const src = path.join(RENDER, `method-${lang}.html`);
  fs.writeFileSync(src, page(lang));
  const pdf = path.join(OUT, `the-praxis-method-${lang}.pdf`);
  if (fs.existsSync(pdf)) fs.unlinkSync(pdf);
  // Its own profile, so a running Edge (or its startup boost) cannot take the job and quietly drop it
  execFileSync(EDGE, ['--headless=new', '--disable-gpu', `--user-data-dir=${path.join(RENDER, 'edge-profile')}`, '--no-first-run',
    '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw',
    '--generate-pdf-document-outline', '--virtual-time-budget=20000', `--print-to-pdf=${pdf}`, 'file:///' + src.replace(/\\/g, '/')],
  { stdio: 'ignore', timeout: 180000 });
  waitForFile(pdf);
  const bytes = fs.statSync(pdf).size, f = pdfFacts(pdf);
  const issues = [];
  if (f.pages !== PAGES) {
    issues.push(`${f.pages} pages, expected ${PAGES} (text overflowed: the last page of the PDF lists where)`);
    try { issues.push(execFileSync('pdftotext', ['-enc', 'UTF-8', '-f', f.pages, '-l', f.pages, pdf, '-'], { encoding: 'utf8' }).trim()); } catch { /* pdftotext is optional */ }
  }
  if (f.type3) issues.push(`${f.type3} Type 3 fonts`);
  if (f.fonts.some(n => !/^(Jakarta|Newsreader)/.test(n))) issues.push(`fallback fonts used: ${f.fonts.join(', ')}`);
  if (bytes > MAX_BYTES) issues.push(`${Math.round(bytes / 1024)} KB is over 2 MB`);
  console.log(`${path.relative(ROOT, pdf)}: ${Math.round(bytes / 1024)} KB, ${f.pages} pages, fonts ${f.fonts.join(', ')}`);
  if (issues.length) { console.error(`  check: ${issues.join('; ')}`); process.exitCode = 1; }
}
