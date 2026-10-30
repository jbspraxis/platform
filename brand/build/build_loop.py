"""A 10-second interface loop for social posts: three questions answered, then "Ask your company anything."
Writes brand/build/_render/loop-<w>x<h>.html; render_loop.mjs turns each into frames, ffmpeg into MP4.
The animation is a pure function of time (window.render(t)), so every frame is exact.
"""
from pathlib import Path

import build_kit as kit

OUT = Path(__file__).resolve().parent / '_render'

SCRIPT = r"""
const Q = [
  {q: 'Where is our cash across Lagos and Douala?', t0: 0.5},
  {q: 'Which projects are over budget?', t0: 3.6},
  {q: 'Who is waiting on whom?', t0: 6.4},
];
const ease = x => x <= 0 ? 0 : x >= 1 ? 1 : 1 - Math.pow(1 - x, 3);
const clamp = x => Math.max(0, Math.min(1, x));
const $ = id => document.getElementById(id);
const fmt = (n, cur) => cur === 'NGN' ? '₦' + Math.round(n).toLocaleString('en-GB') : Math.round(n).toLocaleString('en-GB') + ' FCFA';
function settle(el, value, cur, p) {           // the last digits roll from 97% of the value to the value
  el.textContent = fmt(value * (0.97 + 0.03 * ease(p)), cur);
}
window.render = function (t) {
  const scene = t < 3.4 ? 0 : t < 6.2 ? 1 : t < 8.9 ? 2 : 3;
  document.body.dataset.scene = scene;
  $('win').style.opacity = ease(clamp(t / 0.4)) * (scene === 3 ? 1 - ease(clamp((t - 8.9) / 0.35)) : 1);
  $('end').style.opacity = scene === 3 ? ease(clamp((t - 9.05) / 0.4)) : 0;
  $('endrule').style.transform = 'scaleX(' + ease(clamp((t - 9.2) / 0.6)) + ')';
  if (scene === 3) return;
  const s = Q[scene], typed = Math.floor(clamp((t - s.t0) / 0.9) * s.q.length);
  const sent = t > s.t0 + 1.05;
  $('input').textContent = sent ? '' : s.q.slice(0, typed);
  $('caret').style.opacity = sent ? 0 : (Math.floor(t * 2.4) % 2 ? 0.25 : 1);
  $('placeholder').style.opacity = !sent && typed === 0 ? 1 : 0;
  $('asked').textContent = sent ? s.q : '';
  $('asked').style.opacity = ease(clamp((t - s.t0 - 1.05) / 0.25));
  const p = clamp((t - s.t0 - 1.25) / 0.9);       // answer arrival
  for (const k of [0, 1, 2]) $('a' + k).style.display = k === scene ? '' : 'none';
  const a = $('a' + scene);
  a.style.opacity = ease(p); a.style.transform = 'translateY(' + (12 * (1 - ease(p))) + 'px)';
  $('rule').style.transform = 'scaleX(' + ease(clamp((t - s.t0 - 1.2) / 0.8)) + ')';
  if (scene === 0) { settle($('ng'), 184620000, 'NGN', clamp((t - s.t0 - 1.5) / 0.6)); settle($('cm'), 52480000, 'XAF', clamp((t - s.t0 - 1.6) / 0.6)); }
  if (scene === 1) document.querySelectorAll('#a1 .bar i').forEach((b, i) => b.style.transform = 'scaleX(' + ease(clamp((t - s.t0 - 1.45 - i * 0.08) / 0.6)) + ')');
  if (scene === 2) document.querySelectorAll('#a2 .row').forEach((r, i) => r.style.opacity = ease(clamp((t - s.t0 - 1.4 - i * 0.12) / 0.35)));
};
render(0);
"""


