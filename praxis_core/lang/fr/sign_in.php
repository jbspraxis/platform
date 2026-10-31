<?php

// Clés d’accès et code PIN de connexion (docs/SIGN_IN.md), pour le portail client (docs/BRAND.md sections 4, 5 et 12).
// Mêmes clés que lang/en/sign_in.php ; les espaces fines insécables précèdent ? ! : ; (U+202F).

return [
    'login' => [
        'or' => "ou",
        'passkey' => "Se connecter avec une clé d’accès",
        'passkey_busy' => "En attente de votre appareil…",
        'passkey_help' => "Votre empreinte, votre visage ou le verrouillage de l’écran.",
        'pin' => "Se connecter avec un code PIN",
        'password' => "Utiliser votre mot de passe",
        'pin_label' => "Code PIN",
        'pin_help' => "Les 4\u{00A0}chiffres que vous avez choisis.",
        'pin_submit' => "Se connecter",
        'pin_submitting' => "Connexion en cours…",
        'email_required' => "Saisissez votre adresse e-mail.",
        'email_invalid' => "Saisissez une adresse e-mail valide, par exemple ada@acme.example.",
    ],

    'errors' => [
        'pin_digits' => "Saisissez les 4\u{00A0}chiffres de votre code PIN.",
        'pin_failed' => "L’adresse e-mail et le code PIN ne correspondent pas, ou ce compte n’a pas de code PIN. Vérifiez-les, ou utilisez votre mot de passe.",
        'pin_wrong_left' => "{1} Ce code PIN n’est pas le bon. Encore 1\u{00A0}erreur et il sera désactivé.|[2,*] Ce code PIN n’est pas le bon. Encore :left\u{00A0}erreurs et il sera désactivé.",
        'pin_wait' => "Patientez :wait avant de réessayer le code PIN, ou utilisez votre mot de passe.",
        'pin_switched_off' => "Trop d’erreurs\u{202F}: votre code PIN est désactivé. Connectez-vous avec votre mot de passe, puis choisissez un nouveau code PIN.",
        'too_many' => "{1} Trop de tentatives depuis cette connexion. Réessayez dans 1\u{00A0}minute, ou utilisez votre mot de passe.|[2,*] Trop de tentatives depuis cette connexion. Réessayez dans :minutes\u{00A0}minutes, ou utilisez votre mot de passe.",
        'seconds' => "{1} 1\u{00A0}seconde|[2,*] :count\u{00A0}secondes",
        'minutes' => "{1} 1\u{00A0}minute|[2,*] :count\u{00A0}minutes",
    ],

    'passkey' => [
        'failed' => "Votre clé d’accès n’a pas pu être vérifiée. Réessayez, ou utilisez votre mot de passe.",
        'expired' => "Le délai est dépassé. Réessayez.",
        'unknown' => "Cette clé d’accès n’est liée à aucun compte ici. Utilisez votre mot de passe, puis ajoutez une clé d’accès.",
        'inactive' => "Ce compte ne peut pas se connecter. Contactez JBS Praxis si vous pensez qu’il s’agit d’une erreur.",
        'already_added' => "Cette clé d’accès est déjà ajoutée.",
        'too_many' => "Vous avez 10\u{00A0}clés d’accès, le maximum pour un compte. Supprimez-en une pour en ajouter une autre.",
        'cancelled' => "La demande de clé d’accès a été annulée ou a expiré. Réessayez quand vous êtes prêt ou prête.",
        'unsupported' => "Ce navigateur ne prend pas en charge les clés d’accès. Utilisez votre mot de passe, ou essayez un autre navigateur.",
        'exists' => "Cet appareil a déjà une clé d’accès pour votre compte.",
    ],

    'settings' => [
        'title' => "Paramètres de connexion",
        'open' => "Paramètres de connexion",
        'intro' => "Connectez-vous avec votre empreinte, votre visage ou un code PIN au lieu de votre mot de passe.",

        'passkeys_title' => "Clés d’accès",
        'passkeys_help' => "Une clé d’accès vous connecte avec l’empreinte, le visage ou le verrouillage d’écran de cet appareil. Ajoutez-en une sur chaque appareil que vous utilisez.",
        'passkeys_none' => "Aucune clé d’accès pour l’instant. Ajoutez-en une pour vous connecter avec votre empreinte ou votre visage.",
        'passkeys_unsupported' => "Ce navigateur ne peut pas créer de clé d’accès. Ouvrez cette page dans un navigateur à jour sur votre téléphone ou votre ordinateur pour en ajouter une.",
        'add_passkey' => "Ajouter une clé d’accès",
        'adding_passkey' => "En attente de votre appareil…",
        'added' => "Ajoutée le :date",
        'used' => "utilisée le :date",
        'synced' => "Synchronisée",
        'remove_passkey' => "Supprimer",
        'remove_passkey_confirm' => "Supprimer la clé d’accès «\u{00A0}:name\u{00A0}»\u{202F}? Cet appareil devra utiliser votre mot de passe ou votre code PIN pour se connecter.",
        'passkey_added' => "Clé d’accès ajoutée. La prochaine fois, connectez-vous avec votre empreinte ou votre visage.",
        'passkey_removed' => "Clé d’accès supprimée.",

        'pin_title' => "Code PIN",
        'pin_help' => "Un code à 4\u{00A0}chiffres pour vous connecter au lieu de votre mot de passe.",
        'pin_on' => "Votre code PIN est défini.",
        'pin_none' => "Aucun code PIN pour l’instant.",
        'pin_off' => "Votre code PIN a été désactivé après trop d’erreurs. Choisissez-en un nouveau pour l’utiliser à nouveau.",
        'set_pin' => "Choisir un code PIN",
        'change_pin' => "Changer le code PIN",
        'remove_pin' => "Supprimer le code PIN",
        'remove_pin_confirm' => "Supprimer votre code PIN\u{202F}? Vous vous connecterez avec votre mot de passe ou une clé d’accès.",
        'new_pin' => "Nouveau code PIN",
        'new_pin_help' => "4\u{00A0}chiffres. Pas une suite comme 1234 ni un même chiffre répété.",
        'new_pin_again' => "Confirmez le nouveau code PIN",
        'save_pin' => "Enregistrer le code PIN",
        'cancel' => "Annuler",
        'pin_saved' => "Code PIN enregistré. Utilisez-le lors de votre prochaine connexion.",
        'pin_removed' => "Code PIN supprimé.",

        'confirm_title' => "Confirmez votre identité",
        'confirm_help' => "Saisissez votre mot de passe pour modifier votre façon de vous connecter.",
        'password' => "Mot de passe",
        'confirm' => "Confirmer",
        'confirming' => "Vérification…",
    ],

    'pin' => [
        'required' => "Saisissez 4\u{00A0}chiffres.",
        'mismatch' => "Les deux codes PIN ne correspondent pas. Saisissez les mêmes 4\u{00A0}chiffres dans les deux champs.",
        'password_required' => "Saisissez votre mot de passe.",
        'password_wrong' => "Ce n’est pas votre mot de passe. Vérifiez-le et réessayez.",
        'password_too_many' => "Trop de tentatives. Patientez :minutes\u{00A0}minutes et réessayez.",
        'weak' => [
            'digits' => "Saisissez 4\u{00A0}chiffres.",
            'repeated' => "Utilisez des chiffres différents, pas un même chiffre répété.",
            'run' => "Choisissez un code PIN qui n’est pas une suite comme 1234 ou 9876.",
            'pair' => "Choisissez un code PIN qui n’est pas une paire répétée comme 1212.",
            'doubles' => "Choisissez un code PIN qui n’est pas formé de deux doubles comme 1155.",
            'common' => "Ce code PIN fait partie des plus courants. Choisissez-en un autre.",
        ],
    ],

    'offer' => [
        'title' => "Connectez-vous plus vite la prochaine fois",
        'body' => "Ajoutez une clé d’accès et connectez-vous avec votre empreinte ou votre visage.",
        'add' => "Ajouter une clé d’accès",
        'later' => "Plus tard",
        'done' => "Clé d’accès ajoutée. La prochaine fois, connectez-vous avec votre empreinte ou votre visage.",
    ],
];
