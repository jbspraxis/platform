# Company profile: the files the pages place

Everything here is built by `tools/company-profile/build_assets.py` and committed. The server never runs
that script; it only reads these files.

```
python tools/company-profile/build_assets.py
```

## Why everything is a JPEG

The live web PHP has no `gd` (`docs/DOCUMENTS.md` section 3). Without it:

- dompdf **cannot read a PNG at all** — `Cpdf::addPngFromFile()` returns before drawing when
  `imagecreatefrompng()` is missing;
- a CSS `background-image` throws outright — `AbstractRenderer::_background_image()` needs
  `imagecreatetruecolor()`;
- a **JPEG** needs nothing but `getimagesize()`, which is core PHP, and an **SVG** is drawn by
  php-svg-lib.

So every raster image here is a JPEG, placed as an `<img>`, and no page carries a CSS background image.
A PNG dropped into `logos/` will not appear in the PDF.

## What is in here

| Folder | What | Notes |
|---|---|---|
| `content/` | The approved copy, English and French | The lead chat's `content.{en,fr}.json`. When PR 1's seeder lands at `database/seeders/company-profile/`, point `company-profile:preview` there and delete this folder. |
| `fonts/` | Seven static cuts of Newsreader and Plus Jakarta Sans, and their OFL licences | php-font-lib reads a static TTF, not a woff2 and not a variable axis. Newsreader has an optical size axis, so it is cut three times: Display for 36pt and up, Headline for 17 to 30, Text for 9 to 13. |
| `img/` | Place photography, cut to the page it bleeds across | Cut at build time so no page ever squashes or crops an image. |
| `screens/` | Each client's own platform sign-in screen | From `praxis_site/public/img/screens`, already public on the website, and sign-in screens, so none carries a client's records. 2:1 as captured; nothing is cropped. |
| `logos/` | Client logos, greyscale on Paper | The wall reads as one wall rather than fifteen brands shouting. Sized by area, not height, so a wide wordmark does not drown a square mark. |
| `brand/` | The logo lockups | JPEG renders from the brand kit's PNGs. The kit's SVGs are an automatic trace with nested `<svg>` and `<use>`, which php-svg-lib draws only partly — the wordmark disappears — so they are not used. |
| `device/` | The bar stack | The one piece of real SVG: plain polygons, which php-svg-lib is reliable at. Ends cut on the diagonal like the logo's, Ink left of the split and Teal right of it (`docs/BRAND.md` section 9). |

## Adding a client logo

Put the source `.webp` in `praxis_site/public/img/clients/`, run the build script, and the logo appears
on the wall. The wall falls back to this folder whenever the profile's own content carries no logos,
which is how it works until PR 3's logo library lands.
