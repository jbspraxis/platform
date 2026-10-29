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
