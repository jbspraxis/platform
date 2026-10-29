"""Builds the JBS Praxis brand kit (logo versions, icons, splash screens, email and social images).

Source: public_html_PRAXIS/assets/img/logo_colour.svg (five flat colour layers traced from the original artwork).
Rasterising uses headless Microsoft Edge, so no image libraries are needed. Run from anywhere:

    python brand/build/build_kit.py
"""
import json
import re
import shutil
import struct
import subprocess
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BRAND = ROOT / 'brand'
KIT = BRAND / 'kit'
RENDER = BRAND / 'build' / '_render'
SRC_SVG = ROOT / 'public_html_PRAXIS' / 'assets' / 'img' / 'logo_colour.svg'
SRC_PNG = ROOT / 'public_html_PRAXIS' / 'assets' / 'img' / 'logo_light.png'
EDGE = Path(r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe')

INK, TEAL, WHITE, BRIGHT, MIST, SLATE, MUTED, ON_DARK_MUTED = (
    '#0C142B', '#0C8B9B', '#FFFFFF', '#0BB8C4', '#F2F5F7', '#4A5468', '#5E6679', '#BBBDC4')

# Geometry of the source artwork, in its own units (viewBox 0 0 400 515.6)
VB_W, VB_H = 400, 515.6
SPLIT_X = 200                 # the dark/teal split of the bars
MID_TOP, MID_BOTTOM = 138, 378  # between the top bars and the bottom bars: letters and wordmark
MONO = (50, 148, 300, 182)    # crop of the JBS letters
WORD = (62, 340, 276, 32)     # crop of the PRAXIS wordmark (letters fill about 28.8 of the 32 units)

COLOURWAYS = {
    'colour': (INK, TEAL),       # on Paper, Mist, Teal Tint
    'reversed': (WHITE, BRIGHT),  # on Ink, Petrol
    'ink': (INK, INK),           # one colour
    'white': (WHITE, WHITE),     # one colour on dark
}


def drop_specks(d, min_size=4.0):
    """Removes stray fragments left by the automatic trace: subpaths smaller than min_size units."""
    kept = []
    for sub in re.split(r'(?=M)', d.strip()):
        nums = [float(n) for n in re.findall(r'-?\d+(?:\.\d+)?', sub)]
        xs, ys = nums[0::2], nums[1::2]
        if xs and ys and max(max(xs) - min(xs), max(ys) - min(ys)) >= min_size:
            kept.append(sub.strip())
    return ' '.join(kept)


def load_layers():
    s = SRC_SVG.read_text(encoding='utf-8')
    layers = []
    for tag in re.findall(r'<path\b[^>]*>', s):
        d = re.search(r'\sd="([^"]+)"', tag).group(1)
        fill = re.search(r'fill="(#[0-9a-fA-F]+)"', tag).group(1).upper()
        layers.append((drop_specks(d), fill))
    ink = [d for d, f in layers if f == INK]
    teal = [d for d, f in layers if f != INK]
    assert len(ink) == 1 and len(teal) == 4, 'unexpected source layers'
    return ink, teal


INK_D, TEAL_D = load_layers()


def art(pid, dark, light, mid_only=False, word_only=False):
    """Inner SVG markup in source units. Bars: dark left of the split, light right.
    Letters: the source's navy layer in dark, its four teal layers flattened to light.
    A hairline stroke in the fill colour closes the seams between traced layers."""
    paint = 'stroke-width="1.3" stroke-linejoin="round" fill-rule="evenodd"'
    defs = (f'<defs><g id="{pid}i">' + ''.join(f'<path d="{d}"/>' for d in INK_D) + '</g>'
            f'<g id="{pid}t">' + ''.join(f'<path d="{d}"/>' for d in TEAL_D) + '</g>')
    if word_only:
        return defs + f'</defs><use href="#{pid}i" fill="{dark}" stroke="{dark}" {paint}/>'
    if dark == light:
        return defs + (f'</defs><g fill="{dark}" stroke="{dark}" {paint}>'
                       f'<use href="#{pid}t"/><use href="#{pid}i"/></g>')
    bars_h = VB_H - MID_BOTTOM
    defs += (f'<clipPath id="{pid}l"><rect x="0" y="0" width="{SPLIT_X}" height="{MID_TOP}"/>'
             f'<rect x="0" y="{MID_BOTTOM}" width="{SPLIT_X}" height="{bars_h}"/></clipPath>'
             f'<clipPath id="{pid}r"><rect x="{SPLIT_X}" y="0" width="{VB_W - SPLIT_X}" height="{MID_TOP}"/>'
             f'<rect x="{SPLIT_X}" y="{MID_BOTTOM}" width="{VB_W - SPLIT_X}" height="{bars_h}"/></clipPath>'
             f'<clipPath id="{pid}m"><rect x="0" y="{MID_TOP}" width="{VB_W}" height="{MID_BOTTOM - MID_TOP}"/></clipPath></defs>')
    mid = (f'<g clip-path="url(#{pid}m)">'
           f'<use href="#{pid}t" fill="{light}" stroke="{light}" {paint}/>'
           f'<use href="#{pid}i" fill="{dark}" stroke="{dark}" {paint}/></g>')
    if mid_only:
        return defs + mid
    return (defs +
            f'<g clip-path="url(#{pid}l)" fill="{dark}" stroke="{dark}" {paint}><use href="#{pid}t"/><use href="#{pid}i"/></g>'
            f'<g clip-path="url(#{pid}r)" fill="{light}" stroke="{light}" {paint}><use href="#{pid}t"/><use href="#{pid}i"/></g>'
            + mid)


def svg_doc(vb, body, title, w=None, h=None):
    size = f' width="{w}" height="{h}"' if w else ''
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}"{size} role="img" aria-label="{title}">'
            f'<title>{title}</title>{body}</svg>\n')


