<?php

// HQ › Paramètres et accès › Intégrations (docs/OPPORTUNITIES.md, section 6). Mêmes clés que lang/en/integrations.php.
// Typographie : espace fine insécable (U+202F) avant ; ? ! % et à l’intérieur des « », espace insécable (U+00A0)
// avant les deux-points et entre un nombre et son unité, apostrophe typographique ’.

return [
    'nav' => "Intégrations",
    'title' => "Intégrations",
    'subtitle' => "Le compte Google qui héberge les réunions, la clé Claude qui rédige les documents et le budget mensuel de l’IA.",
    'copy' => "Copier",
    'copied' => "Copié",
    'testing' => "Test en cours…",
    'secret_saved' => "Enregistré. Il n’est plus jamais affiché\u{00A0}: saisissez-en un nouveau uniquement pour le remplacer.",

    'google' => [
        'title' => "Google (Agenda, Meet, Docs et sauvegardes Drive)",
        'intro' => "HQ crée les réunions avec un lien Meet dans l’agenda de :account, récupère leurs transcriptions et dépose les sauvegardes chiffrées du serveur dans son Drive. Saisissez le client OAuth du projet Google Cloud, enregistrez-le, puis connectez le compte.",
        'drive' => [
            'allowed' => "Sauvegardes Drive\u{00A0}: autorisées. HQ ne voit que le dossier de sauvegarde qu’il a créé, rien d’autre dans Drive.",
            'missing' => "Sauvegardes Drive\u{00A0}: reconnectez Google pour les autoriser. Cliquez sur Reconnecter Google et cochez «\u{202F}Afficher, modifier, créer et supprimer uniquement les fichiers Google Drive que vous utilisez avec cette application\u{202F}»\u{202F}; HQ ne voit rien d’autre dans Drive.",
        ],
        'status' => [
            'connected' => "Connecté en tant que :email",
            'not_connected' => "Non connecté",
            'reconnect' => "Reconnexion nécessaire",
        ],
        'redirect_label' => "URI de redirection autorisé",
        'redirect_help' => "Il doit figurer exactement ainsi sur le client OAuth dans Google Cloud.",
        'client_id' => "ID client",
        'client_secret' => "Code secret du client",
        'secret_help' => "Dans le fichier JSON téléchargé pour le client OAuth.",
        'save_client' => "Enregistrer le client",
        'connect' => "Connecter Google",
        'reconnect' => "Reconnecter Google",
        'test' => "Tester la connexion",
        'disconnect' => "Déconnecter",
        'disconnect_confirm' => "Déconnecter Google\u{202F}? HQ cesse de créer des réunions et de récupérer les transcriptions jusqu’à la prochaine connexion.",
        'flash' => [
            'client_first' => "Enregistrez d’abord l’ID client et le code secret, puis connectez Google.",
            'state' => "Cette connexion à Google n’a pas été lancée depuis HQ\u{202F}; rien n’a été connecté. Cliquez de nouveau sur Connecter Google.",
            'cancelled' => "Google n’a pas été connecté, car l’accès n’a pas été autorisé. Cliquez sur Connecter Google et accordez toutes les autorisations.",
            'failed' => "Google n’a pas confirmé la connexion. Cliquez de nouveau sur Connecter Google.",
            'connected' => "Google connecté en tant que :email.",
            'missing_scopes' => "Certaines autorisations n’ont pas été accordées\u{202F}; les réunions ou les transcriptions ne fonctionneront pas. Cliquez sur Reconnecter Google et cochez toutes les cases.",
            'other_account' => "Connecté en tant que :email. Les réunions iront dans cet agenda\u{202F}; le compte convenu est :expected.",
            'client_saved' => "Client enregistré. Cliquez maintenant sur Connecter Google.",
            'test_ok' => "La connexion fonctionne (:email).",
            'disconnected' => "Google déconnecté.",
        ],
        'errors' => [
            'not_connected' => "Google n’est pas encore connecté. Connectez-le dans Paramètres et accès › Intégrations.",
            'revoked' => "Google a retiré l’accès de HQ. Reconnectez-le dans Paramètres et accès › Intégrations.",
            'quota' => "Google limite les demandes pour le moment. Réessayez dans quelques minutes.",
            'scopes' => "Google a refusé, car une autorisation n’a pas été accordée. Reconnectez Google et cochez toutes les cases.",
            'not_found' => "Google ne trouve pas cette réunion, cet enregistrement ou ce fichier.",
            'client' => "Google a refusé l’ID client ou le code secret. Vérifiez-les dans Paramètres et accès › Intégrations.",
            'failed' => "Google n’a pas répondu comme prévu. Réessayez dans une minute\u{202F}; si le problème persiste, consultez l’état du système.",
            'client_id_required' => "Saisissez l’ID client.",
            'client_id_format' => "Saisissez l’ID client tel que Google l’affiche, se terminant par .apps.googleusercontent.com.",
            'secret_required' => "Saisissez le code secret du client.",
        ],
    ],

    'claude' => [
        'title' => "Claude (API Anthropic)",
        'intro' => "Claude rédige le questionnaire, la proposition, l’énoncé des travaux et le projet, ainsi que les notes et comptes rendus de réunion. Créez la clé dans l’espace de travail HQ sur platform.claude.com.",
        'status' => [
            'saved' => "Clé enregistrée",
            'env' => "Clé issue des paramètres du serveur",
            'missing' => "Aucune clé",
        ],
        'key' => "Clé d’API",
        'key_help' => "Commence par sk-ant-. Conservée chiffrée.",
        'save' => "Enregistrer la clé",
        'test' => "Tester la clé",
        'remove' => "Supprimer la clé",
        'remove_confirm' => "Supprimer la clé Claude\u{202F}? Les brouillons de l’IA s’arrêtent jusqu’à l’enregistrement d’une nouvelle clé.",
        'models' => "Claude Sonnet rédige le questionnaire, la proposition, l’énoncé des travaux et le projet\u{202F}; Claude Haiku rédige les notes de réunion, les comptes rendus au client et les résumés des modifications.",
        'flash' => [
            'saved' => "Clé Claude enregistrée.",
            'removed' => "Clé Claude supprimée.",
            'test_ok' => "La clé fonctionne (:model, :cost\u{00A0}\$).",
        ],
        'errors' => [
            'key_required' => "Saisissez la clé d’API.",
            'key_format' => "Saisissez la clé complète telle que la Console l’affiche, commençant par sk-ant-.",
        ],
    ],

    'gemini' => [
        'on' => "Gemini est configuré sur le serveur pour le chat du site, la messagerie et le calculateur de coûts.",
        'off' => "Gemini n’a pas de clé sur le serveur\u{202F}: le chat du site, l’IA de la messagerie et le calculateur de coûts ne peuvent pas l’utiliser.",
    ],

    'budget' => [
        'title' => "Budget mensuel de l’IA",
        'status' => [
            'ok' => "Dans le budget",
            'close' => "Plus de 80\u{202F}% consommés",
            'reached' => "Budget épuisé\u{00A0}: tâches d’IA arrêtées",
        ],
        'this_month' => "Dépensé ce mois-ci",
        'spent_of' => ":spent\u{00A0}\$ sur :cap\u{00A0}\$",
        'help' => "Une tâche qui pourrait faire dépasser le budget du mois ne démarre pas\u{202F}: HQ s’arrête avant la limite de dépenses de la Console Claude. Le mois commence le 1er (heure de Lagos).",
        'label' => "Budget mensuel (USD)",
        'save' => "Enregistrer le budget",
        'by_purpose' => "Ce mois-ci, par usage",
        'purpose' => "Usage",
        'runs' => "Tâches",
        'cost' => "Coût",
        'usd' => ":amount\u{00A0}\$",
        'none' => "Aucune tâche d’IA ce mois-ci.",
        'flash' => [
            'saved' => "Budget mensuel de l’IA fixé à :amount\u{00A0}\$.",
        ],
        'errors' => [
            'amount' => "Saisissez un montant en dollars américains compris entre 0 et 1\u{202F}000.",
        ],
    ],
];
