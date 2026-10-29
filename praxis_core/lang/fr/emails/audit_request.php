<?php

// AuditRequested : la copie du visiteur après sa demande d’audit et de démonstration offerts. AuditRequestAlert : en anglais uniquement, voir lang/en.
return [
    'subject' => 'Nous avons bien reçu votre demande d’audit offert',
    'preheader' => 'Nous vous confirmerons le rendez-vous par e-mail, avec le lien pour y participer.',
    'intro' => 'Merci d’avoir demandé un audit et une démonstration offerts à JBS Praxis. Notre équipe vous confirmera le rendez-vous par e-mail et vous enverra le lien pour y participer.',
    'requested_time' => 'Créneau demandé',
    'topic' => 'Sujet',
    'change' => 'Pour modifier le créneau ou le sujet, répondez à cet e-mail.',

    // Date et heure demandées (heure de Lagos). Formats isoFormat de Carbon : « jeudi 5 novembre 2026, 10 h 30 (heure d’Afrique de l’Ouest, WAT) ».
    'when' => ':date, :time (heure d’Afrique de l’Ouest, WAT)',
    'time_format' => "H\u{202F}[h]\u{202F}mm",

    // Les sujets proposés par le formulaire (valeurs enregistrées à gauche) ; tout autre sujet est affiché tel que saisi.
    'topics' => [
        'Performance Audit' => 'Audit de performance et de conformité',
        'Data Architecture' => 'Revue de l’ingénierie des données',
        'Custom Software' => 'Développement de logiciel sur mesure ou d’ERP',
        'Retainer' => 'Maintenance et assistance continues',
    ],
];
