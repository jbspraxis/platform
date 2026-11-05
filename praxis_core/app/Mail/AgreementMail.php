<?php

namespace App\Mail;

use App\Models\Letters\LetterCounterparty;
use App\Models\Letters\LetterDocument;
use App\Support\Letters\Agreements;
use App\Support\Letters\Letters;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;

/**
 * The two emails an agreement sends (docs/DOCUMENTS.md section 12), both on the branded layout:
 *
 *   request  to the other party: the issued PDF to read, and the private link to the page where they sign it.
 *            The one-time code is not in this email — it is asked for on that page and sent separately, so the
 *            link alone signs nothing.
 *   signed   to both sides once everybody has signed: the signed PDF, with the signature record page.
 *
 * English only: an agreement is written in one language, and the document itself is the thing being sent.
 */
class AgreementMail extends BrandedMail
{
    public const REQUEST = 'request';

    public const SIGNED = 'signed';

    public string $kind;

    public function __construct(
        string $kind,
        public LetterDocument $document,
        public ?LetterCounterparty $party = null,
        public ?string $pdf = null,
    ) {
        $this->kind = $kind === self::SIGNED ? self::SIGNED : self::REQUEST;
    }

    protected function subjectLine(): string
    {
        return __('emails.agreement.' . $this->kind . '_subject', ['title' => $this->title()]);
    }

    public function content(): Content
    {
        $document = $this->document;
        $party = $this->party;
        $expires = $party?->token_expires_at;

        return new Content(view: 'emails.agreement', with: [
            'kind' => $this->kind,
            'title' => $this->title(),
            'firstName' => $party ? (explode(' ', trim($party->name))[0] ?: null) : null,
            'signingUrl' => $this->kind === self::REQUEST && $party ? Agreements::signingUrl($party) : null,
            'hasPdf' => $this->pdf !== null,
            'days' => Agreements::DAYS,
            'expires' => $expires?->timezone('Africa/Lagos')->format('j F Y'),
            'facts' => array_filter([
                __('emails.agreement.fact_reference') => $document->reference,
                __('emails.agreement.fact_date') => CarbonImmutable::parse($document->document_date)->format('j F Y'),
                __('emails.agreement.fact_parties') => $this->parties(),
                __('emails.agreement.fact_signed_on') => $this->kind === self::SIGNED && $document->fully_signed_at
                    ? $document->fully_signed_at->timezone('Africa/Lagos')->format('j F Y') : null,
            ]),
        ]);
    }

    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }
        $name = $this->kind === self::SIGNED
            ? Agreements::signedFilename($this->document)
            : Letters::filename($this->document);

        return [Attachment::fromData(fn () => $this->pdf, $name)->withMime('application/pdf')];
    }

    private function title(): string
    {
        return (string) ($this->document->title ?: $this->document->reference);
    }

    /** "JBS Praxis Limited and Jonathan Ndamzi Destiny" */
    private function parties(): string
    {
        $company = \App\Support\Letters\Letterhead::company($this->document->legal_entity_id)
            ?? \App\Support\Letters\Letterhead::defaultCompany() ?? [];
        $names = $this->document->counterparties->map(fn ($party) => $party->company ?: $party->name)->filter()->all();
        array_unshift($names, (string) ($company['legal_name'] ?? 'JBS Praxis'));

        return implode(' and ', $names);
    }

    /** An agreement is written in one language; the email follows the document, not the reader */
    public static function locales(): array
    {
        return ['en'];
    }

    public static function samples(): array
    {
        $document = (new LetterDocument)->forceFill([
            'id' => '2f9a5c31-7a41-4a0e-9a3d-6b2f8c0d1e2f',
            'reference' => 'JBS/AGR/2026/001',
            'type' => 'agreement',
            'type_code' => 'AGR',
            'title' => 'Project Contractor Agreement (demo)',
            'legal_entity_id' => null,
            'document_date' => '2026-11-06',
            'status' => LetterDocument::SENT_FOR_SIGNATURE,
            'fully_signed_at' => CarbonImmutable::parse('2026-11-07 10:05:00', 'UTC'),
            'year' => 2026,
        ]);
        $party = (new LetterCounterparty)->forceFill([
            'id' => '3a7b6d42-8b52-4b1f-8b4e-7c3d9e1f2a3b',
            'document_id' => $document->id,
            'name' => 'Ada Demo',
            'role_title' => 'Full-Stack Engineer',
            'email' => 'ada@example.com',
            'status' => LetterCounterparty::SENT,
            // A made-up token, so the sample shows the button the real email carries
            'token' => 'demo0000demo0000demo0000demo0000demo0000demo0000',
            'token_expires_at' => CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'),
        ]);
        $document->setRelation('counterparties', collect([$party]));

        return [
            'request' => new static(self::REQUEST, $document, $party),
            'signed' => new static(self::SIGNED, $document, $party),
        ];
    }
}
