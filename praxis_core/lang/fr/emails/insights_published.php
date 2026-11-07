<?php

// InsightsArticlePublishedMail : un relecteur a publié un article envoyé par un contributeur via Écrire pour Insights sur le site.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => 'Votre article est en ligne sur JBS Praxis Insights',
    'preheader' => 'Merci de l’avoir écrit. Il est en ligne sur le site.',
    'headline' => 'Votre article est en ligne',
    'intro' => "Merci d’avoir écrit «\u{202F}:title\u{202F}». Notre équipe éditoriale l’a lu et publié sur JBS Praxis Insights.",
    'button' => 'Lire votre article',
    'credit' => 'Comme tous les articles d’Insights, il paraît sous le nom de JBS Praxis. N’hésitez pas à en partager le lien avec vos collègues.',
    'reply' => "Une autre idée\u{202F}? Vous pouvez nous l’envoyer à tout moment, ou répondre à cet e-mail.",
    'reason' => 'Vous recevez ce message parce que vous avez envoyé un article à JBS Praxis Insights depuis jbspraxis.com.',
];
