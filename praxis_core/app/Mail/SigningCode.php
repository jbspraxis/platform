<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * A 6-digit signing code (docs/BRAND.md section 13, "Signing code"): for signing a proposal
 * (Portal\ProposalGateway), a statement of work (Portal\SowReview) or signing off a project handover
 * (Portal\SignoffGateway). The code appears in the body only, never in the subject or the preheader.
 */
class SigningCode extends BrandedMail
{
    public const PROPOSAL = 'proposal';
    public const SOW = 'sow';
    public const HANDOVER = 'handover';

    public $code;
    public $purpose;
    public $project;
    public $firstName;
    public $minutes;

    /** $purpose is self::PROPOSAL, self::SOW or self::HANDOVER; $project is the document or project title (optional). */
    public function __construct($code, string $purpose = self::PROPOSAL, $project = null, $firstName = null, int $minutes = 10)
    {
        $this->code = (string) $code;
        $this->purpose = in_array($purpose, [self::HANDOVER, self::SOW], true) ? $purpose : self::PROPOSAL;
        $this->project = trim((string) $project);
        $this->firstName = $firstName;
        $this->minutes = $minutes;
    }

    protected function subjectLine(): string
    {
        return __('emails.signing.subject', ['minutes' => $this->minutes]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signing-code');
    }

    public static function samples(): array
    {
        return [
            'proposal' => new static('482913', self::PROPOSAL, 'Month-end close redesign (demo)', 'Ada'),
            'sow' => new static('316540', self::SOW, 'A faster month-end close (demo)', 'Ada'),
            'handover' => new static('705218', self::HANDOVER, 'Payroll system go-live (demo)'),
        ];
    }
}
