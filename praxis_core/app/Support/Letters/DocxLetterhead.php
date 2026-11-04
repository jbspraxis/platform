<?php

namespace App\Support\Letters;

/**
 * The blank letterhead as a Word document (docs/DOCUMENTS.md section 6), for the rare document somebody would
 * rather type in Word: the same header and footer as the PDF, in a real header and footer, so they repeat on
 * every page and the typing stays in the body.
 *
 * There is no Word library in vendor and none is wanted (the live server has no Composer: docs/PWA.md), so the
 * file is written here. A .docx is a zip of XML parts; the zip is written by hand with deflate and crc32, which
 * every PHP has, rather than the zip extension, which the live PHP may not.
 *
 * It is built from the legal entity each time it is asked for, so it is never out of date.
 */
final class DocxLetterhead
{
    public static function build(array $company): string
    {
        $details = Letterhead::details($company);
        $logo = (string) file_get_contents(Letterhead::logoPngPath());
        [$logoWidth, $logoHeight] = self::pngSize($logo);

        // 186pt wide, as on the PDF; a Word EMU is 1/914400 inch
        $widthEmu = (int) round(186 / 72 * 914400);
        $heightEmu = (int) round($widthEmu * $logoHeight / max(1, $logoWidth));

        $files = [
            '[Content_Types].xml' => self::contentTypes(),
            '_rels/.rels' => self::rootRels(),
            'word/document.xml' => self::document(),
            'word/_rels/document.xml.rels' => self::documentRels(),
            'word/styles.xml' => self::styles(),
            'word/header1.xml' => self::header($details, $widthEmu, $heightEmu),
            'word/_rels/header1.xml.rels' => self::headerRels(),
            'word/footer1.xml' => self::footer($details),
            'word/media/logo.png' => $logo,
        ];

        return self::zip($files);
    }

    public static function filename(array $company): string
    {
        $name = preg_replace('/[^A-Za-z0-9 ]+/', '', (string) ($company['legal_name'] ?? 'JBS Praxis'));

        return trim($name) . ' letterhead.docx';
    }

    // ------------------------------------------------------------------ the parts

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private static function documentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
            . '</Relationships>';
    }

    private static function headerRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/>'
            . '</Relationships>';
    }

    /** Body text in a sans face at 10pt, the colour the letterhead uses */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="4A5468"/><w:sz w:val="20"/>'
            . '</w:rPr></w:rPrDefault></w:docDefaults>'
            . '</w:styles>';
    }

    /** One empty paragraph, then the section: A4, the letterhead's margins, and the header and footer */
    private static function document(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . '<w:p/>'
            . '<w:sectPr>'
            . '<w:headerReference w:type="default" r:id="rId2" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
            . '<w:footerReference w:type="default" r:id="rId3" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
            // A4 is 11906 by 16838 twentieths of a point; margins match the PDF (56pt sides, 48pt top, 86pt bottom)
            . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1760" w:right="1120" w:bottom="1720" w:left="1120" w:header="680" w:footer="560" w:gutter="0"/>'
            . '</w:sectPr>'
            . '</w:body></w:document>';
    }

    private static function header(array $details, int $widthEmu, int $heightEmu): string
    {
        $office = array_filter(array_merge(
            [$details['name']],
            $details['office_lines'],
            [$details['phone'], $details['email']]
        ));
        $first = array_shift($office);

        $right = '<w:p><w:pPr><w:jc w:val="right"/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>'
            . self::run($first, 18, '0C142B', bold: false, font: 'Cambria');
        foreach ($office as $line) {
            $right .= '<w:br/>' . self::run($line, 15, '5E6679');
        }
        $right .= '</w:p>';

        $logo = '<w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:drawing>'
            . '<wp:inline distT="0" distB="0" distL="0" distR="0" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            . '<wp:extent cx="' . $widthEmu . '" cy="' . $heightEmu . '"/><wp:docPr id="1" name="Logo"/>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="1" name="Logo"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="rIdLogo" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
            . '<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';

        $row = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>'
            . '<w:tblBorders><w:bottom w:val="single" w:sz="12" w:color="0C8B9B"/></w:tblBorders>'
            . '<w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="4600"/><w:gridCol w:w="5066"/></w:tblGrid>'
            . '<w:tr><w:tc><w:tcPr><w:tcW w:w="4600" w:type="dxa"/></w:tcPr>' . $logo . '</w:tc>'
            . '<w:tc><w:tcPr><w:tcW w:w="5066" w:type="dxa"/></w:tcPr>' . $right . '</w:tc></w:tr></w:tbl>'
            . '<w:p><w:pPr><w:spacing w:after="0" w:line="120" w:lineRule="exact"/></w:pPr></w:p>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' . $row . '</w:hdr>';
    }

    private static function footer(array $details): string
    {
        $paragraph = '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/>'
            . '<w:pBdr><w:top w:val="single" w:sz="8" w:color="0C8B9B" w:space="4"/></w:pBdr></w:pPr>'
            . self::run($details['footer_legal'], 14, '5E6679')
            . '<w:br/>' . self::run($details['footer_contact'], 14, '5E6679')
            . '</w:p>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' . $paragraph . '</w:ftr>';
    }

    /** $size is half-points, as Word counts them */
    private static function run(string $text, int $size, string $colour, bool $bold = false, ?string $font = null): string
    {
        return '<w:r><w:rPr>'
            . ($font ? '<w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '"/>' : '')
            . ($bold ? '<w:b/>' : '')
            . '<w:color w:val="' . $colour . '"/><w:sz w:val="' . $size . '"/></w:rPr>'
            . '<w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
    }

    /** A PNG says how big it is in the IHDR chunk, which is always the first one */
    private static function pngSize(string $png): array
    {
        $header = unpack('Nwidth/Nheight', substr($png, 16, 8));

        return [(int) ($header['width'] ?? 1), (int) ($header['height'] ?? 1)];
    }

    // ------------------------------------------------------------------ the zip

    /**
     * A zip file, written here rather than with the zip extension, which the live PHP may not have. Deflate and
     * crc32 are part of PHP itself. No directory entries and no extra fields: Word asks for nothing more.
     *
     * @param  array<string, string>  $files  path inside the zip => its bytes
     */
    private static function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        // A fixed MS-DOS date and time (1 January 2026, 00:00), so the file is the same bytes every time it is
        // built and Word never sees the zero date, which is not a date at all
        $time = 0;
        $date = ((2026 - 1980) << 9) | (1 << 5) | 1;

        foreach ($files as $path => $contents) {
            $crc = crc32($contents);
            $uncompressed = strlen($contents);
            $deflated = gzdeflate($contents, 9);
            // A file that deflate makes no smaller is stored as it is
            $compress = $deflated !== false && strlen($deflated) < $uncompressed;
            $data = $compress ? $deflated : $contents;
            $method = $compress ? 8 : 0;
            $name = $path;

            $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, $time, $date, $crc, strlen($data), $uncompressed, strlen($name), 0) . $name;
            $local .= $header . $data;

            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, $time, $date, $crc, strlen($data), $uncompressed,
                strlen($name), 0, 0, 0, 0, 0, $offset) . $name;

            $offset += strlen($header) + strlen($data);
        }

        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);

        return $local . $central . $end;
    }
}
