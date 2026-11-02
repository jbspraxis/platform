<?php

// Les factures telles que le client les reçoit (App\Support\Finance\InvoicePdf ; docs/QA-PORTAL.md D11)
return [
    'pdf' => [
        'invoice' => 'Facture',
        'tax_invoice' => 'Facture',
        'from' => 'Émise par',
        'to' => 'Facturée à',
        'tax_number' => "Numéro d’identification fiscale\u{00A0}: :number",
        'vat_number' => "Numéro de TVA\u{00A0}: :number",
        'registration' => "Numéro d’immatriculation\u{00A0}: :number",
        'issued' => 'Date',
        'due' => 'À régler avant le',
        'project' => 'Projet',
        'reference' => 'Référence',
        'details' => 'Détail',
        'how_to_pay' => 'Comment régler',
        'bank' => 'Banque ou prestataire',
        'account_name' => 'Titulaire du compte',
        'account_number' => 'Numéro de compte',
        'routing' => 'Code guichet ou SWIFT',
        'currency' => 'Devise',
        'no_accounts' => 'Votre chef de projet vous envoie les coordonnées du compte à créditer.',
        'pay_note' => "Indiquez :number comme référence du paiement, puis déposez votre preuve de paiement sur le portail client, dans Factures et paiements.",
        'paid' => 'Payée',
        'paid_on' => 'Payée le :date',
        'footer' => 'JBS Praxis · :number',
    ],
    'portal' => [
        'pdf' => 'Télécharger le PDF',
        'pdf_label' => 'Télécharger la facture :number en PDF',
    ],
];