def stacked(way, pid='s'):
    dark, light = COLOURWAYS[way]
    return svg_doc(f'0 0 {VB_W} {VB_H}', art(pid, dark, light), 'JBS Praxis')


def monogram_body(way, pid, x, y, w):
    dark, light = COLOURWAYS[way]
    h = w * MONO[3] / MONO[2]
    vb = ' '.join(str(v) for v in MONO)
    return f'<svg x="{x:.2f}" y="{y:.2f}" width="{w:.2f}" height="{h:.2f}" viewBox="{vb}">{art(pid, dark, light, mid_only=True)}</svg>'


def monogram(way, pid='m'):
    return svg_doc(' '.join(str(v) for v in MONO), art(pid, *COLOURWAYS[way], mid_only=True), 'JBS Praxis')


# Horizontal lockup: the monogram at height H, PRAXIS at a cap height of 0.3H, 0.5H apart
H = MONO[3]
CAP = 0.3 * H
WORD_SCALE = CAP / 28.8
WORD_W, WORD_H = WORD[2] * WORD_SCALE, WORD[3] * WORD_SCALE
GAP = 0.5 * H
HZ_W = MONO[2] + GAP + WORD_W


def horizontal_body(way, pid):
    dark, _ = COLOURWAYS[way]
    wvb = ' '.join(str(v) for v in WORD)
    return (monogram_body(way, pid + 'a', 0, 0, MONO[2]) +
            f'<svg x="{MONO[2] + GAP:.2f}" y="{(H - WORD_H) / 2:.2f}" width="{WORD_W:.2f}" height="{WORD_H:.2f}" viewBox="{wvb}">'
            f'{art(pid + "b", dark, dark, word_only=True)}</svg>')


def horizontal(way, pid='h'):
    return svg_doc(f'0 0 {HZ_W:.2f} {H}', horizontal_body(way, pid), 'JBS Praxis')


