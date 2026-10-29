<?php

// MeetingApprovedMail : un collaborateur a approuvé dans HQ un audit et une démonstration offerts ; le prospect reçoit la date, l’heure et le lien.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => "Confirmé\u{00A0}: votre audit offert le :date à :time (WAT)",
    'preheader' => 'Votre audit offert est planifié. Le lien pour rejoindre la réunion figure dans cet e-mail.',
    'headline' => 'Votre audit offert est confirmé',
    'intro' => 'Nous confirmons votre audit et votre démonstration offerts pour :company. En voici les détails.',
    'label_date' => 'Date',
    'label_time' => 'Heure',
    'label_format' => 'Format',
    'time_range' => 'de :start à :end (heure d’Afrique de l’Ouest, WAT)',
    'time_single' => ':time (heure d’Afrique de l’Ouest, WAT)',
    'format_online' => 'En ligne, via le lien ci-dessous',
    'button' => 'Rejoindre la réunion',
    'prepare' => 'Invitez les collègues qui connaissent le mieux les processus à améliorer. Nous examinerons ensemble le fonctionnement actuel de votre organisation, les pertes de temps et d’argent, et ce que nous changerions en priorité.',
    'reschedule' => 'Si ce créneau ne vous convient plus, répondez à cet e-mail pour que nous en fixions un autre.',
    'reason' => 'Vous recevez ce message parce que JBS Praxis a planifié un audit et une démonstration offerts avec :company.',
];