def html(w, h, pid):
    sq = w == h
    s = w / 1920 if not sq else 1080 / 1300
    win_w = round(1500 * s) if not sq else 940
    logo_h = 30
    logo = kit.svg_doc(f'0 0 {kit.HZ_W:.2f} {kit.H}', kit.horizontal_body('colour', pid), 'JBS Praxis', round(logo_h * kit.HZ_W / kit.H), logo_h)
    end_logo = kit.svg_doc(f'0 0 {kit.HZ_W:.2f} {kit.H}', kit.horizontal_body('colour', pid + 'e'), 'JBS Praxis', round(44 * kit.HZ_W / kit.H), 44)
    f = 'font-family:"Plus Jakarta Sans",Arial,sans-serif'
    css = f"""
    *{{box-sizing:border-box;margin:0}} body{{width:{w}px;height:{h}px;background:#F2F5F7;{f};color:#0C142B;overflow:hidden;position:relative}}
    #win{{position:absolute;left:50%;top:50%;width:{win_w}px;transform:translate(-50%,-50%);background:#fff;border:1px solid #D9E0E6;border-radius:18px;box-shadow:0 24px 60px rgba(12,20,43,.10);overflow:hidden}}
    .bar0{{display:flex;align-items:center;justify-content:space-between;padding:20px 28px;border-bottom:1px solid #E6EBEF}}
    .ws{{font-size:17px;font-weight:600;color:#0C142B}} .ws span{{color:#4A5468;font-weight:500}}
    .body{{padding:34px 40px 30px;min-height:{460 if not sq else 560}px}}
    #asked{{font:400 34px/1.2 Newsreader,Georgia,serif;letter-spacing:-.01em;min-height:44px}}
    .rule{{display:flex;width:240px;height:3px;margin:20px 0 26px;transform-origin:left}} .rule i{{flex:1}}
    .kpis{{display:flex;gap:22px}} .kpi{{flex:1;border:1px solid #E6EBEF;border-radius:12px;padding:20px 22px}}
    .lab{{font-size:15px;color:#4A5468;font-weight:500}} .val{{font-size:{38 if not sq else 34}px;font-weight:700;margin-top:6px;font-variant-numeric:tabular-nums;letter-spacing:-.01em}}
    .src{{display:inline-flex;gap:8px;align-items:center;margin-top:22px;font-size:14px;color:#08707C;background:#E8F4F5;border-radius:999px;padding:7px 14px;font-weight:600}}
    .proj{{display:grid;grid-template-columns:{'260px 1fr 70px' if not sq else '230px 1fr 64px'};gap:14px;align-items:center;font-size:17px;padding:9px 0;border-bottom:1px solid #EEF2F5}}
    .bar{{height:10px;background:#E6EBEF;border-radius:6px;overflow:hidden}} .bar i{{display:block;height:100%;background:#0C8B9B;transform-origin:left}}
    .over{{color:#B42318;font-weight:700;text-align:right;font-variant-numeric:tabular-nums}}
    .row{{display:flex;justify-content:space-between;align-items:center;padding:13px 0;border-bottom:1px solid #EEF2F5;font-size:18px}}
    .pill{{font-size:13px;font-weight:700;border-radius:999px;padding:5px 11px}} .you{{background:#FFF4E5;color:#9A5B00}} .them{{background:#E8F4F5;color:#08707C}}
    .ask{{display:flex;align-items:center;gap:12px;margin:0 40px 32px;border:1px solid #CBD4DC;border-radius:12px;padding:16px 18px;font-size:19px;min-height:60px;position:relative}}
    #placeholder{{position:absolute;left:18px;color:#8A94A6}} #caret{{display:inline-block;width:2px;height:24px;background:#0C8B9B;margin-left:2px}}
    .send{{margin-left:auto;background:#0C142B;color:#fff;border-radius:9px;padding:9px 16px;font-size:15px;font-weight:600}}
    #end{{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:30px;opacity:0}}
    #end h1{{font:400 {96 if not sq else 84}px/1.05 Newsreader,Georgia,serif;letter-spacing:-.015em;text-align:center}}
    #endrule{{display:flex;width:360px;height:4px;transform-origin:left}} #endrule i{{flex:1}}
    .note{{position:absolute;right:26px;bottom:20px;font-size:13px;color:#8A94A6}}
    """
    body = f"""<style>{css}</style>
    <div id="win">
      <div class="bar0"><div>{logo}</div><div class="ws">Ndali Group <span>· Workspace</span></div></div>
      <div class="body">
        <div id="asked"></div>
        <div class="rule" id="rule"><i style="background:#0C142B"></i><i style="background:#0C8B9B"></i></div>
        <div id="a0"><div class="kpis"><div class="kpi"><div class="lab">Lagos, all accounts</div><div class="val" id="ng"></div></div>
          <div class="kpi"><div class="lab">Douala, all accounts</div><div class="val" id="cm"></div></div></div>
          <div class="src">Bank feeds · today 07:30</div></div>
        <div id="a1"><div class="lab" style="margin-bottom:10px">4 projects are over budget, ₦38,200,000 above plan</div>
          <div class="proj"><span>Apapa depot expansion</span><span class="bar"><i style="width:100%"></i></span><span class="over">+12%</span></div>
          <div class="proj"><span>Cold room, Ikeja</span><span class="bar"><i style="width:72%"></i></span><span class="over">+8%</span></div>
          <div class="proj"><span>Route planning, Ibadan</span><span class="bar"><i style="width:46%"></i></span><span class="over">+5%</span></div>
          <div class="proj"><span>Solar, Ibadan depot</span><span class="bar"><i style="width:28%"></i></span><span class="over">+3%</span></div></div>
        <div id="a2"><div class="lab" style="margin-bottom:6px">3 items wait on you, 2 wait on others</div>
          <div class="row"><span>Leave request · Apapa</span><span class="pill you">Waiting on you</span></div>
          <div class="row"><span>Purchase order · 1,240 cartons</span><span class="pill you">Waiting on you</span></div>
          <div class="row"><span>Payment run · Douala</span><span class="pill you">Waiting on you</span></div>
          <div class="row"><span>Stock count · Ibadan</span><span class="pill them">Waiting on Operations</span></div>
          <div class="row"><span>Supplier contract · Lagos</span><span class="pill them">Waiting on Legal</span></div></div>
      </div>
      <div class="ask"><span id="placeholder">Ask your company anything</span><span id="input"></span><span id="caret"></span><span class="send">Ask</span></div>
    </div>
    <div id="end">{end_logo}<h1>Ask your company anything.</h1><div id="endrule"><i style="background:#0C142B"></i><i style="background:#0C8B9B"></i></div></div>
    <div class="note">Illustrative data</div>
    <script>{SCRIPT}</script>"""
    return kit.page(body, '#F2F5F7', fonts=True)


def main():
    OUT.mkdir(exist_ok=True)
    for w, h in [(1920, 1080), (1080, 1080)]:
        p = OUT / f'loop-{w}x{h}.html'
        p.write_text(html(w, h, f'lp{w}'), encoding='utf-8')
        print('ok', p)


if __name__ == '__main__':
    main()
