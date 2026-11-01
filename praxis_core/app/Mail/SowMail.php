<?php

namespace App\Mail;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\StatementOfWork;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\ProposalPdf;
use App\Support\Opportunities\Sows;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Carbon;

/**
 * A version of the statement of work, sent once the owner or the lead approved and signed it for JBS Praxis
 * (docs/OPPORTUNITIES.md section 4, row 5): the PDF attached and a signed link to the portal page where an active
 * contact of the organisation signs it with the one-time code, or asks for changes. In the opportunity's language.
 * The PDF's MIME type is given, so nothing guesses it (the live PHP has no fileinfo).
 */
class SowMail extends BrandedMail
{
    public function __construct(public StatementOfWork $sow, public ?string $clientUserId = null, public ?string $firstName = null, public ?string $pdf = null) {}

    protected function subjectLine(): string
    {
        return $this->sow->version > 1
            ? __('emails.sow.subject_version', ['title' => $this->sow->title, 'n' => $this->sow->version])
            : __('emails.sow.subject', ['title' => $this->sow->title]);
    }

    public function content(): Content
    {
        $s = $this->sow;
        $language = app()->getLocale() === 'fr' ? 'fr' : 'en';
        $validUntil = $s->validUntil() ?? Carbon::now()->addDays(max(1, (int) $s->validity_days));

        return new Content(view: 'emails.sow', with: [
            'company' => $s->opportunity?->organization?->name ?? '',
            'changes' => $s->version > 1 && filled($s->changes_summary) ? MeetingMinutesMail::blocks((string) $s->changes_summary) : [],
            'facts' => [
                __('emails.sow.reference') => "{$s->reference} · " . __('emails.sow.version', ['n' => $s->version]),
                __('emails.sow.between') => Sows::partyName($s),
                // VAT included, as the approved proposal states it (the milestones share out the price before VAT)
                __('emails.sow.total') => ProposalPdf::money((float) ($s->proposal?->total_value ?? 0), (string) ($s->proposal?->currency ?? ''), $language),
                __('emails.sow.valid_until') => $validUntil->copy()->setTimezone('Africa/Lagos')->locale($language)->translatedFormat(__('emails.sow.date_format')),
            ],
            'portalUrl' => $s->opportunity ? PortalLinks::sow($s, $this->clientUserId) : null,
            'hasPdf' => $this->pdf !== null,
        ]);
    }

    public function attachments(): array
    {
        return $this->pdf === null ? [] : [
            Attachment::fromData(fn () => $this->pdf, \App\Support\Opportunities\SowPdf::filename($this->sow))->withMime('application/pdf'),
        ];
    }

    public static function samples(): array
    {
        $organisation = (new Organization)->forceFill(['id' => '7f6c2a52-1b1e-4a8e-9a7a-0c0d1e2f3a4b', 'name' => 'Acme Ltd (demo)']);
        $opportunity = (new Opportunity)->forceFill(['id' => '0b5c8d1e-2f3a-4b5c-8d6e-7f8091a2b3c4', 'title' => 'Finance system', 'language' => 'en'])
            ->setRelation('organization', $organisation);
        $proposal = (new Proposal)->forceFill(['id' => '5d6e7f80-91a2-4b3c-8d4e-5f6071829304', 'currency' => 'USD', 'total_value' => 26875.00]);
        $sow = fn (int $version, ?string $changes) => (new StatementOfWork)->forceFill([
            'id' => $version === 1 ? '8e9fa0b1-c2d3-4e5f-8a6b-7c8d9e0f1a2b' : '9fa0b1c2-d3e4-4f5a-9b6c-8d9e0f1a2b3c',
            'reference' => 'SOW-20261112-731', 'version' => $version, 'title' => 'A faster month-end close for the Lagos entities',
            'validity_days' => 30, 'changes_summary' => $changes, 'provider_legal_name' => 'JBS Praxis (demo legal name)',
            'sent_at' => CarbonImmutable::parse('2026-11-12 09:00:00', 'UTC'),
        ])->setRelation('opportunity', $opportunity)->setRelation('proposal', $proposal);

        return [
            'first' => new static($sow(1, null), null, 'Ada'),
            'version' => new static($sow(2, "We split the build into two milestones, as you asked.\n\n- Milestones: the build is now M2 and M3"), null, 'Ada'),
        ];
    }
}
