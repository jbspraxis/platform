<?php

namespace App\Mail;

use App\Support\Opportunities\ProposalPdf;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Carbon;

/**
 * An invoice, to the client's billing contacts when it is issued (owner, 2 Nov 2026; docs/QA-PORTAL.md D11): the
 * PDF attached (InvoicePdf) and a link to the Client Portal, where they upload their proof of payment. In the
 * organisation's language. $invoice is the invoices row with the project's title and the organisation's name.
 */
class InvoiceMail extends BrandedMail
{
    public function __construct(public object $invoice, public ?string $firstName = null, public ?string $pdf = null, public ?string $filename = null) {}

    protected function subjectLine(): string
    {
        return __('emails.invoice.subject', ['number' => $this->invoice->invoice_number, 'project' => $this->invoice->project_title ?? '']);
    }

    public function content(): Content
    {
        $i = $this->invoice;
        $language = app()->getLocale() === 'fr' ? 'fr' : 'en';

        return new Content(view: 'emails.invoice', with: [
            'company' => $i->organisation_name ?? '',
            'facts' => [
                __('emails.invoice.number') => $i->invoice_number,
                __('emails.invoice.project') => $i->project_title ?? '',
                __('emails.invoice.amount') => ProposalPdf::money((float) $i->total_amount, (string) $i->currency, $language),
                __('emails.invoice.due') => Carbon::parse($i->due_date)->locale($language)->translatedFormat(__('emails.invoice.date_format')),
                __('emails.invoice.from') => $i->issuer_name ?? null,
            ],
            'portalUrl' => route('client.dashboard'),
            'hasPdf' => $this->pdf !== null,
        ]);
    }

    public function attachments(): array
    {
        return $this->pdf === null ? [] : [
            Attachment::fromData(fn () => $this->pdf, $this->filename ?? 'invoice.pdf')->withMime('application/pdf'),
        ];
    }

    public static function samples(): array
    {
        $invoice = (object) [
            'invoice_number' => 'INV-202611-4032', 'project_title' => 'Finance system', 'organisation_name' => 'Acme Ltd (demo)',
            'total_amount' => 4800000.00, 'currency' => 'NGN', 'due_date' => '2026-11-20', 'issuer_name' => 'JBS Praxis (demo legal name)',
        ];

        return ['issued' => new static($invoice, 'Ada')];
    }
}
