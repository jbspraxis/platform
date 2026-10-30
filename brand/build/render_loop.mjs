// Renders a loop HTML (window.render(t)) frame by frame through headless Edge (Chrome DevTools Protocol, Node's
// built-in WebSocket), then encodes MP4 with ffmpeg.  node render_loop.mjs <html> <w> <h> <out.mp4> [seconds] [fps]
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';

const [html, W, H, out, secs = '10', fps = '30'] = process.argv.slice(2);
const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const port = 9300 + Math.floor(Math.random() * 500);
const profile = join(tmpdir(), 'loop-edge-' + port);
const frames = join(tmpdir(), 'loop-frames-' + port);
mkdirSync(frames, { recursive: true });
const proc = spawn(edge, ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${port}`,
  `--user-data-dir=${profile}`, '--force-device-scale-factor=1', '--allow-file-access-from-files', 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
try {
  let targets;
  for (let i = 0; i < 50 && !targets; i++) { await sleep(200); try { targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); } catch {} }
  const ws = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(r => ws.addEventListener('open', r, { once: true }));
  let id = 0; const pending = new Map();
  ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } });
  const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
  await send('Emulation.setDeviceMetricsOverride', { width: +W, height: +H, deviceScaleFactor: 1, mobile: false });
  await send('Page.enable');
  await send('Page.navigate', { url: pathToFileURL(resolve(html)).href });
  await sleep(2500);
  await send('Runtime.evaluate', { expression: 'document.fonts.ready.then(() => true)', awaitPromise: true });
  const n = Math.round(+secs * +fps);
  for (let f = 0; f < n; f++) {
    await send('Runtime.evaluate', { expression: `render(${f / +fps})` });
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    writeFileSync(join(frames, String(f).padStart(4, '0') + '.png'), Buffer.from(shot.result.data, 'base64'));
  }
  ws.close();
  const r = spawnSync('ffmpeg', ['-y', '-loglevel', 'error', '-framerate', fps, '-i', join(frames, '%04d.png'), '-c:v', 'libx264',
    '-pix_fmt', 'yuv420p', '-crf', '18', '-preset', 'slow', '-movflags', '+faststart', out], { stdio: 'inherit' });
  console.log(r.status === 0 ? `ok ${out} (${n} frames)` : 'ffmpeg failed');
} finally {
  proc.kill(); await sleep(500); rmSync(frames, { recursive: true, force: true }); rmSync(profile, { recursive: true, force: true });
}
