/**
 * Renders the silent loop's burned-in captions as transparent 1920×1080 PNGs in the JBS Praxis
 * style (BRAND.md §7–8): an Ink panel, Plus Jakarta Sans 600 in white, an optional time stamp
 * in Bright Teal (the one accent allowed on Ink). The edit overlays them with 150 ms fades.
 *
 *   node captions/render.js <captions.json> <out-dir>
 *
 * captions.json: [{ "id": "s1", "time": "07:45", "text": "The company reports before you ask" }, …]
 */
const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright-core");

const FONTS = path.join(__dirname, "..", "endcard", "fonts");

function html(c) {
  // Inline the fonts: a page made with setContent has no base URL to load files from.
  const font = (f) => `url(data:font/woff2;base64,${fs.readFileSync(path.join(FONTS, f)).toString("base64")})`;
  const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;");
  return `<!doctype html><html><head><meta charset="utf-8"><style>
  @font-face { font-family: "PJS"; src: ${font("plus-jakarta-sans-latin.woff2")} format("woff2"); font-weight: 200 800; }
  @font-face { font-family: "PJS"; src: ${font("plus-jakarta-sans-latin-ext.woff2")} format("woff2"); font-weight: 200 800;
    unicode-range: U+0100-02BA, U+20A0-20C0; }
  html, body { margin: 0; background: transparent; }
  .cap { position: absolute; left: 72px; bottom: 72px; display: inline-flex; align-items: baseline; gap: 18px;
    background: rgba(12, 20, 43, 0.92); border-radius: 8px; padding: 20px 30px 22px;
    font-family: "PJS", sans-serif; -webkit-font-smoothing: antialiased; }
  .time { color: #0BB8C4; font-weight: 600; font-size: 34px; line-height: 44px; font-variant-numeric: tabular-nums; }
  .text { color: #FFFFFF; font-weight: 600; font-size: 40px; line-height: 48px; }
  /* The quiet note (3-minute cut, chapter 1): Slate on a Paper panel, 26 px, weight 500. */
  .cap.note { background: #FFFFFF; border: 1px solid #D8DEE6; padding: 16px 24px 18px; }
  .cap.note .text { color: #4A5468; font-weight: 500; font-size: 26px; line-height: 34px; }
</style></head><body><div class="cap${c.style === "note" ? " note" : ""}">${c.time ? `<span class="time">${esc(c.time)}</span>` : ""}<span class="text">${esc(c.text)}</span></div></body></html>`;
}

async function main() {
  const [file, outDir] = process.argv.slice(2);
  const caps = JSON.parse(fs.readFileSync(file, "utf8"));
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ channel: "msedge", headless: true });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
  for (const c of caps) {
    await page.setContent(html(c));
    await page.evaluate(() => document.fonts.ready);
    const words = c.text.trim().split(/\s+/).length;
    if (words > 7 && c.style !== "note") console.warn(`caption ${c.id} has ${words} words (max 7)`);
    await page.screenshot({ path: path.join(outDir, `${c.id}.png`), omitBackground: true });
    console.log(`${c.id}.png`);
  }
  await browser.close();
}

main().catch((e) => { console.error(e); process.exit(1); });
