<?php

namespace App\Support\Import\Xlsx;

use App\Support\Import\ImportFailed;
use DOMDocument;
use DOMElement;

/**
 * Reads the cells of every sheet of an .xlsx workbook as text, with DOM only (the live PHP has it; no package).
 * Shared and inline strings, numbers (2348031234567 stays whole, never 2.3E+12), TRUE/FALSE, and dates (cells
 * formatted as dates come back as 2026-11-03) are handled; formulas give their last calculated value.
 */
final class XlsxReader
{
    private const RELS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Built-in number formats that are dates or times */
    private const DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47];

    /**
     * Every sheet in workbook order: ['name' => …, 'rows' => [excel row number => [column index from 0 => text]]].
     * Rows with nothing in them are left out; more than $maxRows filled rows in one sheet stops the reading.
     *
     * @throws ImportFailed
     */
    public static function read(string $path, int $maxRows): array
    {
        $zip = Zip::open((string) file_get_contents($path), $path);
        $workbook = self::xml($zip->get('xl/workbook.xml') ?? throw new ImportFailed('This file isn’t an Excel workbook we can read. Save it as .xlsx (Excel workbook) or CSV and upload it again.'));
        $targets = self::relationships($zip->get('xl/_rels/workbook.xml.rels'));
        $date1904 = false;
        foreach (self::children($workbook, 'workbookPr') as $pr) {
            $date1904 = in_array(strtolower($pr->getAttribute('date1904')), ['1', 'true'], true);
        }

        $strings = self::sharedStrings($zip, $targets);
        $dateStyles = self::dateStyles($zip, $targets);

        $sheets = [];
        foreach (self::children($workbook, 'sheet') as $sheet) {
            $target = $targets[$sheet->getAttributeNS(self::RELS, 'id')] ?? null;
            $xml = $target ? $zip->get($target) : null;
            if ($xml === null) {
                continue;
            }
            $sheets[] = ['name' => $sheet->getAttribute('name'), 'rows' => self::rows(self::xml($xml), $strings, $dateStyles, $date1904, $maxRows)];
        }

        return $sheets;
    }

    private static function rows(DOMDocument $doc, array $strings, array $dateStyles, bool $date1904, int $maxRows): array
    {
        $rows = [];
        $next = 1;
        foreach (self::children($doc, 'row') as $row) {
            $number = (int) $row->getAttribute('r') ?: $next;
            $next = $number + 1;
            $cells = [];
            $column = 0;
            foreach ($row->childNodes as $c) {
                if (! $c instanceof DOMElement || $c->localName !== 'c') {
                    continue;
                }
                $ref = $c->getAttribute('r');
                $column = $ref !== '' ? self::columnIndex($ref) : $column;
                $value = self::cell($c, $strings, $dateStyles, $date1904);
                if ($value !== '') {
                    $cells[$column] = $value;
                }
                $column++;
            }
            if ($cells) {
                $rows[$number] = $cells;
                if (count($rows) > $maxRows + 5) {
                    throw new ImportFailed('This file has more than ' . number_format($maxRows) . ' rows. Split it into smaller files and import them one by one.');
                }
            }
        }

        return $rows;
    }

    private static function cell(DOMElement $c, array $strings, array $dateStyles, bool $date1904): string
    {
        $type = $c->getAttribute('t');
        $v = null;
        $inline = '';
        foreach ($c->childNodes as $n) {
            if ($n instanceof DOMElement && $n->localName === 'v') {
                $v = $n->textContent;
            } elseif ($n instanceof DOMElement && $n->localName === 'is') {
                $inline = self::text($n);
            }
        }

        $value = match ($type) {
            's' => $strings[(int) $v] ?? '',
            'inlineStr' => $inline,
            'b' => $v === null ? '' : ((int) $v ? 'TRUE' : 'FALSE'),
            'e' => '',
            'str' => (string) $v,
            default => $v === null ? '' : self::number($v, isset($dateStyles[(int) $c->getAttribute('s')]), $date1904),
        };

        return trim(str_replace("\u{00A0}", ' ', $value));
    }

    /** 2348031234567 and 2.348031234567E+12 both give "2348031234567"; 0.30000000000000004 gives "0.3"; a date cell gives Y-m-d */
    private static function number(string $v, bool $isDate, bool $date1904): string
    {
        if (! is_numeric($v)) {
            return $v;
        }
        $f = (float) $v;
        if ($isDate && $f > 0 && $f < 2958466) {
            $base = $date1904 ? 24107 : 25569; // days from the sheet's day 0 to 1 January 1970
            $seconds = (int) round(($f - $base) * 86400);
            $day = gmdate('Y-m-d', $seconds);

            return fmod($f, 1.0) > 0.00001 ? $day . ' ' . gmdate('H:i', $seconds) : $day;
        }
        if (floor($f) === $f && abs($f) < 1e15) {
            return sprintf('%.0f', $f);
        }

        return (string) $f;
    }

    /** "AB12" → 27 */
    private static function columnIndex(string $ref): int
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $ref));
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max($n - 1, 0);
    }

    private static function sharedStrings(Zip $zip, array $targets): array
    {
        $path = $targets['@sharedStrings'] ?? 'xl/sharedStrings.xml';
        $xml = $zip->get($path);
        if ($xml === null) {
            return [];
        }
        $strings = [];
        foreach (self::children(self::xml($xml), 'si') as $si) {
            $strings[] = self::text($si);
        }

        return $strings;
    }

    /** The text of a string item: its <t>, or its rich-text runs' <t> joined (phonetic guides left out) */
    private static function text(DOMElement $node): string
    {
        $out = '';
        foreach ($node->getElementsByTagNameNS('*', 't') as $t) {
            if ($t->parentNode instanceof DOMElement && $t->parentNode->localName === 'rPh') {
                continue;
            }
            $out .= $t->textContent;
        }

        return $out;
    }

    /** Style indexes (cellXfs) whose number format is a date or a time: index => true */
    private static function dateStyles(Zip $zip, array $targets): array
    {
        $xml = $zip->get($targets['@styles'] ?? 'xl/styles.xml');
        if ($xml === null) {
            return [];
        }
        $doc = self::xml($xml);
        $custom = [];
        foreach (self::children($doc, 'numFmt') as $fmt) {
            $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $fmt->getAttribute('formatCode'));
            $custom[(int) $fmt->getAttribute('numFmtId')] = (bool) preg_match('/[dmyhs]/i', $code);
        }
        $dates = [];
        foreach (self::children($doc, 'cellXfs') as $xfs) {
            $i = 0;
            foreach ($xfs->childNodes as $xf) {
                if (! $xf instanceof DOMElement || $xf->localName !== 'xf') {
                    continue;
                }
                $id = (int) $xf->getAttribute('numFmtId');
                if (in_array($id, self::DATE_FORMATS, true) || ($custom[$id] ?? false)) {
                    $dates[$i] = true;
                }
                $i++;
            }
        }

        return $dates;
    }

    /** Relationship id => entry path inside the zip; '@sharedStrings' and '@styles' point at those parts */
    private static function relationships(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $targets = [];
        foreach (self::children(self::xml($xml), 'Relationship') as $rel) {
            $target = $rel->getAttribute('Target');
            $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            $path = preg_replace('#/[^/]+/\.\./#', '/', $path);
            $targets[$rel->getAttribute('Id')] = $path;
            $type = $rel->getAttribute('Type');
            if (str_ends_with($type, '/sharedStrings')) {
                $targets['@sharedStrings'] = $path;
            } elseif (str_ends_with($type, '/styles')) {
                $targets['@styles'] = $path;
            }
        }

        return $targets;
    }

    /** @return iterable<DOMElement> every element with this local name, whatever its namespace prefix */
    private static function children(DOMDocument|DOMElement $node, string $name): iterable
    {
        return $node->getElementsByTagNameNS('*', $name);
    }

    private static function xml(string $xml): DOMDocument
    {
        // No document types (no entities to expand) and nothing fetched from the network
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new ImportFailed('This file isn’t an Excel workbook we can read. Save it as .xlsx (Excel workbook) or CSV and upload it again.');
        }
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $ok) {
            throw new ImportFailed('Part of this workbook is damaged. Open it in Excel, save it again as .xlsx, and upload it again.');
        }

        return $doc;
    }
}
