/**
 * The edit, as a script (runbook §8): picture, captions, voice, music, loudness, deliverables.
 *
 *   node edit/edit.js 60s            (reads edit/cut-60s.json, demo/voiceover.json)
 *
 * Picture: the recorded shots (4K masters, already framed and zoomed in the browser) are cut on
 * the storyboard's timeline, with cross-fades only where the storyboard has them, scaled to
 * 1920×1080 at 60 fps. Silent loop: captions burned in, 30 fps, under 8 MB, plus a poster under
 * 100 KB. Narrated versions: the voice lines at their storyboard times over the music bed at
 * about −24 dB, ducked under the voice, loudness −16 LUFS (site) and −14 LUFS (YouTube copies).
 */
const fs = require("fs");
const path = require("path");
const { spawnSync } = require("child_process");

const DEMO = path.resolve(__dirname, "..", "..");
const CAP = path.join(DEMO, "captures");
const WORK = path.join(DEMO, "work");
const OUT = path.join(WORK, "edit");
fs.mkdirSync(OUT, { recursive: true });

function ff(args, { capture = false } = {}) {
  const r = spawnSync("ffmpeg", ["-hide_banner", "-y", ...args], { encoding: "utf8", maxBuffer: 1 << 26 });
  if (r.status !== 0) throw new Error(`ffmpeg failed:\n${(r.stderr || "").slice(-3000)}`);
  return capture ? r.stderr : "";
}

function wavSeconds(file) {
  const b = fs.readFileSync(file);
  let off = 12, bps = 0, len = 0;
  while (off + 8 <= b.length) {
    const id = b.toString("ascii", off, off + 4);
    const n = b.readUInt32LE(off + 4);
    if (id === "fmt ") bps = b.readUInt32LE(off + 16);
    if (id === "data") { len = Math.min(n, b.length - off - 8); break; }
    off += 8 + n + (n % 2);
  }
  return len / bps;
}

/* ---------------- picture ---------------- */
function renderPngs(list, dir) {
  fs.mkdirSync(dir, { recursive: true });
  const file = path.join(dir, "list.json");
  fs.writeFileSync(file, JSON.stringify(list, null, 1));
  const r = spawnSync("node", [path.join(__dirname, "..", "captions", "render.js"), file, dir], { encoding: "utf8" });
  if (r.status !== 0) throw new Error(r.stderr);
}

function picture(cut) {
  const out = path.join(OUT, `${cut.id}-picture.mp4`);
  if (cut.overlays && cut.overlays.length) renderPngs(cut.overlays, path.join(OUT, `overlays-${cut.id}`));
  const args = [];
  const parts = [];
  cut.segments.forEach((s, i) => {
    args.push("-i", path.join(CAP, `${s.clip}-take${s.take || 1}.mp4`));
    parts.push(`[${i}:v]trim=start=${s.from}:end=${s.to},setpts=PTS-STARTPTS,scale=1920:1080:flags=lanczos,fps=60,format=yuv420p,settb=AVTB[v${i}]`);
  });
  let cur = "v0";
  let dur = cut.segments[0].to - cut.segments[0].from;
  cut.segments.slice(1).forEach((s, k) => {
    const i = k + 1;
    const d = s.to - s.from;
    const next = `j${i}`;
    if (s.xfade) {
      parts.push(`[${cur}][v${i}]xfade=transition=fade:duration=${s.xfade}:offset=${(dur - s.xfade).toFixed(3)}[${next}]`);
      dur = dur - s.xfade + d;
    } else {
      parts.push(`[${cur}][v${i}]concat=n=2:v=1:a=0[${next}]`);
      dur += d;
    }
    cur = next;
  });
  // A fade up from Mist at the very start, where the storyboard asks for one (3-minute cut).
  if (cut.fade_in_from_mist) {
    parts.push(`[${cur}]fade=t=in:st=0:d=${cut.fade_in_from_mist}:color=0xF2F5F7[fi]`);
    cur = "fi";
  }
  // Notes that belong to the picture in every version (the fictional-company note).
  (cut.overlays || []).forEach((o, k) => {
    const idx = cut.segments.length + k;
    args.push("-loop", "1", "-t", String(dur), "-i", path.join(OUT, `overlays-${cut.id}`, `${o.id}.png`));
    parts.push(`[${idx}:v]format=rgba,fade=t=in:st=${o.in}:d=0.15:alpha=1,fade=t=out:st=${(o.out - 0.2).toFixed(2)}:d=0.2:alpha=1[ov${k}]`);
    parts.push(`[${cur}][ov${k}]overlay=0:0:enable='between(t,${o.in},${o.out})'[ovd${k}]`);
    cur = `ovd${k}`;
  });
  ff([...args, "-filter_complex", parts.join(";"), "-map", `[${cur}]`, "-c:v", "libx264", "-preset", "slow",
    "-crf", "16", "-pix_fmt", "yuv420p", "-profile:v", "high", "-movflags", "+faststart", out]);
  console.log(`picture ${path.basename(out)}: ${dur.toFixed(2)} s`);
  return { file: out, duration: dur };
}

