<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * A 6-digit signing code (docs/BRAND.md section 13, "Signing code"): for signing a proposal
 * (Portal\ProposalGateway), a statement of work (Portal\SowReview), an agreement on the company letterhead
 * (Portal\AgreementSigning, docs/DOCUMENTS.md section 12) or signing off a project handover
 * (Portal\SignoffGateway). The code appears in the body only, never in the subject or the preheader.
 */
class SigningCode extends BrandedMail
{
    public const PROPOSAL = 'proposal';
    public const SOW = 'sow';
    public const HANDOVER = 'handover';

    /** An agreement countersigned by the other party (docs/DOCUMENTS.md section 12) */
    public const AGREEMENT = 'agreement';

    /** Approving a proposal from a link that wasn't sent to a contact (docs/QA-PORTAL.md D5) */
    public const APPROVAL = 'approval';

    public $code;
    public $purpose;
    public $project;
    public $firstName;
    public $minutes;
    public $signer;

    /**
     * $purpose is self::PROPOSAL, self::SOW or self::HANDOVER; $project is the document or project title (optional);
     * $signer ("Name, title") is who the code signs as, said in the email (the SOW's signing page).
     */
    public function __construct($code, string $purpose = self::PROPOSAL, $project = null, $firstName = null, int $minutes = 10, ?string $signer = null)
    {
        $this->code = (string) $code;
        $this->purpose = in_array($purpose, [self::HANDOVER, self::SOW, self::APPROVAL, self::AGREEMENT], true) ? $purpose : self::PROPOSAL;
        $this->project = trim((string) $project);
        $this->firstName = $firstName;
        $this->minutes = $minutes;
        $this->signer = trim((string) $signer);
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
            'sow' => new static('316540', self::SOW, 'A faster month-end close (demo)', 'Ada', signer: 'Ada Obi, Finance director'),
            'handover' => new static('705218', self::HANDOVER, 'Payroll system go-live (demo)'),
            'agreement' => new static('228476', self::AGREEMENT, 'Project Contractor Agreement (demo)', 'Ada', signer: 'Ada Demo, Full-Stack Engineer'),
            'approval' => new static('640271', self::APPROVAL, 'A faster month-end close (demo)', 'Ada', signer: 'Ada Obi, Finance director'),
        ];
    }
}
