<?php

// ProposalMail : une version de la proposition, avec son PDF et un lien pour la relire dans le portail (Opportunités, PR 4).
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => "Notre proposition\u{00A0}: :title",
    'subject_version' => "Notre proposition, version :n\u{00A0}: :title",
    'preheader' => "Notre proposition pour «\u{202F}:title\u{202F}»\u{202F}: lisez-la, commentez-la et approuvez-la en ligne.",
    'headline' => 'Notre proposition',
    'intro' => "Merci pour le temps que vous nous avez consacré. Voici notre proposition pour «\u{202F}:title\u{202F}». Vous pouvez la lire en ligne, commenter chaque partie et l’approuver depuis la page ci-dessous.",
    'intro_version' => "Merci pour vos commentaires. Voici la version :n de notre proposition pour «\u{202F}:title\u{202F}». Vous pouvez la lire en ligne, commenter chaque partie et l’approuver depuis la page ci-dessous.",
    'changes' => 'Ce qui a changé depuis la version :n',
    'reference' => 'Référence',
    'version' => 'version :n',
    'total' => 'Total TTC',
    'valid_until' => "Valable jusqu’au",
    'date_format' => 'j F Y',
    'button' => 'Relire la proposition',
    'pdf' => 'La proposition est aussi jointe en PDF.',
    'how' => "L’approuver sur cette page enregistre votre nom et votre fonction\u{202F}; c’est l’énoncé des travaux qui suivra que nous signerons ensemble. Une question\u{202F}? Répondez à cet e-mail ou réservez une réunion de suivi depuis la page.",
    'reason' => 'Vous recevez ce message parce que JBS Praxis a préparé une proposition pour :company.',
];
