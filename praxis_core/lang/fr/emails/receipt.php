<?php

// ReceiptMail : le reçu d’un paiement, envoyé aux contacts facturation du client (docs/RECEIPTS.md)
return [
    'subject' => "Reçu :receipt\u{00A0}: paiement reçu pour la facture :invoice",
    'preheader' => "Nous avons bien reçu votre paiement de :amount pour la facture :invoice. Le reçu est joint.",
    'headline' => "Paiement reçu",
    'intro' => "Merci. Nous avons bien reçu votre paiement pour «\u{202F}:project\u{202F}». Le reçu est joint en PDF.",
    'receipt' => "Reçu",
    'invoice' => "Facture",
    'amount' => "Montant reçu",
    'received' => "Reçu le",
    'method' => "Mode de paiement",
    'reference' => "Référence",
    'left' => "Reste à payer sur la facture",
    'fully_paid' => "La facture :invoice est désormais entièrement réglée.",
    'button' => "Ouvrir le portail client",
    'portal' => "Vos reçus et vos factures sont aussi dans votre portail client, rubrique Factures et paiements. Une question\u{202F}? Répondez à cet e-mail.",
    'reason' => "Vous recevez cet e-mail car vous gérez les factures de :company dans le portail client JBS Praxis.",
    'date_format' => 'j F Y',
];
