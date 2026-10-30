<?php

// FreeAuditDeclinedMail : un collaborateur a décliné dans HQ une demande d’audit offert ; le visiteur est invité à
// choisir un autre créneau. Le motif de l’équipe n’apparaît jamais.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => 'Votre demande d’audit offert du :date',
    'preheader' => 'Nous ne pouvons pas confirmer le créneau demandé. Choisissez-en un autre ou répondez à cet e-mail.',
    'headline' => 'Votre demande d’audit offert',
    'intro' => 'Merci d’avoir demandé un audit et une démonstration offerts pour :company. Nous ne sommes pas en mesure de confirmer le créneau demandé.',
    'label_date' => 'Date',
    'label_time' => 'Heure',
    'time' => ':time (heure d’Afrique de l’Ouest, WAT)',
    'another' => "Si vous souhaitez toujours nous rencontrer, choisissez un autre créneau\u{00A0}: nous vous le confirmerons par e-mail.",
    'button' => 'Choisir un autre créneau',
    'reply' => 'Vous pouvez aussi répondre à cet e-mail pour nous indiquer les jours qui vous conviennent.',
    'reason' => 'Vous recevez ce message parce que vous avez demandé à JBS Praxis un audit et une démonstration offerts pour :company.',
];
