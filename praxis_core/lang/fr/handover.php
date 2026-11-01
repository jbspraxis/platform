<?php

// Livraisons de jalon (docs/OPPORTUNITIES.md section 12, PR 6). defaults, ticket et signoff sont lus par le client,
// dans la langue de l’opportunité.
return [

    'severity' => ['Low' => "Faible", 'Medium' => "Moyenne", 'Critical' => "Critique"],

    'defaults' => [
        'title' => "Livraison du jalon\u{00A0}: :milestone",
        'agenda' => "Livraison du jalon :milestone.",
        'delivered' => "Ce que ce jalon a livré\u{00A0}:",
        'more' => "… et :count autre tâche|… et :count autres tâches",
        'points' => "Vos questions et les points à corriger\u{00A0}: nous consignons chacun d’eux et vous indiquons comment il a été traité.",
    ],

    'form' => [
        'title' => "Planifier la livraison du jalon",
        'callout' => "Cette réunion livre le jalon :milestone. Une fois sa transcription reçue, l’IA liste les points soulevés pour que vous les confirmiez comme incidents.",
    ],

    'tag' => "Livraison du jalon\u{00A0}: :milestone",
    'counts' => [
        'draft' => "{1} 1 point à examiner|[2,*] :count points à examiner",
        'confirmed' => "{1} 1 incident|[2,*] :count incidents",
    ],

    'points' => [
        'title' => "Points soulevés",
        'help' => "Confirmez chaque point pour créer un incident sur le projet\u{00A0}: il apparaît dans Qualité et incidents, sur le tableau de sprint et dans le portail du client. Rien n’est créé sans votre confirmation.",
        'help_read' => "Le responsable de l’opportunité confirme chaque point comme incident sur le projet.",
        'waiting' => "Une fois la transcription reçue, l’IA liste ici les points soulevés.",
        'drafting' => "L’IA liste les points soulevés…",
        'failed' => "L’IA n’a pas pu lister les points\u{00A0}: :reason",
        'none_found' => "L’IA n’a trouvé aucun point à traiter dans cette réunion. Ajoutez-en un vous-même si elle en a manqué un.",
        'not_drafted' => "Les points soulevés n’ont pas encore été listés.",
        'draft' => "Lister les points",
        'redraft' => "Lister à nouveau les points",
        'retry' => "Réessayer",
        'redraft_help' => "Une nouvelle liste remplace les points encore à examiner. Les points confirmés ou écartés restent.",
        'empty' => "Aucun point pour l’instant.",
        'awaiting' => "En attente de la confirmation du responsable.",
        'by_hand' => "Ajouté à la main",
        'left_out' => "Écartés (:count)",
        'statuses' => ['draft' => "À examiner", 'confirmed' => "Incident créé", 'dismissed' => "Écarté"],
        'issues' => "Incidents créés",
        'issue' => "Incident\u{00A0}: :status",
        'labelled' => ":label\u{00A0}: :value",
        'closed_on' => ":status le :date",
        'removed' => "Incident supprimé",
        'reply' => "Dernière réponse\u{00A0}: :reply",
        'open_issue' => "Ouvrir dans Qualité et incidents",
    ],

    'fields' => [
        'title' => "Point",
        'detail' => "Ce qui a été demandé",
        'raised_by' => "Soulevé par",
        'severity' => "Gravité",
        'task' => "Tâche du jalon",
        'no_task' => "Aucune tâche",
    ],

    'actions' => [
        'confirm' => "Créer l’incident",
        'dismiss' => "Écarter",
        'restore' => "Rétablir",
        'add' => "Ajouter un point",
        'add_title' => "Ajouter un point",
        'add_submit' => "Ajouter le point",
        'confirm_all' => "{1} Créer l’incident|[2,*] Créer les :count incidents",
        'confirm_all_ask' => "Créer un incident pour chaque point encore à examiner\u{202F}? Le client les voit dans son portail.",
        'copy' => "Copier pour la recette",
        'copied' => "Copié",
    ],

    'done' => [
        'confirmed' => "Incident créé\u{00A0}: «\u{202F}:title\u{202F}». Il figure dans Qualité et incidents et sur le tableau de sprint.",
        'confirmed_all' => "{0} Aucun incident créé\u{00A0}: chaque point était déjà confirmé ou écarté.|{1} 1 incident créé. Il figure dans Qualité et incidents et sur le tableau de sprint.|[2,*] :count incidents créés. Ils figurent dans Qualité et incidents et sur le tableau de sprint.",
        'dismissed' => "«\u{202F}:title\u{202F}» écarté\u{00A0}: aucun incident créé.",
    ],

    'errors' => [
        'milestone' => "Ce jalon n’appartient pas au projet de cette opportunité. Ouvrez le tableau de sprint du projet et réessayez.",
        'cannot_schedule' => "Vous ne pouvez pas planifier de réunion pour cette opportunité. Demandez à un membre de son équipe, ou à une personne qui gère les opportunités, de planifier la livraison du jalon.",
        'decided' => "Ce point a déjà été confirmé ou écarté. La liste est maintenant à jour.",
        'no_project' => "Cette opportunité n’a pas de projet\u{00A0}: aucun incident ne peut être créé.",
        'title' => "Donnez un titre au point.",
        'title_long' => "Raccourcissez le titre à 200\u{00A0}caractères au plus.",
        'detail_long' => "Raccourcissez la demande à 2\u{202F}000\u{00A0}caractères au plus.",
        'raised_by_long' => "Raccourcissez le nom à 150\u{00A0}caractères au plus.",
        'severity' => "Choisissez Faible, Moyenne ou Critique.",
        'task' => "Choisissez une tâche de ce projet, ou aucune tâche.",
        'invalid' => "Impossible d’enregistrer cette valeur. Actualisez la page et réessayez.",
        'full' => "Cette réunion compte déjà :max points, le maximum possible.",
    ],

    'ticket' => [
        'raised_by' => "Soulevé par :name lors de la livraison du jalon «\u{202F}:meeting\u{202F}» du :date.",
        'raised' => "Soulevé lors de la livraison du jalon «\u{202F}:meeting\u{202F}» du :date.",
    ],

    'signoff' => [
        'heading' => "Points soulevés lors de «\u{202F}:title\u{202F}» (:date)",
        'line' => ":number. :title (:severity)\u{00A0}: :status.",
        'closed_on' => ":status le :date",
        'removed' => "Incident supprimé",
        'reply' => "Dernière réponse\u{00A0}: «\u{202F}:reply\u{202F}»",
        'none' => "Aucun point n’a été confirmé comme incident.",
    ],

    'notify' => [
        'title' => "Point de livraison de jalon sur votre tâche",
        'message' => "«\u{202F}:title\u{202F}», soulevé lors d’une livraison de jalon, concerne une tâche qui vous est attribuée. Consultez-le dans Qualité et incidents.",
    ],

    'board' => [
        'title' => "Livraison du jalon|Livraison des jalons",
        'none' => "Aucune réunion de livraison pour ce jalon.",
        'no_milestones' => "Ce sprint ne contient encore aucune tâche de jalon\u{00A0}: rien à livrer pour l’instant.",
        'schedule' => "Planifier la livraison",
        'schedule_another' => "En planifier une autre",
        'no_opportunity' => "Ce projet n’a pas d’opportunité\u{00A0}: la livraison de ses jalons ne peut pas être planifiée ici, car les réunions appartiennent à une opportunité.",
        'no_permission' => "Un membre de l’équipe de l’opportunité, ou une personne qui gère les opportunités, la planifie.",
        'when' => ":date, :time (WAT)",
        'open' => "Ouvrir la réunion",
        'review' => "{0} Aucun point à examiner|{1} 1 point à examiner|[2,*] :count points à examiner",
        'issue_count' => "{0} aucun incident pour l’instant|{1} 1 incident (ouverts\u{00A0}: :open)|[2,*] :count incidents (ouverts\u{00A0}: :open)",
        'issues' => "{1} Incident ouvert|[2,*] :count incidents ouverts",
    ],
];
