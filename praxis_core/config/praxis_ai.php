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
        // The proof pages (PR4): the work, one page per case study (praxis_site App\Support\Work), and the sectors
        '/work' => [
            'en' => ['label' => 'See our work', 'purpose' => 'Case studies: the systems JBS Praxis built for each client, how they run day to day and what changed. Clients not on the approved list are described by sector only.'],
            'fr' => ['label' => 'Voir nos réalisations', 'purpose' => 'Les études de cas : les systèmes construits par JBS Praxis pour chaque client, leur fonctionnement au quotidien et ce qui a changé. Les clients hors de la liste approuvée sont décrits par leur secteur.'],
        ],
        '/industries' => [
            'en' => ['label' => 'See the sectors we serve', 'purpose' => 'The sectors JBS Praxis works in (logistics, consumer goods, retail, pharmaceuticals, oil and gas, technology, small businesses, education and faith organisations), each linked to its work.'],
            'fr' => ['label' => 'Voir les secteurs que nous servons', 'purpose' => 'Les secteurs où intervient JBS Praxis (logistique, biens de consommation, commerce, industrie pharmaceutique, pétrole et gaz, technologie, petites entreprises, éducation et organisations confessionnelles), chacun relié à ses réalisations.'],
        ],
        '/work/smart-logistics' => [
            'en' => ['label' => 'Read the Smart Logistics case study', 'purpose' => 'Praxis LS for Smart Logistics and Services in Cameroon: shipment files, warehouse, fleet, payroll and OHADA accounting in one system, in English and French.'],
            'fr' => ['label' => 'Lire l’étude de cas Smart Logistics', 'purpose' => 'Praxis LS pour Smart Logistics and Services au Cameroun : dossiers d’expédition, entrepôt, flotte, paie et comptabilité OHADA dans un seul système, en français et en anglais.'],
        ],
        '/work/mycity-app' => [
            'en' => ['label' => 'Read the MyCity App case study', 'purpose' => 'The ERP JBS Praxis is building for MyCity App in Nigeria: recruitment, onboarding, attendance, performance, payroll, partnerships and spending control (in build).'],
            'fr' => ['label' => 'Lire l’étude de cas MyCity App', 'purpose' => 'L’ERP que JBS Praxis construit pour MyCity App au Nigeria : recrutement, intégration, présence, performance, paie, partenariats et maîtrise des dépenses (en construction).'],
        ],
        '/work/pixie-girl-global' => [
            'en' => ['label' => 'Read the Pixie Girl Global case study', 'purpose' => 'The platform behind Pixie Girl Global in Nigeria: landed cost, one stock for every sales channel, the brand’s own online shop, deliveries, payroll and accounts.'],
            'fr' => ['label' => 'Lire l’étude de cas Pixie Girl Global', 'purpose' => 'La plateforme de Pixie Girl Global au Nigeria : coût de revient complet, un seul stock pour tous les canaux de vente, la boutique en ligne de la marque, livraisons, paie et comptabilité.'],
        ],
        '/work/orika-living' => [
            'en' => ['label' => 'Read the Orika Living case study', 'purpose' => 'Orika Living’s ERP and online shop in Nigeria: sales, stockists on consignment, stock, deliveries and accounts that agree with each other.'],
            'fr' => ['label' => 'Lire l’étude de cas Orika Living', 'purpose' => 'L’ERP et la boutique en ligne d’Orika Living au Nigeria : ventes, revendeurs en dépôt-vente, stock, livraisons et une comptabilité qui concorde.'],
        ],
        '/work/pharmaceutical-manufacturer' => [
            'en' => ['label' => 'Read the pharmaceutical case study', 'purpose' => 'With Tales Consulting, a field-force platform for a leading pharmaceutical manufacturer (never named): visits, orders, approvals, forecasting and financial models (in build; demo on request).'],
            'fr' => ['label' => 'Lire l’étude de cas pharmaceutique', 'purpose' => 'Avec Tales Consulting, une plateforme pour la force de vente d’un grand laboratoire pharmaceutique (jamais nommé) : visites, commandes, validations, prévisions et modèles financiers (en construction ; démo sur demande).'],
        ],
        '/work/fmcg-distributor-cameroon' => [
            'en' => ['label' => 'Read the FMCG distributor case study', 'purpose' => 'An FMCG distributor in Cameroon (never named) whose live ERP JBS Praxis audited and rebuilt in stages: orders, returnable containers, fleet, payroll and OHADA accounts.'],
            'fr' => ['label' => 'Lire l’étude de cas du distributeur', 'purpose' => 'Un distributeur de biens de consommation au Cameroun (jamais nommé) dont JBS Praxis a audité puis reconstruit l’ERP en service : commandes, emballages consignés, flotte, paie et comptabilité OHADA.'],
        ],
        '/work/nfelia' => [
            'en' => ['label' => 'Read about nfelia', 'purpose' => 'nfelia, a JBS Praxis product for small businesses: their own website, plus orders, invoices, receipts, debts, stock and deliveries on the phone, in Nigeria, Cameroon, Ghana and Kenya.'],
            'fr' => ['label' => 'Découvrir nfelia', 'purpose' => 'nfelia, un produit JBS Praxis pour les petites entreprises : leur propre site web, avec commandes, factures, reçus, dettes, stock et livraisons sur le téléphone, au Nigeria, au Cameroun, au Ghana et au Kenya.'],
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
