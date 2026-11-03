<?php

namespace App\Support\Import;

use App\Models\User;
use App\Support\Import\Xlsx\CsvReader;
use App\Support\Import\Xlsx\XlsxReader;
use App\Support\Import\Xlsx\XlsxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The shared import engine (docs/LEADS.md section 6). The same four things for every ImportSchema:
 *
 *   template($schema)                         the .xlsx to fill in: sheet 1 a guide to the columns, sheet 2 the data
 *   analyse($schema, $path, $name)            the preview: every row Ready, Needs fixing (reasons, red cells) or Already exists
 *   run($schema, $path, $name, $options, $by) imports the Ready rows (each row of a file once) and what existing records still lack
 *   fixFile($schema, $analysis)               the rows that need fixing, as an .xlsx with the red cells and what to fix
 *
 * Files go up to 1,000 rows and 5 MB, as .xlsx (Excel, LibreOffice, Google Sheets) or .csv. The dialog keeps the
 * uploaded file between its steps with keep() / stored() / forget() (a day at most).
 */
final class Importer
{
    public const MAX_ROWS = 1000;

    public const MAX_KB = 5120;

    public const EXTENSIONS = ['xlsx', 'csv'];

    /** The guide sheet's name (the drop-downs on the data sheet read their lists from it) */
    public const GUIDE = 'How to fill it in';

    private const DIR = 'imports';

    public static function template(ImportSchema $schema): string
    {
        return self::workbook($schema, []);
    }

    /** The rows that need fixing, ready to fix and upload again (their cells to fix in red, what to fix in the last column) */
    public static function fixFile(ImportSchema $schema, Analysis $analysis): string
    {
        return self::workbook($schema, $analysis->toFix(), $analysis->fileName);
    }

    /**
     * Read the file into a preview. Nothing is written.
     *
     * @throws ImportFailed when the whole file can't be read
     */
    public static function analyse(ImportSchema $schema, string $path, string $fileName, array $options = [], ?User $by = null): Analysis
    {
        $run = new Run((string) sha1_file($path), $fileName, $options, $by);
        [$sheet, $groups, $ignored] = self::read($schema, $path, $fileName, $run);

        return Analysis::fromGroups($fileName, $run->hash, $sheet, $groups, $ignored);
    }

    /**
     * Read the file again (what is in HQ may have changed since the preview), create a record from each Ready group,
     * add the new rows of existing records, and return the analysis with what happened ($analysis->result: created,
     * added, addedTo). A row of a file comes in once however often the file is imported: the schema checks the
     * row's dedupe key (Run::dedupeKey) in existing().
     *
     * @throws ImportFailed
     */
    public static function run(ImportSchema $schema, string $path, string $fileName, array $options, User $by): Analysis
    {
        $hash = (string) sha1_file($path);
        $lock = Cache::lock('import:' . $hash, 300);
        if (! $lock->get()) {
            throw new ImportFailed('This file is being imported already. Wait a moment, then look at the list.');
        }
        try {
            $run = new Run($hash, $fileName, $options, $by);
            [$sheet, $groups, $ignored] = self::read($schema, $path, $fileName, $run);
            $created = [];
            $done = [];
            $added = 0;
            $addedTo = 0;
            foreach ($groups as $group) {
                if ($group->existing === null) {
                    if ($group->goodLines() && ($id = $schema->create($group, $run)) !== null) {
                        $created[] = $id;
                        foreach ($group->goodLines() as $line) {
                            $done[$line] = 'imported';
                        }
                    }
                    continue;
                }
                $group->adds = array_values(array_filter($group->adds, fn ($l) => $group->isGood($l)));
                if ($group->adds) {
                    if ($n = $schema->add($group, $run)) {
                        $added += $n;
                        $addedTo++;
                        foreach ($group->adds as $line) {
                            if ($group->isGood($line)) {
                                $done[$line] = 'added';
                            }
                        }
                    }
                }
            }
            $schema->finish($run, $created, $added);

            $analysis = Analysis::fromGroups($fileName, $hash, $sheet, $groups, $ignored);
            foreach ($done as $line => $status) {
                if ($analysis->rows[$line]['status'] !== 'fix') {
                    $analysis->rows[$line]['status'] = $status;
                }
            }
            $analysis->result = ['created' => count($created), 'added' => $added, 'addedTo' => $addedTo];

            return $analysis;
        } finally {
            $lock->release();
        }
    }

