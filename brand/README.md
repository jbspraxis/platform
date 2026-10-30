# JBS Praxis brand book and kit

| File | What it is |
|---|---|
| `index.html` | The brand book. One self-contained file: share it as it is. Its "Download PDF" and "Download the brand kit" buttons work offline because both files are embedded. |
| `JBS-Praxis-Brand-Book.pdf` | The same book on A4, for sales, marketing and partners. |
| `JBS-Praxis-Brand-Kit.zip` | Everything in `kit/`: logo versions, favicons, app icons, splash screens, email logo, social images. |
| `kit/` | The kit unzipped, for developers. |

The words come from `docs/BRAND.md`; that file is the source. To rebuild after changing it (Windows, needs Python, Node and Microsoft Edge):

```
python brand/build/build_kit.py
node brand/build/build_book.js
```

`build_kit.py` builds the logo versions from `public_html_PRAXIS/assets/img/logo_colour.svg` and renders the PNGs with headless Edge. `build_book.js` turns `docs/BRAND.md` into the HTML book, prints the PDF, then embeds the PDF and the kit into `index.html`. `pdf_sheet.js` renders PDF pages to a contact sheet for checking.

## The Praxis Method guide

The PDF the website's guide form emails (HQ attaches the edition in the visitor's language), in English and French:
`praxis_core/resources/pdf/the-praxis-method-en.pdf` and `the-praxis-method-fr.pdf`. The words and layout are in
`method/the-praxis-method.en.html`, `method/the-praxis-method.fr.html` and `method/method.css`; they follow the site's
How we work, Services and Security and data pages. To rebuild (needs Python with fontTools, Node and Microsoft Edge):

```
node brand/build/build_method.js
```

It makes static font instances (`method_fonts.py`), checks the words (banned terms, contractions, French spacing),
prints each edition and fails if text runs out of its box or the PDF falls back to another font. Look at the pages
with `node brand/build/pdf_sheet.js 1 4 2 600 praxis_core/resources/pdf/the-praxis-method-fr.pdf`.
