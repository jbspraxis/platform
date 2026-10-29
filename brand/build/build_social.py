"""Profile pictures and banners for JBS Praxis on YouTube, X and Instagram (brand/kit/social/<platform>/).
Reuses the logo artwork and the Edge renderer from build_kit.py.   python brand/build/build_social.py
"""
from pathlib import Path

import build_kit as kit

OUT = kit.KIT / 'social'
LINE = 'Ask your company anything.'
DESCRIPTOR = 'Consulting and engineering for Africa’s institutions'


def avatar(size, pid):
    """The full stacked logo, centred on white with room for the circle crop every platform applies."""
    h = round(size * 0.70)
    w = round(h * kit.VB_W / kit.VB_H)
    logo = kit.stacked('colour', pid).replace('<svg ', f'<svg width="{w}" height="{h}" ', 1)
    body = (f'<div style="width:{size}px;height:{size}px;background:#FFFFFF;display:flex;align-items:center;'
            f'justify-content:center">{logo}</div>')
    return kit.page(body, '#FFFFFF')


def banner(w, h, safe_w, safe_h, line_px, pid, safe_left=None, bars=True):
    """Mist field; inside the safe area: the horizontal logo, the homepage line, the descriptor and the Praxis rule.
    Outside it (seen on wide screens only): the logo's bar stack, Ink left of the split, Teal right."""
    left = safe_left if safe_left is not None else (w - safe_w) // 2
    top = (h - safe_h) // 2
    logo_h = round(line_px * 0.55)
    logo = kit.svg_doc(f'0 0 {kit.HZ_W:.2f} {kit.H}', kit.horizontal_body('colour', pid), 'JBS Praxis',
                       round(logo_h * kit.HZ_W / kit.H), logo_h)
    # The logo's bars, flush to the right edge and centred in the band desktops show (outside the text's safe area)
    bar_h, gap = round(safe_h * 0.07), round(safe_h * 0.075)
    stack = 5 * bar_h + 4 * gap
    longest = round((w - safe_w) / 2 * 0.8)
    bars = '' if not bars else ''.join(
        f'<div style="position:absolute;right:0;top:{(h - stack) // 2 + k * (bar_h + gap)}px;'
        f'width:{longest - k * round(longest * 0.16)}px;height:{bar_h}px;'
        f'background:linear-gradient(to right,#0C142B 0 50%,#0C8B9B 50% 100%);clip-path:polygon(0 0,100% 0,100% 100%,{round(bar_h * 1.6)}px 100%)"></div>'
        for k in range(5))
    body = (f'<div style="position:relative;width:{w}px;height:{h}px;background:#F2F5F7;overflow:hidden">{bars}'
            f'<div style="position:absolute;left:{left}px;top:{top}px;width:{safe_w}px;height:{safe_h}px;display:flex;'
            f'flex-direction:column;justify-content:center;gap:{round(line_px * 0.28)}px">'
            f'<div>{logo}</div>'
            f'<div style="font:400 {line_px}px/1.08 Newsreader,Georgia,serif;letter-spacing:-0.01em;color:#0C142B">{LINE}</div>'
            f'<div style="font:500 {round(line_px * 0.34)}px/1.35 \'Plus Jakarta Sans\',Arial,sans-serif;color:#4A5468">{DESCRIPTOR}</div>'
            f'<div style="display:flex;width:{round(safe_w * 0.42)}px;height:{max(3, round(line_px * 0.05))}px;margin-top:{round(line_px * 0.1)}px">'
            f'<i style="flex:1;background:#0C142B"></i><i style="flex:1;background:#0C8B9B"></i></div>'
            '</div></div>')
    return kit.page(body, '#F2F5F7', fonts=True)


def main():
    jobs = [
        # name, html, width, height
        ('youtube/jbs-praxis-youtube-profile-800.png', avatar(800, 'yp'), 800, 800),
        ('youtube/jbs-praxis-youtube-watermark-150.png', avatar(150, 'yw'), 150, 150),
        # YouTube banner 2560x1440: the text stays inside the 1546x423 area every device shows
        ('youtube/jbs-praxis-youtube-banner-2560x1440.png', banner(2560, 1440, 1546, 423, 96, 'yb'), 2560, 1440),
        ('x/jbs-praxis-x-profile-400.png', avatar(400, 'xp'), 400, 400),
        # X header 1500x500: the profile picture covers the lower left, so the text sits right of it
        ('x/jbs-praxis-x-header-1500x500.png', banner(1500, 500, 1000, 380, 72, 'xh', safe_left=380, bars=False), 1500, 500),
        ('instagram/jbs-praxis-instagram-profile-1080.png', avatar(1080, 'ip'), 1080, 1080),
    ]
    for name, html, w, h in jobs:
        out = OUT / name
        kit.shoot(Path(name).stem, html, w, h, out)
        print('ok', name)


if __name__ == '__main__':
    main()