    /** @return array{0: string, 1: array<string, Group>, 2: array<int, string>} the data sheet's name, the checked groups, the headers not read */
    private static function read(ImportSchema $schema, string $path, string $fileName, Run $run): array
    {
        [$sheet, $headerLine, $map, $ignored] = self::header($schema, self::sheets($path, $fileName));
        $columns = [];
        foreach ($schema->columns() as $c) {
            $columns[$c->key] = $c;
        }

        $groups = [];
        $count = 0;
        foreach ($sheet['rows'] as $line => $cells) {
            if ($line <= $headerLine) {
                continue;
            }
            $values = array_fill_keys(array_keys($columns), '');
            foreach ($map as $index => $key) {
                $values[$key] = $columns[$key]->read((string) ($cells[$index] ?? ''));
            }
            if (implode('', $values) === '') {
                continue;
            }
            if (++$count > self::MAX_ROWS) {
                throw new ImportFailed('This file has more than ' . number_format(self::MAX_ROWS) . ' rows. Split it into smaller files and import them one by one.');
            }
            $values = $schema->fill($values);
            $key = $schema->groupKey($values, $line);
            $groups[$key] ??= new Group($key, []);
            $groups[$key]->rows[$line] = $values;
        }
        if (! $groups) {
            throw new ImportFailed('The ‘' . $sheet['name'] . '’ sheet has no rows under its headers yet. Fill in the rows, then upload the file again.');
        }

        foreach ($groups as $group) {
            $group->existing = $schema->existing($group, $run);
            $schema->check($group, $run);
        }

        return [$sheet['name'], $groups, $ignored];
    }

    /** The file's sheets, as XlsxReader gives them */
    private static function sheets(string $path, string $fileName): array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $head = (string) file_get_contents($path, false, null, 0, 4);
        if ($extension === 'xls' || $head === "\xD0\xCF\x11\xE0") {
            throw new ImportFailed('This is an older Excel file (.xls). Open it in Excel, choose Save as › Excel workbook (.xlsx), and upload that.');
        }
        if ($head === "PK\x03\x04") {
            return XlsxReader::read($path, self::MAX_ROWS);
        }
        if (in_array($extension, ['csv', 'txt'], true)) {
            return CsvReader::read($path, pathinfo($fileName, PATHINFO_FILENAME), self::MAX_ROWS);
        }

