<?php

// Pages d’erreur de HQ et du portail (resources/views/errors) : ce qui s’est passé, et comment revenir
return [
    'code' => "Erreur :code",
    'portal_sign_in' => "Se connecter au portail client",
    'hq_home' => "Aller à HQ",
    'home' => "Aller sur jbspraxis.com",
    'contact' => "Écrire à info@jbspraxis.com",

    'link' => [
        'title' => "Ce lien a expiré",
        'body' => "Les liens de nos e-mails sont valables un temps limité, et celui-ci a pris fin ou est incomplet. Connectez-vous à votre portail client pour retrouver vos propositions et énoncés des travaux, ou demandez à votre contact chez JBS Praxis de vous le renvoyer.",
    ],
    'forbidden' => [
        'title' => "Vous ne pouvez pas ouvrir cette page",
        'body' => "Votre compte n’y donne pas accès. Connectez-vous avec le bon compte, ou demandez à votre contact chez JBS Praxis.",
    ],
    'not_found' => [
        'title' => "Page introuvable",
        'body' => "L’adresse est peut-être mal saisie, ou la page a été déplacée.",
    ],
    'expired' => [
        'title' => "Cette page a expiré",
        'body' => "Elle est restée ouverte trop longtemps sans être utilisée. Revenez en arrière, rechargez la page et réessayez.",
    ],
    'too_many' => [
        'title' => "Trop de tentatives",
        'body' => "Pour votre sécurité, patientez une minute, puis réessayez.",
    ],
    'server' => [
        'title' => "Un problème est survenu de notre côté",
        'body' => "Nous en sommes informés. Réessayez dans quelques minutes.",
    ],
    'unavailable' => [
        'title' => "De retour dans un instant",
        'body' => "Nous mettons ce service à jour. Réessayez dans quelques minutes.",
    ],
];
