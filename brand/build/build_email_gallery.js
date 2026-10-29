// Builds brand/email-previews.html: every branded email (from `php artisan app:email-samples`) in English and French,
// side by side, with subject lines and the plain-text part. One self-contained file for review.
//   cd praxis_core && APP_ENV=localmysql MAIL_MAILER=log php artisan app:email-samples
//   node brand/build/build_email_gallery.js
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const SAMPLES = path.join(ROOT, 'praxis_core', 'storage', 'app', 'email-samples');
const OUT = path.join(ROOT, 'brand', 'email-previews.html');
const index = JSON.parse(fs.readFileSync(path.join(SAMPLES, 'index.json'), 'utf8'));
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// Who receives what, in the order a client meets them
const GROUPS = [
  ['Website visitors', 'Sent the moment someone uses the website, in the language of the page (or the one they chose).', ['EnquiryReceived', 'AuditRequested', 'PraxisFrameworkDispatch', 'CopqReportMail']],
  ['From HQ to prospects and clients', 'Sent by staff from HQ, in the organisation’s stored language, else English.', ['MeetingApprovedMail', 'UserInvitation', 'TeamMemberInvitation', 'SigningCode', 'IntelligenceBroadcast']],
  ['Internal: to intelligence@', 'Team alerts, English only. Replies go straight to the visitor.', ['EnquiryAlert', 'AuditRequestAlert', 'SystemAlert']],
];
const NAMES = {
  EnquiryReceived: 'Enquiry received', AuditRequested: 'Free audit requested', PraxisFrameworkDispatch: 'Method download',
  CopqReportMail: 'Cost of poor quality report', MeetingApprovedMail: 'Free audit confirmed', UserInvitation: 'Account invitation',
  TeamMemberInvitation: 'Team member invitation', SigningCode: 'Signing code', IntelligenceBroadcast: 'Broadcast',
  EnquiryAlert: 'New enquiry', AuditRequestAlert: 'New free audit request', SystemAlert: 'System Health alert',
};

const bySlug = {};
for (const r of index) (bySlug[r.slug] ||= {})[r.locale] = r;
const frame = (slug, loc) => {
  const html = fs.readFileSync(path.join(SAMPLES, `${slug}.${loc}.html`), 'utf8');
  const text = fs.readFileSync(path.join(SAMPLES, `${slug}.${loc}.txt`), 'utf8');
  return `<figure class="mail">
    <figcaption><span class="lang">${loc === 'fr' ? 'Français' : 'English'}</span><strong>${esc(bySlug[slug][loc].subject)}</strong></figcaption>
    <iframe loading="lazy" title="${esc(bySlug[slug][loc].subject)}" srcdoc="${esc(html)}"></iframe>
    <details><summary>Plain-text version</summary><pre>${esc(text)}</pre></details>
  </figure>`;
};

let body = '', toc = '', count = 0;
for (const [title, note, classes] of GROUPS) {
  body += `<section><h2>${title}</h2><p class="note">${note}</p>`;
  toc += `<li><a href="#${classes[0]}">${title}</a></li>`;
  for (const cls of classes) {
    const slugs = Object.keys(bySlug).filter(s => s.startsWith(cls + '-'));
    slugs.forEach((slug, i) => {
      const variant = slug.slice(cls.length + 1).replace(/-/g, ' ');
      body += `<article id="${i === 0 ? cls : slug}"><h3>${NAMES[cls] || cls}${slugs.length > 1 ? `<span class="variant">${esc(variant)}</span>` : ''}</h3>
        <div class="pair">${['en', 'fr'].filter(l => bySlug[slug][l]).map(l => frame(slug, l)).join('')}</div></article>`;
      count += Object.keys(bySlug[slug]).length;
    });
  }
  body += '</section>';
}

const html = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>JBS Praxis email previews</title>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--ink:#0C142B;--teal:#0C8B9B;--deep:#08707C;--mist:#F2F5F7;--line:#D8DEE6;--muted:#5E6679;--slate:#4A5468}
*{box-sizing:border-box}body{margin:0;background:#fff;color:var(--slate);font:400 15px/24px "Plus Jakarta Sans",Arial,sans-serif}
header{background:var(--ink);color:#fff;padding:48px 48px 32px}
header h1{font:400 44px/52px Newsreader,Georgia,serif;margin:0 0 12px}
header p{margin:0 0 6px;color:#BBBDC4;max-width:52em}
header ul{list-style:none;padding:0;margin:20px 0 0;display:flex;flex-wrap:wrap;gap:8px 24px}header a{color:#0BB8C4;font-weight:500}
.rule{height:2px;background:linear-gradient(to right,var(--ink) 0 50%,var(--teal) 50% 100%)}
section{padding:40px 48px 8px;border-bottom:1px solid var(--line)}
h2{font:500 32px/40px Newsreader,Georgia,serif;color:var(--ink);margin:0 0 4px}.note{margin:0 0 24px;color:var(--muted)}
h3{font:600 17px/24px "Plus Jakarta Sans",Arial,sans-serif;color:var(--ink);margin:28px 0 12px;display:flex;gap:12px;align-items:baseline}
.variant{font-weight:500;font-size:13px;color:var(--muted)}
.pair{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,560px),1fr));gap:24px}
.mail{margin:0;background:var(--mist);padding:12px}
.mail figcaption{display:grid;gap:2px;padding:4px 4px 10px;font-size:14px;line-height:20px;color:var(--ink)}
.lang{font-size:12px;font-weight:600;color:var(--deep)}
iframe{display:block;width:100%;height:880px;border:0;background:var(--mist)}
details{padding:8px 4px 2px}summary{cursor:pointer;font-size:13px;font-weight:500;color:var(--deep)}
pre{white-space:pre-wrap;font:400 13px/20px ui-monospace,Consolas,monospace;background:#fff;padding:12px;margin:8px 0 0;color:var(--ink)}
@media (max-width:700px){header,section{padding-left:16px;padding-right:16px}header h1{font-size:32px;line-height:40px}}
</style></head><body>
<header><h1>Email previews</h1>
<p>Every email the JBS Praxis system sends, in English and French, as clients will see it. Sample data is fictional.</p>
<p>${count} previews. Sent from system@jbspraxis.com; replies go to intelligence@jbspraxis.com.</p>
<ul>${toc}</ul></header><div class="rule"></div>
${body}
</body></html>`;
fs.writeFileSync(OUT, html);
console.log(`${OUT}: ${count} previews, ${Math.round(html.length / 1024)} KB`);
