"""
Rebuilds the seed renditions of the client logos from the website's own WebP files.

    python database/seeders/company-profile/build_logos.py

It reads praxis_site/public/img/clients/*.webp, trims each logo to its ink, scales it to 160 px high (at most
720 px wide) and writes three files per logo into database/seeders/company-profile/logos:

    <key>.png    the artwork on transparency, for the deck and anything that can read a PNG
    <key>.jpg    the same on white, which is what dompdf can draw: the live PHP has no gd, so a PNG is no use
    <key>.webp   what jbspraxis.com is served

Needs Pillow. Nothing in the application runs this: it is a build step, and its output is committed, because
no PHP on this machine or on the live server can convert an image.
"""

import pathlib
import sys

from PIL import Image

TARGET_HEIGHT = 160
MAX_WIDTH = 720

HERE = pathlib.Path(__file__).resolve().parent
SOURCE = HERE.parents[2].parent / "praxis_site" / "public" / "img" / "clients"
OUT = HERE / "logos"


def ink_box(image):
    """The box the artwork fills: anything transparent or near-white is not ink."""
    pixels = image.load()
    width, height = image.size
    left, top, right, bottom = width, height, -1, -1
    for y in range(height):
        for x in range(width):
            r, g, b, a = pixels[x, y]
            if a < 12 or (r > 246 and g > 246 and b > 246):
                continue
            left, right = min(left, x), max(right, x)
            top, bottom = min(top, y), max(bottom, y)
    if right < 0:
        return 0, 0, width, height
    return left, top, right + 1, bottom + 1


def main():
    if not SOURCE.is_dir():
        sys.exit("Could not find %s" % SOURCE)
    OUT.mkdir(parents=True, exist_ok=True)

    for source in sorted(SOURCE.glob("*.webp")):
        image = Image.open(source).convert("RGBA").crop(ink_box(Image.open(source).convert("RGBA")))
        scale = TARGET_HEIGHT / image.height
        if image.width * scale > MAX_WIDTH:
            scale = MAX_WIDTH / image.width
        image = image.resize(
            (max(1, round(image.width * scale)), max(1, round(image.height * scale))),
            Image.LANCZOS,
        )
        image.save(OUT / (source.stem + ".png"), "PNG", optimize=True)
        white = Image.new("RGB", image.size, (255, 255, 255))
        white.paste(image, mask=image.split()[3])
        white.save(OUT / (source.stem + ".jpg"), "JPEG", quality=92, optimize=True)
        image.save(OUT / (source.stem + ".webp"), "WEBP", quality=90, method=6)
        print(source.stem, image.size)


if __name__ == "__main__":
    main()
