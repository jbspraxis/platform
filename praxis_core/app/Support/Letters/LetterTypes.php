<?php

namespace App\Support\Letters;

/**
 * The ready-made templates (docs/DOCUMENTS.md section 4): what each one is called, the code its reference numbers
 * run with, and the few fields the author fills. Nothing here is a legal opinion: the wording is the firm's own
 * house wording, and every sentence of it can be edited before the document is issued.
 *
 *   RES  board resolution      the sole director's written resolution (open a bank account; appoint a signatory)
 *   LET  formal letter         a letter on the letterhead, and the free letter and Claude's draft
 *   INT  letter of introduction, and the one-page introduction of the firm itself to a company we want to work with
 *   EMP  employment letter     an offer, a confirmation or a letter for a bank or an embassy
 *   AUT  authority to act      what one named person may do on the company's behalf
 *   AGR  agreement             a contract both sides sign: JBS Praxis in HQ, the other party online
 */
final class LetterTypes
{
    public const TYPES = [
        'board_resolution' => [
            'code' => 'RES',
            'label' => 'Board resolution',
            'summary' => 'A written resolution of the director, for a bank or a registry.',
            'subtypes' => [
                'open_bank_account' => 'Open a corporate bank account',
                'appoint_signatory' => 'Appoint a signatory',
            ],
        ],
        'formal_letter' => [
            'code' => 'LET',
            'label' => 'Formal letter',
            'summary' => 'A letter to an organisation, with a subject line and a closing.',
        ],
        'introduction' => [
            'code' => 'INT',
            'label' => 'Letter of introduction',
            'summary' => 'Introduces a member of the firm and says what they are there to do.',
        ],
        'company_introduction' => [
            'code' => 'INT',
            'label' => 'Introduction letter',
            'summary' => 'One page introducing JBS Praxis to a company, in English or French, from the published company profile.',
        ],
        'employment' => [
            'code' => 'EMP',
            'label' => 'Employment letter',
            'summary' => 'Confirms that someone works here, for a bank, a landlord or an embassy.',
        ],
        'authority' => [
            'code' => 'AUT',
            'label' => 'Authority to act',
            'summary' => 'Names one person and says exactly what they may do for the company.',
        ],
        'agreement' => [
            'code' => 'AGR',
            'label' => 'Agreement',
            'summary' => 'A contract: JBS Praxis signs it here, the other party countersigns online.',
        ],
        'free_letter' => [
            'code' => 'LET',
            'label' => 'Free letter',
            'summary' => 'A blank letter on the letterhead: you write the whole body yourself.',
        ],
    ];

    /**
     * The other party to an agreement (docs/DOCUMENTS.md section 12). These are not kept in the document's own
     * fields: they are a row of their own in letter_counterparties, because each signatory carries a private
     * link, a one-time code and a signature, and an agreement may have more than one of them later.
     */
    public static function counterpartyFields(): array
    {
        return [
            'name' => ['label' => 'Their name', 'type' => 'text', 'required' => true, 'help' => 'As it should be printed under the signature.'],
            'role_title' => ['label' => 'Their role or title', 'type' => 'text', 'required' => true, 'placeholder' => 'Contractor'],
            'email' => ['label' => 'Their email address', 'type' => 'text', 'required' => true, 'help' => 'Where the private signing link and the one-time code are sent.'],
            'address' => ['label' => 'Their address', 'type' => 'textarea', 'rows' => 3],
            'company' => ['label' => 'Company (optional)', 'type' => 'text', 'help' => 'When they sign for a company rather than for themselves.'],
            'registration' => ['label' => 'Registration number (optional)', 'type' => 'text', 'placeholder' => 'RC 1234567'],
        ];
    }

    /** Fields every document has, whatever its type */
    public static function common(): array
    {
        return [
            'addressed_to' => ['label' => 'Addressed to', 'type' => 'text', 'help' => 'The organisation, as it should appear on the document and on the verify page.'],
            'addressed_to_address' => ['label' => 'Their address', 'type' => 'textarea', 'rows' => 3],
            'salutation' => ['label' => 'Salutation', 'type' => 'text', 'placeholder' => 'Dear Sir or Madam,'],
            'closing' => ['label' => 'Closing', 'type' => 'text', 'placeholder' => 'Yours faithfully,'],
        ];
    }

