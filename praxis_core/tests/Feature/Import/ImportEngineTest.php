<?php

namespace Tests\Feature\Import;

use App\Models\User;
use App\Support\Import\Analysis;
use App\Support\Import\ImportFailed;
use App\Support\Import\Importer;
use App\Support\Import\Xlsx\XlsxReader;
use App\Support\Import\Xlsx\XlsxWriter;
use App\Support\Import\Xlsx\Zip;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The shared import engine (docs/LEADS.md section 6, The shared import engine) on a made-up schema (TeamsSchema), with
 * no database: the zip and .xlsx layers in plain PHP, the template, reading files (Excel's own shapes, CSV), the
 * preview's statuses and red cells, importing each row of a file once, the file of rows to fix, and the kept uploads.
 */
class ImportEngineTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        TeamsSchema::$known = TeamsSchema::$people = TeamsSchema::$created = TeamsSchema::$added = [];
        $this->dir = sys_get_temp_dir() . '/import-engine-test-' . getmypid();
        @mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function file(string $name, string $bytes): string
    {
        file_put_contents($path = $this->dir . '/' . $name, $bytes);

        return $path;
    }

    /** A workbook written the way Excel writes one: shared strings, styles with a date format, sheets by relationship */
    private function excelWorkbook(array $sheets, array $strings = []): string
    {
        $wb = '<?xml version="1.0" encoding="UTF-8"?><x:workbook xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><x:sheets>';
        $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $files = [];
        $i = 0;
        foreach ($sheets as $name => $xml) {
            $i++;
            $wb .= '<x:sheet name="' . $name . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="/xl/worksheets/s' . $i . '.xml"/>';
            $files['xl/worksheets/s' . $i . '.xml'] = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xml . '</sheetData></worksheet>';
        }
        $rels .= '<Relationship Id="rS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rT" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $sst = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach ($strings as $s) {
            $sst .= str_starts_with($s, '<') ? '<si>' . $s . '</si>' : '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
        }
        $styles = '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy;@"/></numFmts>'
            . '<cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="164" applyNumberFormat="1"/><xf numFmtId="49"/></cellXfs></styleSheet>';

        return Zip::build(['xl/workbook.xml' => $wb . '</x:sheets></x:workbook>', 'xl/_rels/workbook.xml.rels' => $rels, 'xl/sharedStrings.xml' => $sst . '</sst>', 'xl/styles.xml' => $styles] + $files);
    }

    /** A filled template: the schema's own headers on a 'Teams' sheet */
    private function teamsFile(array $rows, array $headers = null): string
    {
        $headers ??= array_map(fn ($c) => $c->header(), (new TeamsSchema())->columns());

        return (new XlsxWriter())->sheet('Teams', [$headers, ...$rows])->build();
    }

    public function test_zip_files_round_trip_and_bad_ones_are_refused(): void
    {
        $zip = Zip::open(Zip::build(['a.txt' => str_repeat('Kilo ', 2000), 'dir/b.xml' => '<b>é</b>', 'empty' => '']));
        $this->assertSame(str_repeat('Kilo ', 2000), $zip->get('a.txt'));
        $this->assertSame('<b>é</b>', $zip->get('dir/b.xml'));
        $this->assertSame('', $zip->get('empty'));
        $this->assertNull($zip->get('missing'));

        // A zip made by ZipArchive (stored and deflated entries), where the zip extension exists (CI has it)
        if (class_exists(\ZipArchive::class)) {
            $path = $this->dir . '/archive.zip';
            $za = new \ZipArchive();
            $za->open($path, \ZipArchive::CREATE);
            $za->addFromString('stored.txt', 'plain');
            $za->setCompressionName('stored.txt', \ZipArchive::CM_STORE);
            $za->addFromString('packed.txt', str_repeat('x', 5000));
            $za->close();
            $read = Zip::open(file_get_contents($path), $path);
            $this->assertSame('plain', $read->get('stored.txt'));
            $this->assertSame(str_repeat('x', 5000), $read->get('packed.txt'));
        }

        $this->expectException(ImportFailed::class);
        Zip::open('not a zip at all');
    }

    public function test_the_template_has_a_guide_and_a_data_sheet(): void
    {
        $schema = new TeamsSchema();
        $path = $this->file('template.xlsx', Importer::template($schema));
        $sheets = XlsxReader::read($path, 100);

        $this->assertSame([Importer::GUIDE, 'Teams'], array_column($sheets, 'name'));
        $guide = collect($sheets[0]['rows'])->map(fn ($r) => implode(' | ', $r))->implode("\n");
        $this->assertStringContainsString('Import teams: how to fill in this file', $guide);
        $this->assertStringContainsString('Company * | Required | Rows with the same company are one team. | Kilo Ltd | Kilo?', $guide);
        $this->assertStringContainsString('Email + | At least one of the columns marked +', $guide);
        $this->assertStringContainsString('Name | Optional', $guide);
        $this->assertStringContainsString('Small team', $guide); // the drop-down's list
        $this->assertStringContainsString('Example team (guide only)', $guide);
        // Sheet 2: only the headers, required marked *, "one of" marked +
        $this->assertSame([1 => ['Company *', 'Name', 'Email +', 'Phone +', 'Size', 'Started']], $sheets[1]['rows']);

        // The data sheet's drop-down reads the guide's list; text columns keep their zeros
        $xml = Zip::open(file_get_contents($path))->get('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('<dataValidation type="list"', $xml);
        $this->assertStringContainsString('&apos;How to fill it in&apos;!$F$', $xml);
        $this->assertStringContainsString('sqref="E2:E1001"', $xml);
        $this->assertStringContainsString('<pane ySplit="1"', $xml);
        $this->assertMatchesRegularExpression('/<col min="1" max="1" width="20" customWidth="1" style="5"\/>/', $xml);

        // The guide's example rows are never data: an empty template has nothing to import
        try {
            Importer::analyse($schema, $path, 'template.xlsx');
            $this->fail('An empty template was read as data');
        } catch (ImportFailed $e) {
            $this->assertStringContainsString('has no rows under its headers yet', $e->getMessage());
        }
    }

    public function test_reading_what_excel_writes(): void
    {
        // Shared strings (one with rich text runs and a phonetic guide), inline strings, a formula's last value, a long
        // number, one in scientific notation, a date cell, TRUE/FALSE, gaps between cells, title rows above the headers,
        // the data sheet not first, a header given by its alias, and a column the schema doesn't have
        $strings = ['Organisation', 'Full name', 'e-mail', 'Phone', 'Size', 'Started', 'Notes', 'Kilo Ltd', '<r><t>Ada </t></r><r><rPr><b/></rPr><t>Obi</t></r><rPh><t>x</t></rPh>', 'Large team'];
        $data = '<row r="1"><c r="A1" t="inlineStr"><is><t>Our teams (export)</t></is></c></row>'
            . '<row r="3"><c r="A3" t="s"><v>0</v></c><c r="B3" t="s"><v>1</v></c><c r="C3" t="s"><v>2</v></c><c r="D3" t="s"><v>3</v></c><c r="E3" t="s"><v>4</v></c><c r="F3" t="s"><v>5</v></c><c r="H3" t="s"><v>6</v></c></row>'
            . '<row r="4"><c r="A4" t="s"><v>7</v></c><c r="B4" t="s"><v>8</v></c><c r="C4" t="str"><f>LOWER("ADA@KILO.TEST")</f><v>ada@kilo.test</v></c><c r="D4"><v>2348031234567</v></c><c r="E4" t="s"><v>9</v></c><c r="F4" s="1"><v>46329</v></c><c r="H4" t="b"><v>1</v></c></row>'
            . '<row r="5"><c r="A5" t="s"><v>7</v></c><c r="B5" t="inlineStr"><is><t> Bola  Eze </t></is></c><c r="D5"><v>2.348051234567E+12</v></c><c r="E5" t="inlineStr"><is><t>SMALL TEAM</t></is></c></row>'
            . '<row r="9"><c r="A9" s="2"/></row>';
        $path = $this->file('excel.xlsx', $this->excelWorkbook(['Read me' => '<row r="1"><c r="A1" t="inlineStr"><is><t>Hello</t></is></c></row>', 'Export' => $data], $strings));

        $a = Importer::analyse(new TeamsSchema(), $path, 'excel.xlsx');
        $this->assertSame('Export', $a->sheet);
        $this->assertSame(['Notes'], $a->ignored);
        $this->assertSame([4, 5], array_keys($a->rows));
        $this->assertSame(['company' => 'Kilo Ltd', 'name' => 'Ada Obi', 'email' => 'ada@kilo.test', 'phone' => '2348031234567', 'size' => 'large', 'started' => '2026-11-03'], $a->rows[4]['values']);
        $this->assertSame('Bola Eze', $a->rows[5]['values']['name']);
        $this->assertSame('2348051234567', $a->rows[5]['values']['phone']);
        $this->assertSame('small', $a->rows[5]['values']['size']); // a choice's label, in any case, reads as its value
        $this->assertSame(['ready', 'ready'], array_column($a->rows, 'status'));
        $this->assertSame(1, $a->counts()['records']);
    }

    public function test_reading_csv_files(): void
    {
        $schema = new TeamsSchema();
        // French Excel: semicolons, a byte order mark
        $a = Importer::analyse($schema, $this->file('fr.csv', "\xEF\xBB\xBFCompany;Name;Email\nCôte Ltd;Zoé Test;zoe@cote.test\n;;\n"), 'fr.csv');
        $this->assertSame(['Côte Ltd', 'Zoé Test', 'zoe@cote.test'], [$a->rows[2]['values']['company'], $a->rows[2]['values']['name'], $a->rows[2]['values']['email']]);
        $this->assertCount(1, $a->rows);
        // Older Excel saves in the Windows encoding; a quoted cell may hold a comma and a line break
        $a = Importer::analyse($schema, $this->file('win.csv', "Company,Name,Phone\r\n\"Kilo, Lagos\",\"Ren\xE9e\nTest\",0803 555 0101\r\n"), 'win.csv');
        $this->assertSame('Kilo, Lagos', $a->rows[2]['values']['company']);
        $this->assertSame('Renée Test', $a->rows[2]['values']['name']);
        $this->assertSame('0803 555 0101', $a->rows[2]['values']['phone']);
    }

    public function test_files_that_cant_be_imported_say_why(): void
    {
        $schema = new TeamsSchema();
        $cases = [
            ['notes.pdf', '%PDF-1.4', 'Upload an Excel workbook (.xlsx) or a CSV file.'],
            ['old.xls', "\xD0\xCF\x11\xE0rest", 'older Excel file (.xls)'],
            ['broken.xlsx', "PK\x03\x04broken", 'isn’t an Excel workbook we can read'],
            ['headers.csv', "Something,Else\n1,2\n", 'couldn’t find the column headers'],
            ['nocompany.csv', "Name,Email\nAda,ada@kilo.test\n", 'This file has no ‘Company’ column.'],
            ['noreach.csv', "Company,Name,Size\nKilo,Ada,Small team\n", 'at least one of these columns: ‘Email’, ‘Phone’'],
            ['empty.csv', "Company,Name,Email\n", 'has no rows under its headers yet'],
            ['big.csv', "Company,Email\n" . str_repeat("Kilo,a@kilo.test\n", Importer::MAX_ROWS + 1), 'more than 1,000 rows'],
            ['doctype.xlsx', Zip::build(['xl/workbook.xml' => '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><workbook>&e;</workbook>']), 'isn’t an Excel workbook we can read'],
        ];
        foreach ($cases as [$name, $bytes, $message]) {
            try {
                Importer::analyse($schema, $this->file($name, $bytes), $name);
                $this->fail("{$name} was read");
            } catch (ImportFailed $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
    }

    public function test_the_preview_marks_each_row_and_the_fix_file_brings_back_the_rest(): void
    {
        TeamsSchema::$known = ['lima ltd' => 'id-lima'];
        TeamsSchema::$people = ['lima ltd|ada obi'];
        $rows = [
            ['Kilo Ltd', 'Ada Kilo', 'ada@kilo.test', '', 'Small team', ''],   // 2 ready
            ['Kilo Ltd', 'Bola Kilo', 'bola-at-kilo', '', '', ''],            // 3 needs fixing (its email); Kilo still comes in
            ['Mike Ltd', 'Chidi Mike', '', '', 'Huge', ''],                     // 4 needs fixing: no phone or email, and the size
            ['Lima Ltd', 'Ada Obi', '', '', '', ''],                            // 5 already exists, nothing new
            ['LIMA LTD', 'Dayo Lima', '', '', '', ''],                          // 6 already exists, a new person to add
            ['', 'Nobody', 'x@y.test', '', '', ''],                             // 7 needs fixing: no company
        ];
        $path = $this->file('teams.xlsx', $this->teamsFile($rows));
        $a = Importer::analyse(new TeamsSchema(), $path, 'teams.xlsx');

        $this->assertSame([2 => 'ready', 3 => 'fix', 4 => 'fix', 5 => 'exists', 6 => 'add', 7 => 'fix'], array_map(fn ($r) => $r['status'], $a->rows));
        $this->assertSame(['email'], $a->rows[3]['red']);
        $this->assertSame(['Enter an email like ada@kilo.test.'], $a->rows[3]['reasons']);
        $this->assertSame(['size', 'email'], $a->rows[4]['red']);
        $this->assertSame(['Choose Small team or Large team.', 'Add a phone or an email.'], $a->rows[4]['reasons']);
        $this->assertSame(['company'], $a->rows[7]['red']);
        $this->assertSame('Lima Ltd (known)', $a->rows[6]['note']);
        $this->assertSame(['total' => 6, 'ready' => 1, 'add' => 1, 'exists' => 2, 'imported' => 0, 'fix' => 3, 'records' => 1], $a->counts());
        $this->assertTrue($a->hasWork());
        // Kept between the dialog's requests as a plain array
        $this->assertEquals($a, Analysis::fromArray(json_decode(json_encode($a->toArray()), true)));

        // The rows to fix, with their red cells and what to fix; read again, they are the same rows
        $fix = $this->file('fix.xlsx', Importer::fixFile(new TeamsSchema(), $a));
        $sheets = XlsxReader::read($fix, 100);
        $this->assertSame([Importer::GUIDE, 'Teams'], array_column($sheets, 'name'));
        $this->assertStringContainsString('These rows of ‘teams.xlsx’ weren’t imported', $sheets[0]['rows'][2][0]);
        $this->assertSame(['Company *', 'Name', 'Email +', 'Phone +', 'Size', 'Started', 'What to fix'], $sheets[1]['rows'][1]);
        $this->assertSame(['Kilo Ltd', 'Bola Kilo', 'bola-at-kilo', 6 => 'Row 3: Enter an email like ada@kilo.test.'], $sheets[1]['rows'][2]);
        $this->assertSame('Huge', $sheets[1]['rows'][3][4]);
        $this->assertCount(4, $sheets[1]['rows']);
        $this->assertMatchesRegularExpression('/<c r="C2" s="6" t="inlineStr"><is><t xml:space="preserve">bola-at-kilo/', Zip::open(file_get_contents($fix))->get('xl/worksheets/sheet2.xml'));
        $again = Importer::analyse(new TeamsSchema(), $fix, 'fix.xlsx');
        $this->assertSame([], $again->ignored); // "What to fix" isn't reported as an unknown column
        $this->assertSame(['Kilo Ltd', 'Mike Ltd', ''], array_values(array_map(fn ($r) => $r['values']['company'], $again->rows)));
    }

    public function test_importing_brings_in_the_ready_rows_once(): void
    {
        TeamsSchema::$known = ['lima ltd' => 'id-lima'];
        $path = $this->file('teams.xlsx', $this->teamsFile([
            ['Kilo Ltd', 'Ada Kilo', 'ada@kilo.test', '', '', ''],
            ['Kilo Ltd', 'Bola Kilo', 'not-an-email', '', '', ''],
            ['Lima Ltd', 'Dayo Lima', '', '', '', ''],
            ['Mike Ltd', 'Chidi Mike', '', '+234 803 555 0102', '', ''],
        ]));
        $by = new User();
        $by->forceFill(['id' => 'user-1', 'first_name' => 'Test']);

        $a = Importer::run(new TeamsSchema(), $path, 'teams.xlsx', ['colour' => 'ink'], $by);
        $this->assertSame(['created' => 2, 'added' => 1, 'addedTo' => 1], $a->result);
        $this->assertSame([2 => 'imported', 3 => 'fix', 4 => 'added', 5 => 'imported'], array_map(fn ($r) => $r['status'], $a->rows));
        $this->assertSame(['Kilo Ltd', ['Ada Kilo'], 'import:' . sha1_file($path) . ':2', ['colour' => 'ink']], TeamsSchema::$created[0]);
        $this->assertSame([['id-lima', 'Dayo Lima']], TeamsSchema::$added);
        $this->assertSame(2, $a->counts()['imported']);

        // The same file again: the schema finds what it made, so nothing comes in twice
        $again = Importer::run(new TeamsSchema(), $path, 'teams.xlsx', [], $by);
        $this->assertSame(['created' => 0, 'added' => 0, 'addedTo' => 0], $again->result);
        $this->assertCount(2, TeamsSchema::$created);
        $this->assertFalse(Importer::analyse(new TeamsSchema(), $path, 'teams.xlsx')->hasWork());
    }

    public function test_kept_files_belong_to_whoever_uploaded_them(): void
    {
        Storage::fake('local');
        $ada = (new User())->forceFill(['id' => 'ada-id']);
        $bola = (new User())->forceFill(['id' => 'bola-id']);
        $source = $this->file('up.csv', "Company,Email\nKilo,a@kilo.test\n");

        $token = Importer::keep($source, 'csv', $ada);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.csv$/', $token);
        $this->assertNotNull($path = Importer::stored($token, $ada));
        $this->assertNull(Importer::stored($token, $bola));
        $this->assertNull(Importer::stored('../' . $token, $ada));

        $analysis = Importer::analyse(new TeamsSchema(), $path, 'up.csv');
        Importer::remember($token, $ada, $analysis);
        $this->assertEquals($analysis, Importer::recall($token, $ada));
        $this->assertNull(Importer::recall($token, $bola));

        Importer::forget($token, $ada);
        $this->assertNull(Importer::stored($token, $ada));
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }
}
