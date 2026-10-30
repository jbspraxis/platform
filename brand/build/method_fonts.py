# Static font instances for the Praxis Method guide (run by build_method.js).
# Headless Edge prints variable fonts as Type 3 glyphs; static TrueType instances embed as real, subsetted fonts.
# Sources: the self-hosted site fonts in demo/pipeline/endcard/fonts (latin subset: English and French).
#   python brand/build/method_fonts.py <outDir>
import sys
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.ttLib.tables._g_l_y_f import Glyph
from fontTools.varLib import instancer

ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / 'demo' / 'pipeline' / 'endcard' / 'fonts'

# file name -> (source, axis values); the CSS in brand/method/method.css names these files
INSTANCES = {
    'jakarta-400': ('plus-jakarta-sans-latin.woff2', {'wght': 400}),
    'jakarta-500': ('plus-jakarta-sans-latin.woff2', {'wght': 500}),
    'jakarta-600': ('plus-jakarta-sans-latin.woff2', {'wght': 600}),
    'newsreader-display-400': ('newsreader-latin.woff2', {'wght': 400, 'opsz': 72}),
    'newsreader-title-400': ('newsreader-latin.woff2', {'wght': 400, 'opsz': 28}),
    'newsreader-title-500': ('newsreader-latin.woff2', {'wght': 500, 'opsz': 28}),
    'newsreader-text-500': ('newsreader-latin.woff2', {'wght': 500, 'opsz': 14}),
}

NNBSP = 0x202F  # French narrow no-break space: neither font maps it


def add_narrow_space(font):
    """Maps U+202F to a new empty glyph 60% as wide as the font's word space."""
    cmap = font.getBestCmap()
    if NNBSP in cmap:
        return
    name = 'uni202F'
    font.setGlyphOrder(font.getGlyphOrder() + [name])
    glyph = Glyph()
    glyph.numberOfContours = 0
    font['glyf'].glyphs[name] = glyph
    font['glyf'].glyphOrder = font.getGlyphOrder()
    font['hmtx'].metrics[name] = (round(font['hmtx'][cmap[0x20]][0] * 0.6), 0)
    font['maxp'].numGlyphs = len(font.getGlyphOrder())
    for table in font['cmap'].tables:
        if table.isUnicode():
            table.cmap[NNBSP] = name


WEIGHTS = {400: 'Regular', 500: 'Medium', 600: 'SemiBold'}


def rename(font, name):
    """Names each instance after its file (Jakarta Medium, Newsreader Display Regular), so the PDF lists them apart."""
    family = ' '.join(w.capitalize() for w in name.split('-')[:-1])
    style = WEIGHTS[int(name.rsplit('-', 1)[1])]
    table = font['name']
    for record in list(table.names):
        if record.nameID in (16, 17, 21, 22, 25):
            table.removeNames(nameID=record.nameID)
    table.setName(family, 1, 3, 1, 0x409)
    table.setName('Regular', 2, 3, 1, 0x409)
    table.setName(f'{family} {style}', 4, 3, 1, 0x409)
    table.setName(f'{family.replace(" ", "")}-{style}', 6, 3, 1, 0x409)


def main(out):
    out.mkdir(parents=True, exist_ok=True)
    for name, (src, axes) in INSTANCES.items():
        target = out / f'{name}.ttf'
        if target.exists() and target.stat().st_mtime > Path(__file__).stat().st_mtime:
            continue
        font = instancer.instantiateVariableFont(TTFont(SRC / src), axes)
        font.flavor = None
        add_narrow_space(font)
        rename(font, name)
        font.save(target)
        print(f'font {target.name}')


if __name__ == '__main__':
    main(Path(sys.argv[1]))
