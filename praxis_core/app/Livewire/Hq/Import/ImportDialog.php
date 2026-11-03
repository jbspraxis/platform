<?php

namespace App\Livewire\Hq\Import;

use App\Support\Import\Analysis;
use App\Support\Import\ImportFailed;
use App\Support\Import\Importer;
use App\Support\Import\Imports;
use App\Support\Import\ImportSchema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The import dialog of the shared engine (docs/LEADS.md section 6): <livewire:hq.import.import-dialog schema="leads" />.
 * Three steps, the same for every schema:
 *
 *   1. Download the template; upload the filled file (.xlsx or .csv)
 *   2. The preview: each row Ready, Needs fixing (what to fix, the cell in red) or Already exists; the schema's
 *      choices (Leads: owner, source, details, next step); Import, or download the rows to fix
 *   3. What was imported, and the rows to fix as an Excel file
 *
 * The uploaded file and its preview are kept on the server for the person who uploaded it (Importer::keep), never in
 * the page. When an import ends the dialog dispatches "import-finished" (with the schema key) so the page redraws.
 */
class ImportDialog extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $schema;

    public bool $open = false;

    /** The file being uploaded (Livewire's temporary upload) */
    public $file = null;

    /** The kept file (Importer::keep) and its name as uploaded */
    #[Locked]
    public ?string $token = null;

    #[Locked]
    public ?string $fileName = null;

    /** The schema's choices, by option key */
    public array $options = [];

    /** The preview's filter: all, ready, fix, exists */
    public string $filter = 'all';

    public int $limit = 50;

    #[Locked]
    public bool $done = false;

    public function mount(string $schema): void
    {
        $this->schema = $schema;
        abort_unless($this->definition()->canImport(Auth::user()), 403);
    }

    private function definition(): ImportSchema
    {
        return Imports::schema($this->schema);
    }

    public function show(): void
    {
        abort_unless($this->definition()->canImport(Auth::user()), 403);
        $this->startOver();
        $this->open = true;
    }

    public function close(): void
    {
        Importer::forget($this->token, Auth::user());
        if ($this->done) {
            $this->dispatch('import-finished', schema: $this->schema);
        }
        $this->reset(['open', 'file', 'token', 'fileName', 'options', 'filter', 'limit', 'done']);
        $this->resetErrorBag();
    }

    /** Back to step 1 (another file) */
    public function startOver(): void
    {
        Importer::forget($this->token, Auth::user());
        if ($this->done) {
            $this->dispatch('import-finished', schema: $this->schema);
        }
        $this->reset(['file', 'token', 'fileName', 'options', 'filter', 'limit', 'done']);
        $this->resetErrorBag();
    }

    public function downloadTemplate()
    {
        $schema = $this->definition();
        abort_unless($schema->canImport(Auth::user()), 403);
        $bytes = Importer::template($schema);

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, $schema->templateName() . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** The file arrived: keep it and read it into the preview */
    public function updatedFile(): void
    {
        $schema = $this->definition();
        abort_unless($schema->canImport(Auth::user()), 403);
        $this->resetErrorBag();
        $this->validate(['file' => ['required', 'file', 'max:' . Importer::MAX_KB, 'extensions:' . implode(',', Importer::EXTENSIONS)]], [
            'file.required' => 'Choose a file to upload.',
            'file.file' => 'The file didn’t upload. Try again.',
            'file.uploaded' => 'The file didn’t upload. Try again.',
            'file.max' => 'Choose a file under 5 MB. Split a bigger list into smaller files.',
            'file.extensions' => 'Upload an Excel workbook (.xlsx) or a CSV file.',
        ]);

        $name = mb_substr(basename($this->file->getClientOriginalName()), 0, 150);
        $user = Auth::user();
        $token = Importer::keep($this->file->getRealPath(), strtolower($this->file->getClientOriginalExtension()), $user);
        $this->file = null;
        try {
            $analysis = Importer::analyse($schema, Importer::stored($token, $user), $name, [], $user);
        } catch (ImportFailed $e) {
            Importer::forget($token, $user);
            $this->addError('file', $e->getMessage());

            return;
        }
        Importer::remember($token, $user, $analysis);
        $this->token = $token;
        $this->fileName = $name;
        $this->options = Importer::defaults($schema, $user, $name);
        $this->filter = $analysis->counts()['fix'] ? 'fix' : 'all';
        $this->limit = 50;
    }

    public function updatedFilter(): void
    {
        $this->filter = in_array($this->filter, ['all', 'ready', 'fix', 'exists'], true) ? $this->filter : 'all';
        $this->limit = 50;
    }

    public function showMore(): void
    {
        $this->limit += 100;
    }

    public function import(): void
    {
        $schema = $this->definition();
        $user = Auth::user();
        abort_unless($schema->canImport($user), 403);
        $path = Importer::stored($this->token, $user);
        if (! $path || $this->done) {
            return;
        }
        [$options, $errors] = Importer::checkOptions($schema, $this->options, $user, (string) $this->fileName);
        $this->resetErrorBag();
        if ($errors) {
            foreach ($errors as $key => $message) {
                $this->addError('options.' . $key, $message);
            }

            return;
        }
        try {
            $analysis = Importer::run($schema, $path, (string) $this->fileName, $options, $user);
        } catch (ImportFailed $e) {
            $this->addError('import', $e->getMessage());

            return;
        }
        Importer::remember($this->token, $user, $analysis);
        $this->done = true;
        $this->filter = 'fix';
    }

    /** The rows that need fixing, as an Excel file to fix and upload again */
    public function downloadFixes()
    {
        $schema = $this->definition();
        $user = Auth::user();
        abort_unless($schema->canImport($user), 403);
        $analysis = Importer::recall($this->token, $user);
        if (! $analysis || ! $analysis->toFix()) {
            return null;
        }
        $bytes = Importer::fixFile($schema, $analysis);
        $name = pathinfo($analysis->fileName, PATHINFO_FILENAME) . ' (rows to fix).xlsx';

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function render()
    {
        $schema = $this->definition();
        $user = Auth::user();
        $analysis = $this->open ? Importer::recall($this->token, $user) : null;
        if ($this->open && $this->token && ! $analysis) {
            // the kept file is gone (more than a day old): start again
            $this->reset(['token', 'fileName', 'options', 'done']);
        }
        $rows = $analysis ? $this->filtered($analysis) : [];

        return view('livewire.hq.import.import-dialog', [
            'definition' => $schema,
            'analysis' => $analysis,
            'counts' => $analysis?->counts(),
            'rows' => array_slice($rows, 0, $this->limit, true),
            'more' => max(count($rows) - $this->limit, 0),
            'columns' => $schema->columns(),
            'choices' => $analysis && ! $this->done ? $schema->options($user, (string) $this->fileName) : [],
            'staff' => $analysis && ! $this->done ? Importer::staff() : [],
        ]);
    }

    private function filtered(Analysis $analysis): array
    {
        return array_filter($analysis->rows, fn ($r) => match ($this->filter) {
            'ready' => in_array($r['status'], ['ready', 'imported'], true),
            'fix' => $r['status'] === 'fix',
            'exists' => in_array($r['status'], ['exists', 'add', 'added'], true),
            default => true,
        });
    }
}
