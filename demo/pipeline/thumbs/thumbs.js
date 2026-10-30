/**
 * The two YouTube thumbnails (1280×720), on brand and without clickbait (runbook §8): Mist,
 * the Newsreader line, a small label, the horizontal logo, and a framed crop of the real product
 * (BRAND.md §10: a Paper panel, 8 px radius, no device mock-ups).
 *
 *   node thumbs/thumbs.js      (needs work/thumbs/crop-60s.png and crop-3min.png from the edit)
 */
const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright-core");

const DEMO = path.resolve(__dirname, "..", "..");
const FONTS = path.join(__dirname, "..", "endcard", "fonts");
const LOGO = path.join(DEMO, "..", "brand", "kit", "logo", "jbs-praxis-horizontal-colour.svg");
const data = (file, type) => `data:${type};base64,${fs.readFileSync(file).toString("base64")}`;

function html(label, crop) {
  const font = (f) => data(path.join(FONTS, f), "font/woff2");
  return `<!doctype html><html><head><meta charset="utf-8"><style>
  @font-face { font-family: "Newsreader"; src: url(${font("newsreader-latin.woff2")}) format("woff2"); font-weight: 200 800; }
  @font-face { font-family: "PJS"; src: url(${font("plus-jakarta-sans-latin.woff2")}) format("woff2"); font-weight: 200 800; }
  html, body { margin: 0; width: 1280px; height: 720px; background: #F2F5F7; overflow: hidden; }
  .logo { position: absolute; left: 64px; top: 56px; height: 34px; }
  h1 { position: absolute; left: 64px; top: 170px; width: 520px; margin: 0; font-family: "Newsreader", Georgia, serif;
    font-weight: 400; font-size: 76px; line-height: 80px; letter-spacing: -0.01em; color: #0C142B; }
  .rule { position: absolute; left: 64px; top: 446px; width: 300px; height: 3px;
    background: linear-gradient(90deg, #0C142B 0 56%, #0C8B9B 56% 100%); }
  .label { position: absolute; left: 64px; top: 478px; font-family: "PJS", sans-serif; font-weight: 600; font-size: 28px; color: #08707C; }
  .frame { position: absolute; left: 640px; top: 96px; width: 600px; height: 528px; border-radius: 8px;
    background: #FFFFFF url(${data(crop, "image/png")}) left top / cover no-repeat;
    box-shadow: 0 0 0 1px #D8DEE6, 0 8px 24px rgba(12, 20, 43, 0.12); }
</style></head><body>
  <img class="logo" src="${data(LOGO, "image/svg+xml")}" alt="">
  <h1>Ask your company anything.</h1>
  <div class="rule"></div>
  <div class="label">${label}</div>
  <div class="frame"></div>
</body></html>`;
}

(async () => {
  const work = path.join(DEMO, "work", "thumbs");
  const out = path.join(DEMO, "youtube");
  const browser = await chromium.launch({ channel: "msedge", headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });
  for (const [id, label] of [["60s", "The 60-second demo"], ["3min", "A COO’s morning in 3 minutes"]]) {
    await page.setContent(html(label, path.join(work, `crop-${id}.png`)));
    await page.evaluate(() => document.fonts.ready);
    const file = path.join(out, `thumbnail-${id}.png`);
    await page.screenshot({ path: file });
    console.log(`${path.basename(file)}: ${Math.round(fs.statSync(file).size / 1024)} KB`);
  }
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