/* ---------------- silent loop with burned-in captions, and the poster ---------------- */
function loop(cut, pic) {
  const capDir = path.join(OUT, `captions-${cut.id}`);
  const list = path.join(capDir, "captions.json");
  fs.mkdirSync(capDir, { recursive: true });
  fs.writeFileSync(list, JSON.stringify(cut.captions, null, 1));
  const r = spawnSync("node", [path.join(__dirname, "..", "captions", "render.js"), list, capDir], { encoding: "utf8" });
  if (r.status !== 0) throw new Error(r.stderr);

  const args = ["-i", pic.file];
  const parts = [];
  let base = "0:v";
  cut.captions.forEach((c, k) => {
    const i = k + 1;
    args.push("-loop", "1", "-t", String(pic.duration), "-i", path.join(capDir, `${c.id}.png`));
    parts.push(`[${i}:v]format=rgba,fade=t=in:st=${c.in}:d=0.15:alpha=1,fade=t=out:st=${(c.out - 0.2).toFixed(2)}:d=0.2:alpha=1[c${i}]`);
    parts.push(`[${base}][c${i}]overlay=0:0:enable='between(t,${c.in},${c.out})'[o${i}]`);
    base = `o${i}`;
  });
  parts.push(`[${base}]fps=30,format=yuv420p[final]`);
  const target = path.join(DEMO, cut.outputs.loop);
  // Two-pass to stay under 8 MB: about 7.4 MB of picture over the cut's length.
  const kbps = Math.floor((7.4 * 8 * 1024) / pic.duration);
  const common = [...args, "-filter_complex", parts.join(";"), "-map", "[final]", "-an", "-c:v", "libx264", "-preset", "slow",
    "-b:v", `${kbps}k`, "-maxrate", `${Math.round(kbps * 1.6)}k`, "-bufsize", `${kbps * 3}k`, "-pix_fmt", "yuv420p"];
  const passlog = path.join(OUT, `pass-${cut.id}`);
  ff([...common, "-pass", "1", "-passlogfile", passlog, "-f", "mp4", "NUL"]);
  ff([...common, "-pass", "2", "-passlogfile", passlog, "-movflags", "+faststart", target]);
  console.log(`loop ${cut.outputs.loop}: ${(fs.statSync(target).size / 1048576).toFixed(2)} MB`);

  const poster = path.join(DEMO, cut.outputs.poster);
  for (const [w, q] of [[1920, 4], [1920, 6], [1920, 8], [1600, 6], [1600, 9], [1280, 8]]) {
    ff(["-ss", String(cut.poster_at), "-i", pic.file, "-frames:v", "1", "-vf", `scale=${w}:-2:flags=lanczos`, "-q:v", String(q), poster]);
    if (fs.statSync(poster).size < 100 * 1024) break;
  }
  console.log(`poster ${cut.outputs.poster}: ${Math.round(fs.statSync(poster).size / 1024)} KB`);
}

/* ---------------- voice, music, loudness ---------------- */
function lines(cutId, lang) {
  const vo = JSON.parse(fs.readFileSync(path.join(DEMO, "voiceover.json"), "utf8"));
  const cut = vo.cuts.find((c) => c.id === cutId);
  const out = [];
  for (const shot of cut.shots || cut.chapters) for (const l of shot.lines) out.push({ id: l.id, start: l.start, end_by: l.end_by, text: l[lang].text });
  return out;
}

function loudnorm(input, output, I, extra = []) {
  const measure = ff(["-i", input, "-af", `loudnorm=I=${I}:TP=-1.5:LRA=11:print_format=json`, "-f", "null", "NUL"], { capture: true });
  const m = JSON.parse(measure.slice(measure.lastIndexOf("{"), measure.lastIndexOf("}") + 1));
  ff(["-i", input, "-af", `loudnorm=I=${I}:TP=-1.5:LRA=11:measured_I=${m.input_i}:measured_TP=${m.input_tp}:measured_LRA=${m.input_lra}:measured_thresh=${m.input_thresh}:offset=${m.target_offset}:linear=true,aresample=48000`, ...extra, output]);
}

