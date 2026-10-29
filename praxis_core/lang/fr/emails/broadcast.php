<?php

// IntelligenceBroadcast : e-mail éditorial envoyé depuis HQ → Campagnes. L’objet et le corps sont ceux de la campagne.
return [
    // Les destinataires sont des contacts clients (prospects et clients) non désabonnés ; ils ne se sont pas inscrits à une liste.
    'reason' => 'Vous recevez ce message parce que vous figurez dans la liste de contacts de JBS Praxis.',
    // Utilisés quand une campagne contient {{ first_name }}, {{ company_name }} ou {{ industry }} et que la valeur manque
    'fallback_first_name' => 'Madame, Monsieur',
    'fallback_company' => 'votre organisation',
    'fallback_industry' => 'votre secteur',
];
