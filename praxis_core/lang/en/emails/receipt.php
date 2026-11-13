<?php

// ReceiptMail: a payment's receipt, to the client's billing contacts (docs/RECEIPTS.md)
return [
    'subject' => 'Receipt :receipt: payment received for invoice :invoice',
    'preheader' => 'We received your payment of :amount for invoice :invoice. The receipt is attached.',
    'headline' => 'Payment received',
    'intro' => 'Thank you. We received your payment for “:project”. The receipt is attached as a PDF.',
    'receipt' => 'Receipt',
    'invoice' => 'Invoice',
    'amount' => 'Amount received',
    'received' => 'Received on',
    'method' => 'Paid by',
    'reference' => 'Reference',
    'left' => 'Left to pay on the invoice',
    'fully_paid' => 'Invoice :invoice is now fully paid.',
    'button' => 'Open the Client Portal',
    'portal' => 'Your receipts and invoices are also in the Client Portal, under Invoices and payments. Questions? Reply to this email.',
    'reason' => 'You are receiving this because you handle invoices for :company in the JBS Praxis Client Portal.',
    'date_format' => 'j F Y',
];
