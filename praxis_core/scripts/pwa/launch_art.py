"""Builds the artwork of the HQ app's launch animation and writes it into
resources/views/components/pwa/launch.blade.php, between the launch-art markers.

One inline SVG made from the real logo (brand/kit/logo/jbs-praxis-stacked-reversed.svg, groups si and st):
the ten bars, each split at the centre into a white half and a Bright Teal half that arrive from both sides;
the letters; the Praxis rule; and "HQ" as outlines from Plus Jakarta Sans 500, so the first frame never waits
for a web font. The layout is option B ("Converge") of the preview the owner approved on 30 October 2026:
there the logo was 99.12px wide (42% of a 236px phone), the rule 2px thick and 14% of the logo's width below
it, and "HQ" 15px (line 19px, baseline 16px) 10% of the width below the rule, letter-spaced 0.02em.

Each bar only carries the parts of the traced logo that lie in its band (every part lies in one band), so a
frame repaints the logo about twice instead of twenty times; the clip rectangles are the preview's.

Needs Python 3.9+ with fontTools and brotli (pip install --user fonttools brotli) and the npm packages of
praxis_core (the font file). Run from anywhere:

    python scripts/pwa/launch_art.py

Then run scripts/pwa/startup_images.py: the startup images are the first frame of this artwork.
"""
import re
from pathlib import Path

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

CORE = Path(__file__).resolve().parents[2]
LOGO = CORE.parent / 'brand' / 'kit' / 'logo' / 'jbs-praxis-stacked-reversed.svg'
FONT = CORE / 'node_modules' / '@fontsource-variable' / 'plus-jakarta-sans' / 'files' / 'plus-jakarta-sans-latin-wght-normal.woff2'
VIEW = CORE / 'resources' / 'views' / 'components' / 'pwa' / 'launch.blade.php'
MARKERS = re.compile(r'(\{\{-- launch-art:[^\n]*--\}\}\n)(.*?)(\{\{-- /launch-art --\}\})', re.S)

WHITE, BRIGHT_TEAL, ON_INK_MUTED = '#FFFFFF', '#0BB8C4', '#BBBDC4'
PAINT = 'stroke-width="1.3" stroke-linejoin="round" fill-rule="evenodd"'  # as in the brand kit: closes seams

# The logo in its own units (viewBox 0 0 400 515.6); the bars split at x = 200
W, LOGO_H, SPLIT = 400, 515.6, 200
MID = (138, 378)  # the letters and PRAXIS, between the top and the bottom bars
# The ten bars: top and bottom of each band, and its place counted from the letters (0 arrives first)
BANDS = [(0, 30, 4), (31, 55, 3), (57, 81, 2), (83, 107.5, 1), (109, 134, 0),
         (381, 405, 0), (407, 431, 1), (433, 457, 2), (459, 483, 3), (485, 515.6, 4)]
SLACK = 1.0  # a part's stroke reaches 0.65 units past its outline

# Below the logo, from the approved preview
PX = W / 99.12  # units per pixel of the preview
RULE_TOP = LOGO_H + 0.14 * W
RULE_WIDTH = 2 * PX
LABEL_TOP = RULE_TOP + RULE_WIDTH + 0.10 * W
EM = 15 * PX
BASELINE = LABEL_TOP + 16 * PX
HEIGHT = LABEL_TOP + 19 * PX
TRACKING = 0.02 * EM  # letter-spacing, after each letter as browsers add it (the pair sits 0.01em left of centre)
LABEL = 'HQ'          # Plus Jakarta Sans has no kerning between H and Q


def num(v):
    s = f'{v:.2f}'.rstrip('0').rstrip('.')
    return '0' if s in ('-0', '') else s


def parts(d):
    """The subpaths of one path, each with its top and bottom (the kit's paths use absolute M, L and C only)."""
    found = []
    for sub in re.split(r'(?=M)', d.strip()):
        sub = sub.strip()
        if not sub:
            continue
        if set(re.findall(r'[A-Za-z]', sub)) - {'M', 'L', 'C'}:
            raise SystemExit('launch_art.py: the logo now uses path commands other than M, L and C; update parts()')
        ys = [float(n) for n in re.findall(r'-?(?:\d+\.?\d*|\.\d+)', sub)][1::2]
        found.append((sub, min(ys), max(ys)))
    return found


def logo_paths():
    """{'st': [parts of each path], 'si': [...]} from the reversed stacked logo."""
    svg = LOGO.read_text(encoding='utf-8')
    found = {}
    for gid in ('st', 'si'):
        group = re.search(r'<g id="%s">(.*?)</g>' % gid, svg, re.S)
        if not group:
            raise SystemExit(f'launch_art.py: no <g id="{gid}"> in {LOGO.name}')
        found[gid] = [parts(d) for d in re.findall(r'<path d="([^"]+)"', group.group(1))]
    return found


