<?php

// La politique de confidentialité (/fr/privacy), version du 30 octobre 2026, version française complète de lang/en/legal.php
// (mêmes clés, mêmes marqueurs :company, :email, :offices, :session_cookie, :session_minutes). Règles du français de
// docs/BRAND.md : vouvoiement, guillemets « », espace fine insécable (U+202F) avant ; : ! ? et à l’intérieur des guillemets.
return [

    'privacy' => [
        'title' => 'Politique de confidentialité | JBS Praxis',
        'description' => 'Comment JBS Praxis collecte, utilise, partage et protège les données personnelles au Nigeria et au Cameroun, et vos droits selon la NDPA et la loi camerounaise.',
        'eyebrow' => 'Confidentialité',
        'h1' => 'Politique de confidentialité',
        'lead' => 'Comment nous collectons, utilisons, partageons et protégeons les données personnelles sur ce site, dans notre portail client et dans nos missions pour nos clients au Nigeria et au Cameroun.',
        'effective' => 'Version du 30 octobre 2026, en vigueur à compter de cette date.',
        'summary_title' => 'En bref',
        'summary' => [
            'Nous collectons ce que vous nous envoyez par nos formulaires, les questions que vous posez à Praxis AI et les données techniques nécessaires au fonctionnement et à la protection de nos systèmes.',
            'Nous les utilisons pour vous répondre, réaliser l’audit gratuit et la démo, vous envoyer ce que vous demandez, étudier les candidatures, travailler avec nos clients et assurer la sécurité de nos systèmes.',
            'Nous ne vendons pas de données personnelles, et Google Analytics ne se charge que si vous l’acceptez.',
            'Les questions posées à Praxis AI sont transmises à Gemini, de Google, et, s’il ne peut pas répondre, à DeepSeek. Merci de ne pas communiquer de données personnelles dans la conversation.',
            'Nous supprimons les CV 12 mois après leur réception, sauf si vous acceptez que nous les gardions plus longtemps ou si vous nous rejoignez.',
            'Vous pouvez demander à consulter, corriger ou supprimer vos données, ou vous opposer à leur utilisation, en écrivant à :email. Vous pouvez aussi saisir l’autorité de protection des données du Nigeria ou du Cameroun.',
        ],
        'toc_title' => 'Sommaire',
        'offices_separator' => ' ; ',
        'cookie_button' => 'Paramètres des cookies',

        'sections' => [
            'who' => ['title' => 'Qui nous sommes', 'body' => [
                ':company (« JBS Praxis », « nous ») est un cabinet de conseil et d’ingénierie qui travaille avec des institutions au Nigeria et au Cameroun. Nous éditons ce site, jbspraxis.com, et exploitons notre propre système (JBS Praxis HQ) ainsi que le portail client JBS Praxis.',
                'Pour les données personnelles décrites dans cette politique, c’est nous qui décidons pourquoi et comment elles sont utilisées : au sens du droit de la protection des données, nous en sommes le responsable du traitement.',
                'Nos bureaux : :offices.',
                'Pour toute question sur cette politique ou sur vos données, écrivez à :email.',
            ]],

            'scope' => ['title' => 'Ce que couvre cette politique', 'body' => [
                'Cette politique couvre les données personnelles des personnes suivantes :',
                ['items' => [
                    'Les visiteurs de jbspraxis.com, en anglais et en français.',
                    'Les personnes qui nous contactent, réservent un audit gratuit et une démo, utilisent le calculateur du coût de la non-qualité, demandent le guide de la méthode Praxis, écrivent pour JBS Praxis Insights, donnent leur avis sur le site ou postulent à un emploi.',
                    'Les personnes qui reçoivent nos e-mails.',
                    'Les personnes de nos clients et prospects avec qui nous travaillons, y compris les utilisateurs du portail client.',
                ]],
                'Lorsque nous concevons ou exploitons un système pour un client, les données personnelles qu’il contient (sur le personnel, les clients ou les fournisseurs de ce client, par exemple) appartiennent au client. Nous ne les traitons que sur ses instructions et dans le cadre de notre contrat avec lui, et c’est sa propre politique de confidentialité qui s’applique. Si votre question porte sur ces données, adressez-vous d’abord à cette organisation ; nous l’aiderons à vous répondre.',
                'Cette politique ne couvre pas les autres sites vers lesquels nous renvoyons, comme nos pages LinkedIn, X, YouTube ou Instagram, qui ont leurs propres règles.',
            ]],

            'collect' => ['title' => 'Ce que nous collectons, et pourquoi', 'body' => [
                'Nous recevons vos données directement de vous, par nos formulaires, nos e-mails et nos réunions, et parfois de votre organisation (par exemple lorsqu’elle vous désigne comme interlocuteur d’un projet). Nous ne collectons que ce dont chaque activité a besoin ; les champs facultatifs sont indiqués et peuvent rester vides.',
                'Aucun formulaire ne demande de données sensibles, comme la santé, la religion ou l’origine ethnique. Merci de ne pas en inclure dans vos messages ou votre CV au-delà du nécessaire.',

                ['heading' => 'Formulaire de contact'],
                'Vos nom, adresse e-mail, organisation et message ; si vous choisissez de les ajouter, votre fonction, votre numéro de téléphone, votre ville, la langue dans laquelle vous souhaitez notre réponse et vos réponses à nos questions sur votre organisation (son stade de développement, son principal point de blocage, les systèmes qu’elle utilise, sa gouvernance et le moment où vous souhaitez commencer).',
                'Nous enregistrons votre organisation, et vous comme son interlocuteur, dans nos fichiers clients (JBS Praxis HQ), conservons votre message, prévenons l’équipe, vous envoyons une confirmation par e-mail et transmettons le détail à l’équipe par e-mail. Nous les utilisons pour vous répondre et assurer le suivi de votre demande.',

                ['heading' => 'Audit gratuit et démo'],
                'Vos nom, adresse e-mail, numéro de téléphone et organisation, le sujet que vous souhaitez examiner, ainsi que la date et l’heure choisies ; votre fonction si vous l’indiquez.',
                'Nous enregistrons la demande, votre organisation et vous comme son interlocuteur, vous écrivons ainsi qu’à l’équipe, et utilisons ces données pour préparer et tenir la séance. Si nous ne pouvons pas honorer le créneau, nous pouvons vous écrire pour en proposer un autre.',

                ['heading' => 'Calculateur du coût de la non-qualité'],
                'Votre adresse e-mail et, si vous les indiquez, vos nom et organisation, avec les chiffres et les choix que vous saisissez : secteur, système principal, principal point de blocage, devise, chiffre d’affaires, effectif, nombre de managers, heures de travail manuel, coût horaire, taux d’erreur et valeur moyenne d’un contrat.',
                'Notre système calcule l’estimation, l’enregistre avec un lien privé vers votre rapport, enregistre votre organisation et vous comme son interlocuteur, vous en envoie une copie par e-mail et prévient l’équipe. Toute personne qui dispose du lien peut ouvrir le rapport : ne le partagez qu’avec des personnes de confiance.',
                'Pour rédiger le court résumé en tête du rapport, nous transmettons à Gemini, de Google, le nom de votre organisation, son type de secteur, son système principal et son principal point de blocage. Nous ne lui transmettons ni votre nom, ni votre adresse e-mail, ni vos chiffres.',
                'Le système classe aussi les rapports en trois groupes selon l’ordre de grandeur des chiffres, pour que notre équipe sache lesquels suivre en premier. Ce classement n’a aucun effet juridique ni aucun effet similaire important pour vous, et c’est une personne qui décide de la suite.',

                ['heading' => 'Le guide de la méthode Praxis'],
                'Votre adresse e-mail. Nous vous envoyons le guide par e-mail et enregistrons la demande avec sa date.',

                ['heading' => 'JBS Praxis Insights'],
                'Lorsque vous proposez un article : vos nom, fonction, adresse e-mail et profil LinkedIn, ainsi que l’article lui-même. Nous vous enregistrons comme contributeur, prévenons nos éditeurs et utilisons vos coordonnées pour examiner l’article et vous contacter à son sujet.',

                ['heading' => 'Candidatures'],
                'Vos nom, adresse e-mail, numéro de téléphone et ville, votre discipline et vos compétences, votre profil LinkedIn, le lien vers votre portfolio si vous l’ajoutez, votre réponse à notre question et votre CV (PDF ou Word, 5 Mo au plus).',
                'Votre CV est conservé dans un espace privé et n’est ouvert que par les membres de l’équipe chargés du recrutement. Si vous postulez à nouveau avec la même adresse e-mail, nous mettons à jour votre candidature et y ajoutons le nouveau CV. Nous pouvons consulter les profils publics dont vous nous donnez le lien. Nous n’utilisons votre candidature que pour étudier votre profil pour des postes chez JBS Praxis.',

                ['heading' => 'Avis sur le site'],
                'Ce que vous signalez, sa catégorie et la gêne occasionnée ; vos nom et adresse e-mail seulement si vous choisissez de les donner. Nous les utilisons pour corriger et améliorer le site.',

                ['heading' => 'Praxis AI, l’assistant du site'],
                'Les questions que vous posez et les réponses que vous recevez. La conversation est conservée dans votre session, sur le serveur de notre site (ses 20 derniers messages), pour que l’assistant puisse la suivre, et elle prend fin avec votre visite. Nous ne l’enregistrons pas dans nos fichiers clients.',
                'Pour répondre, notre système transmet les derniers messages de la conversation (12 au plus), avec nos instructions, à Gemini, de Google. Si Gemini est saturé ou ne répond pas, il les transmet à DeepSeek. Nous n’y joignons ni votre nom, ni votre adresse e-mail, ni votre adresse IP. Merci de ne communiquer dans la conversation aucune donnée personnelle, financière ou confidentielle.',

                ['heading' => 'Nos e-mails'],
                'Si vous figurez dans nos fichiers clients (parce que vous nous avez contactés, avez réservé un audit, utilisé le calculateur, écrit pour Insights ou travaillez avec nous), nous pouvons vous envoyer de temps à autre des e-mails sur nos travaux et nos articles. Chacun contient un lien de désabonnement : suivez-le, confirmez, et nous arrêtons.',
                'Ces e-mails contiennent une petite image qui nous indique quand l’e-mail est ouvert, avec l’adresse IP qui l’a chargée et, lorsque notre prestataire réseau la fournit, le pays. Certaines messageries chargent les images par leurs propres serveurs : l’adresse peut alors être la leur. Si vous ne le souhaitez pas, réglez votre messagerie pour qu’elle ne charge pas les images.',
                'Nous vous envoyons aussi les e-mails que promet chaque formulaire, comme les confirmations et votre rapport de coûts, et seulement en nombre limité chaque jour pour une même adresse.',

                ['heading' => 'Clients et portail client'],
                'Pour les organisations avec lesquelles nous travaillons et leurs équipes : noms, fonctions, adresses e-mail et numéros de téléphone professionnels ; comptes du portail (les mots de passe ne sont conservés que sous forme d’empreinte irréversible) ; propositions, projets, tâches, documents et messages ; validations et réceptions de travaux, avec le nom, la fonction et la signature tracée à l’écran de la personne qui signe, la date et l’heure, son adresse IP et le code à usage unique que nous envoyons par e-mail pour confirmer ; factures et paiements.',
                'Nous les utilisons pour réaliser la mission convenue, garder une trace fiable des décisions, facturer et comptabiliser notre travail, et vous accompagner. Si vous activez les notifications dans le portail, c’est le service de notifications de votre navigateur qui les achemine.',

                ['heading' => 'Données techniques et sécurité'],
                'Lorsque vous utilisez notre site, HQ ou le portail, nos serveurs reçoivent votre adresse IP et des informations techniques sur votre navigateur, comme pour tout site web. Notre hébergeur conserve des journaux de serveur pendant une durée limitée pour faire fonctionner et protéger le service.',
                'Pour éviter les abus, nos systèmes comptent les demandes de chaque connexion : votre adresse IP (pour l’IPv6, sa partie réseau) est conservée à cette fin 24 heures au plus. Les adresses e-mail utilisées pour ces limites ne sont conservées que sous forme brouillée (hachée), 7 jours au plus.',
                'Les personnes connectées à HQ ou au portail ont une session qui enregistre leur adresse IP et leur navigateur, et les actions sur les données sensibles sont inscrites dans un journal d’audit.',
                'Chaque formulaire comporte aussi un champ caché et un minuteur qui distinguent les personnes des programmes automatiques ; ils ne collectent rien sur vous. Si nous activons Cloudflare Turnstile, un contrôle qui joue le même rôle, les pages de formulaire le chargent depuis Cloudflare, qui reçoit votre adresse IP et des informations techniques sur votre navigateur pour effectuer ce contrôle.',
            ]],

            'bases' => ['title' => 'Nos bases légales', 'body' => [
                'Le droit de la protection des données ne nous permet d’utiliser des données personnelles que sur une base légale. Pour chaque finalité, la nôtre est la suivante :',
                ['rows' => [
                    ['Réponse aux demandes, audit gratuit et démo, rapport de coûts', 'Les mesures que vous nous demandez de prendre avant un éventuel contrat, et notre intérêt légitime à répondre aux demandes sur nos services.'],
                    ['Envoi du guide de la méthode Praxis', 'Votre consentement, donné lorsque vous le demandez.'],
                    ['Examen des articles pour Insights', 'Votre consentement, donné lorsque vous envoyez l’article.'],
                    ['Candidatures', 'Les mesures que vous nous demandez de prendre avant un éventuel contrat de travail ; votre consentement si vous acceptez que nous gardions votre CV plus longtemps.'],
                    ['Avis sur le site', 'Notre intérêt légitime à corriger et améliorer le site.'],
                    ['Praxis AI', 'Notre intérêt légitime à répondre aux questions sur nos services.'],
                    ['E-mails sur nos travaux', 'Notre intérêt légitime à tenir informés nos contacts professionnels. Vous pouvez vous y opposer à tout moment.'],
                    ['Google Analytics', 'Votre consentement, donné avec Accepter dans le bandeau cookies. Vous pouvez le retirer à tout moment.'],
                    ['Travail avec nos clients et portail client', 'Notre contrat avec votre organisation, et notre intérêt légitime à travailler avec ses équipes et à conserver la preuve des validations.'],
                    ['Obligations comptables, fiscales et légales', 'Nos obligations légales.'],
                    ['Sécurité, limites et prévention des abus', 'Notre intérêt légitime à protéger nos systèmes, nos clients et nos visiteurs.'],
                ]],
                'Lorsque nous nous fondons sur notre intérêt légitime, nous n’utilisons que ce dont la finalité a besoin, et vous pouvez vous y opposer à tout moment (voir Vos droits).',
                'La loi camerounaise accorde une place centrale au consentement. Lorsque vous nous envoyez un formulaire, vous choisissez de nous donner les informations qu’il demande, pour la finalité qu’il indique. Vous pouvez retirer ce consentement à tout moment en écrivant à :email ; ce retrait ne remet en cause ni ce que nous avons fait auparavant, ni les données que la loi nous oblige à conserver.',
            ]],

            'sharing' => ['title' => 'Qui les reçoit', 'body' => [
                'Nous ne vendons pas de données personnelles, et nous n’utilisons jamais les données d’un client pour les besoins d’un autre.',
                'Chez JBS Praxis, seuls les membres de l’équipe dont le rôle l’exige ont accès aux données personnelles, par des droits définis selon le rôle et tracés.',
                'Nous faisons appel aux prestataires suivants, chacun uniquement pour le service qu’il nous rend :',
                ['items' => [
                    'Notre hébergeur, dont les serveurs font fonctionner ce site, HQ et le portail client, et stockent leurs données et leurs sauvegardes.',
                    'Nos prestataires de messagerie, qui acheminent nos e-mails et hébergent nos boîtes aux lettres.',
                    'Google : Analytics, seulement si vous l’acceptez ; Gemini, pour Praxis AI et le résumé de votre rapport de coûts, ainsi que lorsque notre équipe l’utilise dans HQ pour l’aider à rédiger des propositions, des notes de projet et des e-mails (il reçoit alors le texte nécessaire à cette tâche) ; YouTube, seulement lorsque vous ouvrez la vidéo de démonstration.',
                    'DeepSeek, pour Praxis AI lorsque Gemini ne répond pas.',
                    'Le service de notifications de votre navigateur, exploité par l’éditeur de celui-ci, si vous activez les notifications dans HQ ou le portail.',
                    'Cloudflare, seulement si nous activons son contrôle Turnstile sur nos formulaires.',
                ]],
                'Nous pouvons aussi communiquer des données personnelles lorsque la loi l’exige (par exemple à un tribunal ou à une autorité de contrôle), pour protéger nos droits ou la sécurité d’une personne, ou à des conseils professionnels tenus au secret. Si JBS Praxis est réorganisée ou si son activité est reprise par une autre société, vos données seraient transmises avec elle, dans le respect des engagements de cette politique.',
            ]],

            'transfers' => ['title' => 'Transferts internationaux', 'body' => [
                'Notre équipe au Nigeria et notre équipe au Cameroun travaillent dans les mêmes systèmes : les données d’une personne située dans l’un des deux pays peuvent donc être consultées depuis l’autre.',
                'Certains de nos prestataires traitent aussi des données hors du Nigeria et du Cameroun :',
                ['items' => [
                    'Google, aux États-Unis et dans les autres pays où il est présent.',
                    'DeepSeek, en Chine.',
                    'Nos prestataires d’hébergement et de messagerie, dans les centres de données qu’ils utilisent, qui peuvent se trouver hors du Nigeria et du Cameroun.',
                ]],
                'Lorsque des données quittent le pays, nous n’envoyons que ce dont la tâche a besoin, toujours par des connexions chiffrées, à des prestataires dont les conditions professionnelles précisent comment ils les traitent et les protègent.',
                'La loi nigériane sur la protection des données (Nigeria Data Protection Act) autorise un transfert à l’étranger lorsque le pays de destination offre une protection adéquate, lorsque des garanties appropriées sont en place, ou pour les autres motifs qu’elle prévoit, comme un transfert nécessaire pour faire ce que vous avez demandé. Nous nous appuyons sur les garanties de nos prestataires et, lorsqu’elles ne suffisent pas, sur ces autres motifs.',
                'La loi camerounaise soumet les transferts à l’étranger à l’autorisation préalable de l’Autorité de protection des données à caractère personnel, qui doit publier la liste des pays qu’elle juge adéquats. Nous suivrons cette procédure dès que l’Autorité l’aura publiée.',
                'Vous pouvez nous demander plus d’informations sur un transfert et ses garanties à :email.',
            ]],

            'retention' => ['title' => 'Durée de conservation', 'body' => [
                'Nous ne conservons les données personnelles que le temps nécessaire à la finalité pour laquelle nous les avons collectées, puis nous les supprimons ou les rendons anonymes. En particulier :',
                ['rows' => [
                    ['Candidatures et CV', '12 mois à compter de leur réception. Notre système supprime alors le CV et retire de la candidature tout ce qui vous identifie. Une nouvelle candidature fait repartir les 12 mois. Nous ne gardons un CV plus longtemps qu’avec votre accord, et si vous nous rejoignez, votre candidature entre dans votre dossier du personnel.'],
                    ['Demandes de contact, d’audit et de démo, rapports de coûts et fiches contact', 'Tant que nous sommes en relation, puis 3 ans après notre dernier échange avec vous, sauf si vous devenez client. Le lien du rapport fonctionne tant que nous conservons le rapport.'],
                    ['Demandes du guide de la méthode Praxis', '12 mois.'],
                    ['Articles proposés pour Insights', 'Pendant l’examen de votre article et, s’il est publié, tant qu’il l’est. Vos coordonnées suivent ensuite la règle précédente.'],
                    ['Avis sur le site', '2 ans.'],
                    ['Conversations avec Praxis AI', 'Le temps de votre visite : votre session prend fin après :session_minutes minutes d’inactivité. Nous ne les conservons pas dans nos fichiers. Google et DeepSeek conservent ce qu’ils reçoivent selon leurs propres conditions.'],
                    ['Ouvertures d’e-mails et désabonnements', 'Avec votre fiche contact. Si vous vous désabonnez, nous conservons cette information pour ne plus vous écrire.'],
                    ['Dossiers clients, projets, portail, validations et factures', 'Pendant la mission, puis aussi longtemps que le droit des contrats, la comptabilité et la fiscalité l’exigent (jusqu’à dix ans).'],
                    ['Limites contre les abus', 'Adresses IP : 24 heures au plus. Adresses e-mail brouillées : 7 jours au plus.'],
                    ['Cookies', 'Voir la section Cookies.'],
                ]],
                'Vous pouvez nous demander de supprimer vos données plus tôt (voir Vos droits). Nous pouvons conserver ce que la loi nous oblige à garder, ou ce dont nous avons besoin pour établir ou défendre un droit en justice, tant que ce besoin existe.',
            ]],

            'security' => ['title' => 'Comment nous les protégeons', 'body' => [
                'Nous protégeons les données personnelles avec les contrôles que nous concevons pour nos clients (voir Sécurité et données) :',
                ['items' => [
                    'Des connexions chiffrées (HTTPS) entre votre navigateur et nos systèmes. Le site n’a pas de base de données propre : ce que vous envoyez part directement vers HQ par une connexion signée et chiffrée.',
                    'Des accès par rôle (consulter, créer, modifier, approuver ou exporter) : chacun voit ce dont son travail a besoin, et rien de plus.',
                    'Un journal d’audit des actions sensibles : qui a fait quoi, quand et d’où.',
                    'Un stockage privé pour les documents et les CV, qui ne s’ouvrent que par des liens signés et soumis à autorisation.',
                    'Des limites et des contrôles sur chaque formulaire contre les abus automatisés.',
                    'Des processus alignés sur la norme ISO 27001. Nous ne détenons pas de certification et n’en revendiquons aucune.',
                ]],
                'Aucun système n’est parfaitement sûr. Si une violation met vos données en danger, nous agissons aussitôt pour la contenir, nous informons les autorités comme la loi l’exige (au Nigeria, la NDPC dans les 72 heures après en avoir pris connaissance) et nous vous informons sans retard injustifié lorsqu’elle risque de porter gravement atteinte à vos droits.',
            ]],

            'cookies' => ['title' => 'Cookies', 'body' => [
                'Les cookies sont de petits fichiers qu’un site enregistre dans votre navigateur. Ce site utilise les suivants :',
                ['rows' => [
                    [':session_cookie', 'Relie entre elles les pages de votre visite (vos formulaires et la conversation avec Praxis AI). Nécessaire au fonctionnement du site. Expire après :session_minutes minutes d’inactivité.'],
                    ['XSRF-TOKEN', 'Protège les formulaires contre les requêtes falsifiées. Nécessaire au fonctionnement du site. Expire avec la session.'],
                    ['jbs_consent', 'Retient si vous avez accepté ou refusé Google Analytics. Conservé 6 mois.'],
                    ['_ga, _ga_…', 'Google Analytics : nous indique quelles pages sont consultées. Déposés seulement si vous choisissez Accepter. Conservés jusqu’à 2 ans.'],
                ]],
                'Seul Google Analytics nécessite votre consentement. Si vous choisissez Refuser, ou si vous ne faites aucun choix, il ne se charge jamais ; si vous le refusez plus tard, nous supprimons ses cookies de votre navigateur.',
                'La vidéo de démonstration de la page d’accueil est lue depuis YouTube (youtube-nocookie.com), qui peut enregistrer ses propres données dans votre navigateur. Rien n’est chargé depuis YouTube tant que vous ne choisissez pas Voir la démo de 3 minutes.',
                'HQ et le portail client utilisent leurs propres cookies de session et de sécurité, nécessaires à la connexion.',
                'Vous pouvez modifier votre choix à tout moment avec Paramètres des cookies, en bas de chaque page, et supprimer les cookies dans les réglages de votre navigateur.',
            ]],

            'rights' => ['title' => 'Vos droits', 'body' => [
                'En vertu de la loi nigériane de 2023 sur la protection des données (Nigeria Data Protection Act 2023) et de la loi camerounaise n° 2024/017 du 23 décembre 2024 portant protection des données à caractère personnel, vous pouvez :',
                ['items' => [
                    'Demander si nous détenons des données personnelles vous concernant, et en obtenir une copie avec des informations sur leur utilisation.',
                    'Faire corriger des données inexactes ou incomplètes.',
                    'Faire supprimer vos données lorsque nous n’en avons plus besoin, lorsque vous retirez votre consentement ou lorsqu’elles ont été utilisées de manière illicite.',
                    'Nous demander d’en limiter l’utilisation le temps qu’une question les concernant soit réglée.',
                    'Vous opposer à leur utilisation fondée sur notre intérêt légitime, et à tout moment à nos e-mails sur nos travaux.',
                    'Recevoir les données que vous nous avez fournies dans un format structuré et couramment utilisé, ou les faire transmettre à une autre organisation lorsque c’est techniquement possible.',
                    'Retirer votre consentement à tout moment, sans remettre en cause ce que nous avons fait auparavant.',
                    'Ne pas faire l’objet d’une décision fondée exclusivement sur un traitement automatisé qui produit des effets juridiques ou des effets similaires importants à votre égard. Nous ne prenons aucune décision de ce type.',
                ]],
                'Pour exercer l’un de ces droits, écrivez à :email en précisant votre demande ; nous pouvons vous demander de confirmer votre identité avant d’agir. C’est gratuit. Nous vous répondons sous quelques jours ouvrés et traitons votre demande dans un délai de 30 jours. Si une demande est complexe et demande plus de temps, nous vous en expliquons la raison dans ce délai.',
                'Si nous ne pouvons pas faire ce que vous demandez (par exemple parce que la loi nous oblige à conserver une trace), nous vous expliquons pourquoi.',
                'Si vos données se trouvent dans un système que nous exploitons pour un client, nous transmettons votre demande à ce client et l’aidons à y répondre.',
            ]],

            'complaints' => ['title' => 'Réclamations', 'body' => [
                'Si la façon dont nous traitons vos données ne vous satisfait pas, écrivez-nous d’abord à :email : nous ferons notre possible pour y remédier.',
                'Vous pouvez aussi saisir l’autorité de protection des données :',
                ['items' => [
                    'Au Nigeria : la Nigeria Data Protection Commission (NDPC), par les moyens indiqués sur son site, ndpc.gov.ng.',
                    'Au Cameroun : l’Autorité de protection des données à caractère personnel créée par la loi n° 2024/017, par les moyens qu’elle publie.',
                ]],
                'Si vous vivez ou travaillez ailleurs, vous pouvez aussi vous adresser à l’autorité de votre pays.',
            ]],

            'children' => ['title' => 'Enfants', 'body' => [
                'Notre site et nos services s’adressent à des adultes qui agissent pour leur organisation ou qui postulent à un emploi. Nous ne collectons pas sciemment de données personnelles concernant des personnes de moins de 18 ans. Si vous pensez qu’un enfant nous a transmis des données personnelles, écrivez à :email et nous les supprimerons.',
            ]],

            'changes' => ['title' => 'Modifications de cette politique', 'body' => [
                'Nous revoyons cette politique lorsque nos systèmes ou la loi évoluent. Nous publions ici chaque nouvelle version avec sa date ; lorsqu’une modification est importante, nous le signalons en haut de cette page et, lorsque la loi l’exige, nous vous en informons directement.',
                'Cette politique est publiée en anglais et en français. Les deux versions disent la même chose ; si vous constatez une différence, écrivez-nous : l’interprétation la plus favorable pour vous s’applique.',
            ]],
        ],
    ],
];
