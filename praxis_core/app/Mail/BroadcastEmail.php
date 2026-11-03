<?php

namespace App\Mail;

use App\Support\Campaigns\Blocks;
use App\Support\Campaigns\Layouts;
use App\Support\Campaigns\Renderer;
use App\Support\Campaigns\RichText;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/**
 * A broadcast from HQ › Campaigns › Broadcasts (docs/CAMPAIGNS.md), sent by broadcast:process in the person's language.
 * $version is one language's subject, preheader, headline and blocks; $person the reader's first name, company and
 * kind (client, lead, subscriber, kaizen: the footer's reason). For a real send, $links maps each address in the
 * blocks to its counted one and the unsubscribe address is the reader's own, which inboxes can also use in one
 * click (List-Unsubscribe-Post, RFC 8058).
 */
class BroadcastEmail extends BrandedMail
{
    public function __construct(
        public array $version,
        public string $layout,
        public bool $greeting,
        public array $person,
        public ?string $unsubscribeUrl = null,
        public ?string $pixelUrl = null,
        public array $links = [],
        public bool $test = false,
    ) {}

    protected function subjectLine(): string
    {
        $subject = Renderer::fill(trim((string) ($this->version['subject'] ?? '')), $this->person);

        return $this->test ? __('emails.broadcast.test_subject', ['subject' => $subject]) : $subject;
    }

    public function content(): Content
    {
        $blocks = Blocks::clean($this->version['blocks'] ?? []);
        $headline = trim((string) ($this->version['headline'] ?? '')) ?: trim((string) ($this->version['subject'] ?? ''));

        return new Content(view: 'emails.campaign', with: [
            'body' => Renderer::body($blocks, $this->person, $this->links),
            'headline' => Renderer::fill($headline, $this->person),
            'eyebrow' => Layouts::get($this->layout)['eyebrow'] ? __('emails.broadcast.eyebrow.' . $this->layout) : null,
            'preheader' => $this->preheader($blocks),
            'greeting' => $this->greeting,
        ]);
    }

