<?php

// Invoices as the client gets them (App\Support\Finance\InvoicePdf; docs/QA-PORTAL.md D11)
return [
    'pdf' => [
        'invoice' => 'Invoice',
        'tax_invoice' => 'Tax invoice',
        'from' => 'From',
        'to' => 'Billed to',
        'tax_number' => 'Tax number: :number',
        'vat_number' => 'VAT number: :number',
        'registration' => 'Registration number: :number',
        'issued' => 'Date',
        'due' => 'Due by',
        'project' => 'Project',
        'reference' => 'Reference',
        'details' => 'Details',
        'how_to_pay' => 'How to pay',
        'bank' => 'Bank or provider',
        'account_name' => 'Account name',
        'account_number' => 'Account number',
        'routing' => 'Sort code or SWIFT',
        'currency' => 'Currency',
        'no_accounts' => 'Your project lead sends you the account details to pay into.',
        'pay_note' => 'Put :number as the payment reference, then upload your proof of payment in the Client Portal, under Invoices and payments.',
        'paid' => 'Paid',
        'paid_on' => 'Paid on :date',
        'footer' => 'JBS Praxis · :number',
    ],
    'portal' => [
        'pdf' => 'Download PDF',
        'pdf_label' => 'Download invoice :number as a PDF',
    ],
];
