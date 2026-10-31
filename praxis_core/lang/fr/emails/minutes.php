<?php

// MeetingMinutesMail : le compte rendu d’une réunion client, tel que le responsable de l’opportunité l’a relu (Opportunités, PR 2).
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => "Compte rendu\u{00A0}: :title du :date",
    'preheader' => "Nos décisions et les prochaines étapes de notre réunion «\u{202F}:title\u{202F}».",
    'headline' => 'Compte rendu de notre réunion',
    'intro' => "Merci pour le temps que vous nous avez consacré le :date. Voici ce que nous avons décidé lors de notre réunion «\u{202F}:title\u{202F}» et les prochaines étapes.",
    'button' => 'Voir vos réunions avec nous',
    'corrections' => 'Si un point ne correspond pas à vos notes, répondez à cet e-mail et nous le corrigerons. Vous pouvez aussi réserver une réunion de suivi depuis la page ci-dessus.',
    'reason' => 'Vous recevez ce message parce que vous avez participé à une réunion entre JBS Praxis et :company.',
];
