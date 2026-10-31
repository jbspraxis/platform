<?php

// MeetingInvitationMail : une réunion client organisée dans HQ ou réservée sur le portail (Opportunités, PR 2), en plus de l’invitation de Google.
// Variantes : invite, moved, cancelled. Heures d’Afrique de l’Ouest (WAT).
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'invite' => [
        'subject' => "Invitation\u{00A0}: :title le :date à :time (WAT)",
        'preheader' => 'Rejoignez la réunion avec le lien Google Meet de cet e-mail. Google Agenda vous a aussi envoyé l’invitation.',
        'headline' => 'Vous êtes invité à une réunion avec JBS Praxis',
        'intro' => "Nous avons organisé une réunion avec :company. Google Agenda vous a également envoyé une invitation\u{202F}; en voici les détails.",
    ],
    'moved' => [
        'subject' => "Nouvel horaire\u{00A0}: :title le :date à :time (WAT)",
        'preheader' => 'Notre réunion est déplacée. Le lien reste le même.',
        'headline' => 'Notre réunion change d’horaire',
        'intro' => "Nous avons déplacé notre réunion avec :company. En voici les nouveaux détails\u{202F}; le lien pour la rejoindre reste le même.",
    ],
    'cancelled' => [
        'subject' => "Annulée\u{00A0}: :title du :date",
        'preheader' => 'Cette réunion n’aura pas lieu. Réservez un autre créneau quand vous le souhaitez.',
        'headline' => 'Notre réunion est annulée',
        'intro' => 'Nous avons annulé notre réunion du :date avec :company. Google Agenda la retirera de votre agenda. Réservez un autre créneau ci-dessous, ou répondez à cet e-mail.',
    ],
    'label_meeting' => 'Réunion',
    'format' => 'En ligne, avec Google Meet',
    'agenda' => 'Ordre du jour',
    'notice' => 'Nous enregistrons une transcription de cette réunion avec Google Meet pour rédiger le compte rendu, que nous vous envoyons ensuite. Si vous préférez que nous ne le fassions pas, dites-le-nous en début de réunion.',
    'another_time' => "Besoin d’un autre créneau\u{202F}?",
    'book_link' => 'Réservez une réunion avec nous',
    'book_button' => 'Réserver un autre créneau',
    'reason' => 'Vous recevez ce message parce que JBS Praxis vous a invité à une réunion avec :company.',
];
