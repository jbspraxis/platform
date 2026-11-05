"""Page pictures of an agreement, for docs/documents/previews (docs/DOCUMENTS.md section 12).

The PDFs themselves come from the test that renders the real contract:

    cd praxis_core
    AGREEMENT_PREVIEW_DIR=<a folder> DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
      DB_USERNAME=root DB_DATABASE=praxis_test_agreement \
      php vendor/bin/phpunit tests/Feature/Letters/AgreementPreviewTest.php

Then, from the repository root:

    python brand/build/agreement_previews.py <that folder> docs/documents/previews

It writes one PNG per page, 110 dpi, named agreement-issued-p1.png … and agreement-signed-p8.png.
Needs PyMuPDF (pip install pymupdf); nothing on the server ever runs it.

The signatures in the pictures are a specimen scribble, nobody's real one:
praxis_core/tests/Feature/Letters/fixtures/specimen-signature.jpg, drawn by specimen() below.
"""

import sys
from pathlib import Path

import pymupdf

DPI = 110


def render(pdf_path: Path, out_dir: Path, stem: str) -> int:
    document = pymupdf.open(pdf_path)
    for number, page in enumerate(document, start=1):
        pixmap = page.get_pixmap(dpi=DPI)
        pixmap.save(out_dir / f"{stem}-p{number}.png")
    pages = document.page_count
    document.close()
    return pages


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2
    source, out_dir = Path(sys.argv[1]), Path(sys.argv[2])
    out_dir.mkdir(parents=True, exist_ok=True)

    for stem in ("agreement-issued", "agreement-signed"):
        pdf = source / f"{stem}.pdf"
        if not pdf.exists():
            print(f"missing {pdf}; run AgreementPreviewTest first")
            return 1
        print(f"{stem}: {render(pdf, out_dir, stem)} pages")
    return 0




def specimen(path: Path) -> None:
    """Redraws the specimen signature the preview test applies (a scribble on white, as a JPEG)."""
    import math

    width, height, ink = 420, 130, (0.05, 0.08, 0.17)
    document = pymupdf.open()
    page = document.new_page(width=width, height=height)
    page.draw_rect(pymupdf.Rect(0, 0, width, height), color=None, fill=(1, 1, 1))

    points = []
    for i in range(260):
        t = i / 259
        x = 24 + t * (width - 60)
        y = 86 - 34 * math.sin(t * 7.2) * math.exp(-1.4 * t) - 18 * math.sin(t * 2.1)
        points.append((x, y))
    for a, b in zip(points, points[1:]):
        page.draw_line(pymupdf.Point(*a), pymupdf.Point(*b), color=ink, width=2.1)
    page.draw_line(pymupdf.Point(40, 96), pymupdf.Point(300, 70), color=ink, width=1.6)

    page.get_pixmap(dpi=190, colorspace=pymupdf.csRGB).save(path, jpg_quality=92)


if __name__ == "__main__":
    raise SystemExit(main())