def region(paths, gids, y0, y1, used):
    """The parts of the given groups that reach into the band y0..y1, one <path> per path of the logo (each
    keeps its own even-odd fill), in the kit's drawing order."""
    out = []
    for gid in gids:
        for i, path in enumerate(paths[gid]):
            keep = [s for s, top, bottom in path if bottom >= y0 - SLACK and top <= y1 + SLACK]
            for s, top, bottom in path:
                if bottom >= y0 - SLACK and top <= y1 + SLACK:
                    used[(gid, i, s)] = used.get((gid, i, s), 0) + 1
            if keep:
                out.append(f'<path d="{" ".join(keep)}"/>')
    return ''.join(out)


def label_outlines():
    """"HQ" in Plus Jakarta Sans at weight 500, as path data in logo units, centred under the logo."""
    font = instancer.instantiateVariableFont(TTFont(FONT), {'wght': 500})
    scale = EM / font['head'].unitsPerEm
    cmap, glyphs, hmtx = font.getBestCmap(), font.getGlyphSet(), font['hmtx']
    names = [cmap[ord(c)] for c in LABEL]
    x = (W - sum(hmtx[n][0] * scale + TRACKING for n in names)) / 2
    pen = SVGPathPen(glyphs, ntos=num)
    for n in names:
        glyphs[n].draw(TransformPen(pen, (scale, 0, 0, -scale, x, BASELINE)))
        x += hmtx[n][0] * scale + TRACKING
    return pen.getCommands()


def artwork():
    paths = logo_paths()
    used = {}
    defs = [
        f'<linearGradient id="hql-split" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="{W}" y2="0">'
        f'<stop offset=".5" stop-color="{WHITE}"/><stop offset=".5" stop-color="{BRIGHT_TEAL}"/></linearGradient>',
        f'<clipPath id="hql-mid"><rect x="0" y="{MID[0]}" width="{W}" height="{num(MID[1] - MID[0])}"/></clipPath>',
    ]
    bars = []
    for k, (y0, y1, order) in enumerate(BANDS):
        h = num(y1 - y0)
        defs.append(f'<clipPath id="hql-c{k}l"><rect x="0" y="{num(y0)}" width="{SPLIT}" height="{h}"/></clipPath>'
                    f'<clipPath id="hql-c{k}r"><rect x="{SPLIT}" y="{num(y0)}" width="{W - SPLIT}" height="{h}"/></clipPath>')
        defs.append(f'<g id="hql-b{k}">{region(paths, ("st", "si"), y0, y1, used)}</g>')
        for side, colour in (('l', WHITE), ('r', BRIGHT_TEAL)):
            bars.append(f'<g class="hql-bar hql-{side} hql-o{order}"><g clip-path="url(#hql-c{k}{side})" '
                        f'fill="{colour}" stroke="{colour}" {PAINT}><use href="#hql-b{k}"/></g></g>')
    defs.append(f'<g id="hql-mt">{region(paths, ("st",), *MID, used)}</g>')
    defs.append(f'<g id="hql-mi">{region(paths, ("si",), *MID, used)}</g>')

    # Every part of the logo must be drawn: in exactly one band, or the split would hide or double it
    total = sum(len(p) for gid in paths for p in paths[gid])
    unused = total - len(used)
    shared = sum(1 for n in used.values() if n > 1)
    if unused or shared:
        raise SystemExit(f'launch_art.py: {unused} parts of the logo are in no band and {shared} in more than one')

    letters = (f'<g clip-path="url(#hql-mid)">'
               f'<use href="#hql-mt" fill="{BRIGHT_TEAL}" stroke="{BRIGHT_TEAL}" {PAINT}/>'
               f'<use href="#hql-mi" fill="{WHITE}" stroke="{WHITE}" {PAINT}/></g>')
    rule = (f'<path class="hql-rule" d="M0 {num(RULE_TOP + RULE_WIDTH / 2)}H{W}" fill="none" '
            f'stroke="url(#hql-split)" stroke-width="{num(RULE_WIDTH)}"/>')
    name = f'<path class="hql-label" d="{label_outlines()}" fill="{ON_INK_MUTED}"/>'
    height = num(HEIGHT)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{height}" viewBox="0 0 {W} {height}" focusable="false">'
            f'<defs>{"".join(defs)}</defs>{"".join(bars)}{letters}{rule}{name}</svg>'), total


def main():
    svg, total = artwork()
    view = VIEW.read_text(encoding='utf-8')
    if not MARKERS.search(view):
        raise SystemExit(f'launch_art.py: no launch-art markers in {VIEW}')
    VIEW.write_text(MARKERS.sub(lambda m: m.group(1) + svg + '\n' + m.group(3), view, count=1), encoding='utf-8', newline='\n')
    print(f'launch_art.py: {total} parts of the logo in 10 bands and the letters, {len(svg) / 1024:.1f} KB of SVG '
          f'written to {VIEW.relative_to(CORE)}')


if __name__ == '__main__':
    main()
