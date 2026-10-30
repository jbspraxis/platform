"""A 20-second interface loop for social posts: three people ask, Praxis AI answers (a one-line summary, then the
cards), then "Ask your company anything." Writes brand/build/_render/loop-<w>x<h>.html; render_loop.mjs turns each
into frames and ffmpeg into MP4. The animation is a pure function of time (window.render(t)), so every frame is exact.
"""
from pathlib import Path

import build_kit as kit

OUT = Path(__file__).resolve().parent / '_render'

SCRIPT = r"""
const SCENE = 6.0, START = 0.4, END = START + 3 * SCENE;     // 3 exchanges, then the end card until 20 s
const Q = [
  {q: 'Where is our cash across Lagos and Douala?', who: 'Olatunde', role: 'COO', ini: 'O', col: '#0C142B',
   sum: 'Your cash across Lagos and Douala is ₦184.6 million and 52.5 million FCFA, as of 07:30 this morning.'},
  {q: 'Which projects are over budget?', who: 'Mireille', role: 'CFO', ini: 'M', col: '#08707C',
   sum: 'Four projects are over budget, ₦38.2 million above plan in total. Apapa depot expansion is the largest gap.'},
  {q: 'Who is waiting on whom?', who: 'Wanjiru', role: 'Managing Director', ini: 'W', col: '#4A5468',
   sum: 'Three items wait on you and two wait on others. The oldest is a leave request at Apapa, open for 2 days.'},
];
const ease = x => x <= 0 ? 0 : x >= 1 ? 1 : 1 - Math.pow(1 - x, 3);
const clamp = x => Math.max(0, Math.min(1, x));
const $ = id => document.getElementById(id);
const fmt = (n, cur) => cur === 'NGN' ? '₦' + Math.round(n).toLocaleString('en-GB') : Math.round(n).toLocaleString('en-GB') + ' FCFA';
const settle = (el, v, cur, p) => el.textContent = fmt(v * (0.97 + 0.03 * ease(p)), cur);
window.render = function (t) {
  const k = t >= END ? 3 : Math.max(0, Math.floor((t - START) / SCENE));
  const S = START + k * SCENE, u = t - S;                    // time inside the exchange
  $('win').style.opacity = ease(clamp(t / 0.4)) * (k === 3 ? 1 - ease(clamp((t - END) / 0.4)) : 1);
  $('end').style.opacity = k === 3 ? ease(clamp((t - END - 0.2) / 0.5)) : 0;
  $('endrule').style.transform = 'scaleX(' + ease(clamp((t - END - 0.4) / 0.8)) + ')';
  if (k === 3) return;
  const s = Q[k];
  $('thread').style.opacity = u > SCENE - 0.3 ? 1 - ease(clamp((u - (SCENE - 0.3)) / 0.3)) : 1;
  const typed = Math.floor(clamp(u / 1.5) * s.q.length), sent = u > 1.65;
  $('input').textContent = sent ? '' : s.q.slice(0, typed);
  $('caret').style.opacity = sent ? 0 : (Math.floor(t * 2.4) % 2 ? 0.25 : 1);
  $('placeholder').style.opacity = !sent && typed === 0 ? 1 : 0;
  $('typing').textContent = sent || typed === 0 ? '' : s.who + ' is typing…';
  // the person's message
  $('msg').style.opacity = ease(clamp((u - 1.65) / 0.25));
  $('msg').style.transform = 'translateY(' + 10 * (1 - ease(clamp((u - 1.65) / 0.25))) + 'px)';
  $('av').textContent = s.ini; $('av').style.background = s.col;
  $('who').textContent = s.who; $('role').textContent = s.role; $('q').textContent = s.q;
  // Praxis AI: thinking, then the summary streams in, then the cards
  const ai = clamp((u - 1.95) / 0.25);
  $('ai').style.opacity = ease(ai);
  const thinking = u > 1.95 && u < 2.6;
  $('dots').style.display = thinking ? '' : 'none';
  document.querySelectorAll('#dots i').forEach((d, i) => d.style.opacity = 0.3 + 0.7 * Math.abs(Math.sin((t * 4) - i * 0.6)));
  $('sum').textContent = u < 2.6 ? '' : s.sum.slice(0, Math.floor(clamp((u - 2.6) / 1.5) * s.sum.length));
  const p = clamp((u - 4.1) / 0.6);
  for (const j of [0, 1, 2]) $('a' + j).style.display = j === k ? '' : 'none';
  $('a' + k).style.opacity = ease(p); $('a' + k).style.transform = 'translateY(' + 10 * (1 - ease(p)) + 'px)';
  if (k === 0) { settle($('ng'), 184620000, 'NGN', clamp((u - 4.2) / 0.6)); settle($('cm'), 52480000, 'XAF', clamp((u - 4.3) / 0.6)); }
  if (k === 1) document.querySelectorAll('#a1 .bar i').forEach((b, i) => b.style.transform = 'scaleX(' + ease(clamp((u - 4.2 - i * 0.1) / 0.7)) + ')');
  if (k === 2) document.querySelectorAll('#a2 .row').forEach((r, i) => r.style.opacity = ease(clamp((u - 4.15 - i * 0.12) / 0.35)));
};
render(0);
"""