    public function headers(): Headers
    {
        if (! $this->unsubscribeUrl || $this->test) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<' . $this->unsubscribeUrl . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /** The preview text the editor gives, else the opening words of the first text */
    private function preheader(array $blocks): string
    {
        $given = trim((string) ($this->version['preheader'] ?? ''));
        if ($given !== '') {
            return Renderer::fill($given, $this->person);
        }
        $first = collect($blocks)->firstWhere('type', 'text');
        $text = $first ? preg_replace('/\s+/u', ' ', RichText::text(Renderer::fill($first['html'], $this->person, html: true))) : '';

        return Str::limit(trim($text), 140, '…');
    }

    public static function samples(): array
    {
        $person = ['first_name' => 'Ada', 'last_name' => 'Okafor', 'company' => 'Acme Ltd (demo)', 'kind' => 'client'];
        $unsubscribe = route('email.unsubscribe', ['token' => 'preview']);
        $samples = [];
        foreach (self::sampleVersions() as $name => [$layout, , $version]) {
            $who = $layout === 'event' ? ['kind' => 'lead'] + $person : ($layout === 'letter' ? ['kind' => 'subscriber'] + $person : $person);
            $samples[$name] = new static($version, $layout, $layout !== 'insight', $who, $unsubscribe);
        }

        return $samples;
    }

    /** name => [layout, locale, version]: fictional broadcasts in every layout, in both languages */
    private static function sampleVersions(): array
    {
        $button = fn (string $locale) => Blocks::blank('button', $locale);
        $nbsp = "\u{00A0}";
        $nnbsp = "\u{202F}";

        return [
            'announcement-en' => ['announcement', 'en', [
                'subject' => 'We have opened an office in Douala',
                'headline' => 'JBS Praxis now works from Douala as well as Lagos',
                'blocks' => [
                    ['type' => 'text', 'html' => '<p>From this week, our Douala team works from 1030 Avenue Douala Manga Bell, Bali (demo address). Clients in Cameroon and Central Africa now have a team in their own time zone and language.</p><p>Nothing changes in how we work with {{ company_name }}: the same people, the same portal.</p>'],
                    $button('en'),
                ],
            ]],
            'announcement-fr' => ['announcement', 'fr', [
                'subject' => 'Nous ouvrons un bureau à Douala',
                'headline' => 'JBS Praxis travaille désormais depuis Douala et Lagos',
                'blocks' => [
                    ['type' => 'text', 'html' => "<p>Dès cette semaine, notre équipe de Douala vous accueille au 1030 avenue Douala Manga Bell, Bali (adresse de démonstration). Nos clients au Cameroun et en Afrique centrale disposent désormais d’une équipe dans leur fuseau horaire et leur langue.</p><p>Rien ne change dans notre collaboration avec {{ company_name }}{$nbsp}: les mêmes interlocuteurs, le même portail.</p>"],
                    $button('fr'),
                ],
            ]],
            'insight-en' => ['insight', 'en', [
                'subject' => 'Three checks before your month-end close',
                'preheader' => 'Where month-end delays come from, and what to change first.',
                'blocks' => [
                    ['type' => 'text', 'html' => '<p>Most month-end delays we see come from three places: late supplier invoices, manual bank reconciliations and approvals that wait in someone’s inbox.</p>'],
                    ['type' => 'figures', 'items' => [['value' => '6 days', 'label' => 'average close at the start'], ['value' => '2 days', 'label' => 'after 3 months'], ['value' => '41%', 'label' => 'fewer late approvals']]],
                    ['type' => 'heading', 'text' => 'What to change first'],
                    ['type' => 'text', 'html' => '<ul><li><strong>Agree a cut-off date</strong> with your 10 largest suppliers.</li><li><strong>Reconcile the main bank accounts</strong> every week, not once a month.</li><li><strong>Give every approval</strong> a named owner and a deadline.</li></ul>'],
                    ['type' => 'quote', 'text' => 'We closed March in two days for the first time.', 'source' => 'Finance director, a leading manufacturer (demo)'],
                    $button('en'),
                ],
            ]],
            'insight-fr' => ['insight', 'fr', [
                'subject' => 'Trois vérifications avant votre clôture mensuelle',
                'preheader' => 'D’où viennent les retards de clôture, et par quoi commencer.',
                'blocks' => [
                    ['type' => 'text', 'html' => "<p>La plupart des retards de clôture que nous observons ont trois origines{$nbsp}: les factures fournisseurs tardives, les rapprochements bancaires manuels et les approbations qui attendent dans une boîte de réception.</p>"],
                    ['type' => 'figures', 'items' => [['value' => '6 jours', 'label' => 'de clôture au départ'], ['value' => '2 jours', 'label' => 'après 3 mois'], ['value' => "41{$nnbsp}%", 'label' => 'd’approbations en retard en moins']]],
                    ['type' => 'heading', 'text' => 'Par quoi commencer'],
                    ['type' => 'text', 'html' => '<ul><li><strong>Convenez d’une date limite</strong> avec vos 10 principaux fournisseurs.</li><li><strong>Rapprochez les principaux comptes bancaires</strong> chaque semaine, et non une fois par mois.</li><li><strong>Confiez chaque approbation</strong> à une personne nommée, avec une échéance.</li></ul>'],
                    ['type' => 'quote', 'text' => 'Nous avons clôturé mars en deux jours pour la première fois.', 'source' => 'Directeur financier, un grand industriel (démonstration)'],
                    $button('fr'),
                ],
            ]],
            'event-en' => ['event', 'en', [
                'subject' => 'Invitation: a breakfast on cash visibility, Lagos, 20 November',
                'headline' => 'Breakfast briefing: knowing your cash position every morning',
                'blocks' => [
                    ['type' => 'text', 'html' => '<p>We are hosting a short breakfast for finance directors and their teams. In an hour, we show how three organisations now see their cash position every morning, and what it took.</p>'],
                    ['type' => 'details', 'rows' => [['label' => 'Date', 'value' => 'Thursday 20 November 2026'], ['label' => 'Time', 'value' => '08:00 to 09:30 WAT'], ['label' => 'Place', 'value' => '21B Bode Ajakaiye Street, Lekki, Lagos']]],
                    ['type' => 'button', 'label' => 'Reserve your place', 'url' => 'https://jbspraxis.com/contact'],
                ],
            ]],
            'event-fr' => ['event', 'fr', [
                'subject' => "Invitation{$nbsp}: un petit-déjeuner sur la trésorerie, Douala, le 20 novembre",
                'headline' => "Petit-déjeuner{$nbsp}: connaître sa trésorerie chaque matin",
                'blocks' => [
                    ['type' => 'text', 'html' => '<p>Nous organisons un court petit-déjeuner pour les directeurs financiers et leurs équipes. En une heure, nous montrons comment trois organisations connaissent désormais leur trésorerie chaque matin, et ce que cela a demandé.</p>'],
                    ['type' => 'details', 'rows' => [['label' => 'Date', 'value' => 'jeudi 20 novembre 2026'], ['label' => 'Heure', 'value' => '8 h à 9 h 30 (WAT)'], ['label' => 'Lieu', 'value' => '1030 avenue Douala Manga Bell, Bali, Douala']]],
                    ['type' => 'button', 'label' => 'Réserver votre place', 'url' => 'https://jbspraxis.com/contact'],
                ],
            ]],
            'case_study-en' => ['case_study', 'en', [
                'subject' => 'How a distributor cut stock-outs by half',
                'blocks' => [
                    ['type' => 'heading', 'text' => 'The problem'],
                    ['type' => 'text', 'html' => '<p>A regional distributor (demo) ran out of its best sellers twice a month, while slow lines filled the warehouse. Orders were placed from memory.</p>'],
                    ['type' => 'figures', 'items' => [['value' => '−52%', 'label' => 'stock-outs'], ['value' => '₦38m', 'label' => 'stock released'], ['value' => '9 weeks', 'label' => 'from audit to go-live']]],
                    ['type' => 'quote', 'text' => 'We now order from the numbers, not from memory.', 'source' => 'Operations manager (demo)'],
                    $button('en'),
                ],
            ]],
            'case_study-fr' => ['case_study', 'fr', [
                'subject' => 'Comment un distributeur a réduit ses ruptures de moitié',
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Le problème'],
                    ['type' => 'text', 'html' => '<p>Un distributeur régional (démonstration) manquait de ses meilleures ventes deux fois par mois, pendant que les références lentes remplissaient l’entrepôt. Les commandes se faisaient de mémoire.</p>'],
                    ['type' => 'figures', 'items' => [['value' => "−52{$nnbsp}%", 'label' => 'de ruptures'], ['value' => "38{$nbsp}M{$nbsp}₦", 'label' => 'de stock libéré'], ['value' => '9 semaines', 'label' => 'de l’audit à la mise en service']]],
                    ['type' => 'quote', 'text' => 'Nous commandons désormais à partir des chiffres, et non de mémoire.', 'source' => 'Responsable des opérations (démonstration)'],
                    $button('fr'),
                ],
            ]],
            'letter-en' => ['letter', 'en', [
                'subject' => 'A note from the JBS Praxis team',
                'blocks' => [
                    ['type' => 'text', 'html' => '<p>Thank you for following our work this year. Three things we learned from the organisations we worked with:</p><ol><li><strong>Start with one process</strong>, not the whole company.</li><li><em>Measure before you change</em>: a baseline makes every result visible.</li><li>Keep the people who do the work in the room.</li></ol><p>If one of these speaks to you, <a href="https://jbspraxis.com/schedule">book a free audit and demo</a>.</p>'],
                ],
            ]],
            'letter-fr' => ['letter', 'fr', [
                'subject' => 'Un mot de l’équipe JBS Praxis',
                'blocks' => [
                    ['type' => 'text', 'html' => "<p>Merci d’avoir suivi notre travail cette année. Trois enseignements tirés des organisations que nous avons accompagnées{$nbsp}:</p><ol><li><strong>Commencez par un processus</strong>, pas par toute l’entreprise.</li><li><em>Mesurez avant de changer</em>{$nbsp}: une référence rend chaque résultat visible.</li><li>Associez les personnes qui font le travail.</li></ol><p>Si l’un d’eux vous parle, <a href=\"https://jbspraxis.com/schedule\">réservez un audit et une démonstration offerts</a>.</p>"],
                ],
            ]],
        ];
    }
}
