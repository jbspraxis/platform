<?php

// La page derrière le lien de désabonnement des diffusions (hôte du portail, /email/unsubscribe/…, docs/CAMPAIGNS.md 4.5).
// Le désabonnement arrête tout envoi marketing à l’adresse : diffusions et prospection. Formulation à confirmer par le conseil.
return [
    'unsubscribe' => [
        'title' => 'Préférences e-mail',
        'heading' => "Vous désabonner des nouvelles de JBS Praxis\u{202F}?",
        'body' => 'Nous cesserons d’envoyer nos actualités, analyses et invitations à :email.',
        'body_plain' => 'Nous cesserons d’envoyer nos actualités, analyses et invitations à cette adresse.',
        'keeps' => 'Les e-mails liés à notre travail avec vous, comme les factures et les propositions, continueront de vous parvenir.',
        'button' => 'Me désabonner',
        'done_heading' => 'Vous êtes désabonné',
        'done_body' => 'Nous n’enverrons plus d’actualités, d’analyses ni d’invitations à :email.',
        'done_body_plain' => 'Nous n’enverrons plus d’actualités, d’analyses ni d’invitations à cette adresse.',
        'mistake' => 'S’il s’agit d’une erreur, répondez à l’un de nos e-mails ou écrivez-nous à :contact.',
        'invalid_heading' => 'Ce lien ne fonctionne pas',
        'invalid_body' => 'Il est peut-être incomplet. Écrivez-nous à :contact et nous retirerons votre adresse.',
        'preview' => 'Ceci est un aperçu depuis un e-mail de test : le bouton ne désabonne personne.',
        'other_language' => 'English',
    ],
];
