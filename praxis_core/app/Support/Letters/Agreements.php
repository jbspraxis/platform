<?php

namespace App\Support\Letters;

use App\Mail\AgreementMail;
use App\Mail\SigningCode;
use App\Models\Letters\LetterCounterparty;
use App\Models\Letters\LetterDocument;
use App\Models\User;
use App\Support\Signing\SigningCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Agreements: a document both sides sign (docs/DOCUMENTS.md section 12).
 *
 * JBS Praxis signs it in HQ and issues it, exactly as it issues every other document — the PDF is rendered once,
 * stored with its SHA-256 and never changed. "Send for signature" then emails the other party a private link to
 * a page in the portal, where they read the whole agreement, enter the one-time code emailed to them, type their
 * full name and draw or upload their signature. The same mechanism as a client signing a proposal or a statement
 * of work (App\Support\Signing\SigningCodes), with the same limits.
 *
 * The signature is applied to a **new** PDF with a signature record page at the end. The issued file stays as it
 * was: the signed version points back at it by printing its fingerprint on that page.
 *
 * The data model takes more than one counterparty (one letter_counterparties row each, each with its own link,
 * code and signature); the screen writes one today, and the document only becomes fully signed when every one of
 * them has signed.
 */
final class Agreements
{
    /** How long a signing link lasts before it has to be sent again */
    public const DAYS = 14;

    /** What SigningCodes counts this kind of document under */
    public const KIND = 'agreement';

    public const MAX_SIGNATURE_BYTES = 2_000_000;

    // ------------------------------------------------------------------ the other party

    /**
     * Write the other party of an agreement that is still a draft. One row today, at position 1; the table and
     * everything below take more.
     */
    public static function saveCounterparty(LetterDocument $document, array $data): LetterCounterparty
    {
        if (! $document->isAgreement()) {
            throw new RuntimeException('Only an agreement has another party.');
        }
        if (! $document->isDraft()) {
            throw new RuntimeException('An issued agreement cannot be changed.');
        }

        $party = self::first($document) ?? new LetterCounterparty(['document_id' => $document->id, 'position' => 1]);
        $party->fill([
            'document_id' => $document->id,
            'position' => $party->position ?: 1,
            'name' => Str::limit(trim((string) ($data['name'] ?? '')), 150, ''),
            'role_title' => Str::limit(trim((string) ($data['role_title'] ?? '')), 150, ''),
            'email' => Str::limit(mb_strtolower(trim((string) ($data['email'] ?? ''))), 150, ''),
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'company' => Str::limit(trim((string) ($data['company'] ?? '')), 200, '') ?: null,
            'registration' => Str::limit(trim((string) ($data['registration'] ?? '')), 100, '') ?: null,
        ])->save();

        return $party->fresh();
    }

