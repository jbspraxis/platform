<?php

// SowMail : une version de l’énoncé des travaux, avec son PDF et un lien pour le signer dans le portail (Opportunités PR 5).
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => "Énoncé des travaux à signer\u{00A0}: :title",
    'subject_version' => "Énoncé des travaux, version :n, à signer\u{00A0}: :title",
    'preheader' => "L’énoncé des travaux pour «\u{202F}:title\u{202F}»\u{00A0}: lisez-le et signez-le en ligne avec un code à usage unique.",
    'headline' => 'Votre énoncé des travaux',
    'intro' => "Merci d’avoir approuvé notre proposition. Voici l’énoncé des travaux pour «\u{202F}:title\u{202F}», signé pour JBS Praxis. Vous pouvez le lire et le signer en ligne depuis la page ci-dessous.",
    'intro_version' => "Merci pour vos commentaires. Voici la version :n de l’énoncé des travaux pour «\u{202F}:title\u{202F}», signée pour JBS Praxis. Vous pouvez la lire et la signer en ligne depuis la page ci-dessous.",
    'changes' => 'Ce qui a changé depuis la version :n',
    'reference' => 'Référence',
    'version' => 'version :n',
    'between' => 'Entre vous et',
    'total' => 'Total TTC',
    'valid_until' => 'À signer avant le',
    'date_format' => 'j F Y',
    'button' => 'Lire et signer',
    'pdf' => 'L’énoncé des travaux est aussi joint en PDF.',
    'how' => "Pour signer, indiquez votre nom et votre fonction, dessinez votre signature, puis saisissez le code que nous vous envoyons par e-mail. La signature émet la facture d’acompte dans votre portail. Des questions ou des changements\u{202F}? Répondez à cet e-mail ou demandez des changements depuis la page.",
    'reason' => 'Vous recevez ce message parce que JBS Praxis a préparé un énoncé des travaux pour :company.',
];
