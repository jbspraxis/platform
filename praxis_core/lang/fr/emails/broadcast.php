<?php

// Diffusions (HQ › Campagnes › Diffusions, docs/CAMPAIGNS.md). L’objet, le titre et les blocs sont ceux de la diffusion ;
// voici les mots qui les entourent. IntelligenceBroadcast (diffusions d’avant le 3 novembre 2026) utilise 'reason' et les valeurs par défaut.
return [
    // Pourquoi la personne le reçoit, selon ce qu’elle est pour nous (formulation à confirmer par le conseil, docs/CAMPAIGNS.md section 9)
    'reason' => 'Vous recevez ce message parce que vous figurez dans la liste de contacts de JBS Praxis.',
    'reasons' => [
        'client' => 'Vous recevez ce message parce que votre organisation travaille avec JBS Praxis.',
        'lead' => 'Vous recevez ce message parce que vous êtes en relation avec JBS Praxis.',
        'subscriber' => 'Vous recevez ce message parce que vous avez demandé à recevoir des nouvelles de JBS Praxis.',
        'kaizen' => 'Vous recevez ce message parce que vous avez écrit pour JBS Praxis Insights.',
    ],
    // La petite mention au-dessus du titre, selon la mise en page
    'eyebrow' => [
        'announcement' => 'Nouvelles de JBS Praxis',
        'insight' => 'Analyses JBS Praxis',
        'event' => 'Invitation',
        'case_study' => 'Étude de cas',
    ],
    // Un envoi de test depuis l’éditeur
    'test_subject' => "Test\u{00A0}: :subject",
    // Utilisés quand une diffusion contient {{ first_name }}, {{ company_name }} ou {{ industry }} et que la valeur manque
    'fallback_first_name' => 'Madame, Monsieur',
    'fallback_company' => 'votre organisation',
    'fallback_industry' => 'votre secteur',
];
