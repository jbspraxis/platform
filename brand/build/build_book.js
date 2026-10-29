// Builds the JBS Praxis brand book from docs/BRAND.md and the brand kit:
//   brand/JBS-Praxis-Brand-Book.pdf  (A4, rendered by headless Edge)
//   brand/index.html                 (self-contained: images, the PDF and the kit zip are embedded)
// Run after build_kit.py:  node brand/build/build_book.js
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..', '..');
const BRAND = path.join(ROOT, 'brand');
const KIT = path.join(BRAND, 'kit');
const RENDER = path.join(BRAND, 'build', '_render');
const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PDF_NAME = 'JBS-Praxis-Brand-Book.pdf';
const ZIP_NAME = 'JBS-Praxis-Brand-Kit.zip';

const md = fs.readFileSync(path.join(ROOT, 'docs', 'BRAND.md'), 'utf8').replace(/\r\n/g, '\n');
const VERSION = (md.match(/^Version ([\d.]+), ([^.]+)\./m) || [, '1.1', '29 October 2026']);

// ---------- helpers ----------
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const kitFile = p => path.join(KIT, ...p.split('/'));
const svgText = p => fs.readFileSync(kitFile(p), 'utf8').trim();
const dataUri = (p, type = 'image/png') => `data:${type};base64,${fs.readFileSync(p).toString('base64')}`;
const png = p => dataUri(kitFile(p));
const NN = '\u202F', NB = '\u00A0';
// French typography for strings written in this file (same rules as BRAND.md)
const fr = t => t.replace(/« /g, '«' + NN).replace(/ »/g, NN + '»').replace(/ ([;?!])/g, NN + '$1')
  .replace(/ :(?=\s|$)/g, NB + ':').replace(/(\d) (?=\d{3}(?!\d))/g, '$1' + NN).replace(/'/g, '’');
const slug = t => t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
// Inline SVGs need unique ids when several copies share a page
let svgSeq = 0;
const inlineSvg = (p, attrs = '') => {
  const k = `u${++svgSeq}_`;
  return svgText(p).replace(/\sid="/g, ` id="${k}`).replace(/href="#/g, `href="#${k}`)
    .replace(/url\(#/g, `url(#${k}`).replace('<svg ', `<svg ${attrs} `);
};

const hexRgb = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
const lum = h => { const c = hexRgb(h).map(v => v / 255).map(v => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
const r1 = n => n.toFixed(1);

// ---------- markdown (the subset BRAND.md uses) ----------
function inline(s) {
  return s.split(/(`[^`]*`)/).map((p, i) => {
    if (i % 2) return `<code>${esc(p.slice(1, -1))}</code>`;
    return esc(p)
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*\w])\*([^*\s][^*]*?)\*(?![*\w])/g, '$1<em>$2</em>');
  }).join('');
}

function parseBlocks(text) {
  const lines = text.split('\n');
  const blocks = [];
  let i = 0;
  const isStart = l => /^(```|#{1,4} |---\s*$|\||> |\s*([-*]|\d+\.) )/.test(l);
  while (i < lines.length) {
    const l = lines[i];
    if (/^```/.test(l)) {
      const lang = l.slice(3).trim(); const body = []; i++;
      while (i < lines.length && !/^```/.test(lines[i])) body.push(lines[i++]);
      i++; blocks.push({ type: 'code', lang, text: body.join('\n') });
    } else if (/^#{1,4} /.test(l)) {
      const level = l.match(/^#+/)[0].length;
      blocks.push({ type: 'h', level, text: l.slice(level + 1).trim() }); i++;
    } else if (/^---\s*$/.test(l)) { blocks.push({ type: 'hr' }); i++; }
    else if (/^\|/.test(l)) {
      const rows = [];
      while (i < lines.length && /^\|/.test(lines[i])) rows.push(lines[i++]);
      const cells = r => r.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
      const body = rows.filter(r => !/^\|\s*:?-{3,}/.test(r)).map(cells);
      blocks.push({ type: 'table', head: body[0], rows: body.slice(1) });
    } else if (/^> /.test(l)) {
      const q = [];
      while (i < lines.length && /^> /.test(lines[i])) q.push(lines[i++].slice(2));
      blocks.push({ type: 'quote', lines: q });
    } else if (/^\s*([-*]|\d+\.) /.test(l)) {
      const items = [];
      while (i < lines.length && /^\s*([-*]|\d+\.) /.test(lines[i])) {
        const m = lines[i].match(/^(\s*)([-*]|\d+\.) (.*)$/);
        items.push({ depth: m[1].length ? 1 : 0, ordered: /\d/.test(m[2]), text: m[3] }); i++;
      }
      blocks.push({ type: 'list', items });
    } else if (!l.trim()) i++;
    else {
      const p = [];
      while (i < lines.length && lines[i].trim() && !isStart(lines[i])) p.push(lines[i++].trim());
      blocks.push({ type: 'p', text: p.join(' ') });
    }
  }
  return blocks;
}

function renderList(items) {
  const tag = items[0].ordered ? 'ol' : 'ul';
  let html = `<${tag}>`, open = false;
  items.forEach((it, k) => {
    let t = it.text, cls = '';
    if (/^\[ \] /.test(t)) { t = t.slice(4); cls = ' class="check"'; }
    if (it.depth === 0) {
      if (open) html += '</li>';
      html += `<li${cls}>${inline(t)}`; open = true;
      if (!(items[k + 1] && items[k + 1].depth === 1)) { html += '</li>'; open = false; }
    } else {
      const prevNested = items[k - 1] && items[k - 1].depth === 1;
      if (!prevNested) html += '<ul>';
      html += `<li>${inline(t)}</li>`;
      if (!(items[k + 1] && items[k + 1].depth === 1)) { html += '</ul></li>'; open = false; }
    }
  });
  return html + `</${tag}>`;
}

function renderBlock(b) {
  switch (b.type) {
    case 'p': return `<p>${inline(b.text)}</p>`;
    case 'list': return renderList(b.items);
    case 'quote': return `<blockquote>${b.lines.map(l => `<p>${inline(l)}</p>`).join('')}</blockquote>`;
    case 'code': return `<pre class="code"><code>${esc(b.text)}</code></pre>`;
    case 'table':
      return `<div class="table-wrap"><table><thead><tr>${b.head.map(c => `<th>${inline(c)}</th>`).join('')}</tr></thead>` +
        `<tbody>${b.rows.map(r => `<tr>${r.map(c => `<td>${inline(c)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
    default: return '';
  }
}

// ---------- specimens ----------
const C = {
  ink: '#0C142B', petrol: '#044B6A', deep: '#08707C', teal: '#0C8B9B', bright: '#0BB8C4',
  paper: '#FFFFFF', mist: '#F2F5F7', tint: '#E3F2F3', line: '#D8DEE6', lineStrong: '#858C9D', muted: '#5E6679', slate: '#4A5468',
};

const LOGO_TILES = [
  ['logo/jbs-praxis-stacked-colour.svg', 'Stacked, colour', 'On Paper', C.paper, 'stacked'],
  ['logo/jbs-praxis-stacked-colour.svg', 'Stacked, colour', 'On Mist', C.mist, 'stacked'],
  ['logo/jbs-praxis-stacked-reversed.svg', 'Stacked, reversed', 'On Ink', C.ink, 'stacked'],
  ['logo/jbs-praxis-stacked-reversed.svg', 'Stacked, reversed', 'On Petrol', C.petrol, 'stacked'],
  ['logo/jbs-praxis-stacked-ink.svg', 'Stacked, one colour', 'Ink, for print and stamps', C.tint, 'stacked'],
  ['logo/jbs-praxis-stacked-white.svg', 'Stacked, one colour', 'White, on dark', C.ink, 'stacked'],
  ['logo/jbs-praxis-horizontal-colour.svg', 'Horizontal lockup, colour', 'Headers and rails', C.paper, 'wide'],
  ['logo/jbs-praxis-horizontal-reversed.svg', 'Horizontal lockup, reversed', 'HQ and Client Portal rail', C.ink, 'wide'],
  ['logo/jbs-praxis-monogram-colour.svg', 'Monogram, colour', 'Small spaces', C.paper, 'mono'],
  ['logo/jbs-praxis-monogram-reversed.svg', 'Monogram, reversed', 'Icons and avatars', C.ink, 'mono'],
];

const downloadables = {}; // id -> {name, type, text}
function svgDownload(p) {
  const id = 'svg-' + slug(path.basename(p, '.svg'));
  downloadables[id] = { name: path.basename(p), type: 'image/svg+xml', text: svgText(p) };
  return `<button class="link-btn screen-only" type="button" data-download="${id}">Download SVG</button>`;
}

function logoSheet() {
  return `<div class="wide logo-grid">${LOGO_TILES.map(([p, name, use, bg, shape]) => `
    <figure class="logo-tile ${shape}">
      <div class="logo-field" style="background:${bg}${bg === C.paper ? `;box-shadow:inset 0 0 0 1px ${C.line}` : ''}">${inlineSvg(p, 'aria-hidden="true"')}</div>
      <figcaption><strong>${name}</strong><span>${use}</span>${svgDownload(p)}</figcaption>
    </figure>`).join('')}
    <figure class="logo-tile stacked">
      <div class="logo-field" style="background:${C.mist}"><img src="${png('logo/jbs-praxis-original-gradient.png')}" alt=""></div>
      <figcaption><strong>Original artwork</strong><span>Gradient; large brand moments only (owner to decide)</span></figcaption>
    </figure></div>`;
}

function logoRules() {
  const colour = 'logo/jbs-praxis-stacked-colour.svg';
  const img = `<img src="data:image/svg+xml;base64,${Buffer.from(svgText(colour)).toString('base64')}" alt="">`;
  const donts = [
    ['Don’t stretch or squash', 'transform:scaleX(1.45)'],
    ['Don’t recolour', 'filter:hue-rotate(150deg) saturate(2.4)'],
    ['Don’t rotate', 'transform:rotate(-14deg)'],
    ['Don’t add shadows or glows', 'filter:drop-shadow(0 0 10px #0BB8C4) drop-shadow(0 6px 6px rgba(0,0,0,.5))'],
  ];
  return `<div class="wide rules-grid">
    <figure class="clear">
      <div class="clear-field"><div class="clear-box">${inlineSvg(colour, 'aria-hidden="true"')}</div></div>
      <figcaption><strong>Clear space</strong><span>One bar plus one gap on every side, about 5% of the logo's height.</span></figcaption>
    </figure>
    <figure class="mins">
      <div class="mins-field">
        <div><div style="height:112px">${inlineSvg(colour, 'aria-hidden="true" height="112"')}</div><span>Stacked: 112px</span></div>
        <div><div style="height:24px">${inlineSvg('logo/jbs-praxis-horizontal-colour.svg', 'aria-hidden="true" height="24"')}</div><span>Horizontal: 24px</span></div>
        <div><div style="height:16px"><img src="${dataUri(path.join(RENDER, 'favicon-16.png'))}" width="16" height="16" alt=""></div><span>Icon: 16px</span></div>
      </div>
      <figcaption><strong>Minimum sizes</strong><span>On screen. In print: 25mm stacked, 8mm horizontal.</span></figcaption>
    </figure>
  </div>
  <div class="wide dont-grid">${donts.map(([t, s]) => `
    <figure><div class="dont-field"><div style="${s}">${img}</div></div><figcaption>${t}</figcaption></figure>`).join('')}
    <figure><div class="dont-field busy"><div>${img}</div></div><figcaption>Don’t place on busy images</figcaption></figure>
  </div>`;
}

function swatches() {
  const brand = [
    ['Ink', C.ink, 'Text, dark surfaces'], ['Petrol', C.petrol, 'Hover, heading accent'],
    ['Deep Teal', C.deep, 'Links, actions, focus'], ['Teal', C.teal, 'The logo’s teal; large text, rules'],
    ['Bright Teal', C.bright, 'On dark surfaces only'],
  ];
  const neutrals = [['Paper', C.paper], ['Mist', C.mist], ['Teal Tint', C.tint], ['Line', C.line], ['Line Strong', C.lineStrong], ['Muted', C.muted], ['Slate', C.slate]];
  const contrast = hex => {
    const w = ratio(hex, '#FFFFFF'), k = ratio(hex, C.ink);
    return hex === C.bright ? `${r1(k)}:1 on Ink` : `${r1(w)}:1 on white`;
  };
  return `<div class="wide swatches brand">${brand.map(([n, h, role]) => `
    <figure><div class="chip" style="background:${h}"></div><figcaption><strong>${n}</strong>
      <span class="hex">${h}</span><span>RGB ${hexRgb(h).join(' ')}</span><span>${role}</span><span>${contrast(h)}</span></figcaption></figure>`).join('')}</div>
    <div class="wide swatches neutral">${neutrals.map(([n, h]) => `
    <figure><div class="chip" style="background:${h}${h === C.paper ? `;box-shadow:inset 0 0 0 1px ${C.line}` : ''}"></div><figcaption><strong>${n}</strong><span class="hex">${h}</span></figcaption></figure>`).join('')}</div>`;
}

function functionalDemo() {
  const pills = [['Paid', '#1A6E47', '#E7F4EC'], ['Awaiting signature', '#8A5700', '#FBF1DF'], ['Overdue', '#B3261E', '#FBE9E7'], ['Sent', C.petrol, C.tint]];
  return `<div class="wide panel">
    <div class="pills">${pills.map(([t, f, b]) => `<span class="pill" style="color:${f};background:${b}"><i style="background:${f}"></i>${t}</span>`).join('')}</div>
    <p class="small">Status always pairs the colour with a word. Text on its tint passes AA (5.4:1 to 8.2:1).</p>
    <div class="proportion" aria-label="Proportions: about 70% Paper and Mist, 20% Ink and Slate, 10% teal">
      <span style="flex:70;background:${C.paper}">Paper and Mist, about 70%</span>
      <span style="flex:20;background:${C.ink};color:#fff">Ink and Slate, 20%</span>
      <span style="flex:10;background:${C.teal};color:#fff">Teal 10%</span>
    </div></div>`;
}

function darkDemo() {
  return `<div class="wide panel dark">
    <p class="d1">Primary text in white</p>
    <p class="d2">Secondary text in #BBBDC4, a solid colour rather than opacity.</p>
    <p class="d3">Accents, links and focus in Bright Teal</p>
    <div class="d-status"><span style="color:#4CC38A">Verified</span><span style="color:#E0A33A">Awaiting verification</span><span style="color:#F0857B">Overdue</span></div>
    <div class="d-rule"></div>
  </div>`;
}

function dataDemo() {
  const seq = ['#E3F2F3', '#A8DCE0', '#5FC6CE', '#36A7B3', '#0C8B9B', '#08707C', '#044B6A'];
  const bars = [[62, 48, 30], [80, 55, 41], [71, 66, 38], [90, 72, 52]];
  const chart = `<svg viewBox="0 0 360 150" class="mini-chart" role="img" aria-label="Example chart with three series">
    ${bars.map((g, k) => g.map((v, s) => `<rect x="${24 + k * 84 + s * 22}" y="${130 - v * 1.3}" width="18" height="${v * 1.3}" fill="${[C.petrol, C.teal, '#A8DCE0'][s]}" ${s === 2 ? `stroke="${C.petrol}" stroke-width="1"` : ''}/>`).join('')).join('')}
    <line x1="16" y1="130.5" x2="352" y2="130.5" stroke="${C.lineStrong}"/>
    ${['Q1', 'Q2', 'Q3', 'Q4'].map((q, k) => `<text x="${55 + k * 84}" y="146" text-anchor="middle" font-size="11" fill="${C.muted}">${q}</text>`).join('')}
  </svg>`;
  return `<div class="wide panel data">
    <div class="ramp">${seq.map((h, k) => `<span style="background:${h};color:${k < 5 ? C.ink : '#fff'}">${h}</span>`).join('')}</div>
    <div class="chart-row">${chart}<ul class="legend"><li><i style="background:${C.petrol}"></i>Series 1, Petrol</li><li><i style="background:${C.teal}"></i>Series 2, Teal</li><li><i style="background:#A8DCE0;box-shadow:inset 0 0 0 1px ${C.petrol}"></i>Series 3, light teal with a Petrol edge</li></ul></div>
  </div>`;
}

function typeSpecimen() {
  return `<div class="wide type-pair">
    <div class="type-card serif">
      <div class="aa">Aa</div>
      <p class="face">Newsreader</p><p class="job">Speaks: headlines, the homepage line, headline numbers, email headlines, document titles.</p>
      <p class="sample">Order, made visible.</p>
      <p class="glyphs">₦4,200,000 ${fr('« Réserver un audit offert »')} É À Ç</p>
    </div>
    <div class="type-card sans">
      <div class="aa">Aa</div>
      <p class="face">Plus Jakarta Sans</p><p class="job">Works: body text, navigation, buttons, forms, tables, HQ and the Client Portal.</p>
      <table class="mini-table"><tr><td>INV-0042</td><td>Acme Ltd (demo)</td><td class="num">₦1,200,000</td><td><span class="pill" style="color:#1A6E47;background:#E7F4EC"><i style="background:#1A6E47"></i>Paid</span></td></tr>
      <tr><td>INV-0043</td><td>Atlantic Pharma (demo)</td><td class="num">₦845,500</td><td><span class="pill" style="color:${C.petrol};background:${C.tint}"><i style="background:${C.petrol}"></i>Sent</span></td></tr></table>
      <span class="fake-btn">Book a free audit and demo</span>
    </div></div>`;
}

function typeScale() {
  const rows = [
    ['Display', '64 / 72', 'serif', 64, 72, 400, 'Ask your company anything.'],
    ['H1', '48 / 56', 'serif', 48, 56, 500, 'How we work'],
    ['H2', '36 / 44', 'serif', 36, 44, 500, 'Receivables fell 18% in Q3'],
    ['H3', '26 / 34', 'serif', 26, 34, 500, 'The Praxis Method'],
    ['Headline number', '56 / 60', 'serif', 56, 60, 400, '₦48.6m'],
    ['Lead', '20 / 32', 'sans', 20, 32, 400, 'We design how your organisation runs, then build the system that answers for it.'],
    ['Body', '16 / 26', 'sans', 16, 26, 400, 'Most institutions run on effort: numbers rebuilt by hand every month.'],
    ['UI label, button', '14 / 20', 'sans', 14, 20, 500, 'Send invitation'],
    ['Caption', '13 / 18', 'sans', 13, 18, 400, 'Source: Invoices, updated today at 09:40.'],
  ];
  return `<div class="wide scale">${rows.map(([n, s, f, px, lh, w, t]) => `
    <div class="scale-row"><div class="scale-meta"><strong>${n}</strong><span>${s}</span></div>
    <div class="scale-sample ${f}" style="font-size:${px}px;line-height:${lh}px;font-weight:${w}">${t}</div></div>`).join('')}</div>`;
}

function barStack(w = 640, h = 12, gap = 13, dark = C.ink, light = C.teal) {
  const off = h * 1.6; // the logo's diagonal ends
  const bars = [0, 1, 2].map(k => {
    const y = k * (h + gap), x0 = k * off * 1.6, x1 = w - x0;
    return `<polygon points="${x0},${y} ${x1},${y} ${x1 - off},${y + h} ${x0 + off},${y + h}"/>`;
  }).join('');
  const H = 3 * h + 2 * gap, id = 'bs' + (++svgSeq);
  return `<svg viewBox="0 0 ${w} ${H}" class="barstack" aria-hidden="true"><defs>
    <clipPath id="${id}l"><rect width="${w / 2}" height="${H}"/></clipPath><clipPath id="${id}r"><rect x="${w / 2}" width="${w / 2}" height="${H}"/></clipPath></defs>
    <g clip-path="url(#${id}l)" fill="${dark}">${bars}</g><g clip-path="url(#${id}r)" fill="${light}">${bars}</g></svg>`;
}

function praxisRule() {
  return `<div class="wide panel rule-forms">
    <figure><div class="hairline"></div><figcaption><strong>1. Hairline</strong><span>Separates sections in place of boxes.</span></figcaption></figure>
    <figure>${barStack()}<figcaption><strong>2. Bar stack</strong><span>Section openers and report covers, cut like the logo’s bars.</span></figcaption></figure>
    <figure><div class="moment"><span class="q">What are our receivables today?</span><span class="line"><i></i><b></b></span><span class="a">₦48.6m</span></div>
      <figcaption><strong>3. The moment</strong><span>Joins a question to its answer, Ink then Teal across the split. Demo data.</span></figcaption></figure>
  </div>`;
}

function motionDemo() {
  return `<div class="wide panel motion-demo">
    <div class="demo-screen" aria-label="Demo: a question joined to its answer">
      <p class="demo-q">What are our receivables today?</p>
      <div class="demo-line"><i></i><b></b></div>
      <p class="demo-a"><span class="sr-value">₦48.6m</span><span class="vis" aria-hidden="true">₦48.6m</span></p>
      <p class="demo-src">Source: Invoices, updated today at 09:40. Demo data.</p>
    </div>
    <button class="btn-primary screen-only" type="button" id="play-moment">Play the moment</button>
  </div>`;
}

function emailMock(lang) {
  const t = {
    en: { h: 'Your JBS Praxis Client Portal is ready', hello: 'Hello Ada,',
      body: 'Your organisation’s Client Portal is set up. Choose a password to sign in and see your projects, proposals and invoices in one place.',
      btn: 'Set up your account', alt: 'If the button doesn’t work, copy this link into your browser:',
      bye: 'Kind regards,', team: 'The JBS Praxis team', f1: 'JBS Praxis, Lagos and Douala',
      f2: 'You are receiving this because your organisation uses the JBS Praxis Client Portal.', subj: 'Subject: Your JBS Praxis Client Portal is ready' },
    fr: { h: fr('Votre portail client JBS Praxis est prêt'), hello: fr('Bonjour Madame Okafor,'),
      body: fr("Le portail client de votre organisation est prêt. Choisissez un mot de passe pour vous connecter et retrouver vos projets, propositions et factures au même endroit."),
      btn: fr('Configurer votre compte'), alt: fr('Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :'),
      bye: fr('Bien cordialement,'), team: fr("L'équipe JBS Praxis"), f1: fr('JBS Praxis, Lagos et Douala'),
      f2: fr("Vous recevez ce message parce que votre organisation utilise le portail client JBS Praxis."), subj: fr('Objet : Votre portail client JBS Praxis est prêt') },
  }[lang];
  return `<figure class="mail-wrap"><figcaption>${t.subj}</figcaption><div class="mail">
    <div class="mail-card">
      <div class="mail-head"><img src="${png('email/jbs-praxis-email-logo@2x.png')}" alt="JBS Praxis" height="48"></div>
      <div class="mail-rule"><i></i><b></b></div>
      <div class="mail-body">
        <h4>${t.h}</h4><p>${t.hello}</p><p>${t.body}</p>
        <span class="mail-btn">${t.btn}</span>
        <p class="mail-small">${t.alt}<br>portal.jbspraxis.com/setup/…</p>
        <p>${t.bye}<br>${t.team}</p>
      </div>
      <div class="mail-foot"><p>${t.f1}</p><p>${t.f2}</p></div>
    </div></div></figure>`;
}

function phone(inner, label) {
  return `<figure class="phone-fig"><div class="phone">${inner}</div><figcaption>${label}</figcaption></figure>`;
}

function appsGallery() {
  const icon = png('icons/icon-512.png');
  const others = ['Mail', 'Calendar', 'Files', 'Maps', 'Notes', 'Photos', 'Clock', 'Settings', 'Camera', 'Weather'];
  const home = `<div class="home"><div class="home-grid">
      <span class="app"><img src="${icon}" alt=""><em>HQ</em></span>
      <span class="app"><img src="${icon}" alt=""><em>Client Portal</em></span>
      ${others.map(n => `<span class="app"><i></i><em>${n}</em></span>`).join('')}
    </div></div>`;
  const splashHq = `<img class="splash" src="${png('splash/hq-splash-1179x2556.png')}" alt="">`;
  const splashPortal = `<img class="splash" src="${png('splash/portal-splash-1179x2556.png')}" alt="">`;
  const fav16 = dataUri(path.join(RENDER, 'favicon-16.png')), fav32 = png('icons/favicon-32.png');
  return `<div class="wide phones">
      ${phone(home, 'Installed on a phone: the app icon')}
      ${phone(splashHq, 'HQ splash screen')}
      ${phone(splashPortal, 'Client Portal splash screen')}
    </div>
    <div class="wide panel browser">
      <div class="tabbar"><span class="tab"><img src="${fav32}" width="16" height="16" alt="">Overview | JBS Praxis HQ</span><span class="tab dim"><img src="${fav32}" width="16" height="16" alt="">Invoices | JBS Praxis Client Portal</span></div>
      <div class="icon-row">
        <figure><img src="${fav16}" width="16" height="16" alt=""><figcaption>Favicon 16</figcaption></figure>
        <figure><img src="${fav32}" width="32" height="32" alt=""><figcaption>Favicon 32</figcaption></figure>
        <figure><img src="${png('icons/apple-touch-icon.png')}" width="60" height="60" style="border-radius:14px" alt=""><figcaption>iPhone, 180</figcaption></figure>
        <figure><img src="${png('icons/icon-192.png')}" width="72" height="72" alt=""><figcaption>App icon, 192 and 512</figcaption></figure>
        <figure><img src="${png('icons/icon-maskable-512.png')}" width="72" height="72" style="border-radius:50%" alt=""><figcaption>Maskable, cropped by Android</figcaption></figure>
        <figure><img src="${png('social/jbs-praxis-avatar-400.png')}" width="72" height="72" style="border-radius:50%" alt=""><figcaption>LinkedIn avatar</figcaption></figure>
      </div>
    </div>
    <div class="wide og-row">
      <figure><img src="${png('social/jbs-praxis-og-en-1200x630.png')}" alt="Link preview in English"><figcaption>Link preview, English (1200 × 630)</figcaption></figure>
      <figure><img src="${png('social/jbs-praxis-og-fr-1200x630.png')}" alt="Link preview in French"><figcaption>Link preview, French</figcaption></figure>
    </div>`;
}

function kitSection() {
  const groups = [
    ['logo', 'Logo versions in SVG, plus PNG exports and the original artwork'],
    ['icons', 'Favicons, app icons, web app manifests and the <head> lines for HQ and the Client Portal'],
    ['splash', 'iPhone and iPad splash screens for HQ and the Client Portal'],
    ['email', 'The email header logo'],
    ['social', 'LinkedIn avatar and link-preview images in English and French'],
  ];
  const list = dir => {
    const out = [];
    const walk = d => fs.readdirSync(d, { withFileTypes: true }).forEach(e => e.isDirectory() ? walk(path.join(d, e.name)) : out.push(path.relative(KIT, path.join(d, e.name)).replace(/\\/g, '/')));
    walk(path.join(KIT, dir)); return out.sort();
  };
  return `<section class="section" id="brand-kit"><h2 class="sec"><span class="num">Kit</span>Brand kit</h2><div class="rule"></div>
    <p>Every file below is in one zip. Use the files as they are; do not redraw, recolour or re-export them.</p>
    <p class="screen-only"><button class="btn-primary" type="button" data-download="kit">Download the brand kit (.zip)</button></p>
    <div class="table-wrap"><table><thead><tr><th>Folder</th><th>What it holds</th><th>Files</th></tr></thead><tbody>
    ${groups.map(([d, what]) => `<tr><td><strong>${d}</strong></td><td>${esc(what)}</td><td class="files">${list(d).map(f => esc(f.split('/').slice(1).join('/'))).join('<br>')}</td></tr>`).join('')}
    </tbody></table></div></section>`;
}

// ---------- injections into the document flow ----------
const before = {
  '3|Spacing': praxisRule,
  '3|The hero moment (storyboard)': motionDemo,
  '3|Documents (proposals, reports, invoices)': appsGallery,
  '3|Proportions': functionalDemo,
  '3|Data visualisation': darkDemo,
  '3|Accessibility': dataDemo,
  '3|Loading and fallbacks': typeScale,
};
const afterNext = {
  '3|The mark': logoSheet,
  '3|Rules': logoRules,
  '2|7. Colour': swatches,
  '2|8. Typography': typeSpecimen,
};
const replaceNextCode = { '3|Layout': () => `<div class="wide mail-pair">${emailMock('en')}${emailMock('fr')}</div>` };

// ---------- assemble ----------
function build({ embed }) {
  svgSeq = 0;
  const blocks = parseBlocks(md);
  const sections = []; const toc = [];
  let cur = null, intro = [], pending = null, replaceCode = null, prevWasH3 = false, skip = false;
  for (const b of blocks) {
    if (b.type === 'h' && b.level === 1) continue;
    if (b.type === 'hr') continue;
    if (b.type === 'h' && b.level === 2) {
      skip = b.text === 'Contents';
      if (skip) continue;
      const m = b.text.match(/^(\d+)\.\s+(.*)$/) || b.text.match(/^Appendix ([A-Z]):\s+(.*)$/);
      const num = m ? m[1] : '', title = m ? m[2] : b.text;
      const id = slug(b.text);
      const label = title.charAt(0).toUpperCase() + title.slice(1);
      toc.push({ id, num, title: label });
      cur = { id, html: `<h2 class="sec"><span class="num">${num}</span>${inline(label)}</h2><div class="rule"></div>` };
      sections.push(cur);
      const key = `2|${b.text}`;
      if (afterNext[key]) pending = afterNext[key];
      prevWasH3 = false;
      continue;
    }
    if (skip) continue;
    if (!cur) { intro.push(b); continue; }
    if (b.type === 'h') {
      const key = `${b.level}|${b.text}`;
      if (before[key]) cur.html += before[key]();
      cur.html += `<h${b.level}>${inline(b.text)}</h${b.level}>`;
      if (afterNext[key]) pending = afterNext[key];
      if (replaceNextCode[key]) replaceCode = replaceNextCode[key];
      prevWasH3 = b.level === 3;
      continue;
    }
    if (b.type === 'code' && replaceCode) { cur.html += replaceCode(); replaceCode = null; continue; }
    // A paragraph that is all bold, straight after a heading, is a statement line (essence, promise, descriptor)
    if (b.type === 'p' && prevWasH3 && /^\*\*[^*]+\*\*$/.test(b.text)) cur.html += `<p class="statement">${inline(b.text.slice(2, -2))}</p>`;
    else cur.html += renderBlock(b);
    prevWasH3 = false;
    if (pending) { cur.html += pending(); pending = null; }
  }

  const introHtml = intro.filter(b => b.type === 'p' && !/^Version /.test(b.text)).map(renderBlock).join('');
  const kit = kitSection();
  toc.push({ id: 'brand-kit', num: '', title: 'Brand kit' });

  const embeds = !embed ? '' : [
    `<script type="application/octet-stream" id="dl-pdf" data-name="${PDF_NAME}" data-type="application/pdf" data-encoding="base64">${fs.readFileSync(path.join(BRAND, PDF_NAME)).toString('base64')}</script>`,
    `<script type="application/octet-stream" id="dl-kit" data-name="${ZIP_NAME}" data-type="application/zip" data-encoding="base64">${fs.readFileSync(path.join(BRAND, ZIP_NAME)).toString('base64')}</script>`,
  ].join('\n');
  const svgEmbeds = Object.entries(downloadables).map(([id, d]) =>
    `<script type="text/plain" id="dl-${id}" data-name="${d.name}" data-type="${d.type}">${d.text}</script>`).join('\n');

  return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>JBS Praxis brand book</title>
<meta name="description" content="The JBS Praxis brand architecture: platform, voice, vocabulary, logo, colour, type, motion, email and applications, in English and French.">
<meta name="robots" content="noindex">
<link rel="icon" href="${png('icons/favicon-32.png')}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>${CSS}</style>
</head>
<body>
<a class="skip screen-only" href="#s-intro">Skip to content</a>
<div class="layout">
  <nav class="rail screen-only" aria-label="Contents">
    <div class="rail-logo">${inlineSvg('logo/jbs-praxis-horizontal-reversed.svg', 'aria-hidden="true"')}</div>
    <p class="rail-title">Brand book, version ${VERSION[1]}</p>
    <details class="toc-wrap" open><summary>Contents</summary>
      <ol class="toc">${toc.map(t => `<li><a href="#${t.id}"><span class="n">${t.num}</span>${inline(t.title)}</a></li>`).join('')}</ol>
    </details>
    <div class="rail-actions">
      <button class="btn-primary" type="button" data-download="pdf">Download PDF</button>
      <button class="btn-ghost" type="button" data-download="kit">Download the brand kit</button>
    </div>
  </nav>
  <main>
    <header class="cover">
      <div class="cover-logo">${inlineSvg('logo/jbs-praxis-stacked-reversed.svg', 'aria-hidden="true"')}</div>
      <div class="cover-text">
        <h1>Brand architecture</h1>
        <p class="cover-sub">JBS Praxis. Consulting and engineering for Africa’s institutions.</p>
        <p class="cover-meta">Version ${VERSION[1]}, ${VERSION[2]}. In English and French.</p>
        <div class="cover-actions screen-only">
          <button class="btn-primary" type="button" data-download="pdf">Download PDF</button>
          <button class="btn-ghost" type="button" data-download="kit">Download the brand kit</button>
        </div>
      </div>
      <div class="cover-bars">${barStack(640, 12, 13, '#FFFFFF', C.bright)}</div>
    </header>
    <section class="section intro" id="s-intro">
      <h2 class="sec"><span class="num"></span>How to use this book</h2><div class="rule"></div>
      ${introHtml}
      <h3>Where to start</h3>
      <div class="table-wrap"><table><thead><tr><th>If you work in</th><th>Read first</th></tr></thead><tbody>
        <tr><td>Sales and partnerships</td><td>1 Brand platform (positioning, promise, proof, boilerplates), 2 Brand architecture, 4 Voice, 5 Vocabulary</td></tr>
        <tr><td>Marketing and content</td><td>1 Brand platform, 3 Personality, 4 Voice, 5 Vocabulary, 10 Imagery, 14 Applications, and the brand kit</td></tr>
        <tr><td>Design and engineering</td><td>6 Logo to 15 Design tokens</td></tr>
        <tr><td>Anyone writing in French</td><td>4 Voice (French, numbers and dates) and 5 Vocabulary</td></tr>
      </tbody></table></div>
      <nav class="print-toc print-only" aria-label="Contents"><h3>Contents</h3><ol>${toc.map(t => `<li><a href="#${t.id}"><span class="n">${t.num}</span>${inline(t.title)}</a></li>`).join('')}</ol></nav>
    </section>
    ${sections.map(s => `<section class="section" id="${s.id}">${s.html}</section>`).join('\n')}
    ${kit}
    <footer class="end"><div class="end-logo">${inlineSvg('logo/jbs-praxis-horizontal-reversed.svg', 'aria-hidden="true"')}</div><p>Order, made visible.</p><p class="small">The owner approves changes to this book (section 16). Source: docs/BRAND.md.</p></footer>
  </main>
</div>
${embeds}
${svgEmbeds}
<script>${JS}</script>
</body>
</html>`;
}

const CSS = `
:root{color-scheme:light;--ink:#0C142B;--petrol:#044B6A;--deep:#08707C;--teal:#0C8B9B;--bright:#0BB8C4;--paper:#FFFFFF;--mist:#F2F5F7;--tint:#E3F2F3;--line:#D8DEE6;--line-strong:#858C9D;--muted:#5E6679;--slate:#4A5468;--dm:#BBBDC4;--dr:#2E3549;
--serif:"Newsreader",Georgia,serif;--sans:"Plus Jakarta Sans","Segoe UI",Arial,sans-serif;--settle:cubic-bezier(.2,0,0,1);--rail:272px}
*{box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{margin:0;background:var(--paper);color:var(--slate);font:400 16px/26px var(--sans);font-variant-numeric:lining-nums}
img,svg{max-width:100%}
a{color:var(--deep);text-underline-offset:3px}
:focus-visible{outline:2px solid var(--deep);outline-offset:2px}
.rail :focus-visible,.cover :focus-visible,.end :focus-visible{outline-color:var(--bright)}
.skip{position:absolute;left:-9999px;top:8px;background:var(--paper);padding:8px 12px;z-index:10}.skip:focus{left:8px}
.print-only{display:none}
.layout{display:grid;grid-template-columns:var(--rail) minmax(0,1fr)}
.rail{position:sticky;top:0;height:100vh;overflow:auto;background:var(--ink);color:var(--dm);padding:28px 18px 24px;display:flex;flex-direction:column;gap:18px}
.rail-logo svg{display:block;width:196px;height:auto}
.rail-title{margin:0;font:500 13px/18px var(--sans);color:var(--dm)}
.toc-wrap summary{font:600 13px/18px var(--sans);color:#fff;cursor:pointer;margin-bottom:6px;list-style:none}
.toc-wrap summary::-webkit-details-marker{display:none}
.toc{list-style:none;margin:0;padding:0}
.toc a{display:flex;gap:8px;padding:5px 8px 5px 10px;border-left:2px solid transparent;color:var(--dm);text-decoration:none;font:500 13.5px/19px var(--sans)}
.toc a:hover{color:#fff}
.toc a.on{color:#fff;border-left-color:var(--bright)}
.toc .n{min-width:20px;font-variant-numeric:tabular-nums}
.rail-actions{margin-top:auto;display:grid;gap:8px}
button{font:500 14px/20px var(--sans);cursor:pointer}
.btn-primary,.btn-ghost{padding:10px 16px;border-radius:4px;border:1px solid transparent;text-align:center}
.btn-primary{background:var(--deep);color:#fff}.btn-primary:hover{background:var(--petrol)}
.rail .btn-primary,.cover .btn-primary{background:var(--bright);color:var(--ink)}.rail .btn-primary:hover,.cover .btn-primary:hover{background:#fff}
.btn-ghost{background:transparent;color:#fff;border-color:#4A5468}.btn-ghost:hover{border-color:var(--bright)}
.link-btn{background:none;border:0;padding:0;color:var(--deep);text-decoration:underline;text-underline-offset:3px;font:500 13px/18px var(--sans);justify-self:start}
main{min-width:0}
.cover{background:var(--ink);color:#fff;min-height:100vh;display:grid;grid-template-columns:minmax(0,5fr) minmax(0,6fr);grid-template-rows:1fr auto;align-items:center;column-gap:72px;padding:72px 72px 48px}
.cover-logo svg{display:block;width:100%;max-width:380px;height:auto;margin-left:auto}
.cover h1{font:400 64px/72px var(--serif);letter-spacing:-.01em;margin:0 0 20px;color:#fff}
.cover-sub{font:400 20px/32px var(--sans);color:var(--dm);margin:0 0 8px;max-width:30em}
.cover-meta{font:500 14px/20px var(--sans);color:var(--dm);margin:0 0 32px}
.cover-actions{display:flex;flex-wrap:wrap;gap:12px}
.cover-bars{grid-column:1 / -1;justify-self:end;width:min(420px,60%);margin-top:56px}
.barstack{display:block;width:100%;height:auto}
.section{padding:88px 72px 48px;border-top:1px solid var(--line)}
.section>*{max-width:760px}
.section>.wide,.section>.table-wrap,.section>pre,.section>.rule{max-width:1060px}
h2.sec{font:500 48px/56px var(--serif);color:var(--ink);letter-spacing:-.01em;margin:0 0 20px;display:flex;gap:24px;align-items:baseline;text-wrap:balance}
h2.sec .num{color:var(--teal);font-weight:400;font-variant-numeric:tabular-nums}
h2.sec .num:empty{display:none}
.rule{height:2px;background:linear-gradient(to right,var(--ink) 0 50%,var(--teal) 50% 100%);margin:0 0 40px}
h3{font:500 26px/34px var(--serif);color:var(--ink);margin:48px 0 12px;text-wrap:balance}
h4{font:600 16px/24px var(--sans);color:var(--ink);margin:24px 0 8px}
p{margin:0 0 16px}
strong{color:var(--ink);font-weight:600}
ul,ol{margin:0 0 20px;padding-left:22px}
li{margin:0 0 8px}li>ul{margin:8px 0 0}
li.check{list-style:none;position:relative}
li.check::before{content:"";position:absolute;left:-22px;top:5px;width:14px;height:14px;border:1.5px solid var(--line-strong);border-radius:3px}
.statement{font:400 40px/48px var(--serif);color:var(--ink);letter-spacing:-.01em;margin:4px 0 24px;text-wrap:balance}
blockquote{margin:16px 0 24px;padding:4px 0 4px 20px;border-left:2px solid var(--deep);color:var(--ink)}
blockquote p{margin:0 0 8px}
code{font:500 .88em var(--sans);background:var(--mist);color:var(--ink);padding:1px 5px;border-radius:4px;font-variant-numeric:tabular-nums}
pre.code{background:var(--mist);padding:20px 24px;overflow:auto;font:400 13px/20px ui-monospace,Consolas,monospace;color:var(--ink);margin:16px 0 28px}
pre.code code{background:none;padding:0;font:inherit}
.table-wrap{overflow-x:auto;margin:20px 0 32px}
table{border-collapse:collapse;width:100%;font:400 14px/22px var(--sans);color:var(--slate)}
th{font-weight:600;color:var(--ink);text-align:left;padding:10px 12px 10px 0;border-bottom:1px solid var(--line-strong);vertical-align:bottom}
td{padding:10px 12px 10px 0;border-bottom:1px solid var(--line);vertical-align:top}
td code{font-size:13px}
td.files{font-size:13px;line-height:20px;color:var(--muted)}
.small{font-size:14px;line-height:22px;color:var(--muted)}
.panel{background:var(--mist);padding:32px;margin:28px 0 40px}
figure{margin:0}
figcaption{font:400 13px/18px var(--sans);color:var(--muted)}
figcaption strong{display:block;font-size:14px;line-height:20px}
/* logo */
.logo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:20px;margin:28px 0 40px}
.logo-tile{display:grid;gap:10px;align-content:start}
.logo-tile.wide,.logo-tile.mono{grid-column:span 2}
.logo-field{aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;padding:14%}
.logo-tile.wide .logo-field,.logo-tile.mono .logo-field{aspect-ratio:2.05/1;padding:9% 12%}
.logo-field svg,.logo-field img{width:100%;height:100%;object-fit:contain;display:block}
.logo-tile figcaption{display:grid;gap:2px}
.rules-grid{display:grid;grid-template-columns:1fr 1.4fr;gap:20px;margin:24px 0 20px}
.clear-field,.mins-field{background:var(--mist);display:flex;align-items:center;justify-content:center;padding:28px;min-height:280px;margin-bottom:10px}
.clear-box{outline:1px dashed var(--deep);outline-offset:10px;height:200px}.clear-box svg{height:100%;width:auto;display:block}
.mins-field{gap:36px;align-items:flex-end;flex-wrap:wrap}
.mins-field>div{display:grid;gap:8px;justify-items:center;font:500 12px/16px var(--sans);color:var(--muted)}
.mins-field svg{display:block;width:auto}
.dont-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px;margin:0 0 40px}
.dont-field{aspect-ratio:1/1;background:var(--mist);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;margin-bottom:8px}
.dont-field>div{width:46%}.dont-field img{display:block;width:100%}
.dont-field::after{content:"";position:absolute;inset:auto 10px 10px auto;width:18px;height:18px;border-radius:50%;background:#B3261E;
  -webkit-mask:linear-gradient(45deg,transparent 44%,#000 44% 56%,transparent 56%),linear-gradient(-45deg,transparent 44%,#000 44% 56%,transparent 56%)}
.dont-field.busy{background:repeating-linear-gradient(120deg,#5FC6CE 0 14px,#E0A33A 14px 26px,#0C142B 26px 40px)}
.dont-grid figcaption{color:#B3261E;font-weight:500}
/* colour */
.swatches{display:grid;gap:16px;margin:28px 0 20px}
.swatches.brand{grid-template-columns:repeat(5,minmax(0,1fr))}
.swatches.neutral{grid-template-columns:repeat(7,minmax(0,1fr));margin-bottom:40px}
.swatches .chip{height:150px;margin-bottom:10px}
.swatches.neutral .chip{height:72px}
.swatches figcaption{display:grid;gap:1px}
.swatches strong{font:500 20px/26px var(--serif);color:var(--ink)}
.swatches.neutral strong{font:600 13px/18px var(--sans)}
.hex{font-variant-numeric:tabular-nums;color:var(--ink);font-weight:500}
.pills{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px}
.pill{display:inline-flex;align-items:center;gap:6px;font:600 13px/18px var(--sans);padding:3px 10px;border-radius:999px;white-space:nowrap}
.pill i{width:6px;height:6px;border-radius:50%}
.proportion{display:flex;height:56px;margin-top:20px;font:500 12px/16px var(--sans);color:var(--ink);box-shadow:inset 0 0 0 1px var(--line)}
.proportion span{display:flex;align-items:center;padding:0 12px;overflow:hidden;white-space:nowrap}
.panel.dark{background:var(--ink)}
.panel.dark p{margin:0 0 10px}.d1{color:#fff;font:500 20px/28px var(--sans)}.d2{color:var(--dm)}.d3{color:var(--bright);font-weight:500}
.d-status{display:flex;flex-wrap:wrap;gap:24px;font:600 14px/20px var(--sans);margin:16px 0 20px}
.d-rule{height:1px;background:var(--dr)}
.ramp{display:grid;grid-template-columns:repeat(7,1fr);margin-bottom:24px}
.ramp span{padding:28px 8px 10px;font:500 12px/16px var(--sans);font-variant-numeric:tabular-nums}
.chart-row{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:28px;align-items:end}
.mini-chart{width:100%;height:auto;background:var(--paper);display:block}
.mini-chart text{font-family:var(--sans)}
.legend{list-style:none;padding:0;margin:0;font:500 13px/18px var(--sans);color:var(--ink)}
.legend li{display:flex;gap:10px;align-items:center;margin-bottom:10px}.legend i{width:14px;height:14px;flex:none}
/* type */
.type-pair{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:28px 0 40px}
.type-card{background:var(--mist);padding:32px}
.aa{font-size:120px;line-height:1;color:var(--ink);margin-bottom:20px}
.serif .aa,.serif .sample{font-family:var(--serif)}.sans .aa{font-family:var(--sans);font-weight:500}
.face{font:600 16px/24px var(--sans);color:var(--ink);margin:0 0 4px}.job{font-size:14px;line-height:22px;color:var(--muted)}
.sample{font-size:40px;line-height:48px;color:var(--ink);letter-spacing:-.01em;margin:20px 0 12px}
.glyphs{font:400 22px/32px var(--serif);color:var(--slate)}
.mini-table{margin:16px 0 20px;background:var(--paper);font-variant-numeric:tabular-nums}
.mini-table td{padding:10px 12px;font-size:14px;color:var(--ink)}.mini-table td.num{text-align:right}
.fake-btn{display:inline-block;background:var(--deep);color:#fff;font:500 14px/20px var(--sans);padding:10px 16px;border-radius:4px}
.scale{margin:20px 0 40px;border-top:1px solid var(--line-strong)}
.scale-row{display:grid;grid-template-columns:180px minmax(0,1fr);gap:24px;padding:18px 0;border-bottom:1px solid var(--line);align-items:baseline}
.scale-meta{display:grid;font:400 13px/18px var(--sans);color:var(--muted)}
.scale-sample{color:var(--ink);text-wrap:balance}
.scale-sample.serif{font-family:var(--serif);letter-spacing:-.01em}.scale-sample.sans{font-family:var(--sans)}
/* rule forms */
.rule-forms{display:grid;gap:36px}
.rule-forms figcaption{display:grid;gap:2px;margin-top:12px}
.hairline{height:1px;background:#C5CCD6}
.rule-forms .barstack{max-width:420px}
.moment{display:grid;grid-template-columns:auto minmax(80px,1fr) auto;align-items:center;gap:16px}
.moment .q{font:500 15px/22px var(--sans);color:var(--ink)}
.moment .a{font:400 40px/44px var(--serif);color:var(--ink)}
.moment .line,.demo-line,.mail-rule{display:flex;height:2px}
.moment .line i,.demo-line i,.mail-rule i{flex:1;background:var(--ink)}.moment .line b,.demo-line b,.mail-rule b{flex:1;background:var(--teal)}
/* motion */
.motion-demo{display:grid;gap:20px;justify-items:start}
.demo-screen{background:var(--paper);box-shadow:inset 0 0 0 1px var(--line);border-radius:8px;padding:28px 32px;width:100%;max-width:640px}
.demo-q{font:500 16px/24px var(--sans);color:var(--ink);margin:0 0 16px}
.demo-line{transform-origin:left center;margin-bottom:16px}
.demo-a{font:400 56px/60px var(--serif);color:var(--ink);margin:0 0 8px;font-variant-numeric:lining-nums tabular-nums;position:relative}
.demo-a .sr-value{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.demo-src{font-size:13px;line-height:18px;color:var(--muted);margin:0}
/* email */
.mail-pair{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:24px 0 40px}
.mail-wrap>figcaption{font:500 13px/18px var(--sans);color:var(--ink);margin-bottom:8px}
.mail{background:#F2F5F7;padding:24px}
.mail-card{background:#fff;border-radius:8px;overflow:hidden}
.mail-head{padding:16px 28px 8px}.mail-head img{display:block;height:48px;width:auto}
.mail-body{padding:24px 28px 8px;font:400 15px/24px var(--sans);color:#4A5468}
.mail-body h4{font:400 26px/32px var(--serif);color:#0C142B;margin:0 0 16px}
.mail-btn{display:inline-block;background:#08707C;color:#fff;font:500 15px/20px var(--sans);padding:12px 18px;border-radius:4px;margin:4px 0 16px}
.mail-small{font-size:13px;line-height:20px;color:#5E6679}
.mail-foot{border-top:1px solid #D8DEE6;padding:14px 28px 16px;font:400 12px/18px var(--sans);color:#5E6679}.mail-foot p{margin:0}
/* applications */
.phones{display:grid;grid-template-columns:repeat(3,minmax(0,220px));gap:28px;justify-content:start;margin:28px 0 28px}
.phone{aspect-ratio:9/19.5;border:9px solid #0C142B;border-radius:34px;overflow:hidden;background:#DCE3EA;position:relative;margin-bottom:10px}
.phone .splash{display:block;width:100%;height:100%;object-fit:cover}
.home{padding:44px 14px 14px}
.home-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px 6px}
.app{display:grid;justify-items:center;gap:4px}
.app img,.app i{width:100%;max-width:40px;aspect-ratio:1/1;height:auto;border-radius:10px;display:block;background:#B9C3CF}
.app em{font:500 9px/12px var(--sans);font-style:normal;color:#0C142B;white-space:nowrap;overflow:hidden;max-width:56px;text-overflow:ellipsis}
.browser{padding:0 0 28px;overflow:hidden}
.tabbar{background:#DCE3EA;display:flex;gap:2px;padding:8px 8px 0}
.tab{display:flex;align-items:center;gap:8px;background:var(--paper);padding:9px 14px;border-radius:8px 8px 0 0;font:500 13px/18px var(--sans);color:var(--ink);white-space:nowrap;overflow:hidden;min-width:0}
.tab.dim{background:transparent;color:var(--slate)}
.icon-row{display:flex;flex-wrap:wrap;gap:32px;align-items:flex-end;padding:28px 32px 0}
.icon-row figure{display:grid;justify-items:center;gap:8px;max-width:110px;text-align:center}
.og-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:0 0 40px}
.og-row img{display:block;width:100%;height:auto;box-shadow:0 0 0 1px var(--line);margin-bottom:8px}
.end{background:var(--ink);color:var(--dm);padding:72px;display:grid;gap:12px}
.end-logo svg{display:block;width:240px;height:auto;margin-bottom:12px}
.end p{margin:0;font:400 28px/36px var(--serif);color:#fff}.end p.small{font:400 13px/20px var(--sans);color:var(--dm)}
@media screen and (max-width:1100px){
  .swatches.brand{grid-template-columns:repeat(3,minmax(0,1fr))}.swatches.neutral{grid-template-columns:repeat(4,minmax(0,1fr))}
  .type-pair,.mail-pair,.og-row,.rules-grid,.chart-row{grid-template-columns:1fr}
  .dont-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media screen and (max-width:900px){
  .layout{display:block}
  .rail{position:static;height:auto;padding:16px}
  .rail-logo svg{width:160px}
  .toc-wrap:not([open]) + .rail-actions{margin-top:0}
  .rail-actions{display:none}
  .cover{grid-template-columns:1fr;padding:48px 16px 32px;min-height:auto;row-gap:32px}
  .cover-logo svg{max-width:220px;margin:0}
  .cover h1{font-size:40px;line-height:48px}
  .cover-bars{margin-top:8px}
  .section{padding:56px 16px 32px}
  h2.sec{font-size:34px;line-height:42px;gap:14px}
  h3{font-size:22px;line-height:30px}
  .statement{font-size:28px;line-height:36px}
  .panel{padding:20px}
  .logo-tile.wide,.logo-tile.mono{grid-column:auto}
  .swatches.brand,.swatches.neutral{grid-template-columns:repeat(2,minmax(0,1fr))}
  .dont-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .phones{grid-template-columns:repeat(2,minmax(0,1fr))}
  .scale-row{grid-template-columns:1fr;gap:6px}
  .scale-sample{font-size:min(100%, 9vw)!important;line-height:1.2!important}
  .moment{grid-template-columns:1fr}
  .end{padding:48px 16px}
}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
@page{size:A4;margin:16mm 15mm 18mm;
  @bottom-left{content:"JBS Praxis brand architecture, version ${VERSION[1]}";font:400 8pt "Plus Jakarta Sans",Arial,sans-serif;color:#5E6679}
  @bottom-right{content:counter(page);font:500 8pt "Plus Jakarta Sans",Arial,sans-serif;color:#5E6679}}
@page :first{margin:0;@bottom-left{content:none}@bottom-right{content:none}}
@page back{margin:0;@bottom-left{content:none}@bottom-right{content:none}}
@media print{
  *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .screen-only{display:none!important}.print-only{display:block}
  .layout{display:block}
  body{font-size:10pt;line-height:15pt}
  .cover{height:297mm;min-height:0;padding:34mm 20mm 18mm;break-after:page;column-gap:14mm}
  .cover h1{font-size:38pt;line-height:44pt}.cover-sub{font-size:13pt;line-height:19pt}.cover-meta{font-size:9.5pt}
  .cover-logo svg{max-width:72mm}
  .cover-bars{width:80mm;margin-top:0;align-self:end}
  .section{break-before:page;padding:0;border:0}
  .section>*,.section>.wide,.section>.table-wrap,.section>pre,.section>.rule{max-width:none}
  h2.sec{font-size:26pt;line-height:32pt;margin-bottom:10pt}
  .rule{margin-bottom:18pt}
  h3{font-size:15pt;line-height:20pt;margin:18pt 0 6pt}
  h2,h3,h4{break-after:avoid}
  p,li{orphans:3;widows:3}
  .statement{font-size:22pt;line-height:28pt}
  table{font-size:8.5pt;line-height:12.5pt}
  th,td{padding:5pt 8pt 5pt 0}
  tr,figure,.panel,.type-card,.scale-row,.mail-wrap,.phone-fig,blockquote{break-inside:avoid}
  .table-wrap{overflow:visible}
  pre.code{white-space:pre-wrap;font-size:7.5pt;line-height:11pt;padding:10pt}
  .panel{padding:14pt;margin:12pt 0 18pt}
  .logo-grid{grid-template-columns:repeat(4,1fr);gap:10pt}
  .logo-tile.wide,.logo-tile.mono{grid-column:span 2}
  .swatches .chip{height:28mm}.swatches.neutral .chip{height:13mm}
  .aa{font-size:60pt}.sample{font-size:24pt;line-height:30pt}
  .scale-row{padding:8pt 0}
  .scale-sample{font-size:min(100%,28pt)!important;line-height:1.2!important}
  .phones{grid-template-columns:repeat(3,44mm);gap:8mm}
  .mail-pair{gap:10pt}.mail{padding:10pt}
  .end{page:back;break-before:page;height:297mm;align-content:center;padding:30mm 22mm}
  a{color:inherit;text-decoration:none}
  .print-toc ol{columns:2;column-gap:12mm;padding-left:0;list-style:none}
  .print-toc li{break-inside:avoid;margin:0 0 4pt}
  .print-toc .n{display:inline-block;min-width:8mm;color:#0C8B9B}
}`;

const JS = `
(function(){
  // On phones the contents start folded, so the cover is the first thing people see
  var tocWrap=document.querySelector('.toc-wrap');
  if(tocWrap&&window.matchMedia('(max-width: 900px)').matches)tocWrap.open=false;
  // Contents: highlight the section in view
  var links=[].slice.call(document.querySelectorAll('.toc a'));
  var byId={};links.forEach(function(a){byId[a.getAttribute('href').slice(1)]=a});
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){links.forEach(function(a){a.classList.remove('on');a.removeAttribute('aria-current')});var a=byId[e.target.id];if(a){a.classList.add('on');a.setAttribute('aria-current','true')}}})},{rootMargin:'-20% 0px -70% 0px'});
    document.querySelectorAll('main section[id]').forEach(function(s){io.observe(s)});
  }
  // Downloads: files are embedded in this page so they work when the page is shared on its own
  function blobFor(el){
    if(el.dataset.encoding==='base64'){var b=atob(el.textContent.trim()),u=new Uint8Array(b.length);for(var i=0;i<b.length;i++)u[i]=b.charCodeAt(i);return new Blob([u],{type:el.dataset.type})}
    return new Blob([el.textContent],{type:el.dataset.type});
  }
  document.addEventListener('click',function(ev){
    var btn=ev.target.closest('[data-download]');if(!btn)return;
    var key=btn.getAttribute('data-download'),el=document.getElementById('dl-'+key);
    if(!el){ if(key==='pdf'){window.print()} else if(key==='kit'){location.href='${ZIP_NAME}'} return; }
    var url=URL.createObjectURL(blobFor(el)),a=document.createElement('a');
    a.href=url;a.download=el.dataset.name;document.body.appendChild(a);a.click();
    setTimeout(function(){URL.revokeObjectURL(url);a.remove()},2000);
  });
  // The hero moment: the rule draws, then the last two digits settle
  var play=document.getElementById('play-moment');
  if(play){play.addEventListener('click',function(){
    var line=document.querySelector('.demo-line'),vis=document.querySelector('.demo-a .vis');
    var reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if(reduce||!line.animate){vis.textContent='₦48.6m';return}
    vis.textContent='₦47.1m';
    line.animate([{transform:'scaleX(0)'},{transform:'scaleX(1)'}],{duration:1200,easing:'cubic-bezier(.2,0,0,1)',fill:'both'});
    setTimeout(function(){var t0=performance.now();(function step(t){var k=Math.min(1,(t-t0)/600),e=1-Math.pow(1-k,3);vis.textContent='₦'+(47.1+1.5*e).toFixed(1)+'m';if(k<1)requestAnimationFrame(step)})(t0)},1200);
  })}
})();`;

// ---------- run ----------
fs.mkdirSync(RENDER, { recursive: true });
const printHtml = path.join(RENDER, 'book-print.html');
fs.writeFileSync(printHtml, build({ embed: false }));
const pdfPath = path.join(BRAND, PDF_NAME);
if (fs.existsSync(pdfPath)) fs.unlinkSync(pdfPath);
execFileSync(EDGE, ['--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw',
  '--virtual-time-budget=20000', `--print-to-pdf=${pdfPath}`, 'file:///' + printHtml.replace(/\\/g, '/')], { stdio: 'ignore', timeout: 180000 });
const pdfBytes = fs.statSync(pdfPath).size;
const pages = (fs.readFileSync(pdfPath, 'latin1').match(/\/Type\s*\/Page[^s]/g) || []).length;
fs.writeFileSync(path.join(BRAND, 'index.html'), build({ embed: true }));
console.log(`pdf ${Math.round(pdfBytes / 1024)} KB, ${pages} pages; index.html ${Math.round(fs.statSync(path.join(BRAND, 'index.html')).size / 1024)} KB`);