def html(w, h, pid):
    sq = w == h
    win_w = 1560 if not sq else 960
    logo_h = 30
    logo = kit.svg_doc(f'0 0 {kit.HZ_W:.2f} {kit.H}', kit.horizontal_body('colour', pid), 'JBS Praxis', round(logo_h * kit.HZ_W / kit.H), logo_h)
    end_logo = kit.svg_doc(f'0 0 {kit.HZ_W:.2f} {kit.H}', kit.horizontal_body('colour', pid + 'e'), 'JBS Praxis', round(44 * kit.HZ_W / kit.H), 44)
    f = 'font-family:"Plus Jakarta Sans",Arial,sans-serif'
    css = f"""
    *{{box-sizing:border-box;margin:0}} body{{width:{w}px;height:{h}px;background:#F2F5F7;{f};color:#0C142B;overflow:hidden;position:relative}}
    #win{{position:absolute;left:50%;top:50%;width:{win_w}px;transform:translate(-50%,-50%);background:#fff;border:1px solid #D9E0E6;border-radius:18px;box-shadow:0 24px 60px rgba(12,20,43,.10);overflow:hidden}}
    .bar0{{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid #E6EBEF}}
    .ws{{font-size:17px;font-weight:600}} .ws span{{color:#4A5468;font-weight:500}}
    #thread{{padding:28px 36px 10px;height:{650 if not sq else 760}px}}
    #msg{{display:flex;justify-content:flex-end;margin-bottom:22px}}
    .bub{{max-width:{760 if not sq else 640}px;background:#0C142B;color:#fff;border-radius:16px 16px 4px 16px;padding:14px 18px}}
    .meta{{display:flex;align-items:center;gap:10px;justify-content:flex-end;font-size:14px;color:#4A5468;margin-bottom:8px}}
    .meta b{{color:#0C142B;font-weight:700}}
    #av{{width:30px;height:30px;border-radius:50%;color:#fff;font-weight:700;font-size:14px;display:grid;place-items:center}}
    #q{{font-size:{22 if not sq else 21}px;line-height:1.35}}
    .aihead{{display:flex;align-items:center;gap:10px;font-size:14px;color:#4A5468;margin-bottom:10px}}
    .aiav{{width:30px;height:30px;border-radius:50%;background:#0C8B9B;color:#fff;font-size:12px;font-weight:800;display:grid;place-items:center}}
    .aihead b{{color:#0C142B}}
    #sum{{font:400 {26 if not sq else 25}px/1.35 Newsreader,Georgia,serif;max-width:{1180 if not sq else 860}px;min-height:36px}}
    #dots{{display:flex;gap:6px;padding:10px 0}} #dots i{{width:9px;height:9px;border-radius:50%;background:#0C8B9B}}
    .ans{{margin-top:20px}}
    .kpis{{display:flex;gap:20px}} .kpi{{flex:1;border:1px solid #E6EBEF;border-radius:12px;padding:18px 20px}}
    .lab{{font-size:15px;color:#4A5468;font-weight:500}} .val{{font-size:{36 if not sq else 32}px;font-weight:700;margin-top:6px;font-variant-numeric:tabular-nums;letter-spacing:-.01em}}
    .src{{display:inline-flex;margin-top:16px;font-size:14px;color:#08707C;background:#E8F4F5;border-radius:999px;padding:7px 14px;font-weight:600}}
    .proj{{display:grid;grid-template-columns:{'280px 1fr 70px' if not sq else '240px 1fr 64px'};gap:14px;align-items:center;font-size:17px;padding:8px 0;border-bottom:1px solid #EEF2F5}}
    .bar{{height:10px;background:#E6EBEF;border-radius:6px;overflow:hidden}} .bar i{{display:block;height:100%;background:#0C8B9B;transform-origin:left}}
    .over{{color:#B42318;font-weight:700;text-align:right;font-variant-numeric:tabular-nums}}
    .row{{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #EEF2F5;font-size:17px}}
    .pill{{font-size:13px;font-weight:700;border-radius:999px;padding:5px 11px}} .you{{background:#FFF4E5;color:#9A5B00}} .them{{background:#E8F4F5;color:#08707C}}
    .askwrap{{padding:0 36px 26px}} #typing{{font-size:13px;color:#8A94A6;height:20px;margin-bottom:6px}}
    .ask{{display:flex;align-items:center;gap:12px;border:1px solid #CBD4DC;border-radius:12px;padding:14px 16px;font-size:19px;min-height:58px;position:relative}}
    #placeholder{{position:absolute;left:16px;color:#8A94A6}} #caret{{display:inline-block;width:2px;height:24px;background:#0C8B9B;margin-left:2px}}
    .send{{margin-left:auto;background:#0C142B;color:#fff;border-radius:9px;padding:9px 16px;font-size:15px;font-weight:600}}
    #end{{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:30px;opacity:0}}
    #end h1{{font:400 {96 if not sq else 84}px/1.05 Newsreader,Georgia,serif;letter-spacing:-.015em;text-align:center}}
    #endrule{{display:flex;width:360px;height:4px;transform-origin:left}} #endrule i{{flex:1}}
    .note{{position:absolute;right:26px;bottom:20px;font-size:13px;color:#8A94A6}}
    """
    body = f"""<style>{css}</style>
    <div id="win">
      <div class="bar0"><div>{logo}</div><div class="ws">Ndali Group <span>· Workspace</span></div></div>
      <div id="thread">
        <div id="msg"><div><div class="meta"><b id="who"></b><span id="role"></span><span id="av"></span></div><div class="bub"><div id="q"></div></div></div></div>
        <div id="ai"><div class="aihead"><span class="aiav">AI</span><b>Praxis AI</b></div>
          <div id="dots"><i></i><i></i><i></i></div><div id="sum"></div>
          <div class="ans" id="a0"><div class="kpis"><div class="kpi"><div class="lab">Lagos, all accounts</div><div class="val" id="ng"></div></div>
            <div class="kpi"><div class="lab">Douala, all accounts</div><div class="val" id="cm"></div></div></div>
            <div class="src">Source: bank feeds · today 07:30</div></div>
          <div class="ans" id="a1">
            <div class="proj"><span>Apapa depot expansion</span><span class="bar"><i style="width:100%"></i></span><span class="over">+12%</span></div>
            <div class="proj"><span>Cold room, Ikeja</span><span class="bar"><i style="width:72%"></i></span><span class="over">+8%</span></div>
            <div class="proj"><span>Route planning, Ibadan</span><span class="bar"><i style="width:46%"></i></span><span class="over">+5%</span></div>
            <div class="proj"><span>Solar, Ibadan depot</span><span class="bar"><i style="width:28%"></i></span><span class="over">+3%</span></div>
            <div class="src">Source: projects ledger · budget vs actual, this quarter</div></div>
          <div class="ans" id="a2">
            <div class="row"><span>Leave request · Apapa · 2 days</span><span class="pill you">Waiting on you</span></div>
            <div class="row"><span>Purchase order · 1,240 cartons</span><span class="pill you">Waiting on you</span></div>
            <div class="row"><span>Payment run · Douala</span><span class="pill you">Waiting on you</span></div>
            <div class="row"><span>Stock count · Ibadan</span><span class="pill them">Waiting on Operations</span></div>
            <div class="row"><span>Supplier contract · Lagos</span><span class="pill them">Waiting on Legal</span></div></div>
        </div>
      </div>
      <div class="askwrap"><div id="typing"></div><div class="ask"><span id="placeholder">Ask your company anything</span><span id="input"></span><span id="caret"></span><span class="send">Ask</span></div></div>
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
