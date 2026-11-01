<?php

// VAT per JBS Praxis company (App\Support\Finance\Vat): Master data › Legal entities, and what documents say about VAT
return [
    'entity' => [
        'heading' => 'VAT',
        'charges' => 'Charges VAT',
        'number' => 'VAT number',
        'starts_on' => 'VAT starts on',
        'help' => 'Switch on once the company is registered for VAT. Documents dated from the start date get VAT; anything already sent keeps what it had.',
        'read_only' => 'Only people who may edit master data can change VAT.',
        'rate' => 'Rate for :country in Tax compliance: :rate%.',
        'no_rate' => 'There is no active VAT rate for :country in Master data › Tax compliance yet.',
        'status_on' => 'Charges VAT from :date',
        'status_off' => 'No VAT',
        'status_number' => 'VAT no. :number',
        'errors' => [
            'number' => 'Enter the VAT number to switch VAT on.',
            'starts_on' => 'Enter the date VAT starts to switch VAT on.',
            'rate' => 'Add an active VAT rate for :country in Master data › Tax compliance first, then switch VAT on.',
        ],
        'countries' => ['CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya', 'GLOBAL' => 'the head office'],
        'attributes' => [
            'vat_number' => 'VAT number',
            'vat_starts_on' => 'date VAT starts',
        ],
    ],

    // Vat::describe(): one sentence on whether a document gets VAT, and why
    'describe' => [
        'applies' => 'VAT at :rate%: :company charges VAT from :date (VAT no. :number).',
        'zero_rated' => 'VAT at 0%: :company (VAT no. :number) exports the service to a client in another country, so it is zero-rated.',
        'off' => 'No VAT: :company doesn’t charge VAT yet.',
        'not_yet' => 'No VAT: :company charges VAT from :date.',
        'no_rate' => 'No VAT: there is no active VAT rate for :company’s country in Master data › Tax compliance.',
        'no_company' => 'No VAT: no active JBS Praxis company in Master data › Legal entities bills this client.',
    ],

    // On a document for a client abroad, when the issuing company charges VAT (Vat::note); then the client's number when HQ has it
    'zero_rated' => 'Zero-rated export of services. Customer to account for any VAT under the reverse charge in their country.',
    'client_tax' => 'Customer’s VAT or tax number: :number.',

    // The billing company of an opportunity (open-opportunity form, edit details, overview)
    'billing' => [
        'label' => 'Billing company',
        'default' => ':company (default)',
        'default_none' => 'Nigeria’s company (default)',
        'help' => 'The JBS Praxis company that signs and bills this work. Documents already sent keep the company they were issued by.',
        'only_lead' => 'Only the owner or the opportunity’s lead chooses the billing company.',
        'invalid' => 'Choose one of the active companies in Master data › Legal entities.',
        'client_tax' => 'Client’s VAT or tax number (optional)',
        'client_tax_help' => 'Printed on zero-rated documents when the client is abroad. It is the organisation’s, for all its opportunities.',
    ],

    // Sending a SOW whose VAT HQ signed differs from today's (SowTab)
    'sow_changed' => 'The VAT has changed since this version was signed for JBS Praxis. :now Take it back to draft and approve it again, so the client signs today’s VAT.',

    // Finance › Invoices › Create invoice, before a project is chosen
    'invoice_help' => 'VAT is added only when the JBS Praxis company billing the client charges VAT (Master data › Legal entities).',

    'label' => 'VAT',
    // The total line of a document without VAT (it equals the subtotal)
    'total' => 'Total',
];