def tile(size, frac, radius, pid='t', bg=INK, way='reversed'):
    """The app icon: the monogram centred on an Ink tile."""
    w = size * frac
    h = w * MONO[3] / MONO[2]
    rect = f'<rect width="{size}" height="{size}" rx="{radius}" fill="{bg}"/>'
    return svg_doc(f'0 0 {size} {size}', rect + monogram_body(way, pid, (size - w) / 2, (size - h) / 2, w),
                   'JBS Praxis', size, size)


FONTS = ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
         '<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500'
         '&family=Plus+Jakarta+Sans:wght@400;500;600&display=block" rel="stylesheet">')


def page(body, bg='transparent', fonts=False):
    return ('<!doctype html><html><head><meta charset="utf-8">' + (FONTS if fonts else '') +
            f'<style>html,body{{margin:0;padding:0;background:{bg};overflow:hidden}}svg{{display:block}}</style>'
            f'</head><body>{body}</body></html>')


def shoot(name, html, w, h, out):
    """Render one HTML page to an exact-size PNG with headless Edge."""
    RENDER.mkdir(parents=True, exist_ok=True)
    src = RENDER / f'{name}.html'
    src.write_text(html, encoding='utf-8')
    out.parent.mkdir(parents=True, exist_ok=True)
    if out.exists():
        out.unlink()
    subprocess.run([str(EDGE), '--headless=new', '--disable-gpu', '--hide-scrollbars',
                    '--force-device-scale-factor=1', '--default-background-color=00000000',
                    '--virtual-time-budget=6000', f'--window-size={w},{h}',
                    f'--screenshot={out}', src.as_uri()],
                   check=True, timeout=120, capture_output=True)
    got = struct.unpack('>II', out.read_bytes()[16:24])
    assert got == (w, h), f'{out.name}: expected {w}x{h}, got {got}'
    return out


def write_ico(pngs, out):
    """favicon.ico with PNG-compressed entries (supported by every current browser)."""
    datas = [p.read_bytes() for p in pngs]
    head = struct.pack('<HHH', 0, 1, len(datas))
    offset = 6 + 16 * len(datas)
    entries = b''
    for data in datas:
        w, h = struct.unpack('>II', data[16:24])
        entries += struct.pack('<BBBBHHII', w % 256, h % 256, 0, 0, 1, 32, len(data), offset)
        offset += len(data)
    out.write_bytes(head + entries + b''.join(datas))


SPLASH = [  # (width, height, CSS media query) for iPhone and iPad startup images, portrait
    (1290, 2796, '(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)'),
    (1179, 2556, '(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)'),
    (1170, 2532, '(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)'),
    (750, 1334, '(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)'),
    (2048, 2732, '(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)'),
]
APPS = {'hq': ('JBS Praxis HQ', 'HQ'), 'portal': ('JBS Praxis Client Portal', 'Client Portal')}


def splash_html(w, h, label):
    logo_w = w * (0.26 if w >= 2000 else 0.40)
    fs = round(w * (0.026 if w >= 2000 else 0.042))
    body = (f'<div style="width:{w}px;height:{h}px;background:{INK};display:flex;flex-direction:column;'
            f'align-items:center;justify-content:center">'
            f'<div style="width:{logo_w:.0f}px">{stacked("reversed", "sp").replace("<svg ", "<svg width=\"100%\" ", 1)}</div>'
            f'<div style="margin-top:{fs * 1.6:.0f}px;font:500 {fs}px/1.2 \'Plus Jakarta Sans\',Arial,sans-serif;'
            f'color:{ON_DARK_MUTED}">{label}</div></div>')
    return page(body, INK, fonts=True)


