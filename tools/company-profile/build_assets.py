"""Build the company profile's image and font assets for dompdf.

The live web PHP has no gd (docs/DOCUMENTS.md section 3), and without it dompdf cannot read a PNG at
all: Cpdf::addPngFromFile() returns early when imagecreatefrompng() is missing, and a CSS
background-image throws outright (AbstractRenderer::_background_image). dompdf does carry a JPEG as it
is, with nothing but getimagesize(). So every raster image in the profile is a JPEG placed as an <img>,
and the pages carry no CSS background images.

The brand's logo SVGs are an automatic trace with nested <svg> and <use>, which php-svg-lib draws only
partly (the wordmark disappears), so the lockups come from the brand kit's PNG renders instead.

Run on a machine with Pillow and fontTools; the server never runs this, the output is committed:

    python tools/company-profile/build_assets.py

Writes, under praxis_core/resources/company-profile/:
  logos/*.jpg      client logos, greyscale, flattened on Paper, so the wall reads as one wall
  img/*-page.jpg   place imagery cropped to a full A4 landscape bleed
  img/*-band.jpg   the same, cropped to the cinematic band across a page
  brand/*.jpg      the logo lockups, flattened on Paper and on Ink
  fonts/*.ttf      static, subset cuts of the two brand variable fonts, with their licences
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "praxis_core" / "resources" / "company-profile"
SITE = ROOT / "praxis_site" / "public" / "img"
BRAND = ROOT / "praxis_core" / "resources" / "brand"

PAPER = (255, 255, 255)
INK = (12, 20, 43)  # #0C142B

# The page the imagery bleeds across, and the band it runs in: A4 landscape is 841.89 x 595.28pt.
PAGE_RATIO = 841.89 / 595.28
BAND_RATIO = 841.89 / 232.0

# Place imagery: the sector and city photographs, graded to the brand direction (BRAND.md section 10).
# The horizon sits above centre in all six, so the band crop is taken from 38% down rather than centred.
PLACE = {
    "pharmaceuticals": "pharmaceuticals-2400.webp",
    "consumer-goods": "consumer-goods-2400.webp",
    "oil-and-gas": "oil-and-gas-2400.webp",
    "logistics": "logistics-2400.webp",
    "lagos": "lagos-2400.webp",
    "douala": "douala-2400.webp",
}

# The logo lockups. The brand kit's PNG renders are the source; colour goes on Paper, reversed on Ink.
LOCKUPS = [
    ("jbs-praxis-horizontal-colour-1600.png", "horizontal-colour.jpg", PAPER, 1200),
    ("jbs-praxis-stacked-colour-1200.png", "stacked-colour.jpg", PAPER, 900),
]

# The two brand families, as published by Google Fonts under the SIL Open Font License (the licence
# travels with the files, in praxis_core/resources/company-profile/fonts/OFL-*.txt). The subsets that
# the demo end-card carries are split latin / latin-ext, so neither file alone covers French; these are
# the complete variable sources, cut and subset here instead.
FONT_SOURCES = {
    "newsreader": "https://raw.githubusercontent.com/google/fonts/main/ofl/newsreader/Newsreader%5Bopsz%2Cwght%5D.ttf",
    "newsreader-italic": "https://raw.githubusercontent.com/google/fonts/main/ofl/newsreader/Newsreader-Italic%5Bopsz%2Cwght%5D.ttf",
    "jakarta": "https://raw.githubusercontent.com/google/fonts/main/ofl/plusjakartasans/PlusJakartaSans%5Bwght%5D.ttf",
}
FONT_LICENCES = {
    "OFL-Newsreader.txt": "https://raw.githubusercontent.com/google/fonts/main/ofl/newsreader/OFL.txt",
    "OFL-PlusJakartaSans.txt": "https://raw.githubusercontent.com/google/fonts/main/ofl/plusjakartasans/OFL.txt",
}

# Static instances cut from the variable fonts. Newsreader carries an optical size axis, so a page that
# sets 46pt display type and 9pt captions needs more than one cut of it; php-font-lib reads a static TTF,
# not a woff2 and not a variable axis.
FONT_CUTS = [
    ("newsreader", "newsreader-display.ttf", {"wght": 400, "opsz": 52}),
    ("newsreader", "newsreader-headline.ttf", {"wght": 500, "opsz": 24}),
    ("newsreader", "newsreader-text.ttf", {"wght": 400, "opsz": 12}),
    ("newsreader-italic", "newsreader-text-italic.ttf", {"wght": 400, "opsz": 12}),
    ("jakarta", "jakarta-regular.ttf", {"wght": 400}),
    ("jakarta", "jakarta-medium.ttf", {"wght": 500}),
    ("jakarta", "jakarta-semibold.ttf", {"wght": 600}),
]

# Latin, Latin-1, Latin Extended-A, general punctuation and currency: English, French and the naira sign.
SUBSET_UNICODES = "U+0020-00FF,U+0131,U+0152-0153,U+0100-017F,U+2000-206F,U+20A0-20BF,U+2122,U+2192"


def _flatten(path: Path, background: tuple[int, int, int]) -> Image.Image:
    im = Image.open(path).convert("RGBA")
    return Image.alpha_composite(Image.new("RGBA", im.size, background + (255,)), im).convert("RGB")


def _crop_to(im: Image.Image, ratio: float, vertical: float = 0.5) -> Image.Image:
    """Crop to an exact aspect ratio, so the page never has to squash or clip the image itself."""
    if im.width / im.height > ratio:
        width = round(im.height * ratio)
        left = (im.width - width) // 2
        return im.crop((left, 0, left + width, im.height))
    height = round(im.width / ratio)
    top = round((im.height - height) * vertical)
    return im.crop((0, top, im.width, top + height))


def logos() -> None:
    """Client logos, greyscale on Paper: a wall that reads as one wall, not fifteen brands shouting."""
    dest = OUT / "logos"
    dest.mkdir(parents=True, exist_ok=True)
    for src in sorted((SITE / "clients").glob("*.webp")):
        grey = ImageOps.autocontrast(ImageOps.grayscale(_flatten(src, PAPER)), cutoff=(0, 0))
        # Twice the source height keeps the small type inside some of the marks readable in print.
        grey = grey.resize((grey.width * 2, grey.height * 2), Image.LANCZOS)
        out = dest / (src.stem + ".jpg")
        grey.save(out, "JPEG", quality=95, subsampling=0, optimize=True)
        print(f"  logo  {out.relative_to(ROOT)}  {grey.width}x{grey.height}")


def place() -> None:
    """Place imagery, cropped at build time to the two bleed shapes the pages use."""
    dest = OUT / "img"
    dest.mkdir(parents=True, exist_ok=True)
    for name, filename in PLACE.items():
        src = SITE / "place" / filename
        if not src.exists():
            print(f"  skip  {filename} (missing)", file=sys.stderr)
            continue
        im = Image.open(src).convert("RGB")
        for suffix, ratio, vertical, width in (("page", PAGE_RATIO, 0.5, 1700), ("band", BAND_RATIO, 0.38, 1800)):
            cropped = _crop_to(im, ratio, vertical)
            if cropped.width > width:
                cropped = cropped.resize((width, round(cropped.height * width / cropped.width)), Image.LANCZOS)
            out = dest / f"{name}-{suffix}.jpg"
            cropped.save(out, "JPEG", quality=82, optimize=True, progressive=False)
            print(f"  place {out.relative_to(ROOT)}  {cropped.width}x{cropped.height}  {out.stat().st_size // 1024} KB")


def brand() -> None:
    """The logo lockups as JPEGs, flattened on the surface each one sits on."""
    dest = OUT / "brand"
    dest.mkdir(parents=True, exist_ok=True)
    for filename, out_name, background, width in LOCKUPS:
        src = BRAND / filename
        if not src.exists():
            print(f"  skip  {filename} (missing)", file=sys.stderr)
            continue
        im = _flatten(src, background)
        if im.width > width:
            im = im.resize((width, round(im.height * width / im.width)), Image.LANCZOS)
        out = dest / out_name
        im.save(out, "JPEG", quality=95, subsampling=0, optimize=True)
        print(f"  brand {out.relative_to(ROOT)}  {im.width}x{im.height}  {out.stat().st_size // 1024} KB")


def devices() -> None:
    """The bar stack (BRAND.md section 9), as an SVG: three bars in the logo's proportions, ends cut on
    the diagonal like the logo's, dark left of the split and Teal right of it. Drawn as plain polygons,
    which is the part of SVG php-svg-lib is reliable at; the brand's own lockups are not."""
    dest = OUT / "device"
    dest.mkdir(parents=True, exist_ok=True)

    width, bar, gap, split = 300.0, 12.0, 13.0, 0.55
    height = bar * 3 + gap * 2
    for name, dark, light in (("bar-stack.svg", "#0C142B", "#0C8B9B"), ("bar-stack-reversed.svg", "#FFFFFF", "#0BB8C4")):
        parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:g} {height:g}" width="{width:g}" height="{height:g}">']
        for index in range(3):
            y = index * (bar + gap)
            middle = width * split
            parts.append(
                f'<polygon fill="{dark}" points="{bar:g},{y:g} {middle:g},{y:g} {middle:g},{y + bar:g} 0,{y + bar:g}"/>'
            )
            parts.append(
                f'<polygon fill="{light}" points="{middle:g},{y:g} {width:g},{y:g} {width - bar:g},{y + bar:g} {middle:g},{y + bar:g}"/>'
            )
        parts.append("</svg>")
        out = dest / name
        out.write_text("".join(parts), encoding="utf-8")
        print(f"  device {out.relative_to(ROOT)}")