    public static function first(LetterDocument $document): ?LetterCounterparty
    {
        return LetterCounterparty::where('document_id', $document->id)->orderBy('position')->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, LetterCounterparty> */
    public static function parties(LetterDocument $document)
    {
        return LetterCounterparty::where('document_id', $document->id)->orderBy('position')->get();
    }

    // ------------------------------------------------------------------ sending it out

    /** Why the agreement cannot be sent for signature yet, or null when it can */
    public static function cannotSend(LetterDocument $document): ?string
    {
        if (! $document->isAgreement()) {
            return 'Only an agreement is sent for signature.';
        }
        if (! in_array($document->status, [LetterDocument::ISSUED, LetterDocument::SENT_FOR_SIGNATURE], true)) {
            return $document->isDraft()
                ? 'Issue the agreement first: the other party signs the issued document, not a draft.'
                : 'This agreement can no longer be sent for signature.';
        }
        if ($document->signatures()->count() === 0) {
            return 'Sign it for JBS Praxis before sending it to the other party.';
        }
        $party = self::first($document);
        if (! $party || $party->email === '' || ! filter_var($party->email, FILTER_VALIDATE_EMAIL)) {
            return 'Give the other party a name and a working email address first.';
        }
        if ($party->hasSigned()) {
            return 'They have already signed it.';
        }

        return null;
    }

    /**
     * Emails the other party their private link, with the issued PDF attached. A fresh token each time, so an
     * earlier email's link stops working the moment a new one is sent.
     */
    public static function send(LetterDocument $document, User $by): LetterCounterparty
    {
        if ($refusal = self::cannotSend($document)) {
            throw new RuntimeException($refusal);
        }
        $party = self::first($document);
        $resend = $party->sent_count > 0;

        $party->forceFill([
            'status' => LetterCounterparty::SENT,
            'token' => Str::random(48),
            'token_expires_at' => now()->addDays(self::DAYS),
            'sent_at' => now(),
            'sent_count' => $party->sent_count + 1,
            'sent_by' => $by->id,
            // A link that is sent again cancels any code the old one was waiting for
            'otp' => null,
            'otp_expires_at' => null,
        ])->save();

        try {
            Mail::to($party->email)->locale('en')->send(new AgreementMail(
                AgreementMail::REQUEST, $document, $party->fresh(), Letters::file($document)
            ));
        } catch (\Throwable $e) {
            // Nothing is half-sent: the link is withdrawn, and the screen says so
            $party->forceFill(['status' => $resend ? LetterCounterparty::SENT : LetterCounterparty::PENDING, 'token' => null, 'token_expires_at' => null])->save();
            Log::error('Agreement signing email failed', ['document_id' => $document->id, 'message' => $e->getMessage()]);
            throw new RuntimeException('The email could not be sent. Check the address and try again.');
        }

        if ($document->status !== LetterDocument::SENT_FOR_SIGNATURE) {
            $document->forceFill(['status' => LetterDocument::SENT_FOR_SIGNATURE])->save();
        }

        Letters::audit($resend ? 'AGREEMENT_RESENT' : 'AGREEMENT_SENT',
            ($resend ? 'Sent the signing link for ' : 'Sent ') . $document->reference . ' to ' . $party->name . ' <' . $party->email . '>',
            $document, ['expires_at' => $party->token_expires_at?->toIso8601String(), 'sent_count' => $party->sent_count]);

        return $party->fresh();
    }

    /** Takes the link back: it stops working at once, and the agreement is simply issued again */
    public static function cancel(LetterDocument $document, User $by): void
    {
        $party = self::first($document);
        if (! $party || $party->hasSigned()) {
            throw new RuntimeException('There is nothing to take back.');
        }

        $party->forceFill([
            'status' => LetterCounterparty::PENDING,
            'token' => null,
            'token_expires_at' => null,
            'otp' => null,
            'otp_expires_at' => null,
        ])->save();

        if ($document->status === LetterDocument::SENT_FOR_SIGNATURE) {
            $document->forceFill(['status' => LetterDocument::ISSUED])->save();
        }

        Letters::audit('AGREEMENT_SIGNING_CANCELLED', 'Took back the signing link for ' . $document->reference, $document);
    }

    // ------------------------------------------------------------------ the private link

    /** The signing page's address: the token in the path, and a signature that expires with it */
    public static function signingUrl(LetterCounterparty $party): ?string
    {
        if (! $party->token || ! $party->token_expires_at) {
            return null;
        }
        $relative = URL::temporarySignedRoute('portal.agreement', $party->token_expires_at, ['token' => $party->token], absolute: false);
        $origin = preg_replace('~^(https?://[^/]+).*$~', '$1', route('portal.agreement', ['token' => $party->token]));

        return $origin . $relative;
    }

    /** The party a signing link belongs to, while the link is still live */
    public static function findByToken(?string $token): ?LetterCounterparty
    {
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{24,64}$/', $token)) {
            return null;
        }
        $party = LetterCounterparty::where('token', $token)->first();

        return $party && $party->document_id ? $party : null;
    }

    /** Why this party cannot sign now, or null when they can */
    public static function cannotSign(LetterCounterparty $party, LetterDocument $document): ?string
    {
        if ($party->hasSigned()) {
            return 'signed';
        }
        if ($document->status !== LetterDocument::SENT_FOR_SIGNATURE) {
            return 'closed';
        }
        if (! $party->token_expires_at || $party->token_expires_at->isPast()) {
            return 'expired';
        }

        return null;
    }

    // ------------------------------------------------------------------ the one-time code

    /**
     * Holds the name and the signature the code was issued for, and the code's HMAC — never the code. A newer
     * request replaces both, so a code can never sign for details other than the ones it was asked with.
     */
    public static function holdSignature(LetterCounterparty $party, string $signedName, string $imageBase64, string $code): bool
    {
        // Saved through the model, so the image goes through the encrypted cast rather than straight into a column
        $held = LetterCounterparty::whereKey($party->id)->where('status', LetterCounterparty::SENT)->first();
        if (! $held) {
            return false;
        }
        $held->forceFill([
            'signed_name' => Str::limit(trim($signedName), 150, ''),
            'image' => $imageBase64,
            'otp' => SigningCodes::hash($code),
            'otp_expires_at' => now()->addMinutes(SigningCodes::MINUTES),
        ])->save();

        return true;
    }

    /** Emails the code. Throws when it cannot be sent, and the unsent code is cancelled by the caller. */
    public static function mailCode(LetterCounterparty $party, LetterDocument $document, string $code): void
    {
        Mail::to($party->email)->locale('en')->send(new SigningCode(
            $code,
            SigningCode::AGREEMENT,
            $document->title ?: $document->reference,
            explode(' ', trim($party->signed_name ?: $party->name))[0] ?: null,
            signer: trim(($party->signed_name ?: $party->name) . ', ' . $party->role_title, ', ')
        ));
    }