def og_html(lang):
    line, support, descriptor = {
        'en': ('Ask your company anything.',
               'We design how your organisation runs, then build the system that answers for it.',
               'Consulting and engineering for Africa’s institutions'),
        'fr': ('Posez toutes vos questions à votre entreprise.',
               'Nous concevons le fonctionnement de votre organisation, puis le système qui en rend compte.',
               'Conseil et ingénierie pour les institutions africaines'),
    }[lang]
    size = 72 if lang == 'en' else 60
    logo = svg_doc(f'0 0 {HZ_W:.2f} {H}', horizontal_body('colour', 'og'), 'JBS Praxis', round(40 * HZ_W / H), 40)
    body = (f'<div style="position:relative;width:1200px;height:630px;background:{MIST};font-family:\'Plus Jakarta Sans\',Arial,sans-serif">'
            f'<div style="position:absolute;left:72px;top:64px">{logo}</div>'
            f'<div style="position:absolute;left:72px;top:0;height:530px;width:1000px;display:flex;flex-direction:column;justify-content:center">'
            f'<div style="font:400 {size}px/1.12 Newsreader,Georgia,serif;letter-spacing:-0.01em;color:{INK};text-wrap:balance">{line}</div>'
            f'<div style="margin-top:28px;width:860px;font:400 26px/38px \'Plus Jakarta Sans\',Arial,sans-serif;color:{SLATE}">{support}</div></div>'
            f'<div style="position:absolute;left:72px;top:540px;width:528px;height:2px;background:{INK}"></div>'
            f'<div style="position:absolute;left:600px;top:540px;width:528px;height:2px;background:{TEAL}"></div>'
            f'<div style="position:absolute;left:72px;top:558px;font:500 18px/26px \'Plus Jakarta Sans\',Arial,sans-serif;color:{MUTED}">{descriptor}</div>'
            '</div>')
    return page(body, MIST, fonts=True)


