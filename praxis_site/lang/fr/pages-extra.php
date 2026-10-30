<?php

// Texte des pages « Notre méthode » et « Sécurité et données » (docs/LAUNCH_COPY.md). Règles du français de
// docs/BRAND.md : guillemets « », espace fine insécable avant ; : ! ?
return [

    'how' => [
        'title' => 'Notre méthode | JBS Praxis',
        'description' => "La méthode Praxis\u{202F}: nous diagnostiquons votre fonctionnement, concevons le fonctionnement cible, construisons le système et restons jusqu’à ce qu’il tienne. Le code et les données vous appartiennent.",
        'eyebrow' => 'La méthode Praxis',
        'h1' => 'Notre méthode.',
        'lead' => 'La méthode Praxis fait passer un domaine de votre organisation de son fonctionnement actuel à un système qui en rend compte.',
        'formerly' => 'Anciennement le Praxis Framework.',
        'you_get' => "Vous recevez\u{202F}:",
        'stages' => [
            ['name' => 'Diagnostiquer', 'text' => "Nous travaillons avec les équipes et cartographions le fonctionnement réel d’un domaine\u{202F}: qui fait quoi, où vivent les chiffres, où se perdent le temps et l’argent.", 'get' => 'une vision claire et le rapport de l’audit gratuit.'],
            ['name' => 'Concevoir', 'text' => "Nous concevons le fonctionnement cible\u{202F}: le processus, les contrôles, qui valide quoi, et les quelques indicateurs qui comptent.", 'get' => 'une conception lisible, à valider.'],
            ['name' => 'Construire', 'text' => 'Nous construisons et paramétrons le système autour de cette conception, le relions aux outils que vous conservez et reprenons vos données.', 'get' => 'un système opérationnel, testé avec vos équipes.'],
            ['name' => 'Pérenniser', 'text' => "Nous restons jusqu’à ce que tout tienne\u{202F}: formation des équipes, suivi des premiers cycles, corrections nées du terrain, puis remise des clés.", 'get' => 'un système que vos équipes font tourner, avec un code et des données qui vous appartiennent.'],
        ],
        'expect_title' => 'Ce que vous pouvez attendre',
        'expect' => [
            'Le code et les données vous appartiennent.',
            'Aucune licence par utilisateur.',
            'Anglais et français dès le premier jour.',
            'Une seule équipe, du premier rendez-vous à la remise des clés.',
        ],
        'audit_title' => 'Tout commence par un audit gratuit.',
        'audit_text' => 'Environ une heure, sur un domaine de votre organisation. Vous repartez avec une vision claire de son fonctionnement actuel et des priorités.',
        'guide_title' => 'Recevoir le guide de la méthode Praxis par e-mail',
    ],

    'security' => [
        'title' => 'Sécurité et données | JBS Praxis',
        'description' => "Comment JBS Praxis protège vos données\u{202F}: rôles et permissions, journaux d’audit, stockage privé, processus alignés sur ISO\u{202F}27001, NDPA et loi camerounaise sur la protection des données.",
        'eyebrow' => 'Sécurité et données',
        'h1' => 'Vos données restent les vôtres.',
        'lead' => "Les systèmes que nous construisons protègent l’essentiel dès la conception\u{202F}: qui peut voir, qui peut modifier, et une trace de tout.",
        'blocks' => [
            ['icon' => 'key-round', 'title' => 'Rôles et permissions', 'text' => "Chaque écran et chaque action dépendent du rôle (consulter, créer, modifier, valider, exporter)\u{202F}: chacun voit ce dont son travail a besoin, rien de plus."],
            ['icon' => 'scroll-text', 'title' => 'Une trace de tout', 'text' => "Les actions sensibles sont inscrites dans un journal d’audit\u{202F}: qui a fait quoi, quand et d’où."],
            ['icon' => 'split', 'title' => 'Séparation des tâches', 'text' => 'Celui qui prépare un paiement ou une paie ne peut pas le valider. Les contrôles font partie du travail, ils ne s’ajoutent pas après.'],
            ['icon' => 'lock', 'title' => 'Privé par défaut', 'text' => 'Documents, CV, reçus et preuves de paiement sont stockés en privé et ne s’ouvrent que par des liens signés et autorisés.'],
            ['icon' => 'shield-check', 'title' => "Aligné sur ISO\u{202F}27001", 'text' => "Nos processus suivent les pratiques ISO\u{202F}27001. Nous ne revendiquons pas une certification que nous ne détenons pas."],
            ['icon' => 'user-round-check', 'title' => 'Vos droits', 'text' => "Nous respectons la loi nigériane sur la protection des données (NDPA) et la loi camerounaise. Demandez ce que nous détenons à votre sujet, faites-le corriger ou supprimer\u{202F}: écrivez-nous à l’adresse ci-dessous."],
        ],
        'contact_title' => 'Questions sur vos données',
        'contact_text' => 'Écrivez-nous, nous vous répondrons sous quelques jours ouvrés.',
        'privacy_link' => 'Lire notre politique de confidentialité',
    ],
];
