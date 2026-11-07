<?php

// InsightsArticleRejectedMail : un relecteur n’a pas publié un article envoyé via Écrire pour Insights ; la note du relecteur
// est reproduite telle quelle. Courtois, court, avec une invitation à envoyer une version revue.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => "À propos de votre article «\u{202F}:title\u{202F}»",
    'preheader' => 'Nous ne pouvons pas le publier en l’état. La note de notre éditeur est ci-dessous.',
    'headline' => 'À propos de votre article',
    'intro' => "Merci d’avoir envoyé «\u{202F}:title\u{202F}» à JBS Praxis Insights. Nous l’avons lu attentivement et nous ne pouvons pas le publier en l’état.",
    'note' => "Un mot de notre éditeur\u{00A0}:",
    'again' => 'Vous pouvez le retravailler et nous le renvoyer. Nous le lirons avec plaisir.',
    'button' => 'Envoyer une version revue',
    'reply' => 'Vous pouvez aussi répondre à cet e-mail si vous souhaitez en discuter.',
    'reason' => 'Vous recevez ce message parce que vous avez envoyé un article à JBS Praxis Insights depuis jbspraxis.com.',
];