        throw new ImportFailed('Upload an Excel workbook (.xlsx) or a CSV file.');
    }

    /**
     * Find the data sheet and its header row: the schema's sheet first, then the others; within the first ten rows,
     * the row whose cells name the most columns. Returns [sheet, header row number, cell index => column key, headers not read].
     */
    private static function header(ImportSchema $schema, array $sheets): array
    {
        $lookup = [];
        foreach ($schema->columns() as $c) {
            foreach ($c->matches() as $m) {
                $lookup[$m] ??= $c->key;
            }
        }
        usort($sheets, fn ($a, $b) => (Column::normalise($b['name']) === Column::normalise($schema->sheetName())) <=> (Column::normalise($a['name']) === Column::normalise($schema->sheetName())));

        $best = null;
        foreach ($sheets as $sheet) {
            if (Column::normalise($sheet['name']) === Column::normalise(self::GUIDE)) {
                continue; // the template's guide: its example rows are never data
            }
            foreach (array_slice($sheet['rows'], 0, 10, true) as $line => $cells) {
                $map = [];
                $ignored = [];
                foreach ($cells as $index => $text) {
                    $key = $lookup[Column::normalise($text)] ?? null;
                    if ($key !== null && ! in_array($key, $map, true)) {
                        $map[$index] = $key;
                    } elseif (! in_array(Column::normalise($text), ['whattofix', ''], true)) {
                        $ignored[] = $text;
                    }
                }
                if (! $best || count($map) > count($best[2])) {
                    $best = [$sheet, $line, $map, $ignored];
                }
            }
        }
        $needed = min(2, count($schema->columns()));
        if (! $best || count($best[2]) < $needed) {
            throw new ImportFailed('We couldn’t find the column headers. Download the template and copy your rows into its ‘' . $schema->sheetName() . '’ sheet, under the headers in row 1.');
        }

        $found = array_values($best[2]);
        $missing = array_filter($schema->columns(), fn (Column $c) => $c->required && ! in_array($c->key, $found, true));
        if ($missing) {
            $names = implode(', ', array_map(fn (Column $c) => '‘' . $c->label . '’', $missing));
            $many = count($missing) > 1;
            throw new ImportFailed('This file has no ' . $names . ($many ? ' columns. Add them with those headers' : ' column. Add it with that header') . ', or use the template.');
        }
        $groups = [];
        foreach ($schema->columns() as $c) {
            if ($c->oneOf) {
                $groups[$c->oneOf][] = $c;
            }
        }
        foreach ($groups as $members) {
            if (! array_intersect(array_map(fn (Column $c) => $c->key, $members), $found)) {
                throw new ImportFailed('This file needs at least one of these columns: ' . implode(', ', array_map(fn (Column $c) => '‘' . $c->label . '’', $members)) . '.');
            }
        }

        return $best;
    }

    /** The template, or (with $fix rows) the file of rows to fix */
    private static function workbook(ImportSchema $schema, array $fix, ?string $from = null): string
    {
        $columns = $schema->columns();
        $book = new XlsxWriter();

        // Sheet 1: the guide (columns, required ones marked, examples) and the lists the drop-downs read
        $intro = $fix
            ? ['These rows of ‘' . $from . '’ weren’t imported. Fix the red cells on the ‘' . $schema->sheetName() . '’ sheet (its last column says what to fix), then import this file again.',
                'Rows that were imported aren’t in this file, so importing it again adds nothing twice.', '']
            : [];
        $rows = [[['v' => $schema->title() . ': how to fill in this file', 's' => 'title']]];
        foreach ([...$intro, ...$schema->guide()] as $line) {
            $rows[] = [$line];
        }
        $rows[] = [];
        $headerAt = count($rows);
        $rows[] = array_map(fn ($t) => ['v' => $t, 's' => 'header'], ['Column', 'Required', 'What to put', 'Good example', 'Not like this']);
        foreach ($columns as $c) {
            $rows[] = [['v' => $c->header(), 's' => 'bold'], $c->requirement($c->oneOf ? $schema->oneOfText($c->oneOf) : ''),
                ['v' => $c->help, 's' => 'wrap'], $c->example, ['v' => $c->bad, 's' => 'muted']];
        }
        $lists = [];
        $listColumn = 5;
        foreach ($columns as $i => $c) {
            if (! $c->choices) {
                continue;
            }
            $rows[$headerAt][$listColumn] = ['v' => $c->label . ($c->strict ? '' : ' (or type another)'), 's' => 'header'];
            $labels = array_values($c->choices);
            foreach ($labels as $n => $label) {
                $rows[$headerAt + 1 + $n][$listColumn] = $label;
            }
            $letter = XlsxWriter::column($listColumn);
            $lists[] = [
                'range' => XlsxWriter::column($i) . '2:' . XlsxWriter::column($i) . (self::MAX_ROWS + 1),
                'source' => XlsxWriter::sheetRef(self::GUIDE) . '!$' . $letter . '$' . ($headerAt + 2) . ':$' . $letter . '$' . ($headerAt + 1 + count($labels)),
                'strict' => $c->strict,
                'error' => 'Choose one of: ' . implode(', ', $labels) . '.',
            ];
            $listColumn++;
        }
        $rows = array_replace(array_fill(0, max(array_keys($rows)) + 1, []), $rows);
        if ($examples = $schema->examples()) {
            $rows[] = [];
            $rows[] = [['v' => 'An example', 's' => 'bold']];
            $rows[] = self::headerCells($columns);
            foreach ($examples as $example) {
                $rows[] = self::cells($columns, $example);
            }
        }
        $book->sheet(self::GUIDE, $rows, ['widths' => [30, 24, 60, 30, 40, ...array_fill(0, max(count($columns) - 5, $listColumn - 5), 26)]]);

        // Sheet 2: the data, one row per row of the file
        $data = [self::headerCells($columns)];
        if ($fix) {
            $data[0][] = ['v' => 'What to fix', 's' => 'header'];
            foreach ($fix as $line => $row) {
                $cells = self::cells($columns, $row['values'], $row['red']);
                $cells[] = ['v' => 'Row ' . $line . ': ' . implode(' ', $row['reasons']), 's' => 'errorwrap'];
                $data[] = $cells;
            }
        }
        $text = [];
        foreach ($columns as $i => $c) {
            if ($c->text) {
                $text[] = $i;
            }
        }
        $book->sheet($schema->sheetName(), $data, [
            'widths' => [...array_map(fn (Column $c) => $c->width, $columns), ...($fix ? [60] : [])],
            'freeze' => 1, 'text' => $text, 'lists' => $lists,
        ]);

        return $book->build();
    }

    private static function headerCells(array $columns): array
    {
        return array_map(fn (Column $c) => ['v' => $c->header(), 's' => $c->required ? 'required' : ($c->oneOf ? 'oneof' : 'header')], $columns);
    }

    /** A row's cells in column order: a choice shows its label; $red cells are styled to fix */
    private static function cells(array $columns, array $values, array $red = []): array
    {
        return array_map(function (Column $c) use ($values, $red) {
            $v = (string) ($values[$c->key] ?? '');
            $v = $c->choices[$v] ?? $v;

            return in_array($c->key, $red, true) ? ['v' => $v, 's' => 'error'] : $v;
        }, $columns);
    }

    // --- The dialog's choices --------------------------------------------------------------------------------

    /** Option key => its starting value */
    public static function defaults(ImportSchema $schema, User $by, string $fileName): array
    {
        $values = [];
        foreach ($schema->options($by, $fileName) as $o) {
            $values[$o->key] = $o->default;
        }

        return $values;
    }

    /**
     * Check the dialog's choices: [clean values, errors by option key]. Types first (an active person, one of the
     * choices, a length, a date), then the schema's own rules.
     */
    public static function checkOptions(ImportSchema $schema, array $values, User $by, string $fileName): array
    {
        $clean = [];
        $errors = [];
        foreach ($schema->options($by, $fileName) as $o) {
            $v = trim((string) ($values[$o->key] ?? ''));
            $clean[$o->key] = $v === '' ? null : $v;
            $error = match (true) {
                $o->type === 'staff' && $v === '' => $o->nullable ? null : 'Choose someone.',
                $o->type === 'staff' => self::staffMember($v) ? null : 'Choose someone on the staff whose account is active.',
                $o->type === 'choice' => array_key_exists($v, $o->choices) ? null : 'Choose one of the options.',
                $v === '' => $o->optional ? null : ($o->type === 'date' ? 'Choose a date.' : 'Fill this in.'),
                $o->type === 'date' => self::isDate($v) ? null : 'Choose a date.',
                default => mb_strlen($v) > $o->max ? 'Keep this under ' . number_format($o->max) . ' characters.' : null,
            };
            if ($error) {
                $errors[$o->key] = $error;
            }
        }

        return [$clean, $errors ?: $schema->checkOptions($clean, $by)];
    }

    /** Active staff for the staff selects: id => name, by first name */
    public static function staff(): array
    {
        return User::query()->where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)])->all();
    }

    private static function staffMember(string $id): bool
    {
        return User::query()->whereKey($id)->where('status', 'Active')->whereNull('deleted_at')->exists();
    }

    private static function isDate(string $v): bool
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $v);

        return $d && $d->format('Y-m-d') === $v;
    }

    // --- The uploaded file, kept between the dialog's steps ---------------------------------------------------

    /** Keep an uploaded file for $by; returns its token. Files older than a day go at the same time. */
    public static function keep(string $source, string $extension, User $by): string
    {
        $disk = Storage::disk('local');
        $dayAgo = Carbon::now()->subDay()->getTimestamp();
        foreach ($disk->allFiles(self::DIR) as $old) {
            if ($disk->lastModified($old) < $dayAgo) {
                $disk->delete($old);
            }
        }
        $token = Str::random(40) . '.' . strtolower($extension);
        $disk->put(self::DIR . '/' . $by->id . '/' . $token, (string) file_get_contents($source));

        return $token;
    }

    /** The path of a file kept for $by, or null (gone, or not theirs) */
    public static function stored(?string $token, User $by): ?string
    {
        if (! $token || ! preg_match('/^[A-Za-z0-9]{40}\.(xlsx|csv)$/', $token)) {
            return null;
        }
        $path = self::DIR . '/' . $by->id . '/' . $token;

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    public static function forget(?string $token, User $by): void
    {
        if (self::stored($token, $by)) {
            Storage::disk('local')->delete([self::DIR . '/' . $by->id . '/' . $token, self::DIR . '/' . $by->id . '/' . $token . '.json']);
        }
    }

    /** Keep the preview (or the result) of a kept file, so the dialog doesn't read the file again on every click */
    public static function remember(string $token, User $by, Analysis $analysis): void
    {
        if (self::stored($token, $by)) {
            Storage::disk('local')->put(self::DIR . '/' . $by->id . '/' . $token . '.json', json_encode($analysis->toArray(), JSON_UNESCAPED_UNICODE));
        }
    }

    public static function recall(?string $token, User $by): ?Analysis
    {
        if (! self::stored($token, $by)) {
            return null;
        }
        $json = Storage::disk('local')->get(self::DIR . '/' . $by->id . '/' . $token . '.json');
        $data = $json ? json_decode($json, true) : null;

        return is_array($data) ? Analysis::fromArray($data) : null;
    }
}
