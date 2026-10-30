"""Renders the iPhone and iPad startup images of the HQ app: the first frame of the launch animation (the letters
and HQ on Ink), from the launch component's own markup and CSS, frozen before anything moves. Each image is a
screenshot by headless Chrome (or Edge) at a device's size and pixel ratio, in portrait and in landscape,
written to resources/pwa/startup/hq-<width>x<height>.png in device pixels with a palette of at most 256 colours.
The devices are the ones resources/views/pwa/head/startup-images.blade.php lists in the page head.

Needs Python 3.9+ with Pillow (pip install --user pillow) and Chrome or Edge (set CHROME to choose the browser).
Run from anywhere, after scripts/pwa/launch_art.py:

    python scripts/pwa/startup_images.py
"""
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image, ImageChops

CORE = Path(__file__).resolve().parents[2]
VIEW = CORE / 'resources' / 'views' / 'components' / 'pwa' / 'launch.blade.php'
HEAD = CORE / 'resources' / 'views' / 'pwa' / 'head' / 'startup-images.blade.php'
OUT = CORE / 'resources' / 'pwa' / 'startup'
BROWSERS = [
    r'C:\Program Files\Google\Chrome\Application\chrome.exe',
    r'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
    r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/microsoft-edge',
]

# The component as the installed app shows it (the CSS shows it only in the app's own window), frozen on the
# first frame: no animation runs, so the bars and the rule are still waiting to arrive
FREEZE = ('<style>.hq-launch { display: flex !important; } '
          '*, *::before, *::after { animation: none !important; transition: none !important; }</style>')


def browser():
    for candidate in [os.environ.get('CHROME')] + BROWSERS:
        if candidate and Path(candidate).is_file():
            return candidate
    raise SystemExit('startup_images.py: Chrome or Edge not found; set CHROME to its path')


def devices():
    """[(css width, css height, pixel ratio)] in portrait, from the $devices list of the head partial."""
    block = re.search(r'\$devices\s*=\s*\[(.*?)\];', HEAD.read_text(encoding='utf-8'), re.S)
    found = [tuple(int(v) for v in m) for m in re.findall(r'\[\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\]', block.group(1) if block else '')]
    if not found:
        raise SystemExit(f'startup_images.py: no $devices list in {HEAD}')
    return found


