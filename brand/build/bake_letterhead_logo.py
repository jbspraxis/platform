"""Bakes the brand kit's horizontal logo into the flat SVG the letterhead uses.

    python brand/build/bake_letterhead_logo.py          (needs: pip install svgelements)

Why: the letterhead's PDF is rendered by dompdf, which draws SVG through php-svg-lib. That library
ignores a nested <svg>'s viewBox and placement, and does not clip at all — its ClipPath tag is a stub.
The kit's horizontal logo is two nested <svg> viewports, each cropping a larger artboard through <use>,
so in a PDF it came out as the wrong shapes in the wrong places (docs/DOCUMENTS.md section 3).

This resolves each viewport itself — the viewBox transform, the placement, and the crop, done here by
clipping the outlines to the viewport rectangle — and writes one <svg> whose only children are
<path d="..." fill="...">, in the root viewBox's own coordinates. The artwork is unchanged: curves are
flattened to a tenth of a unit on a 914-unit artboard, far finer than 186 pt of print can show.

Run it again when the brand kit's logo changes.
"""

import io
import math
import os
import re

from svgelements import SVG, Path, Shape

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(ROOT, 'brand', 'kit', 'logo', 'jbs-praxis-horizontal-colour.svg')
DST = os.path.join(ROOT, 'praxis_core', 'resources', 'brand', 'letterhead-logo.svg')
TOLERANCE = 0.1

NESTED = re.compile(
    r'<svg\s+x="([\d.-]+)"\s+y="([\d.-]+)"\s+width="([\d.]+)"\s+height="([\d.]+)"\s+viewBox="([\d.\s-]+)">'
)


def flatten(segment):
    """A segment as a list of points, curves sampled finely enough to stay smooth at any print size."""
    start, end = segment.start, segment.end
    if segment.__class__.__name__ in ('Line', 'Close'):
        return [(end.x, end.y)]
    try:
        length = segment.length(error=1e-3)
    except Exception:
        length = abs(complex(end.x, end.y) - complex(start.x, start.y))
    steps = max(2, min(60, int(math.ceil(length / TOLERANCE))))
    return [(segment.point(i / steps).x, segment.point(i / steps).y) for i in range(1, steps + 1)]


def clip(points, rect):
    """Sutherland-Hodgman against an axis-aligned rectangle (x0, y0, x1, y1)."""
    x0, y0, x1, y1 = rect
    edges = [
        (lambda p: p[0] >= x0, lambda a, b: (x0, a[1] + (b[1] - a[1]) * (x0 - a[0]) / (b[0] - a[0]))),
        (lambda p: p[0] <= x1, lambda a, b: (x1, a[1] + (b[1] - a[1]) * (x1 - a[0]) / (b[0] - a[0]))),
        (lambda p: p[1] >= y0, lambda a, b: (a[0] + (b[0] - a[0]) * (y0 - a[1]) / (b[1] - a[1]), y0)),
        (lambda p: p[1] <= y1, lambda a, b: (a[0] + (b[0] - a[0]) * (y1 - a[1]) / (b[1] - a[1]), y1)),
    ]
    for inside, cross in edges:
        if not points:
            return []
        out = []
        previous = points[-1]
        for point in points:
            if inside(point):
                if not inside(previous):
                    out.append(cross(previous, point))
                out.append(point)
            elif inside(previous):
                out.append(cross(previous, point))
            previous = point
        points = out
    return points


def subpaths(path):
    """Each closed outline of a path, as a list of points."""
    out, current = [], []
    for segment in path.segments():
        name = segment.__class__.__name__
        if name == 'Move':
            if len(current) > 2:
                out.append(current)
            current = [(segment.end.x, segment.end.y)]
        elif name == 'Close':
            if len(current) > 2:
                out.append(current)
            current = []
        else:
            current.extend(flatten(segment))
    if len(current) > 2:
        out.append(current)
    return out


def number(value):
    return ('%.3f' % value).rstrip('0').rstrip('.')


def main():
    source = io.open(SRC, encoding='utf-8').read()
    root = re.search(r'<svg[^>]*viewBox="([\d.\s-]+)"', source).group(1).split()
    width, height = float(root[2]), float(root[3])

    parts = []
    for match in NESTED.finditer(source):
        x, y, w, h = (float(match.group(i)) for i in range(1, 5))
        inner = source[match.end():source.index('</svg>', match.end())]
        standalone = (
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            f'width="{w}" height="{h}" viewBox="{match.group(5)}">{inner}</svg>'
        )
        svg = SVG.parse(io.StringIO(standalone), width=w, height=h, ppi=96)
        for element in svg.elements():
            if not isinstance(element, (Path, Shape)):
                continue
            path = Path(element)
            fill = element.fill
            colour = fill.hexrgb if fill is not None and fill.value is not None else '#0C142B'
            rule = (element.values.get('fill-rule') or '').lower()
            pieces = []
            for points in subpaths(path):
                # The nested viewport's own box, in the root's coordinates, is both the placement and the crop
                moved = [(px + x, py + y) for px, py in points]
                kept = clip(moved, (x, y, x + w, y + h))
                if len(kept) > 2:
                    pieces.append('M' + ' '.join(number(px) + ',' + number(py) for px, py in kept) + 'Z')
            if pieces:
                attribute = ' fill-rule="evenodd"' if rule == 'evenodd' else ''
                parts.append('<path d="%s" fill="%s"%s/>' % (''.join(pieces), colour, attribute))

    out = (
        '<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 %s %s" '
        'role="img" aria-label="JBS Praxis"><title>JBS Praxis</title>%s</svg>'
        % (number(width), number(height), number(width), number(height), ''.join(parts))
    )
    io.open(DST, 'w', encoding='utf-8', newline='\n').write(out)
    print('wrote %s: %d paths, %d bytes' % (DST, len(parts), len(out)))


if __name__ == '__main__':
    main()