function soundtrack(cut, lang, duration, tempo) {
  const dir = path.join(WORK, "voice", `${cut.id}-${lang}`);
  const ls = lines(cut.id, lang);
  const args = [];
  const parts = [];
  const cues = [];
  ls.forEach((l, i) => {
    const wav = path.join(dir, `${l.id}.wav`);
    args.push("-i", wav);
    let t = tempo;
    const said = wavSeconds(wav) / t;
    const room = l.end_by - l.start;
    if (said > room) t = Math.min(1.12, wavSeconds(wav) / room); // fit the window, gently
    const ms = Math.round(l.start * 1000);
    parts.push(`[${i}:a]aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,atempo=${t.toFixed(3)},adelay=${ms}|${ms}[a${i}]`);
    cues.push({ start: l.start, end: Math.min(l.end_by, l.start + wavSeconds(wav) / t, duration - 0.1), text: l.text });
  });
  // A full-length silent track first, so the mix starts at 0 s: without it amix starts at the first
  // line and every line plays early by that line's start time.
  const n = ls.length;
  args.push("-f", "lavfi", "-t", String(duration), "-i", "anullsrc=r=48000:cl=stereo");
  parts.push(`[${n}:a]aformat=sample_fmts=fltp:channel_layouts=stereo[sil]`);
  parts.push(`[sil]${ls.map((_, i) => `[a${i}]`).join("")}amix=inputs=${n + 1}:normalize=0:duration=first,atrim=0:${duration}[voice]`);
  const voiceFile = path.join(OUT, `${cut.id}-${lang}-voice.wav`);
  ff([...args, "-filter_complex", parts.join(";"), "-map", "[voice]", "-ar", "48000", voiceFile]);

  // Voice to -16 LUFS first, then the bed sits about 24 dB under it and ducks when the voice speaks.
  const voiceNorm = path.join(OUT, `${cut.id}-${lang}-voice-16.wav`);
  loudnorm(voiceFile, voiceNorm, -16);
  const bed = path.join(WORK, "music", "bed.wav");
  const mix = path.join(OUT, `${cut.id}-${lang}-mix.wav`);
  ff(["-i", voiceNorm, "-i", bed, "-filter_complex",
    `[1:a]atrim=0:${duration},asetpts=PTS-STARTPTS,highpass=f=90,afade=t=in:st=0:d=1.5,afade=t=out:st=${duration - 3}:d=3,volume=-24.5dB[m];` +
    `[0:a]asplit=2[v][sc];[m][sc]sidechaincompress=threshold=0.015:ratio=5:attack=25:release=450[duck];` +
    `[v][duck]amix=inputs=2:normalize=0,atrim=0:${duration}[out]`,
    "-map", "[out]", "-ar", "48000", mix]);
  return { mix, cues };
}

function vtt(cues, file) {
  const t = (s) => {
    const ms = Math.round(s * 1000);
    const h = String(Math.floor(ms / 3600000)).padStart(2, "0");
    const m = String(Math.floor((ms % 3600000) / 60000)).padStart(2, "0");
    const sec = String(Math.floor((ms % 60000) / 1000)).padStart(2, "0");
    return `${h}:${m}:${sec}.${String(ms % 1000).padStart(3, "0")}`;
  };
  const body = cues.map((c, i) => `${i + 1}\n${t(c.start)} --> ${t(c.end)}\n${c.text}\n`).join("\n");
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, `WEBVTT\n\n${body}`);
}

function narrated(cut, pic, lang, tempo) {
  const { mix, cues } = soundtrack(cut, lang, pic.duration, tempo);
  vtt(cues, path.join(DEMO, "captions", `demo-${cut.id}-${lang}.vtt`));
  const site = path.join(WORK, `${cut.id}-${lang}-site.m4a`);
  const yt = path.join(WORK, `${cut.id}-${lang}-yt.m4a`);
  loudnorm(mix, site, -16, ["-c:a", "aac", "-b:a", "192k"]);
  loudnorm(mix, yt, -14, ["-c:a", "aac", "-b:a", "192k"]);
  const target = path.join(DEMO, cut.outputs[lang]);
  ff(["-i", pic.file, "-i", site, "-map", "0:v", "-map", "1:a", "-c", "copy", "-shortest", "-movflags", "+faststart", target]);
  const upload = path.join(DEMO, "youtube", "upload");
  fs.mkdirSync(upload, { recursive: true });
  ff(["-i", pic.file, "-i", yt, "-map", "0:v", "-map", "1:a", "-c", "copy", "-shortest", "-movflags", "+faststart", path.join(upload, cut.outputs[lang])]);
  console.log(`${cut.outputs[lang]}: ${(fs.statSync(target).size / 1048576).toFixed(1)} MB (+ YouTube copy at -14 LUFS)`);
}

function main() {
  const id = process.argv[2] || "60s";
  const only = process.argv[3]; // optional: picture | loop | en | fr
  const cut = JSON.parse(fs.readFileSync(path.join(__dirname, `cut-${id}.json`), "utf8"));
  const picFile = path.join(OUT, `${cut.id}-picture.mp4`);
  const pic = !only || only === "picture" || !fs.existsSync(picFile) ? picture(cut)
    : { file: picFile, duration: cut.duration };
  if (!only || only === "loop") { if (cut.outputs.loop) loop(cut, pic); }
  const tempo = { en: Number(process.env.EN_TEMPO || 0.9), fr: 1.0 };
  for (const lang of ["en", "fr"]) if ((!only || only === lang) && cut.outputs[lang]) narrated(cut, pic, lang, tempo[lang]);
}

main();