    /**
     * The fields of one type, in the order the form shows them.
     * Field types: text, textarea, date, people (a list of name and role).
     */
    public static function fields(string $type, ?string $subtype = null): array
    {
        return match ($type) {
            'board_resolution' => $subtype === 'appoint_signatory'
                ? self::appointSignatoryFields()
                : self::openAccountFields(),
            'introduction' => [
                'person_name' => ['label' => 'Who is introduced', 'type' => 'text', 'required' => true],
                'person_title' => ['label' => 'Their role', 'type' => 'text', 'required' => true],
                'purpose' => ['label' => 'What they are there to do', 'type' => 'textarea', 'rows' => 3, 'required' => true],
                'valid_until' => ['label' => 'Good until', 'type' => 'date'],
            ],
            'company_introduction' => [
                'locale' => ['label' => 'Language', 'type' => 'select', 'required' => true,
                    'options' => ['en' => 'English', 'fr' => 'Français'],
                    'help' => 'The whole letter, its date and its closing follow this.'],
                'contact_person' => ['label' => 'Contact person', 'type' => 'text',
                    'help' => 'Left out of the salutation when it is empty.'],
                'contact_email' => ['label' => 'Their email (optional)', 'type' => 'text',
                    'help' => 'Printed under the closing so they can reply to the right place.'],
            ],
            'employment' => [
                'employee_name' => ['label' => 'Employee', 'type' => 'text', 'required' => true],
                'job_title' => ['label' => 'Job title', 'type' => 'text', 'required' => true],
                'employment_type' => ['label' => 'Basis', 'type' => 'text', 'placeholder' => 'Full time, permanent'],
                'started_on' => ['label' => 'Started on', 'type' => 'date', 'required' => true],
                'pay' => ['label' => 'Pay (optional)', 'type' => 'text', 'help' => 'Left out of the letter when it is empty.'],
                'purpose' => ['label' => 'What the letter is for', 'type' => 'text', 'placeholder' => 'Opening a bank account'],
            ],
            'authority' => [
                'holder_name' => ['label' => 'Who is authorised', 'type' => 'text', 'required' => true],
                'holder_title' => ['label' => 'Their role', 'type' => 'text', 'required' => true],
                'scope' => ['label' => 'What they may do', 'type' => 'textarea', 'rows' => 4, 'required' => true, 'help' => 'One thing per line; each line becomes a numbered paragraph.'],
                'limits' => ['label' => 'What they may not do', 'type' => 'textarea', 'rows' => 2],
                'valid_from' => ['label' => 'From', 'type' => 'date'],
                'valid_until' => ['label' => 'Until', 'type' => 'date'],
            ],
            default => [],
        };
    }

    private static function openAccountFields(): array
    {
        return [
            'bank_name' => ['label' => 'Bank', 'type' => 'text', 'required' => true, 'placeholder' => 'Kuda Microfinance Bank'],
            'account_type' => ['label' => 'Account to open', 'type' => 'text', 'required' => true, 'placeholder' => 'Corporate current account'],
            'currency' => ['label' => 'Currency', 'type' => 'text', 'placeholder' => 'Naira (NGN)'],
            'signatories' => ['label' => 'Signatories', 'type' => 'people', 'required' => true, 'help' => 'Each person, as the bank should record them.'],
            'mandate' => ['label' => 'How the account is operated', 'type' => 'text', 'placeholder' => 'Any one signatory'],
            'authorised_name' => ['label' => 'Who signs the bank forms', 'type' => 'text'],
            'authorised_title' => ['label' => 'Their role', 'type' => 'text'],
            'place' => ['label' => 'Place', 'type' => 'text', 'help' => 'Where the resolution is passed. The registered office by default.'],
            'legal_basis' => ['label' => 'Legal basis', 'type' => 'textarea', 'rows' => 3],
        ];
    }

