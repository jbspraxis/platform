<?php

namespace App\Mail;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Proposal;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\ProposalPdf;
use App\Support\Opportunities\Proposals;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Carbon;

/**
 * A version of the proposal, sent to the client once the owner or the lead approved it (docs/OPPORTUNITIES.md
 * decision 8): the PDF attached and a signed link to the portal, where they comment by section, approve it or ask
 * for changes. A later version says what changed. In the opportunity's language. The PDF's MIME type is given, so
 * nothing guesses it (the live PHP has no fileinfo).
 */
class ProposalMail extends BrandedMail
{
    public function __construct(public Proposal $proposal, public ?string $clientUserId = null, public ?string $firstName = null, public ?string $pdf = null) {}

    protected function subjectLine(): string
    {
        return $this->proposal->version > 1
            ? __('emails.proposal.subject_version', ['title' => $this->proposal->title, 'n' => $this->proposal->version])
            : __('emails.proposal.subject', ['title' => $this->proposal->title]);
    }

    public function content(): Content
    {
        $p = $this->proposal;
        $language = app()->getLocale() === 'fr' ? 'fr' : 'en'; // the email's own language (->locale())
        $validUntil = $p->validUntil() ?? Carbon::now()->addDays(max(1, (int) $p->validity_days));

        return new Content(view: 'emails.proposal', with: [
            'company' => $p->opportunity?->organization?->name ?? '',
            'changes' => $p->version > 1 && filled($p->changes_summary) ? MeetingMinutesMail::blocks((string) $p->changes_summary) : [],
            'facts' => [
                __('emails.proposal.reference') => "{$p->proposal_ref} · " . __('emails.proposal.version', ['n' => $p->version]),
                // "Total, VAT included", or "Total" when the version carries no VAT (App\Support\Finance\Vat)
                (Proposals::vat($p)['applies'] ? __('emails.proposal.total') : __('vat.total')) => ProposalPdf::money((float) $p->total_value, (string) $p->currency, $language),
                __('emails.proposal.valid_until') => $validUntil->copy()->setTimezone('Africa/Lagos')->locale($language)->translatedFormat(__('emails.proposal.date_format')),
            ],
            'portalUrl' => $p->opportunity ? PortalLinks::proposal($p, $this->clientUserId) : null,
            'hasPdf' => $this->pdf !== null,
        ]);
    }

    public function attachments(): array
    {
        return $this->pdf === null ? [] : [
            Attachment::fromData(fn () => $this->pdf, ProposalPdf::filename($this->proposal))->withMime('application/pdf'),
        ];
    }

    public static function samples(): array
    {
        $organisation = (new Organization)->forceFill(['id' => '7f6c2a52-1b1e-4a8e-9a7a-0c0d1e2f3a4b', 'name' => 'Acme Ltd (demo)']);
        $opportunity = (new Opportunity)->forceFill(['id' => '0b5c8d1e-2f3a-4b5c-8d6e-7f8091a2b3c4', 'title' => 'Finance system', 'language' => 'en'])
            ->setRelation('organization', $organisation);
        $proposal = fn (int $version, ?string $changes) => (new Proposal)->forceFill([
            'id' => $version === 1 ? '5d6e7f80-91a2-4b3c-8d4e-5f6071829304' : '6e7f8091-a2b3-4c4d-9e5f-607182930415',
            'proposal_ref' => 'PRP-20261105-482', 'version' => $version, 'title' => 'A faster month-end close for the Lagos entities',
            'currency' => 'USD', 'subtotal' => 25000.00, 'vat_rate' => 7.5, 'vat_applied' => true, 'total_value' => 26875.00, 'validity_days' => 30, 'changes_summary' => $changes, 'signing_mode' => 'approve',
            'sent_at' => CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'),
        ])->setRelation('opportunity', $opportunity);

        return [
            'first' => new static($proposal(1, null), null, 'Ada'),
            'version' => new static($proposal(2, "We moved the go-live to January and added the training you asked for.\n\n- Timeline: go-live moved from December to January\n- Team and governance: two training days for the finance team"), null, 'Ada'),
        ];
    }
}
