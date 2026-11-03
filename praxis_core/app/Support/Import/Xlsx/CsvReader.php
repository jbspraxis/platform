<?php

namespace App\Support\Import\Xlsx;

use App\Support\Import\ImportFailed;

/**
 * A CSV file read as one sheet, the same shape XlsxReader gives. Commas or semicolons (French Excel saves with
 * semicolons), UTF-8 with or without its mark, or the Windows encoding older Excel uses.
 */
final class CsvReader
{
    /** @return array<int, array{name: string, rows: array<int, array<int, string>>}> */
    public static function read(string $path, string $name, int $maxRows): array
    {
        $text = (string) file_get_contents($path);
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        } elseif (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        if (str_contains($text, "\0")) {
            throw new ImportFailed('This file isn’t a CSV we can read. Save it as .xlsx (Excel workbook) or CSV and upload it again.');
        }
        $firstLine = strtok($text, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        $rows = [];
        $number = 0;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $number++;
            $kept = [];
            foreach ($cells as $i => $cell) {
                $cell = trim(str_replace("\u{00A0}", ' ', (string) $cell));
                if ($cell !== '') {
                    $kept[$i] = $cell;
                }
            }
            if ($kept) {
                $rows[$number] = $kept;
                if (count($rows) > $maxRows + 5) {
                    fclose($handle);
                    throw new ImportFailed('This file has more than ' . number_format($maxRows) . ' rows. Split it into smaller files and import them one by one.');
                }
            }
        }
        fclose($handle);

        return [['name' => $name, 'rows' => $rows]];
    }
}
