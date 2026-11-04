<?php

namespace App\Support\Letters;

use App\Models\CompanyDocument;
use App\Models\Letters\LetterDocument;
use App\Models\Letters\LetterDocumentSignature;
use App\Models\User;
use App\Support\CompanyDocuments\Companies;
use App\Support\CompanyDocuments\Documents;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The rules behind Documents (docs/DOCUMENTS.md): references, issuing, revoking and replacing, and the line each
 * of those writes to the audit log.
 *
 * A draft can be changed as often as the author likes. Issuing renders the PDF once, stores it on the private
 * disk with its SHA-256, and gives the document its verify token: after that the file never changes, so the hash
 * the verify page checks is the hash of the file the reader was given.
 */
final class Letters
{
    public const DISK = 'local';

    /** The company's own prefix on every reference */
    public const PREFIX = 'JBS';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Africa/Lagos');
    }

    /** A new draft, with the type's own starting fields */
    public static function start(string $type, ?string $subtype, array $company, User $author): LetterDocument
    {
        if (! LetterTypes::exists($type)) {
            throw new RuntimeException('Unknown document type: ' . $type);
        }
        $details = Letterhead::details($company);
        $today = self::today();

        [$reference, $sequence] = self::reserve(LetterTypes::code($type), (int) $today->format('Y'));

        $document = LetterDocument::create([
            'reference' => $reference,
            'type_code' => LetterTypes::code($type),
            'type' => $type,
            'subtype' => $subtype,
            'year' => (int) $today->format('Y'),
            'sequence' => $sequence,
            'legal_entity_id' => (string) $company['id'],
            'title' => LetterTypes::defaultTitle($type, $subtype, $details['name']),
            'document_date' => $today->toDateString(),
            'fields' => LetterTypes::defaults($type, $subtype, $details),
            'status' => LetterDocument::DRAFT,
            'created_by' => $author->id,
        ]);

        self::audit('LETTER_CREATED', 'Started ' . $document->reference . ': ' . LetterTypes::label($type, $subtype), $document);

        return $document;
    }

    /** The reference the next document of this code and year will carry (shown before it is saved) */
    public static function nextReference(string $code, int $year): string
    {
        $last = (int) DB::table('letter_documents')->where('type_code', $code)->where('year', $year)->max('sequence');

        return self::format($code, $year, $last + 1);
    }

    /** Takes the next number for real, under a lock so two people cannot take the same one */
    private static function reserve(string $code, int $year): array
    {
        return DB::transaction(function () use ($code, $year) {
            $last = (int) DB::table('letter_documents')->where('type_code', $code)->where('year', $year)
                ->lockForUpdate()->max('sequence');
            $next = $last + 1;

            return [self::format($code, $year, $next), $next];
        });
    }

    public static function format(string $code, int $year, int $sequence): string
    {
        return self::PREFIX . '/' . $code . '/' . $year . '/' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Issue the document: render the PDF once, store it and its hash, and give it the token the QR carries.
     * Everything after this reads that one file; the document itself can only be revoked or replaced.
     */
    public static function issue(LetterDocument $document, User $by): LetterDocument
    {
        if (! $document->isDraft()) {
            throw new RuntimeException('Only a draft can be issued.');
        }

        $token = $document->verify_token ?: Str::random(48);
        $document->verify_token = $token;

        $pdf = LetterPdf::render($document, final: true);
        $path = 'letters/' . $document->year . '/' . $document->id . '.pdf';
        Storage::disk(self::DISK)->put($path, $pdf);

        $document->forceFill([
            'status' => LetterDocument::ISSUED,
            'issued_at' => now(),
            'issued_by' => $by->id,
            'file_path' => $path,
            'file_sha256' => hash('sha256', $pdf),
            'file_bytes' => strlen($pdf),
        ])->save();

        self::audit('LETTER_ISSUED', 'Issued ' . $document->reference . ' (' . $document->title . ')', $document, [
            'sha256' => $document->file_sha256, 'qr' => $document->show_qr, 'copy_public' => $document->copy_public,
        ]);

        return $document;
    }

    public static function revoke(LetterDocument $document, string $reason, User $by): LetterDocument
    {
        if ($document->status !== LetterDocument::ISSUED) {
            throw new RuntimeException('Only an issued document can be revoked.');
        }

        $document->forceFill([
            'status' => LetterDocument::REVOKED,
            'revoked_at' => now(),
            'revoked_by' => $by->id,
            'revoked_reason' => Str::limit(trim($reason), 490),
        ])->save();

        self::audit('LETTER_REVOKED', 'Revoked ' . $document->reference . ': ' . $document->revoked_reason, $document);

        return $document;
    }

    /**
     * A new draft that will take the old document's place. The old one only becomes "replaced" when the new one
     * is issued, so a replacement that is never finished leaves the original standing.
     */
    public static function replace(LetterDocument $document, User $by): LetterDocument
    {
        if (! in_array($document->status, [LetterDocument::ISSUED, LetterDocument::REVOKED], true)) {
            throw new RuntimeException('Only an issued or revoked document can be replaced.');
        }

        $company = Letterhead::company($document->legal_entity_id) ?? Letterhead::defaultCompany();
        $today = self::today();
        [$reference, $sequence] = self::reserve($document->type_code, (int) $today->format('Y'));

        $copy = LetterDocument::create([
            'reference' => $reference,
            'type_code' => $document->type_code,
            'type' => $document->type,
            'subtype' => $document->subtype,
            'year' => (int) $today->format('Y'),
            'sequence' => $sequence,
            'legal_entity_id' => $company['id'],
            'title' => $document->title,
            'addressed_to' => $document->addressed_to,
            'addressed_to_address' => $document->addressed_to_address,
            'document_date' => $today->toDateString(),
            'fields' => $document->fields,
            'body' => $document->body,
            'status' => LetterDocument::DRAFT,
            'show_qr' => $document->show_qr,
            'copy_public' => $document->copy_public,
            'replaces_id' => $document->id,
            'created_by' => $by->id,
        ]);

        self::audit('LETTER_REPLACEMENT_STARTED', 'Started ' . $copy->reference . ' to replace ' . $document->reference, $copy);

        return $copy;
    }

    /** Called when a replacement is issued: the document it replaces says so from then on */
    public static function markReplaced(LetterDocument $replacement): void
    {
        $original = $replacement->replaces_id ? LetterDocument::find($replacement->replaces_id) : null;
        if (! $original || $original->status === LetterDocument::REPLACED) {
            return;
        }

        $original->forceFill([
            'status' => LetterDocument::REPLACED,
            'replaced_by_id' => $replacement->id,
        ])->save();

        self::audit('LETTER_REPLACED', $original->reference . ' was replaced by ' . $replacement->reference, $original);
    }

    /** Apply one person's own signature to a draft. The caller has checked the permission and the fresh sign-in. */
    public static function sign(LetterDocument $document, User $user): LetterDocumentSignature
    {
        if (! $document->isDraft()) {
            throw new RuntimeException('An issued document cannot be signed again.');
        }
        $signature = Signatures::forUser($user);
        if (! $signature) {
            throw new RuntimeException('You have not saved a signature yet.');
        }

        $position = (int) LetterDocumentSignature::where('document_id', $document->id)->max('position');
        $row = LetterDocumentSignature::updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $user->id],
            [
                'signatory_name' => $signature->signatory_name,
                'signatory_title' => $signature->signatory_title,
                'image' => $signature->image,
                'width' => $signature->width,
                'height' => $signature->height,
                'position' => $position + 1,
                'signed_at' => now(),
                'ip_address' => request()?->ip(),
            ]
        );

        self::audit('LETTER_SIGNED', $signature->signatory_name . ' signed ' . $document->reference, $document);

        return $row;
    }

    public static function unsign(LetterDocument $document, User $user): void
    {
        if (! $document->isDraft()) {
            throw new RuntimeException('An issued document cannot be changed.');
        }
        LetterDocumentSignature::where('document_id', $document->id)->where('user_id', $user->id)->delete();
        self::audit('LETTER_SIGNATURE_REMOVED', 'Took a signature off ' . $document->reference, $document);
    }

    /**
     * Files an issued document in Master data › Company documents (docs/COMPANY_DOCUMENTS.md), so the papers the
     * firm issues sit beside the ones it receives. The PDF goes in exactly as it was issued, under its own
     * reference; filing it again is refused, so one document is never in the register twice.
     */
    public static function fileInCompanyDocuments(LetterDocument $document, User $by): CompanyDocument
    {
        if ($document->status === LetterDocument::DRAFT) {
            throw new RuntimeException('Only an issued document can be filed.');
        }
        if ($document->company_document_id) {
            throw new RuntimeException('This document is already in Company documents.');
        }
        $pdf = self::file($document);
        if ($pdf === null) {
            throw new RuntimeException('The issued file is missing, so there is nothing to file.');
        }

        // Company documents takes an uploaded file; the issued PDF is handed over as one, unchanged
        $temporary = storage_path('app/private/letters/tmp-' . $document->id . '.pdf');
        File::ensureDirectoryExists(dirname($temporary));
        File::put($temporary, $pdf);

        try {
            $filed = Documents::create([
                'company' => Companies::keyFor($document->legal_entity_id),
                'kind' => 'other',
                'title' => $document->reference . ' — ' . ($document->title ?: 'Document'),
                'issued_on' => $document->document_date?->toDateString(),
                'notes' => 'Issued by JBS Praxis from HQ › Documents. Reference ' . $document->reference . '.',
            ], new UploadedFile($temporary, self::filename($document), 'application/pdf', null, true), $by);
        } finally {
            File::delete($temporary);
        }

        $document->forceFill(['company_document_id' => $filed->id])->save();
        self::audit('LETTER_FILED', $document->reference . ' was filed in Company documents', $document);

        return $filed;
    }

    /** The issued PDF, or null when the file has gone missing */
    public static function file(LetterDocument $document): ?string
    {
        if (! $document->file_path || ! Storage::disk(self::DISK)->exists($document->file_path)) {
            return null;
        }

        return Storage::disk(self::DISK)->get($document->file_path);
    }

    /** "JBS-RES-2026-001 Opening of a corporate bank account.pdf" */
    public static function filename(LetterDocument $document): string
    {
        $title = trim(preg_replace('/[^A-Za-z0-9 ._-]+/', '', Str::ascii((string) $document->title)));
        $reference = str_replace('/', '-', $document->reference);

        return Str::limit(trim($reference . ' ' . $title), 120, '') . '.pdf';
    }

    public static function audit(string $action, string $description, ?LetterDocument $document = null, array $values = []): void
    {
        rescue(fn () => DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => Str::limit($description, 1900, ''),
            'target_table' => 'letter_documents',
            'target_id' => $document?->id,
            'new_values' => $values ? json_encode($values, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]), null, false);
    }
}
