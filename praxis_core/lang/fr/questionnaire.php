<?php

// Onglet Questionnaire de la page d’une opportunité (docs/OPPORTUNITIES.md, section 4, ligne 3 ; docs/BRAND.md,
// sections 4, 5 et 12). Mêmes clés que lang/en/questionnaire.php. Typographie : espace fine insécable (U+202F)
// avant ; ? ! % et à l’intérieur des « », espace insécable (U+00A0) avant les deux-points, apostrophe ’.

return [
    'empty_title' => "Aucun questionnaire pour l’instant",
    'intro' => "Avant la rédaction de la proposition, l’IA demande au responsable de l’opportunité ce qui reste à préciser. Elle lit tout ce qui y est classé\u{00A0}: l’organisation, l’estimation du coût de la non-qualité et les notes de réunion.",
    'only_lead' => ":name, responsable de l’opportunité, rédige et remplit ce questionnaire.",
    'no_lead' => "Désignez un responsable dans Modifier les informations, onglet Vue d’ensemble\u{00A0}: seul le responsable rédige et remplit le questionnaire.",
    'read_only' => "Ce questionnaire est rempli par :name, responsable de l’opportunité. Vous pouvez le consulter.",
    'closed' => "Cette opportunité est close\u{00A0}: son questionnaire ne peut plus changer.",
    'drafted' => "{1} 1\u{00A0}question rédigée. Vos réponses sont enregistrées au fur et à mesure.|[2,*] :count\u{00A0}questions rédigées. Vos réponses sont enregistrées au fur et à mesure.",
    'fewer' => "L’IA a rédigé :drafted des :asked\u{00A0}questions demandées. Ajoutez les vôtres s’il en manque.",
    'summary' => "{1} Réponses\u{00A0}: :answered sur 1\u{00A0}question|[2,*] Réponses\u{00A0}: :answered sur :count\u{00A0}questions",
    'saved' => "Les réponses sont enregistrées au fur et à mesure",
    'saving' => "Enregistrement…",
    'statuses' => ['drafting' => "En cours de rédaction", 'open' => "Ouvert", 'complete' => "Terminé"],
    'language_names' => ['en' => "anglais", 'fr' => "français"],

    'setup' => [
        'title' => "Rédiger le questionnaire",
        'count' => "Nombre de questions",
        'count_help' => "Entre :min et :max. Comptez une à deux minutes par réponse.",
        'focus' => "Thèmes à approfondir (facultatif)",
        'focus_help' => "Cochez ce que les questions doivent approfondir, ou ne cochez rien pour laisser l’IA choisir.",
        'note' => "Autre chose que l’IA doit savoir (facultatif)",
        'note_help' => "Par exemple\u{00A0}: «\u{202F}La paie est hors périmètre\u{202F}» ou «\u{202F}Le nouveau directeur financier décide\u{202F}».",
        'language' => "Les questions sont rédigées en :language, la langue de cette opportunité.",
        'cost' => "La rédaction utilise Claude Sonnet et coûte quelques centimes sur le budget IA du mois.",
        'no_notes' => "Aucune note de réunion n’est encore classée\u{00A0}: l’IA ne dispose que des informations sur l’organisation. Les questions seront plus précises une fois classées les notes de la réunion de découverte.",
        'submit' => "Rédiger les questions",
        'submitting' => "Envoi à l’IA…",
        'failed' => "La dernière rédaction n’a pas abouti. :message",
    ],

    'focus' => [
        'scope' => "Périmètre et priorités",
        'processes' => "Processus et contrôles",
        'systems' => "Systèmes et données",
        'people' => "Équipes et conduite du changement",
        'timeline' => "Calendrier et jalons",
        'commercial' => "Budget et conditions commerciales",
        'risks' => "Risques et conformité",
        'governance' => "Gouvernance et décideurs",
    ],

    'drafting' => [
        'title' => "{1} Rédaction d’une question…|[2,*] Rédaction de :count\u{00A0}questions…",
        'text' => "En général prêt en moins de 2\u{00A0}minutes. Vous pouvez quitter cette page\u{00A0}: les questions vous attendront à votre retour.",
        'progress' => "Avancement de la rédaction",
        'queued' => "En attente du démarrage de l’IA.",
    ],

    'question' => [
        'number' => "Question :n",
        'added_by_lead' => "Question ajoutée",
        'answered' => "Réponse donnée",
        'not_answered' => "Sans réponse",
        'why' => "Pourquoi c’est important\u{00A0}:",
        'recommended' => "Recommandée",
        'reason' => "Pourquoi l’IA la recommande\u{00A0}:",
        'own' => "Ma propre réponse",
        'own_label' => "Votre réponse",
        'detail' => "Ajouter une précision (facultatif)",
        'answer' => "Réponse",
        'detail_label' => "Précision",
        'no_suggestions' => "Pas encore de réponses suggérées. Rédigez votre réponse ou demandez des suggestions à l’IA.",
        'redraft' => "Reformuler",
        'suggest' => "Suggérer des réponses",
        'suggest_again' => "Suggérer d’autres réponses",
        'remove' => "Retirer",
        'remove_confirm' => "Retirer la question :n et sa réponse\u{202F}?",
        'removed' => "Question :n retirée.",
        'redrafting' => "L’IA reformule cette question…",
        'suggesting' => "L’IA suggère des réponses…",
        'failed' => "La reformulation n’a pas abouti. :message",
        'dismiss' => "Masquer ce message",
    ],

    'redraft' => [
        'title' => "Reformuler la question :n",
        'suggest_title' => "Suggérer des réponses à la question :n",
        'note' => "Que faut-il changer\u{202F}? (facultatif)",
        'suggest_note' => "Autre chose que l’IA doit savoir\u{202F}? (facultatif)",
        'note_help' => "Par exemple\u{00A0}: «\u{202F}Interroger sur les agences, pas sur le siège\u{202F}». Seule cette question est envoyée à l’IA.",
        'suggest_help' => "L’IA garde votre formulation et suggère des réponses, dont celle qu’elle recommande. Seule cette question est envoyée.",
        'clears' => "Sa réponse sera effacée.",
        'submit' => "Reformuler la question",
        'suggest_submit' => "Suggérer des réponses",
    ],

    'add' => [
        'button' => "Ajouter une question",
        'title' => "Ajouter votre propre question",
        'question' => "Question",
        'why' => "Pourquoi c’est important (facultatif)",
        'why_help' => "Une fois la question ajoutée, vous pouvez y répondre vous-même ou demander des suggestions à l’IA.",
        'submit' => "Ajouter la question",
        'done' => "Question :n ajoutée.",
    ],

    'complete' => [
        'button' => "Marquer comme terminé",
        'confirm' => "Marquer le questionnaire comme terminé\u{202F}? Les réponses sont verrouillées et la proposition est rédigée à partir d’elles.",
        'blocked' => "Répondez à chaque question, ou retirez celles qui ne s’appliquent pas, pour le marquer comme terminé.",
        'done' => "Questionnaire terminé. La proposition sera rédigée à partir de ces réponses.",
        'locked' => "Terminé par :name le :date. Les réponses sont verrouillées et alimentent la proposition.",
    ],

    'restart' => [
        'button' => "Recommencer",
        'confirm' => "Recommencer\u{202F}? Ces questions et leurs réponses sont mises de côté, et vous rédigez un nouveau questionnaire.",
        'done' => "Le questionnaire a été mis de côté. Rédigez-en un nouveau ci-dessous.",
    ],

    // La cloche du responsable quand une rédaction se termine en arrière-plan
    'notify' => [
        'ready_title' => "Questions prêtes\u{00A0}: :title",
        'ready' => "{1} 1\u{00A0}question attend votre réponse.|[2,*] :count\u{00A0}questions attendent vos réponses.",
        'failed_title' => "Questionnaire non rédigé\u{00A0}: :title",
        'failed' => "L’IA n’a pas pu rédiger les questions. :message",
    ],

    'errors' => [
        'count' => "Choisissez entre :min et :max\u{00A0}questions.",
        'focus' => "Choisissez les thèmes dans la liste.",
        'note_long' => "Raccourcissez ce texte à 1\u{202F}000\u{00A0}caractères au plus.",
        'question' => "Rédigez la question à laquelle vous voulez répondre.",
        'question_long' => "Raccourcissez la question à 1\u{202F}000\u{00A0}caractères au plus.",
        'answer_long' => "Raccourcissez la réponse à 5\u{202F}000\u{00A0}caractères au plus pour qu’elle soit enregistrée.",
        'not_saved' => "Non enregistré\u{202F}: cette question est en cours de réécriture, ou le questionnaire est clos. Répondez-y de nouveau une fois la nouvelle version arrivée.",
        'incomplete' => "{1} Il reste 1\u{00A0}question sans réponse.|[2,*] Il reste :count\u{00A0}questions sans réponse.",
        'redrafting' => "Attendez que l’IA ait fini de reformuler, puis marquez-le comme terminé.",
        'empty' => "Ajoutez au moins une question, puis marquez-le comme terminé.",
    ],
];