    private static function appointSignatoryFields(): array
    {
        return [
            'bank_name' => ['label' => 'Bank', 'type' => 'text', 'required' => true],
            'account_type' => ['label' => 'Account', 'type' => 'text', 'placeholder' => 'Corporate current account'],
            'account_number' => ['label' => 'Account number', 'type' => 'text'],
            'person_name' => ['label' => 'Person appointed', 'type' => 'text', 'required' => true],
            'person_title' => ['label' => 'Their role', 'type' => 'text', 'required' => true],
            'mandate' => ['label' => 'How the account is operated', 'type' => 'text', 'placeholder' => 'Any one signatory'],
            'authorised_name' => ['label' => 'Who signs the bank forms', 'type' => 'text'],
            'authorised_title' => ['label' => 'Their role', 'type' => 'text'],
            'place' => ['label' => 'Place', 'type' => 'text', 'help' => 'Where the resolution is passed. The registered office by default.'],
            'legal_basis' => ['label' => 'Legal basis', 'type' => 'textarea', 'rows' => 3],
        ];
    }

    /** True when the body is written by hand (a rich-text body) rather than built from the fields */
    public static function isFreeBody(string $type): bool
    {
        return in_array($type, ['formal_letter', 'free_letter', 'agreement'], true);
    }

    /** The agreement is the one kind with a counterparty and an online countersignature */
    public static function isAgreement(string $type): bool
    {
        return $type === 'agreement';
    }

    public static function code(string $type): string
    {
        return self::TYPES[$type]['code'] ?? 'LET';
    }

    public static function label(string $type, ?string $subtype = null): string
    {
        $label = self::TYPES[$type]['label'] ?? 'Document';
        $sub = $subtype ? (self::TYPES[$type]['subtypes'][$subtype] ?? null) : null;

        return $sub ? $label . ': ' . $sub : $label;
    }

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function subtypes(string $type): array
    {
        return self::TYPES[$type]['subtypes'] ?? [];
    }

    /** The title a new document of this type starts with */
    public static function defaultTitle(string $type, ?string $subtype, string $companyName): string
    {
        return match (true) {
            $type === 'board_resolution' && $subtype === 'appoint_signatory' => 'Resolution of the director of ' . $companyName . ': appointment of a signatory',
            $type === 'board_resolution' => 'Resolution of the director of ' . $companyName . ': opening of a corporate bank account',
            $type === 'introduction' => 'Letter of introduction',
            $type === 'company_introduction' => 'An introduction to ' . $companyName,
            $type === 'employment' => 'Letter of employment',
            $type === 'authority' => 'Authority to act',
            $type === 'agreement' => 'Agreement',
            default => '',
        };
    }

    /** What a new document's fields start with, from the company's own details */
    public static function defaults(string $type, ?string $subtype, array $details): array
    {
        $common = ['salutation' => 'Dear Sir or Madam,', 'closing' => 'Yours faithfully,'];

        // The type's own defaults win over the common ones, so an agreement can start with no salutation
        // and no closing, and the introduction letter can leave both to the language the author picks
        return match ($type) {
            'board_resolution' => [
                'place' => trim(implode(', ', $details['office_lines'] ?: [])),
                'mandate' => 'Any one signatory',
                'account_type' => 'Corporate current account',
                'authorised_name' => (string) ($details['representative'] ?? ''),
                'authorised_title' => (string) ($details['representative_title'] ?: 'Director'),
                'legal_basis' => 'The company has one director, who is also its sole shareholder. A resolution in writing signed by the sole director has the same effect as a resolution passed at a duly convened meeting of the board of directors.',
                'signatories' => [[
                    'name' => (string) ($details['representative'] ?? ''),
                    'title' => (string) ($details['representative_title'] ?: 'Director'),
                ]],
            ],
            'company_introduction' => ['locale' => 'en', 'salutation' => '', 'closing' => ''],
            'employment' => ['employment_type' => 'Full time, permanent'],
            // An agreement is not a letter: no salutation, no closing, and the parties are named in the body
            'agreement' => ['salutation' => '', 'closing' => ''],
            default => [],
        } + $common;
    }
}