def first_frame_page(app='hq'):
    """A page with the component's style and overlay, exactly as written in the view, plus the freeze. For the
    Client Portal (app='portal') also the view's portal style: the logo alone, without "HQ"."""
    view = re.sub(r'\{\{--.*?--\}\}\n?', '', VIEW.read_text(encoding='utf-8'), flags=re.S)
    style = re.search(r'<style id="hq-launch-style">.*?</style>', view, re.S)
    portal = re.search(r'<style id="hq-launch-portal">.*?</style>', view, re.S) if app == 'portal' else None
    if app == 'portal' and not portal:
        raise SystemExit(f'startup_images.py: no portal style (id="hq-launch-portal") in {VIEW}')
    overlay = re.search(r'<div class="hq-launch".*?</svg>\s*</div>', view, re.S)
    if not style or not overlay or '<svg' not in overlay.group(0):
        raise SystemExit(f'startup_images.py: no launch style, overlay or artwork in {VIEW} (run launch_art.py first)')
    if re.search(r'\{\{|\{!!|@\w', overlay.group(0)):
        raise SystemExit('startup_images.py: the overlay has Blade syntax in it; the startup images need plain markup')
    return ('<!doctype html><html lang="en"><head><meta charset="utf-8">'
            '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            f'{style.group(0)}{portal.group(0) if portal else ""}{FREEZE}</head><body style="margin:0">{overlay.group(0)}</body></html>')


def shoot(chrome, page, profile, css_w, css_h, ratio, out):
    """One screenshot of the page at css_w x css_h CSS pixels and the given pixel ratio. The browser window has a
    minimum width and its viewport is not exactly --window-size, so the page is shown in an iframe of exactly the
    device's size at the top left of a larger window, and the picture is cut to the iframe."""
    frame = page.with_name(f'frame-{css_w}x{css_h}.html')
    frame.write_text('<!doctype html><html><head><meta charset="utf-8"><style>html, body { margin: 0; overflow: hidden; } '
                     'iframe { position: absolute; top: 0; left: 0; border: 0; display: block; }</style></head><body>'
                     f'<iframe src="{page.name}" width="{css_w}" height="{css_h}" scrolling="no"></iframe></body></html>',
                     encoding='utf-8')
    subprocess.run([
        chrome, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
        '--force-color-profile=srgb', '--force-prefers-no-reduced-motion', f'--user-data-dir={profile}',
        f'--force-device-scale-factor={ratio}', f'--window-size={max(css_w, 600) + 200},{css_h + 200}',
        f'--screenshot={out}', frame.as_uri(),
    ], check=True, timeout=120, capture_output=True)
    size = (css_w * ratio, css_h * ratio)
    with Image.open(out) as img:
        if img.size[0] < size[0] or img.size[1] < size[1]:
            raise SystemExit(f'startup_images.py: {out.name}: the window came out {img.size[0]}x{img.size[1]}, '
                             f'smaller than {size[0]}x{size[1]}')
        return img.convert('RGB').crop((0, 0) + size)


def palette(img):
    """The same picture with a palette of at most 256 colours. The flat colours (Ink, white, Bright Teal, the
    grey of HQ) and the commonest edge shades are kept exactly; the rarer edge shades share the remaining
    entries (median cut). Every pixel takes its truly nearest entry: Pillow's own mapping is approximate and
    would move Ink off #0C142B. Returns the image, the number of colours before, and the largest error."""
    colours = sorted(img.getcolors(maxcolors=1 << 24), reverse=True)
    chosen = [rgb for _, rgb in colours[:256 if len(colours) <= 256 else 64]]
    if len(colours) > 256:
        rest = Image.new('RGB', (len(colours) - 64, 1))
        rest.putdata([rgb for _, rgb in colours[64:]])
        shades = rest.quantize(colors=192, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE)
        used = sorted(i for _, i in shades.getcolors())
        flat = shades.getpalette()
        chosen += [tuple(flat[3 * i:3 * i + 3]) for i in used if tuple(flat[3 * i:3 * i + 3]) not in chosen]
    nearest = {}
    for _, (r, g, b) in colours:
        nearest[r << 16 | g << 8 | b] = min(range(len(chosen)), key=lambda i: (
            (chosen[i][0] - r) ** 2 + (chosen[i][1] - g) ** 2 + (chosen[i][2] - b) ** 2))

    width, height = img.size
    raw, out = img.tobytes(), bytearray()
    background = raw[:3] * width  # rows of Ink only, most of the picture, are copied in one go
    for y in range(height):
        row = raw[3 * width * y:3 * width * (y + 1)]
        if row == background:
            out += bytes([nearest[row[0] << 16 | row[1] << 8 | row[2]]]) * width
        else:
            out += bytes(nearest[row[j] << 16 | row[j + 1] << 8 | row[j + 2]] for j in range(0, 3 * width, 3))
    small = Image.frombytes('P', img.size, bytes(out))
    small.putpalette([c for rgb in chosen for c in rgb])
    error = max(top for _, top in ImageChops.difference(img, small.convert('RGB')).getextrema())
    return small, len(colours), error


def main():
    # python startup_images.py           HQ's images (hq-*.png)
    # python startup_images.py portal    the Client Portal's (portal-*.png): the logo alone
    app = sys.argv[1] if len(sys.argv) > 1 else 'hq'
    if app not in ('hq', 'portal'):
        raise SystemExit('startup_images.py: the app is hq or portal')
    chrome = browser()
    work = Path(tempfile.mkdtemp(prefix='hq-startup-'))
    try:
        page = work / 'first-frame.html'
        page.write_text(first_frame_page(app), encoding='utf-8')
        OUT.mkdir(parents=True, exist_ok=True)
        written, worst = set(), 0
        for w, h, r in devices():
            for css_w, css_h in ((w, h), (h, w)):
                name = f'{app}-{css_w * r}x{css_h * r}.png'
                img = shoot(chrome, page, work / 'profile', css_w, css_h, r, work / name)
                small, colours, error = palette(img)
                small.save(OUT / name, optimize=True)
                worst = max(worst, error)
                written.add(name)
                print(f'  {name}  {colours} colours, largest error {error}/255  {(OUT / name).stat().st_size / 1024:.1f} KB')
        for old in OUT.glob(f'{app}-*.png'):
            if old.name not in written:
                old.unlink()
                print(f'  removed {old.name}')
        total = sum((OUT / n).stat().st_size for n in written)
        print(f'startup_images.py: {len(written)} images, {total / 1024:.0f} KB in all, '
              f'largest colour error {worst}/255, in {OUT.relative_to(CORE)} (with {Path(chrome).name})')
    finally:
        shutil.rmtree(work, ignore_errors=True)


if __name__ == '__main__':
    sys.exit(main())
