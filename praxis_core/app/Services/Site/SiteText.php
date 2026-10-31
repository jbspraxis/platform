<?php

namespace App\Services\Site;

use Illuminate\Support\Facades\Validator;

/**
 * What the site API says to visitors, in the request's language (X-Site-Locale, set as the app
 * locale by VerifySiteSignature). English validation messages are Laravel's own; French ones are
 * here, because the app has no French validation file. Voice: docs/BRAND.md sections 4, 5 and 12.
 */
final class SiteText
{
    private const TEXT = [
        'en' => [
            'invalid' => 'Check the highlighted fields and try again.',
            'slot_taken' => 'That time has just been booked. Choose another time.',
            'summary_words' => 'Keep the summary to 50 words or fewer.',
            'cv_required' => 'Attach your CV as a PDF, DOC or DOCX file.',
            'phone_required' => 'Enter a phone number we can reach you on.',
            'city_required' => 'Enter the city you live in.',
            'other_skill_required' => 'Tell us your discipline.',
            'too_many' => 'You’ve sent several requests in a short time. Try again later.',
            'already_booked' => 'You already have a free audit request waiting for confirmation. We’ll email you about it; to change it, reply to that email.',
            'date_unavailable' => 'Choose a weekday from today up to 3 months ahead.',
        ],
        'fr' => [
            'invalid' => 'Vérifiez les champs signalés et réessayez.',
            'slot_taken' => 'Ce créneau vient d’être réservé. Choisissez un autre horaire.',
            'summary_words' => 'La synthèse ne doit pas dépasser 50 mots.',
            'cv_required' => 'Joignez votre CV au format PDF, DOC ou DOCX.',
            'phone_required' => 'Indiquez un numéro de téléphone où vous joindre.',
            'city_required' => 'Indiquez votre ville de résidence.',
            'other_skill_required' => 'Précisez votre spécialité.',
            'too_many' => 'Vous avez envoyé plusieurs demandes en peu de temps. Réessayez plus tard.',
            'already_booked' => 'Vous avez déjà une demande d’audit offert en attente de confirmation. Nous vous écrirons à ce sujet ; pour la modifier, répondez à cet e-mail.',
            'date_unavailable' => 'Choisissez un jour de semaine, d’aujourd’hui à 3 mois au plus.',
        ],
    ];

    /** French rule messages (only the rules the site API uses) */
    private const FR_RULES = [
        'required' => 'Le champ :attribute est obligatoire.',
        'required_if' => 'Le champ :attribute est obligatoire.',
        'string' => 'Le champ :attribute doit être un texte.',
        'email' => 'Le champ :attribute doit contenir une adresse e-mail valide.',
        'url' => 'Le champ :attribute doit contenir une adresse web valide.',
        'numeric' => 'Le champ :attribute doit être un nombre.',
        'integer' => 'Le champ :attribute doit être un nombre entier.',
        'boolean' => 'Le champ :attribute doit valoir vrai ou faux.',
        'array' => 'Le champ :attribute doit être une liste.',
        'in' => 'La valeur du champ :attribute n’est pas valide.',
        'date_format' => 'Le champ :attribute doit respecter le format :format.',
        'file' => 'Le champ :attribute doit être un fichier.',
        'uploaded' => 'Le fichier :attribute n’a pas pu être envoyé.',
        'mimes' => 'Le fichier :attribute doit être au format :values.',
        'size' => [
            'string' => 'Le champ :attribute doit contenir :size caractères.',
        ],
        'max' => [
            'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
            'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
            'file' => "Le fichier :attribute ne doit pas dépasser :max\u{202F}Ko.",
            'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        ],
        'min' => [
            'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
            'numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
            'file' => "Le fichier :attribute doit faire au moins :min\u{202F}Ko.",
            'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        ],
    ];

    /** French field names, by API name */
    private const FR_ATTRIBUTES = [
        'full_name' => 'nom complet', 'email' => 'adresse e-mail', 'company_name' => 'nom de l’organisation',
        'message' => 'message', 'title' => 'fonction', 'country_code' => 'indicatif pays', 'phone' => 'téléphone',
        'city' => 'ville', 'language' => 'langue', 'budget' => 'budget', 'stage' => 'stade de l’organisation',
        'bottleneck' => 'principal frein', 'other_bottleneck' => 'autre frein', 'tech_reality' => 'systèmes actuels',
        'gov_status' => 'gouvernance', 'diagnostic' => 'diagnostic',
        'enterprise_name' => 'nom de l’organisation', 'focus' => 'objet de l’audit', 'custom_focus' => 'précision sur l’objet',
        'date' => 'date', 'time' => 'heure', 'month' => 'mois',
        'name' => 'nom', 'company' => 'organisation', 'industry' => 'secteur', 'tool' => 'outil principal',
        'acv' => 'valeur moyenne d’un contrat', 'headcount' => 'effectif', 'mgmt_nodes' => 'nombre de responsables',
        'turnover' => 'chiffre d’affaires annuel', 'manual_hrs' => 'heures de travail manuel par semaine',
        'hourly_cost' => 'coût horaire', 'error_rate' => 'taux d’erreur', 'currency' => 'devise',
        'linkedin_url' => 'profil LinkedIn', 'category' => 'catégorie', 'article_title' => 'titre de l’article',
        'exec_summary' => 'synthèse', 'vulnerability' => 'problème', 'solution' => 'solution', 'roi' => 'résultat',
        'identity' => 'nom', 'severity' => 'gravité', 'description' => 'description',
        'primary_skill' => 'spécialité principale', 'other_skill' => 'autre spécialité',
        'secondary_skillset' => 'compétences complémentaires', 'portfolio_url' => 'portfolio',
        'praxis_answer' => 'réponse', 'cv' => 'CV', 'history' => 'historique', 'history.*.role' => 'rôle',
        'history.*.text' => 'message', 'page' => 'page', 'country' => 'pays',
        'start_timing' => 'date de démarrage souhaitée', 'question_number' => 'numéro de la question', 'campaign' => 'campagne', 't' => 'jeton',
    ];

    public static function locale(): string
    {
        return app()->getLocale() === 'fr' ? 'fr' : 'en';
    }

    public static function get(string $key, ?string $locale = null): string
    {
        $locale ??= self::locale();

        return self::TEXT[$locale][$key] ?? self::TEXT['en'][$key];
    }

    /**
     * Validates API input; throws ValidationException (rendered as 422 by ApiErrors).
     *
     * @param  array<string, string>  $custom  "field.rule" => text, in the request's language
     */
    public static function validate(array $data, array $rules, array $custom = []): void
    {
        $messages = $custom;
        $attributes = [];
        if (self::locale() === 'fr') {
            // Field-specific messages ("cv.required") win over the rule messages ("required")
            $messages = $custom + self::FR_RULES;
            $attributes = self::FR_ATTRIBUTES;
        }

        Validator::make($data, $rules, $messages, $attributes)->validate();
    }
}
