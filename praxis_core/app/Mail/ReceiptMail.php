<?php

namespace App\Mail;

use App\Support\Opportunities\ProposalPdf;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Carbon;

/**
 * A payment's receipt (owner, 13 Nov 2026; docs/RECEIPTS.md), to the client's billing contacts as soon as the payment
 * is recorded or verified, and again when HQ sends it: the PDF attached (PaymentReceipts) and what is left to pay.
 * In the organisation's language. $receipt is PaymentReceipts::find()'s row.
 */
class ReceiptMail extends BrandedMail
{
    public function __construct(public object $receipt, public ?string $firstName = null, public float $left = 0.0, public ?string $pdf = null, public ?string $filename = null) {}

    private function money(float $amount): string
    {
        return ProposalPdf::money($amount, (string) $this->receipt->currency, app()->getLocale() === 'fr' ? 'fr' : 'en');
    }

    protected function subjectLine(): string
    {
        return __('emails.receipt.subject', ['receipt' => $this->receipt->receipt_number, 'invoice' => $this->receipt->invoice_number]);
    }

    public function content(): Content
    {
        $r = $this->receipt;
        $language = app()->getLocale() === 'fr' ? 'fr' : 'en';
        $methods = (array) __('invoices.receipt.methods');
        $received = $r->received_on ?: Carbon::parse($r->verified_at ?? $r->created_at)->setTimezone('Africa/Lagos');

        return new Content(view: 'emails.receipt', with: [
            'company' => $r->organisation_name ?? '',
            'amount' => $this->money((float) $r->amount_claimed),
            'facts' => array_filter([
                __('emails.receipt.receipt') => $r->receipt_number,
                __('emails.receipt.invoice') => $r->invoice_number,
                __('emails.receipt.amount') => $this->money((float) $r->amount_claimed),
                __('emails.receipt.received') => Carbon::parse($received)->locale($language)->translatedFormat(__('emails.receipt.date_format')),
                __('emails.receipt.method') => $methods[$r->method] ?? $r->method,
                __('emails.receipt.reference') => $r->tx_hash_or_ref,
                __('emails.receipt.left') => $this->left > 0 ? $this->money($this->left) : null,
            ], fn ($v) => $v !== null && $v !== ''),
            'portalUrl' => route('client.dashboard', ['tab' => 'financials']),
        ]);
    }

    public function attachments(): array
    {
        return $this->pdf === null ? [] : [
            Attachment::fromData(fn () => $this->pdf, $this->filename ?? 'receipt.pdf')->withMime('application/pdf'),
        ];
    }

    public static function samples(): array
    {
        $receipt = (object) [
            'receipt_number' => 'RCT-202611-0001', 'invoice_number' => 'INV-202611-4032', 'project_title' => 'Finance system', 'organisation_name' => 'Acme Ltd (demo)',
            'amount_claimed' => 300000.00, 'currency' => 'NGN', 'received_on' => '2026-11-13', 'verified_at' => '2026-11-13 10:00:00', 'created_at' => '2026-11-13 10:00:00',
            'method' => 'Bank Transfer', 'tx_hash_or_ref' => 'TRF-4410-2287',
        ];

        return ['part-paid' => new static($receipt, 'Ada', 600000.00), 'fully-paid' => new static($receipt, 'Ada', 0.0)];
    }
}
