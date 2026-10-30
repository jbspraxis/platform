/**
 * Records one shot of the Ndali Group demo, frame by frame, at exactly 60 fps.
 *
 *   node record/record.js <shot-id> [--take N] [--dsf 2] [--out ../captures]
 *
 * The page runs on a virtual clock (vclock.js): each frame moves time on by 1000/60 ms, then
 * the page is captured. Frames go straight into ffmpeg (no PNGs on disk). Capture is at
 * 3840×2160 (a 1920×1080 viewport at device scale 2), so the edit can zoom up to 2× and stay
 * sharp; the edit delivers 1920×1080.
 *
 * Shots live in record/shots/<id>.js and export { account, story_now, route, run(rec) }.
 */
const fs = require("fs");
const path = require("path");
const { spawn } = require("child_process");
const { chromium } = require("playwright-core");

const FPS = 60;
const FRAME_MS = 1000 / FPS;
const VIEW = { width: 1920, height: 1080 };
const API = process.env.NDALI_URL || "http://localhost:4100";
const DEMO_ENV = process.env.NDALI_ENV || "C:/Users/Tom-Blake/ndali-demo/.env";

function arg(name, fallback) {
  const i = process.argv.indexOf(`--${name}`);
  return i > 0 ? process.argv[i + 1] : fallback;
}

