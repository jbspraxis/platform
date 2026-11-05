<?php

namespace App\Support\Letters;

use App\Models\Letters\LetterDocument;
use App\Support\Hosts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What a scan of the QR code is answered with (docs/DOCUMENTS.md section 7). The marketing site asks HQ through
 * the signed Site API (GET /api/site/v1/documents/{token}); nothing here depends on being signed in, so the
 * payload carries only what the document itself already says in print, and never an internal id or an email
 * address nobody was given.
 *
 * The token is 48 random characters, not the reference: knowing that a document called JBS/RES/2026/001 exists
 * tells a visitor nothing, and they cannot walk the register by guessing.
 */
final class Verification
{
    /** Scans of one token: more than this in an hour and the page says to try again later */
    public const HITS_PER_HOUR = 60;

    public static function url(string $token): string
    {
        return 'https://' . Hosts::site() . '/verify/' . $token;
    }

    public static function find(string $token): ?LetterDocument
    {
        if (! preg_match('/^[A-Za-z0-9]{24,64}$/', $token)) {
            return null;
        }

        return LetterDocument::query()->where('verify_token', $token)
            ->whereIn('status', [LetterDocument::ISSUED, LetterDocument::SENT_FOR_SIGNATURE, LetterDocument::FULLY_SIGNED,
                LetterDocument::REVOKED, LetterDocument::REPLACED])
            ->first();
    }

    /** Counts this scan and says whether it is one too many */
    public static function tooMany(string $token): bool
    {
        $since = now()->subHour();
        DB::table('letter_verification_hits')->where('created_at', '<', now()->subDay())->delete();
        $count = DB::table('letter_verification_hits')->where('verify_token', $token)->where('created_at', '>=', $since)->count();
        DB::table('letter_verification_hits')->insert([
            'verify_token' => $token,
            'ip_address' => request()?->header('X-Site-Visitor-Ip') ?: request()?->ip(),
            'created_at' => now(),
        ]);

        return $count >= self::HITS_PER_HOUR;
    }

    /**
     * What the verify page shows. Dates are the Lagos day the thing happened, as the document prints them.
     *
     * @return array<string, mixed>
     */
    public static function payload(LetterDocument $document): array
    {
        $company = Letterhead::company($document->legal_entity_id) ?? Letterhead::defaultCompany() ?? [];
        $details = Letterhead::details($company);
        $document->loadMissing('signatures', 'replacedBy', 'replaces', 'counterparties');

        return [
            'reference' => $document->reference,
            'title' => $document->title,
            // The English label, and the keys behind it so the French page can name the kind in French
            'kind' => LetterTypes::label($document->type, $document->subtype),
            'type' => $document->type,
            'subtype' => $document->subtype ?: null,
            'issued_by' => $details['name'],
            'document_date' => CarbonImmutable::parse($document->document_date)->toDateString(),
            'issued_at' => $document->issued_at?->timezone('Africa/Lagos')->toIso8601String(),
            'addressed_to' => $document->addressed_to ?: null,
            'signatories' => $document->signatures->map(fn ($signature) => [
                'name' => $signature->signatory_name,
                'title' => $signature->signatory_title,
            ])->concat($document->counterparties->where('status', 'signed')->map(fn ($party) => [
                // An agreement's other party, named exactly as the document prints them
                'name' => (string) ($party->signed_name ?: $party->name),
                'title' => (string) $party->role_title,
            ]))->values()->all(),
            'status' => $document->status,
            'revoked_at' => $document->revoked_at?->timezone('Africa/Lagos')->toDateString(),
            'revoked_reason' => $document->revoked_reason ?: null,
            'replaced_by' => $document->replacedBy?->reference,
            'replaced_at' => $document->replacedBy?->issued_at?->timezone('Africa/Lagos')->toDateString(),
            'replaces' => $document->replaces?->reference,
            'file_sha256' => $document->file_sha256,
            'file_bytes' => $document->file_bytes,
            // An agreement signed by both sides has a second file; a reader may hold either, so the page checks
            // a dropped copy against both fingerprints. The issued file itself is never touched.
            'countersigned' => $document->status === LetterDocument::FULLY_SIGNED,
            'signed_file_sha256' => $document->signed_file_sha256,
            'signed_at' => $document->fully_signed_at?->timezone('Africa/Lagos')->toDateString(),
            'copy_available' => (bool) $document->copy_public && $document->file_path !== null,
        ];
    }
}
