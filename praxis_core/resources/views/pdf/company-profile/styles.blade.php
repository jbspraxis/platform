@php
    use App\Support\CompanyProfile\Assets;
    use App\Support\CompanyProfile\Layout;
@endphp
<style>
    /*
      Palette, type and the signature device, all from docs/BRAND.md. Sizes are in points, because this
      is a printed page: body text is 9.3/14.5, which gives about 62 characters across five columns.

      Newsreader has an optical size axis, so it is embedded as three cuts and each one is used only at
      the size it was cut for: Display above 36pt, Headline from 17 to 30, Text from 9 to 13.
    */
    @page { margin: 0; }

    @foreach (Assets::fonts() as $font)
    @font-face {
        font-family: '{{ $font['family'] }}';
        src: url('{{ Assets::font($font['file']) }}') format('truetype');
        font-weight: {{ $font['weight'] }};
        font-style: {{ $font['style'] }};
    }
    @endforeach

    html, body { margin: 0; padding: 0; }

    body {
        font-family: 'Jakarta';
        font-size: 9.3pt;
        line-height: 14.5pt;
        color: #4A5468;                                  /* Slate: body text where Ink is too strong */
        font-variant-numeric: lining-nums proportional-nums;
    }

    /* A page, exactly A4 landscape. A sheet even a point larger overflows and dompdf starts a second
       page for the overhang, so the book comes out at twice its length. */
    .sheet {
        width: {{ Layout::PAGE_WIDTH }}pt;
        height: {{ Layout::PAGE_HEIGHT }}pt;
        position: relative;
        page-break-after: always;
    }
    .sheet.last { page-break-after: auto; }
    .surface-ink { background-color: #0C142B; }

    .pad {
        padding: {{ Layout::MARGIN_TOP }}pt {{ Layout::MARGIN_X }}pt {{ Layout::MARGIN_BOTTOM }}pt {{ Layout::MARGIN_X }}pt;
        width: {{ Layout::CONTENT_WIDTH }}pt;
    }

    /*
      Columns are inline blocks, not table cells. dompdf ignores a cell's declared width — with
      table-layout: fixed, with a colgroup, with either — and sets every column of a row to the same
      width, which quietly throws out the grid. An inline block is placed at exactly the width it is
      given. The row sets font-size to 0 so the whitespace between the blocks in this markup carries no
      width of its own, and each column sets its own size back.
    */
    .cols { width: {{ Layout::CONTENT_WIDTH }}pt; font-size: 0; line-height: 0; }
    .col { display: inline-block; vertical-align: top; font-size: 9.3pt; line-height: 14.5pt; }
    .gap { display: inline-block; width: {{ Layout::GUTTER }}pt; height: 1pt; font-size: 0; line-height: 0; }

    /* Type */
    .display {
        font-family: 'Newsreader Display';
        font-weight: 400;
        font-size: 46pt;
        line-height: 52pt;
        letter-spacing: -0.46pt;
        color: #0C142B;
        margin: 0;
    }
    .h1 {
        font-family: 'Newsreader Headline';
        font-weight: 500;
        font-size: 26pt;
        line-height: 31pt;
        color: #0C142B;
        margin: 0;
    }
    .display-sm {
        font-family: 'Newsreader Display';
        font-weight: 400;
        font-size: 34pt;
        line-height: 41pt;
        letter-spacing: -0.34pt;
        color: #0C142B;
        margin: 0;
    }
    .display-md {
        font-family: 'Newsreader Display';
        font-weight: 400;
        font-size: 27pt;
        line-height: 34pt;
        letter-spacing: -0.27pt;
        color: #0C142B;
        margin: 0;
    }
    .statement {
        font-family: 'Newsreader Headline';
        font-weight: 500;
        font-size: 19pt;
        line-height: 27pt;
        color: #0C142B;
        margin: 0;
    }
    .h3 {
        font-family: 'Newsreader Text';
        font-weight: 400;
        font-size: 12.5pt;
        line-height: 16.5pt;
        color: #0C142B;
        margin: 0;
    }
    .serif {
        font-family: 'Newsreader Text';
        font-weight: 400;
        font-size: 10.5pt;
        line-height: 15pt;
        color: #0C142B;
        margin: 0;
    }
    .lead { font-size: 11pt; line-height: 17pt; margin: 0; }
    .body { font-size: 9.3pt; line-height: 14.5pt; margin: 0; }
    .small { font-size: 8.6pt; line-height: 13pt; margin: 0; }
    .meta { font-size: 7.4pt; line-height: 10.5pt; color: #5E6679; margin: 0; }

    /* A small label is sentence case at weight 600, never tracked capitals (BRAND.md section 8). */
    .label { font-size: 8pt; line-height: 12pt; font-weight: 600; color: #08707C; margin: 0; }
    .muted { color: #5E6679; }
    .ink-text { color: #0C142B; }

    /* On Ink: secondary text is a solid colour, never opacity. */
    .surface-ink .h1, .surface-ink .display, .surface-ink .h3, .surface-ink .statement { color: #FFFFFF; }
    .surface-ink .body, .surface-ink .small, .surface-ink .lead { color: #BBBDC4; }
    .surface-ink .label { color: #0BB8C4; }
    .surface-ink .meta { color: #9AA1B2; }

    /*
      The Praxis rule, the one device in the book. Horizontal only: a 2pt rule that is Ink to the left of
      the split and Teal to the right of it, with the split on the column where the answer starts. It
      appears once or twice on a page, never as a pattern.
    */
    .rule { font-size: 0; line-height: 0; height: 2pt; }
    .rule span { display: inline-block; height: 2pt; vertical-align: top; }
    .rule .left { background-color: #0C142B; }
    .rule .right { background-color: #0C8B9B; }
    .surface-ink .rule .left { background-color: #FFFFFF; }
    .surface-ink .rule .right { background-color: #0BB8C4; }

    /* The hairline that separates sections in place of a box. */
    .hair { font-size: 0; line-height: 0; height: 0.5pt; background-color: #C5CCD6; width: 100%; }
    .surface-ink .hair { background-color: #2E3549; }

    /* A Paper panel is the only thing that goes on top of a photograph. */
    .panel { position: absolute; background-color: #FFFFFF; }

    .bleed { position: absolute; left: 0; top: 0; }
</style>