def main():
    if KIT.exists():
        shutil.rmtree(KIT)
    logo, icons, splash, email, social = (KIT / d for d in ('logo', 'icons', 'splash', 'email', 'social'))
    for d in (logo, icons, splash, email, social):
        d.mkdir(parents=True)

    # Vector logo versions
    for way in COLOURWAYS:
        (logo / f'jbs-praxis-stacked-{way}.svg').write_text(stacked(way), encoding='utf-8')
        (logo / f'jbs-praxis-horizontal-{way}.svg').write_text(horizontal(way), encoding='utf-8')
        (logo / f'jbs-praxis-monogram-{way}.svg').write_text(monogram(way), encoding='utf-8')
    shutil.copy(SRC_PNG, logo / 'jbs-praxis-original-gradient.png')

    # PNG exports of the logo (transparent)
    png = logo / 'png'
    sw = round(1200 * VB_W / VB_H)
    for way in ('colour', 'reversed'):
        shoot(f'stacked-{way}', page(stacked(way).replace('<svg ', f'<svg width="{sw}" height="1200" ', 1)),
              sw, 1200, png / f'jbs-praxis-stacked-{way}-1200.png')
        hh = round(1600 * H / HZ_W)
        shoot(f'horizontal-{way}', page(horizontal(way).replace('<svg ', f'<svg width="1600" height="{hh}" ', 1)),
              1600, hh, png / f'jbs-praxis-horizontal-{way}-1600.png')

    # Favicons and app icons
    (icons / 'favicon.svg').write_text(tile(64, 0.8, 12, 'fv'), encoding='utf-8')
    small = []
    for s in (16, 32, 48):
        small.append(shoot(f'fav{s}', page(tile(s, 0.88 if s < 48 else 0.82, s * 0.19, f'f{s}')), s, s,
                           RENDER / f'favicon-{s}.png'))
    write_ico(small, icons / 'favicon.ico')
    shutil.copy(small[1], icons / 'favicon-32.png')
    shoot('apple', page(tile(180, 0.66, 0, 'ap')), 180, 180, icons / 'apple-touch-icon.png')
    for s in (192, 512):
        shoot(f'any{s}', page(tile(s, 0.68, s * 0.2, f'a{s}')), s, s, icons / f'icon-{s}.png')
        shoot(f'mask{s}', page(tile(s, 0.6, 0, f'k{s}')), s, s, icons / f'icon-maskable-{s}.png')

    # Web app manifests, one per app
    for app, (name, short) in APPS.items():
        manifest = {
            'name': name, 'short_name': short, 'lang': 'en', 'start_url': '/', 'scope': '/',
            'display': 'standalone', 'background_color': INK, 'theme_color': INK,
            'icons': [
                {'src': '/icons/icon-192.png', 'sizes': '192x192', 'type': 'image/png', 'purpose': 'any'},
                {'src': '/icons/icon-512.png', 'sizes': '512x512', 'type': 'image/png', 'purpose': 'any'},
                {'src': '/icons/icon-maskable-192.png', 'sizes': '192x192', 'type': 'image/png', 'purpose': 'maskable'},
                {'src': '/icons/icon-maskable-512.png', 'sizes': '512x512', 'type': 'image/png', 'purpose': 'maskable'},
            ],
        }
        (icons / f'manifest-{app}.webmanifest').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')

    # Splash screens (iPhone and iPad startup images)
    startup = {app: [] for app in APPS}
    for app, (_, label) in APPS.items():
        for w, h, media in SPLASH:
            f = shoot(f'splash-{app}-{w}', splash_html(w, h, label), w, h, splash / f'{app}-splash-{w}x{h}.png')
            startup[app].append(f'<link rel="apple-touch-startup-image" href="/splash/{f.name}" media="{media} and (orientation: portrait)">')

    # The <head> lines for each app
    for app, (name, short) in APPS.items():
        lines = [
            f'<!-- {name}: icons, manifest and splash screens (files from brand/kit/icons and brand/kit/splash) -->',
            '<link rel="icon" href="/favicon.ico" sizes="48x48">',
            '<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">',
            '<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">',
            f'<link rel="manifest" href="/manifest-{app}.webmanifest">',
            f'<meta name="theme-color" content="{INK}">',
            '<meta name="apple-mobile-web-app-capable" content="yes">',
            '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">',
            f'<meta name="apple-mobile-web-app-title" content="{short}">',
        ] + startup[app]
        (icons / f'head-{app}.html').write_text('\n'.join(lines) + '\n', encoding='utf-8')

    # Email header logo: horizontal lockup on its own white field, at 2x (shown 32px tall)
    lh, pad = 64, 16
    lw = round(lh * HZ_W / H)
    ebody = (f'<div style="background:{WHITE};padding:{pad}px;width:{lw}px;height:{lh}px">'
             + horizontal('colour', 'em').replace('<svg ', f'<svg width="{lw}" height="{lh}" ', 1) + '</div>')
    shoot('email', page(ebody, WHITE), lw + 2 * pad, lh + 2 * pad, email / 'jbs-praxis-email-logo@2x.png')

    # Social: profile avatar and link-preview images
    shoot('avatar', page(tile(400, 0.62, 0, 'av')), 400, 400, social / 'jbs-praxis-avatar-400.png')
    for lang in ('en', 'fr'):
        shoot(f'og-{lang}', og_html(lang), 1200, 630, social / f'jbs-praxis-og-{lang}-1200x630.png')

    # One zip for sharing
    zpath = BRAND / 'JBS-Praxis-Brand-Kit.zip'
    with zipfile.ZipFile(zpath, 'w', zipfile.ZIP_DEFLATED) as z:
        for f in sorted(KIT.rglob('*')):
            if f.is_file():
                z.write(f, Path('JBS-Praxis-Brand-Kit') / f.relative_to(KIT))
    files = [f for f in KIT.rglob('*') if f.is_file()]
    print(f'kit: {len(files)} files, zip {zpath.stat().st_size // 1024} KB')


if __name__ == '__main__':
    main()
