<?php

// Le brouillon du projet, dans l’onglet Projet de l’opportunité (Opportunités PR 5) : jalons issus de l’énoncé des travaux signé,
// tâches, rôles, puis Créer le projet. Mêmes clés que lang/en/project_draft.php.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'title' => 'Brouillon du projet',
    'from' => "Issu de l’énoncé des travaux :ref, version :n · total du contrat :total HT",
    'lead' => "Responsable du projet\u{00A0}: :name (le responsable de l’opportunité)",
    'read_only' => "Vous pouvez lire ce brouillon. Le dirigeant ou le responsable de l’opportunité le modifie et crée le projet.",
    'later' => 'Plus tard',
    'unavailable' => "L’IA n’a pas pu planifier les tâches (pas de clé Claude, ou budget mensuel épuisé)\u{202F}: les jalons de l’énoncé des travaux sont là, sans tâches. Ajoutez-les à la main, ou relancez la planification quand l’IA sera disponible.",

    'statuses' => [
        'drafting' => 'En cours de rédaction',
        'ready' => 'Prêt à relire',
        'created' => 'Projet créé',
    ],

    'drafting' => [
        'title' => "L’IA planifie le projet",
        'text' => "Elle donne à chaque jalon de l’énoncé des travaux signé son objectif et ses tâches, et liste les rôles nécessaires. Cela prend quelques minutes\u{202F}; vous pouvez quitter cette page et revenir.",
        'progress' => 'Avancement de la planification',
        'failed' => "L’IA n’a pas pu planifier les tâches\u{00A0}: :message Les jalons sont là pour être planifiés à la main.",
    ],

    'redraft' => [
        'button' => 'Planifier à nouveau avec l’IA',
        'title' => 'Planifier à nouveau les tâches avec l’IA',
        'intro' => "Elle remplace l’objectif et les tâches de chaque jalon, ainsi que les rôles. Les dates, poids et montants restent tels quels.",
        'help' => "Facultatif\u{00A0}: ce sur quoi insister, la taille des tâches, les rôles à utiliser.",
        'confirm' => "Planifier à nouveau\u{202F}? Vos modifications des objectifs, des tâches et des rôles seront remplacées.",
        'submit' => 'Planifier à nouveau',
    ],

    'project' => [
        'title' => 'Le projet',
        'intro' => "Vérifiez le nom, la description et la date de début. Changer la date de début décale chaque jalon d’autant de jours.",
        'name' => 'Nom du projet',
        'description' => 'Description',
        'start' => 'Date de début',
        'start_help' => "Le premier jour ouvré après la signature, sauf si vous la changez.",
    ],

    'roles' => [
        'title' => 'Rôles et titulaires',
        'intro' => "Choisissez une personne pour chaque rôle\u{00A0}: elle devient responsable des tâches de ce rôle. Un rôle sans personne laisse ses tâches non attribuées.",
        'role' => 'Rôle',
        'person' => 'Personne',
        'nobody' => 'Personne pour l’instant',
        'picked' => 'Choisie',
        'none' => 'Pas encore de rôles.',
        'add' => 'Ajouter un rôle',
        'new' => 'Nouveau rôle',
        'remove' => "Retirer le rôle «\u{202F}:role\u{202F}»",
    ],

    'milestones' => [
        'label' => 'Jalons et leurs tâches',
        'name' => 'Jalon',
        'start' => 'Début',
        'due' => 'Échéance',
        'goal' => 'Objectif',
        'own' => "ajouté ici, 0\u{202F}%",
        'add' => "Ajouter un jalon à 0\u{202F}%",
        'remove' => 'Retirer',
        'remove_confirm' => "Retirer ce jalon et ses tâches du brouillon\u{202F}?",
    ],

    'tasks' => [
        'count' => ':count tâche|:count tâches',
        'task' => 'Tâche :n',
        'role' => 'Rôle',
        'no_role' => 'Aucun rôle',
        'hours' => 'Heures',
        'milestone' => 'Jalon',
        'description' => "Description et critères d’acceptation",
        'none' => 'Pas encore de tâches dans ce jalon.',
        'add' => 'Ajouter une tâche',
        'remove' => 'Retirer la tâche :n',
    ],

    'create' => [
        'title' => 'Créer le projet',
        'intro' => "La création inscrit ces jalons et ces tâches dans le projet, fixe son responsable et ses dates, et l’active. L’équipe de réalisation est prévenue que le plan est prêt.",
        'summary' => ':count tâche dans :milestones jalons|:count tâches dans :milestones jalons',
        'button' => 'Créer le projet',
        'confirm' => "Créer le projet à partir de ce brouillon\u{202F}? Le plan se modifie ensuite sur la page du projet.",
        'done' => 'Projet créé. Ses jalons et ses tâches figurent ci-dessous.',
        'who' => "Le dirigeant ou le responsable de l’opportunité (:name) crée le projet à partir de ce brouillon.",
        'errors' => [
            'created' => 'Le projet a déjà été créé à partir de ce brouillon.',
            'running' => "L’IA planifie encore. Créez le projet une fois son brouillon arrivé.",
            'title' => 'Donnez un nom au projet.',
            'start' => 'Choisissez la date de début du projet.',
            'dates' => "Chaque jalon a besoin d’une date de début et d’une échéance, et doit finir après avoir commencé.",
            'weights' => "Le total des poids des jalons ne fait plus 100\u{202F}%.",
            'project' => "Le projet créé à la signature de l’énoncé des travaux est introuvable. Demandez à un administrateur.",
            'lead' => "L’opportunité n’a pas de responsable, ou son responsable ne fait plus partie du personnel actif. Choisissez le responsable sur l’opportunité (Vue d’ensemble › Modifier les informations)\u{202F}; ce brouillon le reprend aussitôt.",
            'plan_exists' => "Ce projet a déjà des jalons\u{202F}: un second plan s’y ajouterait. Complétez plutôt le plan depuis la page du projet.",
            'stale' => "Le brouillon a changé depuis le chargement de cette page\u{202F}: rien n’a été créé. Le voici tel qu’il est maintenant. Vérifiez-le et cliquez de nouveau sur Créer le projet.",
        ],
    ],

    'errors' => [
        'not_saved' => "Votre dernière modification n’a pas été enregistrée\u{202F}: le brouillon a changé entre-temps. Le voici tel qu’il est maintenant.",
        'unavailable' => "L’IA n’est pas disponible pour le moment (pas de clé Claude, ou budget mensuel épuisé).",
    ],

    'notify' => [
        'ready_title' => "Brouillon du projet prêt\u{00A0}: :title",
        'ready' => "Les jalons et les tâches sont rédigés à partir de l’énoncé des travaux signé. Relisez-les, puis cliquez sur Créer le projet.",
        'failed_title' => "Brouillon du projet sans tâches\u{00A0}: :title",
        'failed' => "L’IA n’a pas pu planifier les tâches\u{00A0}: :message Les jalons sont prêts à être planifiés à la main.",
        'manual_title' => "Brouillon du projet à planifier à la main\u{00A0}: :title",
        'manual' => "L’énoncé des travaux est signé. L’IA n’est pas disponible\u{202F}: ajoutez vous-même les tâches à ses jalons, puis cliquez sur Créer le projet.",
    ],
];