    public static function forgetCode(LetterCounterparty $party): void
    {
        LetterCounterparty::whereKey($party->id)->update(['otp' => null, 'otp_expires_at' => null]);
    }

    // ------------------------------------------------------------------ signing

    /**
     * The right code signs. Under a lock, so it happens once however many times the button is pressed: the
     * signature held with the code is applied, and when every party has signed, the countersigned PDF is
     * rendered, stored with its own SHA-256 and emailed to both sides. False when it can no longer be signed.
     */
    public static function countersign(LetterCounterparty $party, string $code, ?string $ip): bool
    {
        $signed = DB::transaction(function () use ($party, $code, $ip) {
            $locked = LetterCounterparty::whereKey($party->id)->lockForUpdate()->first();
            $document = $locked ? LetterDocument::whereKey($locked->document_id)->lockForUpdate()->first() : null;
            if (! $locked || ! $document || self::cannotSign($locked, $document) !== null) {
                return null;
            }
            if (! SigningCodes::matches($locked->otp, $locked->otp_expires_at, $code) || blank($locked->image) || blank($locked->signed_name)) {
                return null;
            }

            $locked->forceFill([
                'status' => LetterCounterparty::SIGNED,
                'signed_at' => now(),
                'code_verified_at' => now(),
                'ip_address' => $ip,
                'otp' => null,
                'otp_expires_at' => null,
            ])->save();

            return $document;
        });

        if (! $signed) {
            return false;
        }

        $party = $party->fresh();
        Letters::audit('AGREEMENT_COUNTERSIGNED',
            ($party->signed_name ?: $party->name) . ' signed ' . $signed->reference,
            $signed, ['email' => $party->email, 'ip' => $ip]);

        self::finishIfComplete($signed->fresh());

        return true;
    }

    /** Every party has signed: render the signed version, store it, and send it to everybody */
    private static function finishIfComplete(LetterDocument $document): void
    {
        $parties = self::parties($document);
        if ($parties->isEmpty() || $parties->contains(fn ($party) => ! $party->hasSigned())) {
            return;
        }

        $pdf = LetterPdf::render($document, final: true, countersigned: true);
        $path = 'letters/' . $document->year . '/' . $document->id . '-signed.pdf';
        Storage::disk(Letters::DISK)->put($path, $pdf);

        $document->forceFill([
            'status' => LetterDocument::FULLY_SIGNED,
            'fully_signed_at' => now(),
            'signed_file_path' => $path,
            'signed_file_sha256' => hash('sha256', $pdf),
            'signed_file_bytes' => strlen($pdf),
        ])->save();

        Letters::audit('AGREEMENT_FULLY_SIGNED',
            $document->reference . ' is signed by every party',
            $document, ['signed_sha256' => $document->signed_file_sha256, 'issued_sha256' => $document->file_sha256]);

        self::mailSignedCopies($document->fresh(), $parties, $pdf);
    }

    /** The signed PDF to the other parties and to whoever issued it, each in their own email */
    private static function mailSignedCopies(LetterDocument $document, $parties, string $pdf): void
    {
        // Keyed by the address in lower case so nobody is written to twice, but each is written to as it is held
        $addresses = [];
        foreach ($parties as $party) {
            $addresses[mb_strtolower($party->email)] = ['to' => $party->email, 'party' => $party];
        }
        $issuer = $document->issued_by ? User::find($document->issued_by) : null;
        if ($issuer && filled($issuer->email) && ! isset($addresses[mb_strtolower($issuer->email)])) {
            $addresses[mb_strtolower($issuer->email)] = ['to' => $issuer->email, 'party' => null];
        }

        foreach ($addresses as $row) {
            rescue(fn () => Mail::to($row['to'])->locale('en')->send(
                new AgreementMail(AgreementMail::SIGNED, $document, $row['party'], $pdf)
            ), null, false);
        }
    }

    // ------------------------------------------------------------------ files

    /** The countersigned PDF, or null while it does not exist */
    public static function signedFile(LetterDocument $document): ?string
    {
        if (! $document->signed_file_path || ! Storage::disk(Letters::DISK)->exists($document->signed_file_path)) {
            return null;
        }

        return Storage::disk(Letters::DISK)->get($document->signed_file_path);
    }

    /** "JBS-AGR-2026-001 Project Contractor Agreement (signed).pdf" */
    public static function signedFilename(LetterDocument $document): string
    {
        return preg_replace('/\.pdf$/', ' (signed).pdf', Letters::filename($document));
    }

    /** The signature a signing page sends, checked to be a JPEG exactly as a staff signature is */
    public static function readSignature(string $dataUri): string
    {
        return Signatures::readJpeg($dataUri);
    }
}
