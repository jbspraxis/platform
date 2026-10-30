// QA helper: renders pages of a PDF (the brand book by default) into one PNG contact sheet (pdf.js in headless Edge)
//   node brand/build/pdf_sheet.js <firstPage> <lastPage> [columns] [thumbWidth] [pdfPath]
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const [first = 1, last = 12, cols = 4, tw = 360] = process.argv.slice(2, 6).map(Number);
const BRAND = path.resolve(__dirname, '..');
const RENDER = path.join(__dirname, '_render');
const PDF = process.argv[6] ? path.resolve(process.argv[6]) : path.join(BRAND, 'JBS-Praxis-Brand-Book.pdf');
const pdf = fs.readFileSync(PDF).toString('base64');
const CDN = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174';
const rows = Math.ceil((last - first + 1) / cols);
const th = Math.round(tw * 297 / 210);
const W = cols * (tw + 12) + 12, H = rows * (th + 30) + 12;
const html = `<!doctype html><html><head><meta charset="utf-8">
<script src="${CDN}/pdf.min.js"></script>
<style>body{margin:0;background:#888;display:grid;grid-template-columns:repeat(${cols},${tw}px);gap:12px;padding:12px;font:12px sans-serif}
figure{margin:0}canvas{display:block;background:#fff;width:${tw}px;height:${th}px}figcaption{color:#fff;padding:2px 0}</style></head><body>
<script id="pdf" type="application/octet-stream">${pdf}</script>
<script>
(async () => {
  // A worker loaded from a blob works on a file:// page
  const src = await fetch('${CDN}/pdf.worker.min.js').then(r => r.text());
  pdfjsLib.GlobalWorkerOptions.workerSrc = URL.createObjectURL(new Blob([src], { type: 'text/javascript' }));
  const raw = atob(document.getElementById('pdf').textContent.trim());
  const bytes = new Uint8Array(raw.length); for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
  const doc = await pdfjsLib.getDocument({ data: bytes }).promise;
  for (let n = ${first}; n <= Math.min(${last}, doc.numPages); n++) {
    const page = await doc.getPage(n);
    const vp = page.getViewport({ scale: ${tw * (cols > 4 ? 1.25 : 2)} / page.getViewport({ scale: 1 }).width });
    const c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
    const f = document.createElement('figure'); f.appendChild(c);
    const cap = document.createElement('figcaption'); cap.textContent = 'Page ' + n + ' of ' + doc.numPages; f.appendChild(cap);
    document.body.appendChild(f);
    await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
  }
})();
</script></body></html>`;
fs.mkdirSync(RENDER, { recursive: true });
const src = path.join(RENDER, 'pdf_sheet.html');
fs.writeFileSync(src, html);
const out = path.join(RENDER, `${process.argv[6] ? path.basename(PDF, '.pdf') + '_' : 'pdf_'}${first}-${last}.png`);
execFileSync('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', ['--headless=new', '--disable-gpu', '--hide-scrollbars',
  '--force-device-scale-factor=1', '--virtual-time-budget=60000', `--window-size=${W},${H}`, `--screenshot=${out}`,
  'file:///' + src.replace(/\\/g, '/')], { stdio: 'ignore', timeout: 150000 });
console.log(out);
