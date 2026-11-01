<?php

// Tâches d’IA (App\Support\Ai\Ai, docs/OPPORTUNITIES.md 8.3) : messages d’échec et noms des usages.
// Mêmes clés que lang/en/ai.php. Typographie : espace fine insécable (U+202F) avant ; ? ! %, espace insécable avant les deux-points.

return [
    'errors' => [
        'budget' => "Le budget d’IA du mois est épuisé\u{202F}; rien n’a été rédigé. Le propriétaire peut l’augmenter dans Paramètres et accès › Intégrations.",
        'not_configured' => "L’IA n’est pas encore configurée\u{00A0}: ajoutez la clé Claude dans Paramètres et accès › Intégrations.",
        'key' => "Claude a refusé la clé d’API. Vérifiez-la dans Paramètres et accès › Intégrations.",
        'credit' => "Le crédit Claude est épuisé\u{202F}; rien n’a été rédigé. Ajoutez du crédit sur platform.claude.com, puis réessayez.",
        'busy' => "Claude est très sollicité en ce moment. Réessayez dans quelques minutes.",
        'unreachable' => "HQ n’a pas pu joindre Claude. Réessayez dans une minute.",
        'refused' => "Claude a refusé de rédiger ce contenu. Reformulez les consignes, puis réessayez.",
        'too_long' => "Le brouillon a été interrompu, car il était trop long. Précisez les consignes, puis réessayez.",
        'invalid' => "Le brouillon est revenu incomplet\u{202F}; il n’a pas été enregistré. Réessayez.",
        'stuck' => "La tâche s’est arrêtée avant la fin. Réessayez\u{202F}; si cela se reproduit, consultez l’état du système.",
        'failed' => "Un problème est survenu pendant la rédaction. Réessayez dans une minute\u{202F}; si cela persiste, consultez l’état du système.",
    ],

    'purposes' => [
        'meeting_notes' => "Notes de réunion",
        'client_minutes' => "Comptes rendus au client",
        'handover_points' => "Points de livraison de jalon",
        'questionnaire' => "Questionnaire",
        'questionnaire_question' => "Questionnaire\u{00A0}: une question",
        'proposal' => "Proposition",
        'proposal_changes' => "Modifications",
        'sow' => "Énoncé des travaux",
        'project_draft' => "Projet préparé",
        'connection_test' => "Test de la clé",
    ],
];
