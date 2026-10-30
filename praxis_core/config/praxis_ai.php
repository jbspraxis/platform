<?php

/*
| Praxis AI, the assistant on the new website (docs/SITE_API.md section 6.5). HQ builds its system prompt
| from this site map, the services and the Praxis Method (App\Services\Site\PraxisAi), and the contact
| details and offices from HQ → Marketing → Website settings. Answers point to these pages only: each
| answer carries up to 3 of these paths as "links", shown by the site as buttons with the label below.
| PR2c and PR3 add pages (and the /fr/ paths); add them here when they go live.
*/

return [

    'name' => 'Praxis AI',

    // Buttons per answer, at most
    'max_links' => 3,

    // path => [locale => [label (the button, verb first), purpose (what the page answers)]]
    'site_map' => [
        '/' => [
            'en' => ['label' => 'Go to the home page', 'purpose' => 'What JBS Praxis does, for whom, and how an engagement starts.'],
            'fr' => ['label' => 'Aller à l’accueil', 'purpose' => 'Ce que fait JBS Praxis, pour qui, et comment une mission commence.'],
        ],
        '/schedule' => [
            'en' => ['label' => 'Book a free audit and demo', 'purpose' => 'Choose a time for the free audit and demo: weekdays, Lagos time (WAT), up to 3 months ahead.'],
            'fr' => ['label' => 'Réserver un audit et une démonstration', 'purpose' => 'Choisir un créneau pour l’audit et la démonstration offerts : jours de semaine, heure de Lagos (WAT), jusqu’à 3 mois à l’avance.'],
        ],
        '/contact' => [
            'en' => ['label' => 'Contact us', 'purpose' => 'Send our team a message, with optional questions about the organisation and when it would like to start.'],
            'fr' => ['label' => 'Nous contacter', 'purpose' => 'Écrire à notre équipe, avec des questions facultatives sur l’organisation et la date de démarrage souhaitée.'],
        ],
        '/cost-calculator' => [
            'en' => ['label' => 'Estimate your cost of poor quality', 'purpose' => 'The cost of poor quality calculator: an estimate of what errors, rework and manual work cost a year, with the report sent by email.'],
            'fr' => ['label' => 'Estimer votre coût de la non-qualité', 'purpose' => 'Le calculateur du coût de la non-qualité : une estimation de ce que coûtent par an les erreurs, les reprises et le travail manuel, avec le rapport envoyé par e-mail.'],
        ],
        '/services' => [
            'en' => ['label' => 'See our services and method', 'purpose' => 'Our services and the Praxis Method (See, Design, Build, Hold); the method PDF can be requested by email there.'],
            'fr' => ['label' => 'Découvrir nos services et notre méthode', 'purpose' => 'Nos services et la méthode Praxis (Diagnostiquer, Concevoir, Construire, Pérenniser) ; le PDF de la méthode peut y être demandé par e-mail.'],
        ],
        '/kaizen' => [
            'en' => ['label' => 'Read JBS Praxis Insights', 'purpose' => 'Articles on running organisations: finance, operations, governance, data and systems.'],
            'fr' => ['label' => 'Lire JBS Praxis Insights', 'purpose' => 'Des articles sur le fonctionnement des organisations : finances, opérations, gouvernance, données et systèmes.'],
        ],
        '/kaizen/write' => [
            'en' => ['label' => 'Write for JBS Praxis Insights', 'purpose' => 'Send an article for review by our team, to be published in JBS Praxis Insights.'],
            'fr' => ['label' => 'Écrire pour JBS Praxis Insights', 'purpose' => 'Proposer un article à notre équipe, pour publication dans JBS Praxis Insights.'],
        ],
        '/careers' => [
            'en' => ['label' => 'See careers', 'purpose' => 'Apply to join JBS Praxis, with a CV.'],
            'fr' => ['label' => 'Voir les carrières', 'purpose' => 'Postuler chez JBS Praxis, avec un CV.'],
        ],
        '/feedback' => [
            'en' => ['label' => 'Send website feedback', 'purpose' => 'Report a problem with this website or suggest an improvement.'],
            'fr' => ['label' => 'Envoyer un avis sur le site', 'purpose' => 'Signaler un problème sur ce site ou proposer une amélioration.'],
        ],
    ],
];