/** Reads one value from the demo .env without ever printing it. */
function envValue(key) {
  const text = fs.readFileSync(DEMO_ENV, "utf8");
  const m = text.match(new RegExp(`^${key}=(.*)$`, "m"));
  if (!m) throw new Error(`${key} is missing from ${DEMO_ENV}`);
  return m[1].trim().replace(/^["']|["']$/g, "");
}

/** Deterministic PRNG so typing rhythm is identical in every take. */
function mulberry32(seed) {
  return () => {
    seed |= 0; seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** cubic-bezier(x1,y1,x2,y2) evaluated at x, for cursor paths (BRAND.md §11 easings). */
function bezier(x1, y1, x2, y2) {
  const cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
  const cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
  const sx = (t) => ((ax * t + bx) * t + cx) * t;
  const sy = (t) => ((ay * t + by) * t + cy) * t;
  return (x) => {
    let t = x;
    for (let i = 0; i < 8; i++) {
      const d = sx(t) - x;
      const dx = (3 * ax * t + 2 * bx) * t + cx;
      if (Math.abs(d) < 1e-5 || Math.abs(dx) < 1e-6) break;
      t -= d / dx;
    }
    return sy(Math.min(1, Math.max(0, t)));
  };
}
const easeMove = bezier(0.45, 0, 0.2, 1); // a hand that starts gently and settles
const easeSettle = bezier(0.2, 0, 0, 1); // BRAND.md §11 --ease-settle, for framing moves

// Storyboard framings: centre in 1920×1080 page pixels, scale 1.0 to 1.6.
const FRAMINGS = {
  full: { cx: 960, cy: 540, s: 1 },
  brief: { cx: 724, cy: 520, s: 1.3 },
  composer: { cx: 724, cy: 860, s: 1.6 },
  thread: { cx: 724, cy: 620, s: 1.45 },
  pane: { cx: 1560, cy: 500, s: 1.45 },
  paneTop: { cx: 1560, cy: 400, s: 1.5 },
};

class Recorder {
  constructor(page, cdp, ffmpeg, opts) {
    this.page = page;
    this.cdp = cdp;
    this.ffmpeg = ffmpeg;
    this.dsf = opts.dsf;
    this.view = opts.view || VIEW;
    this.frames = 0;
    this.cursor = { x: 1500, y: 700, hidden: true, pressed: false, ring: null, rx: 0, ry: 0 };
    this.ringStart = null;
    this.rand = mulberry32(opts.seed || 20261103);
    this.marks = [];
  }

  /** Advance one frame of virtual time and capture it. */
  async frame() {
    const c = { ...this.cursor };
    if (this.ringStart != null) {
      const p = (this.frames - this.ringStart) * FRAME_MS / 320;
      if (p <= 1) c.ring = p; else this.ringStart = null;
    }
    await this.page.evaluate(([ms, cur, view]) => window.__vclock.frame(ms, cur, view), [FRAME_MS, c, this.framing()]);
    await this.settle();
    const { data } = await this.cdp.send("Page.captureScreenshot", {
      format: "png",
      optimizeForSpeed: true,
      clip: { x: 0, y: 0, width: this.view.width, height: this.view.height, scale: this.dsf },
    });
    const buf = Buffer.from(data, "base64");
    if (!this.ffmpeg.stdin.write(buf)) await new Promise((r) => this.ffmpeg.stdin.once("drain", r));
    this.frames++;
  }

  /** Let React and friends finish what the frame started; wait out any fetch in flight. */
  async settle() {
    for (let round = 0; round < 40; round++) {
      const inflight = await this.page.evaluate(async () => {
        for (let i = 0; i < 3; i++) {
          await new Promise((r) => { const c = new MessageChannel(); c.port1.onmessage = () => r(); c.port2.postMessage(0); });
        }
        return window.__vclock.inflight;
      });
      if (!inflight) return;
      await new Promise((r) => setTimeout(r, 15));
    }
    throw new Error("A request is still in flight after 1.5 s of real time.");
  }

  async hold(ms) {
    const n = Math.round(ms / FRAME_MS);
    for (let i = 0; i < n; i++) await this.frame();
  }

  /**
   * Framing (storyboard "Zoom"): a centre point in 1920×1080 page pixels and a scale. A move
   * takes `ms` (0.8 s by default) with the settle easing, one at a time, clamped to the frame.
   */
  zoom(target, ms = 800) {
    const to = typeof target === "string" ? FRAMINGS[target] : target;
    if (!to) throw new Error(`Unknown framing ${target}`);
    this.zoomFrom = this.currentFraming();
    this.zoomTo_ = { ...to };
    this.zoomStart = this.frames;
    this.zoomFrames = Math.max(1, Math.round(ms / FRAME_MS));
  }

  /** Jump to a framing with no move (the first frame of a shot). */
  frameAt(target) {
    const to = typeof target === "string" ? FRAMINGS[target] : target;
    this.zoomFrom = { ...to };
    this.zoomTo_ = { ...to };
    this.zoomStart = this.frames;
    this.zoomFrames = 1;
  }

  currentFraming() {
    if (!this.zoomTo_) return { cx: 960, cy: 540, s: 1 };
    const k = Math.min(1, (this.frames - this.zoomStart) / this.zoomFrames);
    const e = easeSettle(k);
    const f = this.zoomFrom;
    const t = this.zoomTo_;
    return { cx: f.cx + (t.cx - f.cx) * e, cy: f.cy + (t.cy - f.cy) * e, s: f.s + (t.s - f.s) * e };
  }

  /** The body transform for the current framing, clamped so the page always fills the frame. */
  framing() {
    const { cx, cy, s } = this.currentFraming();
    const W = this.view.width, H = this.view.height;
    let tx = W / 2 - cx * s;
    let ty = H / 2 - cy * s;
    tx = Math.min(0, Math.max(W - W * s, tx));
    ty = Math.min(0, Math.max(H - H * s, ty));
    return { s: +s.toFixed(5), tx: +tx.toFixed(2), ty: +ty.toFixed(2) };
  }

  /** Hold until the shot's own clock reads `t` seconds (storyboard times, shot-local). */
  async to(t) {
    const target = Math.round(t * FPS);
    while (this.frames < target) await this.frame();
  }

  /** Step the story clock (the top bar, toasts, audit times) without a cut. */
  async storyClock(iso) {
    if (!this.api) return;
    await this.api("/api/demo/clock", { story_now: iso });
    await this.page.evaluate(() => window.dispatchEvent(new Event("demo:clock")));
  }

  mark(label) {
    this.marks.push({ label, frame: this.frames, t: +(this.frames / FPS).toFixed(3) });
  }

  async center(target) {
    if (typeof target === "object") return target;
    // Apply the current framing first, so the box is measured where the viewer will see it.
    await this.page.evaluate((v) => {
      const b = document.body.style;
      b.transformOrigin = "0 0";
      b.transform = v.s === 1 ? "" : `translate(${v.tx}px, ${v.ty}px) scale(${v.s})`;
    }, this.framing());
    const loc = this.page.locator(target).first();
    await loc.waitFor({ state: "visible", timeout: 5000 });
    const b = await loc.boundingBox();
    if (!b) throw new Error(`No box for ${target}`);
    return { x: Math.round(b.x + b.width / 2), y: Math.round(b.y + b.height / 2) };
  }

  showCursor(at) {
    if (at) Object.assign(this.cursor, at);
    this.cursor.hidden = false;
  }

  hideCursor() { this.cursor.hidden = true; }

  /** Glide the cursor to a target over `ms`, with a slight arc, moving the real mouse too. */
  async moveTo(target, ms = 650) {
    const to = await this.center(target);
    const from = { x: this.cursor.x, y: this.cursor.y };
    this.cursor.hidden = false;
    const n = Math.max(1, Math.round(ms / FRAME_MS));
    const dx = to.x - from.x, dy = to.y - from.y;
    const bow = Math.min(60, Math.hypot(dx, dy) * 0.08);
    for (let i = 1; i <= n; i++) {
      const k = easeMove(i / n);
      const arc = Math.sin(Math.PI * k) * bow;
      const len = Math.hypot(dx, dy) || 1;
      this.cursor.x = Math.round((from.x + dx * k + (-dy / len) * arc) * 10) / 10;
      this.cursor.y = Math.round((from.y + dy * k + (dx / len) * arc) * 10) / 10;
      if (i % 3 === 0 || i === n) await this.page.mouse.move(this.cursor.x, this.cursor.y);
      await this.frame();
    }
  }

  /** Move, press for 100 ms with a quiet ring, release; the real click lands on press. */
  async click(target, { move = 650, after = 250 } = {}) {
    if (target) await this.moveTo(target, move);
    this.cursor.pressed = true; // a 100 ms press, no ripple (storyboard conventions)
    await this.page.mouse.click(this.cursor.x, this.cursor.y);
    await this.hold(100);
    this.cursor.pressed = false;
    await this.hold(after);
  }

  /**
   * Type at about `cps` characters a second with a human rhythm: small variations, a short
   * pause after a word, a longer one after a comma. Deterministic across takes.
   */
  async type(text, { cps = 12, duration = null } = {}) {
    const chars = [...text];
    const weights = chars.map((ch) => {
      let w = 0.75 + this.rand() * 0.5;
      if (ch === " ") w += 0.4 + this.rand() * 0.5;
      if (ch === ",") w += 1.6 + this.rand() * 0.6;
      return w;
    });
    // Fit the storyboard window exactly when one is given (the last key lands on time).
    const total = duration != null ? duration * 1000 : (chars.length * 1000) / cps;
    const sum = weights.reduce((a, b) => a + b, 0);
    let due = this.frames * FRAME_MS;
    for (let i = 0; i < chars.length; i++) {
      await this.page.keyboard.type(chars[i]);
      due += (weights[i] / sum) * total;
      while (this.frames * FRAME_MS < due - FRAME_MS / 2) await this.frame();
    }
  }

  /** Off-camera helper: ask a question and wait until its answer has filled the pane. */
  async askQuietly(question, answerId) {
    const box = this.page.locator('[data-demo="composer"]').first();
    await box.click();
    await this.page.keyboard.insertText(question);
    await this.page.keyboard.press("Enter");
    await this.until((id) => {
      const pane = document.querySelector('[data-demo="answer-pane"]');
      return !!pane && pane.getAttribute("data-answer") === id;
    }, { max: 12000, after: 1500, arg: answerId });
  }

  async press(key, after = 0) {
    await this.page.keyboard.press(key);
    if (after) await this.hold(after);
  }

  /** Keep recording until a page condition holds (checked each frame), then `after` ms more. */
  async until(fn, { max = 8000, after = 0, arg } = {}) {
    const n = Math.round(max / FRAME_MS);
    for (let i = 0; i < n; i++) {
      if (await this.page.evaluate(fn, arg)) { if (after) await this.hold(after); return; }
      await this.frame();
    }
    throw new Error(`Condition not met within ${max} ms: ${fn}`);
  }
}

async function main() {
  const id = process.argv[2];
  if (!id) throw new Error("Usage: node record/record.js <shot-id> [--take N]");
  const shot = require(path.join(__dirname, "shots", `${id}.js`));
  const take = Number(arg("take", "1"));
  // A shot may lay out on a smaller CSS viewport (the end card uses 1440×810); the output is
  // always the same pixel size, so the device scale grows to match.
  const view = shot.viewport || VIEW;
  const dsf = (Number(arg("dsf", "2")) * VIEW.width) / view.width;
  const outDir = path.resolve(arg("out", path.join(__dirname, "..", "..", "captures")));
  fs.mkdirSync(path.join(outDir, "downloads"), { recursive: true });
  const outFile = path.join(outDir, `${id}-take${take}.mp4`);

  const base = Date.parse(shot.story_now);
  if (!Number.isFinite(base)) throw new Error(`Bad story_now in ${id}`);
  const clockJs = fs.readFileSync(path.join(__dirname, "vclock.js"), "utf8");

  const browser = await chromium.launch({
    channel: "msedge",
    headless: true,
    args: ["--hide-scrollbars", "--font-render-hinting=none", "--disable-lcd-text"],
  });
  const context = await browser.newContext({
    viewport: view,
    deviceScaleFactor: dsf,
    locale: "en-GB",
    timezoneId: "Africa/Lagos",
    colorScheme: "light",
    reducedMotion: "no-preference",
    acceptDownloads: true,
  });
  await context.addInitScript({ content: `window.__VCLOCK_BASE__=${base};\n${clockJs}` });

  // Session and story state through the API (same cookie jar as the page). Offline shots
  // (the end card) are plain pages and skip this.
  const req = context.request;
  const ok = async (p, r) => { const res = await r; if (!res.ok()) throw new Error(`${p}: HTTP ${res.status()}`); return res; };
  if (!shot.offline) {
    await ok("reset", req.post(`${API}/api/demo/reset`));
    await ok("clock", req.post(`${API}/api/demo/clock`, { data: { story_now: shot.story_now } }));
    await ok("sign-in", req.post(`${API}/api/auth/sign-in`, { data: { account: shot.account, password: envValue("DEMO_PASSWORD") } }));
    if (shot.prepare) await shot.prepare({ req, API, ok });
  }

  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(String(e)));
  page.on("console", (m) => { if (m.type() === "error") errors.push(m.text()); });
  page.on("download", async (d) => {
    const to = path.join(outDir, "downloads", d.suggestedFilename());
    await d.saveAs(to);
    console.log(`downloaded ${path.basename(to)}`);
  });
  const cdp = await context.newCDPSession(page);
  const url = shot.offline
    ? require("url").pathToFileURL(path.resolve(__dirname, shot.file)).href
    : `${API}${shot.route}`;
  await page.goto(url, { waitUntil: "domcontentloaded" });

  // Warm up off-camera: let data and fonts arrive on the virtual clock.
  const warm = new Recorder(page, cdp, { stdin: { write: () => true } }, { dsf, view });
  warm.frame = async function () {
    await page.evaluate((ms) => window.__vclock.frame(ms, { hidden: true }), FRAME_MS);
    await this.settle();
  };
  const callApi = async (p, body) => {
    const res = await req.post(`${API}${p}`, { data: body });
    if (!res.ok()) throw new Error(`${p}: HTTP ${res.status()}`);
  };
  warm.api = callApi;
  await page.evaluate(() => document.fonts.ready);
  await warm.until(() => window.__ndaliReady === true, { max: 10000 });
  // Off-camera set-up (an earlier question already in the thread, a thread opened, focus).
  if (shot.setup) await shot.setup(warm, { page });
  await warm.hold(shot.warmup_ms ?? 600);

  const ffmpeg = spawn("ffmpeg", [
    "-hide_banner", "-loglevel", "error", "-y",
    "-f", "image2pipe", "-framerate", String(FPS), "-c:v", "png", "-i", "-",
    "-c:v", "libx264", "-preset", "medium", "-crf", "12", "-pix_fmt", "yuv444p",
    "-movflags", "+faststart", outFile,
  ], { stdio: ["pipe", "inherit", "inherit"] });

  const rec = new Recorder(page, cdp, ffmpeg, { dsf, view, seed: 20261103 + take });
  rec.api = callApi;
  rec.cursor = { ...warm.cursor };
  const t0 = Date.now();
  await shot.run(rec, { page, take });
  ffmpeg.stdin.end();
  await new Promise((r, j) => ffmpeg.on("close", (code) => (code === 0 ? r() : j(new Error(`ffmpeg ${code}`)))));

  const meta = {
    shot: id, take, frames: rec.frames, seconds: +(rec.frames / FPS).toFixed(3), fps: FPS,
    capture: `${Math.round(view.width * dsf)}x${Math.round(view.height * dsf)}`, story_now: shot.story_now, marks: rec.marks,
    console_errors: errors, real_seconds: Math.round((Date.now() - t0) / 1000),
  };
  fs.writeFileSync(outFile.replace(/\.mp4$/, ".json"), JSON.stringify(meta, null, 2));
  console.log(`${path.basename(outFile)}: ${meta.seconds} s, ${meta.frames} frames, ${meta.real_seconds} s to record` + (errors.length ? `, ${errors.length} console errors` : ""));
  await browser.close();
  if (errors.length) process.exitCode = 2;
}

if (require.main === module) {
  main().catch((e) => { console.error(e.stack || e.message); process.exit(1); });
}

module.exports = { Recorder, bezier, mulberry32 };
