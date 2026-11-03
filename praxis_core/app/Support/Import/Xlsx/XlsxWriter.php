<?php

namespace App\Support\Import\Xlsx;

/**
 * Writes a small .xlsx workbook (the import template and the "fix these rows" file) in plain PHP: text cells only,
 * a few named styles, column widths, a frozen header row and drop-down lists.
 *
 *   $book = new XlsxWriter();
 *   $book->sheet('Leads', [[['v' => 'Organisation name *', 's' => 'required'], 'Country *'], ['Kilo Ltd', 'Nigeria']],
 *       ['widths' => [28, 18], 'freeze' => 1, 'text' => [0, 1], 'lists' => [['range' => 'B2:B1001', 'source' => '"Nigeria,Ghana"', 'strict' => false]]]);
 *   $bytes = $book->build();
 *
 * A cell is a string (or null for empty) or ['v' => text, 's' => style]; styles are the keys of STYLES.
 */
final class XlsxWriter
{
    /** Style name => index in styles.xml's cellXfs (built below in the same order) */
    public const STYLES = [
        'default' => 0, 'title' => 1, 'header' => 2, 'required' => 3, 'oneof' => 4, 'text' => 5, 'error' => 6, 'wrap' => 7, 'bold' => 8,
        'muted' => 9, 'errorwrap' => 10,
    ];

    /** @var array<int, array{name: string, rows: array, options: array}> */
    private array $sheets = [];

    /**
     * $options: widths (column widths, in characters, from column A), freeze (rows kept in view, e.g. 1 for the
     * header), text (columns formatted as text, so 0803… keeps its 0 and +234… its +), lists (drop-downs:
     * range, source as a quoted list "a,b" or a range like 'Guide'!$G$2:$G$20, strict = only those values).
     */
    public function sheet(string $name, array $rows, array $options = []): self
    {
        $this->sheets[] = ['name' => mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name), 0, 31), 'rows' => $rows, 'options' => $options];

        return $this;
    }

    public function build(): string
    {
        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
        ];
        foreach ($this->sheets as $i => $sheet) {
            $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->worksheet($sheet, $i === 0);
        }

        return Zip::build($files);
    }

    /** "A", "B" … "AA" for a column index from 0 */
    public static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }

    /** A sheet name as formulas write it: 'How to fill it in' */
    public static function sheetRef(string $name): string
    {
        return "'" . str_replace("'", "''", $name) . "'";
    }

    private function worksheet(array $sheet, bool $first): string
    {
        $o = $sheet['options'];
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        $xml .= '<sheetViews><sheetView workbookViewId="0"' . ($first ? ' tabSelected="1"' : '') . '>';
        if (! empty($o['freeze'])) {
            $xml .= '<pane ySplit="' . (int) $o['freeze'] . '" topLeftCell="A' . ((int) $o['freeze'] + 1) . '" activePane="bottomLeft" state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';

        $widths = $o['widths'] ?? [];
        $text = array_flip($o['text'] ?? []);
        $count = max(count($widths), $text ? max(array_keys($text)) + 1 : 0);
        if ($count) {
            $xml .= '<cols>';
            for ($i = 0; $i < $count; $i++) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) ($widths[$i] ?? 14) . '" customWidth="1"'
                    . (isset($text[$i]) ? ' style="' . self::STYLES['text'] . '"' : '') . '/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $xml .= '<row r="' . ($r + 1) . '">';
            ksort($row); // a row may be sparse: its keys are column indexes
            foreach ($row as $c => $cell) {
                $value = is_array($cell) ? ($cell['v'] ?? null) : $cell;
                $style = self::STYLES[is_array($cell) ? ($cell['s'] ?? 'default') : 'default'] ?? 0;
                if (($value === null || $value === '') && ! $style && ! isset($text[$c])) {
                    continue;
                }
                $style = $style ?: (isset($text[$c]) ? self::STYLES['text'] : 0);
                $ref = self::column($c) . ($r + 1);
                $xml .= $value === null || $value === ''
                    ? '<c r="' . $ref . '" s="' . $style . '"/>'
                    : '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::escape((string) $value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if (! empty($o['lists'])) {
            $xml .= '<dataValidations count="' . count($o['lists']) . '">';
            foreach ($o['lists'] as $list) {
                $strict = ! empty($list['strict']);
                $xml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="' . ($strict ? 1 : 0) . '"'
                    . ($strict ? ' errorTitle="Choose from the list" error="' . self::escape($list['error'] ?? 'Choose one of the values in the list.') . '"' : '')
                    . ' sqref="' . $list['range'] . '"><formula1>' . self::escape($list['source']) . '</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }

        return $xml . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
    }

    private function workbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<sheet name="' . self::escape($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return $xml . '</sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }

        return $xml . '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function contentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml . '</Types>';
    }

    /** Fonts, fills and the cellXfs in STYLES' order: brand ink and teal, and Excel's own red for cells to fix */
    private function styles(): string
    {
        $font = fn (string $extra = '') => '<font>' . $extra . '<sz val="11"/><name val="Calibri"/><family val="2"/></font>';
        $fonts = [
            $font(),                                    // 0 normal
            $font('<b/>'),                              // 1 bold
            '<font><b/><sz val="14"/><color rgb="FF0C142B"/><name val="Calibri"/><family val="2"/></font>', // 2 title
            $font('<b/><color rgb="FFFFFFFF"/>'),       // 3 bold white
            $font('<color rgb="FF9C0006"/>'),           // 4 dark red
            $font('<color rgb="FF5E6679"/>'),           // 5 muted
        ];
        $fill = fn (string $rgb) => '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        $fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>',
            $fill('F2F5F7'), $fill('044B6A'), $fill('E3F2F3'), $fill('FFC7CE')];
        $xf = fn (int $font = 0, int $fill = 0, int $fmt = 0, bool $wrap = false) => '<xf numFmtId="' . $fmt . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="0" xfId="0"'
            . ($font ? ' applyFont="1"' : '') . ($fill ? ' applyFill="1"' : '') . ($fmt ? ' applyNumberFormat="1"' : '')
            . ($wrap ? ' applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' : '/>');
        $cellXfs = [
            $xf(),                  // default
            $xf(2),                 // title
            $xf(1, 2),              // header
            $xf(3, 3),              // required
            $xf(1, 4),              // oneof
            $xf(0, 0, 49),          // text
            $xf(4, 5, 49),          // error
            $xf(0, 0, 0, true),     // wrap
            $xf(1),                 // bold
            $xf(5, 0, 0, true),     // muted
            $xf(4, 5, 49, true),    // errorwrap
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
            . '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($cellXfs) . '">' . implode('', $cellXfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function escape(string $value): string
    {
        // Characters XML 1.0 doesn't allow (a stray control character pasted from somewhere) are dropped
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
