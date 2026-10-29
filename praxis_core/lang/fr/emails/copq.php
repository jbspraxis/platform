<?php

// CopqReportMail : le rapport sur le coût de la non-qualité, depuis le calculateur de jbspraxis.com ou renvoyé depuis HQ.
// Typographie : U+202F avant ; ? ! % et dans « », U+00A0 avant :, apostrophe ’.
return [
    'subject' => "Votre rapport\u{00A0}: le coût de la non-qualité chez :company",
    'subject_plain' => "Votre rapport\u{00A0}: le coût de la non-qualité",
    'preheader' => 'Votre estimation et le lien vers le rapport complet.',
    'headline' => 'Votre rapport sur le coût de la non-qualité est prêt',
    'intro' => 'Nous estimons que la non-qualité coûte environ :amount par an à :company.',
    'intro_plain' => 'Nous estimons que la non-qualité coûte environ :amount par an à votre organisation.',
    'explain' => 'Le coût de la non-qualité correspond à ce qu’une organisation perd en contrôles manuels, en erreurs corrigées en interne et en erreurs qui atteignent ses clients ou ses bénéficiaires. Votre rapport complet détaille le calcul de cette estimation et l’origine de ce coût.',
    'label_amount' => 'Coût estimé par an',
    'label_date' => 'Date du rapport',
    'report_language' => 'Le rapport complet est pour l’instant disponible en anglais.',
    'button' => 'Consulter votre rapport',
    'share' => "Toute personne disposant de ce lien peut ouvrir le rapport\u{00A0}: ne le partagez qu’avec les personnes de votre organisation qui en ont besoin.",
    'estimate_note' => 'Ces chiffres sont des estimations fondées sur les informations fournies et sur des hypothèses courantes pour votre secteur. Répondez à cet e-mail si vous souhaitez les examiner avec nous lors d’un audit et d’une démonstration offerts.',
    'reason_hq' => 'Vous recevez ce message parce que JBS Praxis a préparé ce rapport pour votre organisation.',
];
