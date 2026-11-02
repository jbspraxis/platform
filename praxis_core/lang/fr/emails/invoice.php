<?php

// InvoiceMail : une facture aux contacts facturation du client, avec son PDF (docs/QA-PORTAL.md D11).
return [
    'subject' => "Facture :number\u{00A0}: :project",
    'preheader' => 'Facture :number de JBS Praxis, avec les coordonnées pour la régler.',
    'headline' => 'Votre facture',
    'intro' => "Voici notre facture pour «\u{00A0}:project\u{00A0}».",
    'number' => 'Facture',
    'project' => 'Projet',
    'amount' => 'Montant dû',
    'due' => 'À régler avant le',
    'from' => 'Émise par',
    'date_format' => 'j F Y',
    'button' => 'Ouvrir le portail client',
    'pdf' => 'La facture est jointe en PDF, avec les comptes à créditer.',
    'how' => "Indiquez :number comme référence du paiement, puis déposez votre justificatif de paiement sur le portail client, dans Factures et paiements. Une question\u{00A0}? Répondez à cet e-mail.",
    'reason' => 'Vous recevez ce message car vous gérez les factures de :company sur le portail client de JBS Praxis.',
];
