<?php

// Planifier les jalons (App\Livewire\Hq\Operations\ScrumMaster, PR 5b). Mêmes clés que lang/en/planner.php.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'title' => 'Planifier les jalons',
    'draft_pending' => "Le plan de ce projet se trouve dans le brouillon du projet issu de l’énoncé des travaux signé. Relisez-le et cliquez sur Créer le projet\u{202F}; revenez ensuite ici pour planifier un jalon à la fois.",
    'open_draft' => 'Ouvrir le brouillon du projet',
    'ready' => 'Les suggestions sont prêtes ci-dessous.',
    'failed' => "L’IA n’a pas pu planifier\u{00A0}: :message",

    'scope' => [
        'label' => 'Périmètre',
        'sources' => "Sources utilisées\u{00A0}: :list. Modifiez le texte avant de lancer\u{202F}; l’IA le lit tel quel.",
        'no_sources' => "Aucune opportunité ne porte le périmètre de ce projet\u{202F}: saisissez-le ou collez-le ici.",
        'help' => "L’assistant planifie à partir de ce texte et du plan adopté. Jamais les transcriptions complètes\u{202F}: il peut demander celle de la réunion qui réglerait une question.",
    ],

    'sources' => [
        'sow' => 'énoncé des travaux signé :ref',
        'proposal' => 'la proposition approuvée',
        'questionnaire' => 'les réponses au questionnaire',
        'notes' => 'notes de :count réunion|notes de :count réunions',
        'change_reports' => ':count avenant approuvé|:count avenants approuvés',
        'legacy' => 'proposition signée :ref',
    ],

    'milestone' => [
        'label' => 'Jalon à planifier',
        'suggest' => 'Suggérer des tâches pour ce jalon',
        'help' => "L’IA suggère de nouvelles tâches et des modifications des tâches de ce jalon, et signale celles qui semblent en double ou hors périmètre. Elle ne retire jamais une tâche et ne nomme personne. Environ 0,05 à 0,15\u{00A0}$ du budget IA.",
    ],

    'progress' => [
        'title' => "L’IA planifie",
        'text' => "Elle lit le périmètre et le plan adopté. Cela prend une minute ou deux\u{202F}; vous pouvez quitter cette page et revenir.",
        'transcript' => "Elle a demandé la transcription de «\u{202F}:meeting\u{202F}» et planifie de nouveau avec.",
        'why' => "Pourquoi\u{00A0}: :why",
    ],

    'review' => [
        'title' => 'Le plan adopté',
        'intro' => 'Les suggestions pour :milestone sont à l’intérieur. Cochez celles à garder.',
        'none' => 'L’IA n’avait rien à ajouter à ce jalon.',
        'expand_all' => 'Tout déplier',
        'collapse_all' => 'Tout replier',
        'done' => ':done sur :total terminées',
        'suggestions' => ':count suggestion|:count suggestions',
        'no_tasks' => 'Pas encore de tâches.',
        'no_lead' => 'Sans responsable',
        'hours' => ":hours\u{00A0}h",
        'no_estimate' => 'sans estimation',
        'new' => 'Nouvelle',
        'edit' => 'Modification suggérée',
        'note' => 'Remarque',
        'why' => "Pourquoi\u{00A0}: :why",
    ],

    'save' => 'Enregistrer la suggestion cochée (:count)|Enregistrer les suggestions cochées (:count)',
    'save_help' => "Les suggestions non cochées sont écartées. Rien n’est jamais retiré, et aucun jalon n’est ajouté.",
    'saved' => ':count suggestion enregistrée. Les autres ont été écartées.|:count suggestions enregistrées. Les autres ont été écartées.',

    'whole' => [
        'title' => 'Plan à relire',
        'draft' => 'Rédiger le plan',
        'help' => "Pour un projet qui n’a pas encore de jalons\u{00A0}: l’IA rédige ses jalons et ses tâches à partir du périmètre. Environ 0,05 à 0,15\u{00A0}$ du budget IA.",
        'intro' => "Vérifiez chaque jalon et chaque tâche. L’enregistrement les ajoute au projet, deux semaines chacun à partir d’aujourd’hui, avec des poids répartis\u{202F}; fixez leurs vraies dates et leurs poids sur la page du projet.",
        'code' => 'J:n',
        'tasks' => ':count tâche|:count tâches',
        'remove_task' => "Écarter «\u{202F}:task\u{202F}»",
        'remove_milestone' => 'Écarter ce jalon',
        'remove_confirm' => "Écarter ce jalon et ses tâches\u{202F}?",
        'save' => 'Enregistrer le plan',
        'save_help' => 'Les jalons et les tâches sont ajoutés à la page du projet.',
        'saved' => 'Plan enregistré. Ses jalons et ses tâches figurent sur la page du projet.',
        'empty_title' => 'Pas encore de brouillon',
        'empty' => "Rédigez le plan à partir du périmètre. Ses jalons et ses tâches apparaissent ici pour que vous les vérifiiez avant de les enregistrer.",
    ],

    'errors' => [
        'rights' => "Seul le responsable du projet ou une personne autorisée à créer des projets planifie ses jalons.",
        'scope' => "Donnez à l’IA un périmètre d’au moins 50\u{202F}caractères.",
        'scope_long' => 'Limitez le périmètre à :max caractères.',
        'milestone' => 'Choisissez le jalon à planifier.',
        'state' => "Ce projet ne peut pas être planifié ainsi pour le moment. Rechargez la page.",
        'stale' => "Les suggestions ont changé depuis le chargement de cette page\u{202F}: rien n’a été enregistré. Les voici telles qu’elles sont maintenant.",
        'gone' => "Ces suggestions ont été enregistrées ou remplacées dans un autre onglet\u{202F}: rien n’a été enregistré ici.",
        'plan_exists' => "Ce projet a maintenant des jalons\u{202F}: ce brouillon n’a pas été ajouté. Planifiez plutôt un jalon à la fois.",
        'empty' => "Le brouillon n’a plus de jalons.",
    ],

    'bell' => [
        'title' => 'Vos tâches ont été mises à jour',
        'changed' => "Le plan de «\u{202F}:project\u{202F}» a modifié :count de vos tâches.|Le plan de «\u{202F}:project\u{202F}» a modifié :count de vos tâches.",
    ],
];