def _source(name: str, url: str) -> Path:
    """The variable source, cached outside the repository: only the built cuts are committed."""
    import urllib.request

    cache = Path(os.environ.get("TEMP", "/tmp")) / "jbs-brand-fonts"
    cache.mkdir(parents=True, exist_ok=True)
    path = cache / f"{name}.ttf"
    if not path.exists():
        print(f"  fetch {url}")
        urllib.request.urlretrieve(url, path)
    return path


def fonts() -> None:
    """Static, subset TTF cuts of the two brand families, plus their licences."""
    import urllib.request

    from fontTools import subset, ttLib
    from fontTools.varLib import instancer

    dest = OUT / "fonts"
    dest.mkdir(parents=True, exist_ok=True)

    for filename, url in FONT_LICENCES.items():
        out = dest / filename
        if not out.exists():
            urllib.request.urlretrieve(url, out)
            print(f"  licence {out.relative_to(ROOT)}")

    options = subset.Options(
        layout_features=["kern", "liga", "ccmp", "locl", "mark", "mkmk", "onum", "lnum", "tnum", "pnum", "frac"],
        notdef_outline=True,
        recommended_glyphs=True,
        name_IDs=["*"],
        name_legacy=True,
        glyph_names=False,
        hinting=True,
        desubroutinize=False,
    )
    for source, filename, axes in FONT_CUTS:
        font = ttLib.TTFont(_source(source, FONT_SOURCES[source]))
        # updateFontNames needs STAT axis values that not every cut names; dompdf matches on the
        # @font-face family in the stylesheet, not on the font's own name table, so names stay as they are.
        instancer.instantiateVariableFont(font, axes, inplace=True, updateFontNames=False)
        subsetter = subset.Subsetter(options=options)
        subsetter.populate(unicodes=subset.parse_unicodes(SUBSET_UNICODES))
        subsetter.subset(font)
        font.flavor = None
        out = dest / filename
        font.save(out)
        print(f"  font  {out.relative_to(ROOT)}  {out.stat().st_size // 1024} KB  {axes}")


if __name__ == "__main__":
    print("company profile assets ->", OUT)
    logos()
    place()
    brand()
    devices()
    fonts()
