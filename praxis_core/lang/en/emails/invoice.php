<?php

// InvoiceMail: an invoice to the client's billing contacts, with its PDF (docs/QA-PORTAL.md D11).
return [
    'subject' => 'Invoice :number: :project',
    'preheader' => 'Invoice :number from JBS Praxis, with the details to pay it.',
    'headline' => 'Your invoice',
    'intro' => 'Here is our invoice for “:project”.',
    'number' => 'Invoice',
    'project' => 'Project',
    'amount' => 'Amount due',
    'due' => 'Due by',
    'from' => 'From',
    'date_format' => 'j F Y',
    'button' => 'Open the Client Portal',
    'pdf' => 'The invoice is attached as a PDF, with the accounts to pay into.',
    'how' => 'Put :number as the payment reference, then upload your proof of payment in the Client Portal, under Invoices and payments. Questions? Reply to this email.',
    'reason' => 'You are receiving this because you handle invoices for :company in the JBS Praxis Client Portal.',
];
