<?php

namespace App\Support\CompanyDocuments;

use App\Models\CompanyDeadline;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Company documents (owner's decisions, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): each company's papers on the private
 * disk, with an issue date, an expiry or renewal date and its bell. Replacing a file adds a version and keeps the
 * older ones; archiving hides a document and keeps everything (nothing is ever deleted). Every change, view and
 * download is a line in system_audit_logs.
 *
 * Who: mdm.view sees and downloads; mdm.create uploads; mdm.edit replaces, edits and applies what Claude read;
 * mdm.delete archives. The screens check; these methods trust their caller.
 */
final class Documents
{
    public const KINDS = [
        'certificate_of_incorporation' => 'Certificate of incorporation',
        'memorandum_and_articles' => 'Memorandum and articles of association',
        'status_report' => 'CAC status report or extract',
        'tax_certificate' => 'Tax (TIN) certificate',
        'vat_certificate' => 'VAT certificate',
        'bank_letter' => 'Bank reference or account letter',
        'insurance' => 'Insurance',
        'licence' => 'Licence or permit',
        'agreement' => 'Signed agreement',
        'policy' => 'Policy',
        'other' => 'Other',
    ];

    /** The kinds Claude reads on upload, suggesting company details and deadlines (Reader) */
    public const READABLE = ['certificate_of_incorporation', 'memorandum_and_articles', 'status_report', 'tax_certificate'];

    /** What the file may be, by the extension its contents show, and the type it is served as */
    public const TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public const MAX_KB = 20480;

    public const DEFAULT_REMIND_DAYS = 30;

    /** On the private disk (storage/app/private): company-documents/<legal entity id or group>/<document id>/v<n>-… */
    public const FOLDER = 'company-documents';

    /** Lagos time, as the reminders run */
    public const TZ = 'Africa/Lagos';

    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:' . self::MAX_KB];
    }

    /** The details of a document, as the upload and edit forms send them (company is a Companies key) */
    public static function rules(): array
    {
        return [
            'company' => ['required', 'string', Rule::in(Companies::keys())],
            'kind' => ['required', 'string', Rule::in(array_keys(self::KINDS))],
            'title' => ['required', 'string', 'max:190'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'remind_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public const ATTRIBUTES = [
        'company' => 'company', 'kind' => 'kind of document', 'title' => 'title', 'issued_on' => 'issue date',
        'expires_on' => 'expiry or renewal date', 'remind_days' => 'reminder', 'notes' => 'notes', 'file' => 'file',
    ];

    /** Checks the details and returns them as stored: legal_entity_id, kind, title, issued_on, expires_on, remind_days, notes */
    public static function validated(array $details): array
    {
        $details = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $details);
        $validator = Validator::make($details, self::rules(), [
            'company.in' => 'Choose one of the companies in the list.',
        ], self::ATTRIBUTES);
        $validator->after(function ($v) use ($details) {
            if (! empty($details['issued_on']) && ! empty($details['expires_on']) && $details['expires_on'] < $details['issued_on']) {
                $v->errors()->add('expires_on', 'The expiry or renewal date is before the issue date. Check both dates.');
            }
        });
        $data = $validator->validate();

        return [
            'legal_entity_id' => Companies::idFor((string) $data['company']),
            'kind' => $data['kind'],
            'title' => $data['title'],
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'remind_days' => isset($data['remind_days']) ? (int) $data['remind_days'] : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** A new document and its first version; Claude starts reading it when it is a kind it reads */
    public static function create(array $details, UploadedFile $file, User $by): CompanyDocument
    {
        $values = self::validated($details);
        self::checkFile($file);

        $doc = DB::transaction(function () use ($values, $file, $by) {
            $doc = CompanyDocument::create($values + ['created_by' => $by->id, 'updated_by' => $by->id]);
            $version = self::store($doc, $file, 1, $by);
            $doc->forceFill(['current_version_id' => $version->id])->save();
            self::log('COMPANY_DOCUMENT_UPLOADED', $doc, "Uploaded ‘{$doc->title}’ (" . self::KINDS[$doc->kind] . ') for ' . Companies::label($doc->legal_entity_id) . '.',
                null, self::auditValues($doc) + ['file' => $version->original_name], $by->id);

            return $doc;
        });
        Reader::start($doc->currentVersion);

        return $doc->fresh();
    }

    /** A new file for the document: the next version becomes the current one, the earlier ones stay */
    public static function replace(CompanyDocument $doc, UploadedFile $file, User $by): CompanyDocumentVersion
    {
        self::checkFile($file);
        $version = DB::transaction(function () use ($doc, $file, $by) {
            $number = (int) CompanyDocumentVersion::where('document_id', $doc->id)->lockForUpdate()->max('number') + 1;
            $version = self::store($doc, $file, $number, $by);
            $doc->forceFill(['current_version_id' => $version->id, 'updated_by' => $by->id])->save();
            self::log('COMPANY_DOCUMENT_REPLACED', $doc, "Replaced the file of ‘{$doc->title}’ (" . Companies::label($doc->legal_entity_id) . "): version {$number}.",
                null, ['version' => $number, 'file' => $version->original_name], $by->id);

            return $version;
        });
        Reader::start($version);

        return $version->fresh();
    }

    /** Changes the details; returns field => [before, after] */
    public static function update(CompanyDocument $doc, array $details, User $by): array
    {
        $values = self::validated($details);
        $before = self::auditValues($doc);
        $doc->fill($values);
        $diff = [];
        foreach (self::auditValues($doc) as $field => $value) {
            if ($before[$field] !== $value) {
                $diff[$field] = [$before[$field], $value];
            }
        }
        if (! $diff) {
            return [];
        }
        $doc->updated_by = $by->id;
        $doc->save();
        self::log('COMPANY_DOCUMENT_EDITED', $doc, "Edited the details of ‘{$doc->title}’ (" . Companies::label($doc->legal_entity_id) . ').',
            array_map(fn ($p) => $p[0], $diff), array_map(fn ($p) => $p[1], $diff), $by->id);

        return $diff;
    }

    public static function archive(CompanyDocument $doc, User $by): void
    {
        if ($doc->isArchived()) {
            return;
        }
        $doc->forceFill(['archived_at' => now(), 'archived_by' => $by->id])->save();
        self::log('COMPANY_DOCUMENT_ARCHIVED', $doc, "Archived ‘{$doc->title}’ (" . Companies::label($doc->legal_entity_id) . '). Its files are kept.', null, null, $by->id);
    }

    public static function restore(CompanyDocument $doc, User $by): void
    {
        if (! $doc->isArchived()) {
            return;
        }
        $doc->forceFill(['archived_at' => null, 'archived_by' => null])->save();
        self::log('COMPANY_DOCUMENT_RESTORED', $doc, "Restored ‘{$doc->title}’ (" . Companies::label($doc->legal_entity_id) . ') from the archive.', null, null, $by->id);
    }

    /** Opening a file or downloading it: one audit line each time */
    public static function logAccess(CompanyDocument $doc, CompanyDocumentVersion $version, bool $download, ?string $userId): void
    {
        self::log($download ? 'COMPANY_DOCUMENT_DOWNLOADED' : 'COMPANY_DOCUMENT_VIEWED', $doc,
            ($download ? 'Downloaded' : 'Viewed') . " ‘{$doc->title}’ (" . Companies::label($doc->legal_entity_id) . "), version {$version->number}.",
            null, ['version' => $version->number], $userId);
    }

    /**
     * Where a document stands: archived, no expiry date, expired, due soon (within its reminder, 30 days when it has
     * none) or valid. @return array{key: string, label: string, pill: string, days: ?int}
     */
    public static function status(CompanyDocument $doc, ?string $today = null): array
    {
        if ($doc->isArchived()) {
            return ['key' => 'archived', 'label' => 'Archived', 'pill' => 'pill-neutral', 'days' => null];
        }
        if (! $doc->expires_on) {
            return ['key' => 'none', 'label' => 'No expiry date', 'pill' => 'pill-neutral', 'days' => null];
        }
        $days = self::daysUntil($doc->expires_on->format('Y-m-d'), $today);

        return match (true) {
            $days < 0 => ['key' => 'expired', 'label' => 'Expired', 'pill' => 'pill-danger', 'days' => $days],
            $days === 0 => ['key' => 'soon', 'label' => 'Expires today', 'pill' => 'pill-warning', 'days' => 0],
            $days <= ($doc->remind_days ?? self::DEFAULT_REMIND_DAYS) => ['key' => 'soon', 'label' => 'Expires in ' . $days . ($days === 1 ? ' day' : ' days'), 'pill' => 'pill-warning', 'days' => $days],
            default => ['key' => 'valid', 'label' => 'Valid', 'pill' => 'pill-success', 'days' => $days],
        };
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::TZ)->format('Y-m-d');
    }

    /** Whole days from today (Lagos) to $date: negative once it has passed */
    public static function daysUntil(string $date, ?string $today = null): int
    {
        return (int) CarbonImmutable::parse($today ?? self::today())->diffInDays(CarbonImmutable::parse($date), false);
    }

    /** "3 Dec 2026" */
    public static function day(?string $date): string
    {
        return $date ? CarbonImmutable::parse($date)->format('j M Y') : '';
    }

    /** "Thursday 3 December 2026", for sentences (docs/BRAND.md section 4) */
    public static function longDay(string $date): string
    {
        return CarbonImmutable::parse($date)->format('l j F Y');
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /** One line in system_audit_logs for a document (or a deadline: $target_table follows the model) */
    public static function log(string $action, CompanyDocument|CompanyDeadline $target, string $description, ?array $old, ?array $new, ?string $userId): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'description' => Str::limit($description, 2000),
            'target_table' => $target->getTable(),
            'target_id' => $target->getKey(),
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    private static function auditValues(CompanyDocument $doc): array
    {
        return [
            'legal_entity_id' => $doc->legal_entity_id,
            'kind' => $doc->kind,
            'title' => $doc->title,
            'issued_on' => $doc->issued_on?->format('Y-m-d'),
            'expires_on' => $doc->expires_on?->format('Y-m-d'),
            'remind_days' => $doc->remind_days,
            'notes' => $doc->notes,
        ];
    }

    /** The file again, for callers that skipped the form's own rules */
    private static function checkFile(UploadedFile $file): void
    {
        Validator::make(['file' => $file], ['file' => self::fileRules()], [
            'file.mimes' => 'Upload a PDF, JPG, PNG or Word (.docx) file.',
            'file.max' => 'The file is over 20 MB. Upload a smaller scan or a compressed PDF.',
        ], self::ATTRIBUTES)->validate();
    }

    private static function store(CompanyDocument $doc, UploadedFile $file, int $number, User $by): CompanyDocumentVersion
    {
        // The type comes from the file's contents, never from its name
        $ext = strtolower((string) $file->guessExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (! isset(self::TYPES[$ext])) {
            throw ValidationException::withMessages(['file' => 'Upload a PDF, JPG, PNG or Word (.docx) file.']);
        }
        $dir = self::FOLDER . '/' . Companies::keyFor($doc->legal_entity_id) . '/' . $doc->id;
        // Read before storing: Livewire moves its temporary upload when it stores it on the same disk
        $size = (int) $file->getSize();
        $sha256 = (string) hash_file('sha256', $file->getRealPath());
        $name = Str::limit($file->getClientOriginalName() ?: "document.{$ext}", 250, '');
        $path = $file->storeAs($dir, "v{$number}-" . Str::lower(Str::random(12)) . ".{$ext}", 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'HQ couldn’t save the file. Try again; if it keeps happening, tell the HQ administrator.']);
        }

        return CompanyDocumentVersion::create([
            'document_id' => $doc->id,
            'number' => $number,
            'path' => $path,
            'original_name' => $name,
            'mime' => self::TYPES[$ext],
            'size' => $size,
            'sha256' => $sha256,
            'uploaded_by' => $by->id,
            'created_at' => now(),
        ]);
    }
}
