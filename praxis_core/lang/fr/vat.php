<?php

// La TVA par société JBS Praxis (App\Support\Finance\Vat) : Données de référence › Entités juridiques, et ce que disent les documents
return [
    'entity' => [
        'heading' => "TVA",
        'charges' => "Facture la TVA",
        'number' => "Numéro de TVA",
        'starts_on' => "Début de la TVA",
        'help' => "À activer une fois la société immatriculée à la TVA. Les documents datés à partir de la date de début ont la TVA\u{202F}; ce qui a déjà été envoyé garde ce qu’il avait.",
        'read_only' => "Seules les personnes autorisées à modifier les données de référence peuvent changer la TVA.",
        'rate' => "Taux pour :country dans Conformité fiscale\u{202F}: :rate\u{202F}%.",
        'no_rate' => "Aucun taux de TVA actif pour :country dans Données de référence › Conformité fiscale pour l’instant.",
        'status_on' => "Facture la TVA à partir du :date",
        'status_off' => "Pas de TVA",
        'status_number' => "N° de TVA :number",
        'errors' => [
            'number' => "Saisissez le numéro de TVA pour activer la TVA.",
            'starts_on' => "Saisissez la date de début de la TVA pour l’activer.",
            'rate' => "Ajoutez d’abord un taux de TVA actif pour :country dans Données de référence › Conformité fiscale, puis activez la TVA.",
        ],
        'countries' => ['CM' => "le Cameroun", 'NG' => "le Nigéria", 'KE' => "le Kenya", 'GLOBAL' => "le siège"],
        'attributes' => [
            'vat_number' => "numéro de TVA",
            'vat_starts_on' => "date de début de la TVA",
        ],
    ],

    'describe' => [
        'applies' => "TVA à :rate\u{202F}%\u{202F}: :company facture la TVA à partir du :date (n° de TVA :number).",
        'off' => "Pas de TVA\u{202F}: :company ne facture pas encore la TVA.",
        'not_yet' => "Pas de TVA\u{202F}: :company facture la TVA à partir du :date.",
        'no_rate' => "Pas de TVA\u{202F}: aucun taux de TVA actif pour le pays de :company dans Données de référence › Conformité fiscale.",
        'no_company' => "Pas de TVA\u{202F}: aucune société JBS Praxis active dans Données de référence › Entités juridiques ne facture ce client.",
    ],

    'sow_changed' => "La TVA a changé depuis que cette version a été signée pour JBS Praxis. :now Remettez-la en brouillon et approuvez-la de nouveau, pour que le client signe la TVA d’aujourd’hui.",

    'invoice_help' => "La TVA n’est ajoutée que si la société JBS Praxis qui facture le client facture la TVA (Données de référence › Entités juridiques).",

    'label' => "TVA",
    'total' => "Total",
];
