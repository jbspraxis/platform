/**
 * Virtual clock and synthetic cursor, injected into the page before any app code runs.
 *
 * Why: the demo is recorded frame by frame. Real time keeps running while a frame is being
 * captured (a 4K screenshot takes ~150 ms), so anything driven by real time would jump. Here
 * every clock the page can read (Date, performance.now, requestAnimationFrame, timers) and
 * every CSS/Web animation is stepped by the recorder, exactly 1000/60 ms per frame.
 *
 * The recorder calls window.__vclock.frame(ms, cursor) once per frame, waits for the page to
 * be idle (no fetch in flight), then takes the screenshot.
 */
(() => {
  if (window.__vclock) return;
  // No spell-check squiggles in the recording (they mark Nigerian place names as errors).
  // The attribute is inherited, so setting it on <html> covers every field.
  document.documentElement && document.documentElement.setAttribute("spellcheck", "false");
  document.addEventListener("DOMContentLoaded", () => document.documentElement.setAttribute("spellcheck", "false"));

  const BASE = Number(window.__VCLOCK_BASE__ || Date.parse("2026-11-03T07:45:00+01:00"));
  const RealDate = Date;
  let now = 0; // virtual ms since the page started

  /* ---------------- Date and performance.now ---------------- */
  function VDate(...args) {
    if (!(this instanceof VDate)) return new RealDate(BASE + now).toString();
    return args.length === 0 ? new RealDate(BASE + now) : new RealDate(...args);
  }
  VDate.prototype = RealDate.prototype;
  VDate.now = () => BASE + now;
  VDate.parse = RealDate.parse;
  VDate.UTC = RealDate.UTC;
  Object.defineProperty(VDate, Symbol.hasInstance, { value: (v) => v instanceof RealDate });
  window.Date = VDate;
  const perf = window.performance;
  perf.now = () => now;

  /* ---------------- timers ---------------- */
  const timers = new Map();
  let nextTimer = 1;
  function addTimer(fn, ms, args, repeat) {
    const id = nextTimer++;
    const delay = Math.max(0, Number(ms) || 0);
    timers.set(id, { at: now + delay, fn, args, repeat: repeat ? Math.max(1, delay) : 0, seq: id });
    return id;
  }
  window.setTimeout = (fn, ms, ...args) => addTimer(fn, ms, args, false);
  window.setInterval = (fn, ms, ...args) => addTimer(fn, ms, args, true);
  window.clearTimeout = window.clearInterval = (id) => { timers.delete(id); };
  window.requestIdleCallback = (fn) =>
    addTimer(() => fn({ didTimeout: false, timeRemaining: () => 12 }), 1, [], false);
  window.cancelIdleCallback = (id) => timers.delete(id);

  /* ---------------- requestAnimationFrame ---------------- */
  let rafs = new Map();
  let nextRaf = 1;
  const nativeRaf = window.requestAnimationFrame.bind(window);
  window.requestAnimationFrame = (cb) => { const id = nextRaf++; rafs.set(id, cb); return id; };
  window.cancelAnimationFrame = (id) => { rafs.delete(id); };

  /* ---------------- network in flight ---------------- */
  let inflight = 0;
  const nativeFetch = window.fetch.bind(window);
  window.fetch = (...a) => {
    inflight++;
    return nativeFetch(...a).finally(() => { inflight--; });
  };
  const XO = XMLHttpRequest.prototype.open;
  const XS = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (...a) { return XO.apply(this, a); };
  XMLHttpRequest.prototype.send = function (...a) {
    inflight++;
    this.addEventListener("loadend", () => { inflight--; }, { once: true });
    return XS.apply(this, a);
  };

  /* ---------------- CSS transitions, CSS animations, Web Animations ---------------- */
  const born = new WeakMap(); // animation -> virtual time we first saw it
  function totalLength(a) {
    try {
      const t = a.effect.getComputedTiming();
      return t.endTime; // delay + active duration + endDelay (Infinity when infinite)
    } catch { return Infinity; }
  }
  function syncAnimations() {
    for (const a of document.getAnimations()) {
      if (!born.has(a)) {
        born.set(a, now);
        try { a.pause(); } catch { /* already finished */ }
      }
      const t = now - born.get(a);
      const end = totalLength(a);
      try {
        if (Number.isFinite(end) && t >= end) {
          a.currentTime = end;
          a.finish();
        } else {
          a.currentTime = t;
        }
      } catch { /* cancelled under us */ }
    }
  }

  /* ---------------- synthetic cursor ---------------- */
  let cursorEl = null;
  let ringEl = null;
  function ensureCursor() {
    if (cursorEl || !document.body) return;
    const wrap = document.createElement("div");
    wrap.setAttribute("aria-hidden", "true");
    wrap.style.cssText =
      "position:fixed;left:0;top:0;width:0;height:0;z-index:2147483647;pointer-events:none;";
    ringEl = document.createElement("div");
    ringEl.style.cssText =
      "position:fixed;left:-18px;top:-18px;width:36px;height:36px;border-radius:50%;" +
      "border:2px solid rgba(26,28,32,.55);opacity:0;pointer-events:none;will-change:transform,opacity;";
    cursorEl = document.createElement("div");
    cursorEl.style.cssText =
      "position:fixed;left:0;top:0;width:30px;height:30px;pointer-events:none;will-change:transform;" +
      "filter:drop-shadow(0 2px 3px rgba(0,0,0,.28));transform-origin:2px 2px;";
    cursorEl.innerHTML =
      '<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">' +
      '<path d="M3 2.5 L3 24.5 L8.6 19.4 L12.4 27.6 L16.1 26 L12.4 17.9 L20 17.9 Z" ' +
      'fill="#FFFFFF" stroke="#1A1C20" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    wrap.appendChild(ringEl);
    wrap.appendChild(cursorEl);
    document.documentElement.appendChild(wrap);
  }
  const settle = (p) => { // cubic-bezier(0.2,0,0,1) approximated for the click ring
    const x = Math.min(1, Math.max(0, p));
    return 1 - Math.pow(1 - x, 3);
  };
  function drawCursor(c) {
    ensureCursor();
    if (!cursorEl) return;
    if (!c || c.hidden) { cursorEl.style.opacity = "0"; ringEl.style.opacity = "0"; return; }
    cursorEl.style.opacity = "1";
    const s = c.pressed ? 0.9 : 1;
    cursorEl.style.transform = `translate(${c.x - 3}px, ${c.y - 2.5}px) scale(${s})`;
    if (c.ring != null && c.ring >= 0 && c.ring <= 1) {
      const k = settle(c.ring);
      ringEl.style.opacity = String(0.45 * (1 - k));
      ringEl.style.transform = `translate(${c.rx}px, ${c.ry}px) scale(${0.55 + 0.45 * k})`;
    } else {
      ringEl.style.opacity = "0";
    }
  }

  /* ---------------- stepping ---------------- */
  function runTimersUntil(target) {
    // Fire due timers in order of due time, then creation; nested 0 ms timers fire too.
    for (let guard = 0; guard < 10000; guard++) {
      let next = null;
      for (const [id, t] of timers) {
        if (t.at <= target && (!next || t.at < next[1].at || (t.at === next[1].at && t.seq < next[1].seq))) {
          next = [id, t];
        }
      }
      if (!next) break;
      const [id, t] = next;
      now = Math.max(now, t.at);
      if (t.repeat) { t.at = now + t.repeat; t.seq = nextTimer++; } else { timers.delete(id); }
      try {
        typeof t.fn === "function" ? t.fn(...(t.args || [])) : (0, eval)(String(t.fn));
      } catch (e) { console.error(e); }
    }
    now = target;
  }

  window.__vclock = {
    base: BASE,
    get now() { return now; },
    get inflight() { return inflight; },
    pendingTimers() { return timers.size; },
    /** One frame: move time on, fire timers, run rAF callbacks, step animations, draw the cursor,
     *  and frame the shot (a zoom rendered by the browser itself, so text stays sharp). */
    frame(ms, cursor, view) {
      if (view && document.body) {
        const b = document.body.style;
        b.transformOrigin = "0 0";
        b.transform = view.s === 1 ? "" : `translate(${view.tx}px, ${view.ty}px) scale(${view.s})`;
      }
      runTimersUntil(now + ms);
      const batch = rafs;
      rafs = new Map();
      for (const cb of batch.values()) {
        try { cb(now); } catch (e) { console.error(e); }
      }
      syncAnimations();
      drawCursor(cursor);
      return { now, inflight };
    },
    /** Real-time paint wait, for the recorder only. */
    nativeFrame() { return new Promise((r) => nativeRaf(() => r())); },
    setCursor: drawCursor,
  };
})();
