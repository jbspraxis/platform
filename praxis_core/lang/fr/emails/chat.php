<?php

// ChatUnreadMail : messages d’une conversation de projet (docs/CHAT.md) non lus de l’autre côté depuis une heure.
return [
    'client_subject' => "{1} Nouveau message de votre équipe projet|[2,*] :count nouveaux messages de votre équipe projet",
    'client_preheader' => ":name vous a écrit au sujet de :project.",
    'client_headline' => "Votre équipe projet vous a écrit",
    'client_intro' => "{1} Vous avez 1 message non lu au sujet de :project dans votre portail client.|[2,*] Vous avez :count messages non lus au sujet de :project dans votre portail client.",
    'client_button' => "Ouvrir la conversation",
    'client_reason' => "Vous recevez cet e-mail car un message qui vous est destiné dans le portail client JBS Praxis n’a pas été lu dans l’heure. Nous envoyons au plus un e-mail par heure pour chaque conversation.",
    'staff_subject' => "Message non lu de :client",
    'staff_preheader' => ":name a écrit au sujet de :project et personne ne l’a lu chez JBS Praxis depuis une heure.",
    'staff_headline' => ":client attend une réponse",
    'staff_intro' => "{1} 1 message de :client au sujet de :project n’a pas été lu depuis une heure.|[2,*] :count messages de :client au sujet de :project n’ont pas été lus depuis une heure.",
    'staff_button' => "Répondre dans Messages clients",
    'staff_reason' => "Envoyé à l’équipe projet de :project lorsqu’un message du client n’est pas lu dans l’heure.",
    'more' => "{1} Et 1 message plus ancien.|[2,*] Et :count messages plus anciens.",
    'at' => ":name · :time",
];
