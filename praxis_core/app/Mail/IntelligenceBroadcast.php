<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/**
 * A broadcast written before Campaigns (3 Nov 2026) in the old Broadcasts screen, still sent by broadcast:process if one was queued;
 * new broadcasts are BroadcastEmail (docs/CAMPAIGNS.md).
 * The subject and the HTML body are the campaign's own, with the contact's details already filled in.
 * The layout adds the sign-off, the unsubscribe link (footer and List-Unsubscribe header) and the open-tracking pixel.
 */
class IntelligenceBroadcast extends BrandedMail
{
    public $editorialSubject;
    public $htmlBody;
    public $unsubscribeUrl;
    public $trackingPixelUrl;

    /** Inline styles for the tags the composer writes, so its plain HTML reads like the rest of the email. Existing style attributes are kept. */
    private const BODY_STYLES = [
        'p' => "margin: 0 0 16px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'ul' => "margin: 0 0 16px; padding: 0 0 0 24px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'ol' => "margin: 0 0 16px; padding: 0 0 0 24px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'li' => "margin: 0 0 8px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;",
        'h1' => "margin: 24px 0 12px; font-family: 'Newsreader', Georgia, serif; font-weight: 400; font-size: 22px; line-height: 28px; color: #0C142B;",
        'h2' => "margin: 24px 0 12px; font-family: 'Newsreader', Georgia, serif; font-weight: 400; font-size: 22px; line-height: 28px; color: #0C142B;",
        'h3' => "margin: 20px 0 8px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-weight: 600; font-size: 17px; line-height: 26px; color: #0C142B;",
        'strong' => 'font-weight: 600; color: #0C142B;',
        'b' => 'font-weight: 600; color: #0C142B;',
        'a' => 'color: #08707C; text-decoration: underline;',
    ];

    public function __construct($subjectLine, $htmlBody, $unsubscribeUrl = null, $trackingPixelUrl = null)
    {
        $this->editorialSubject = $subjectLine;
        $this->htmlBody = $htmlBody;
        $this->unsubscribeUrl = $unsubscribeUrl;
        $this->trackingPixelUrl = $trackingPixelUrl;
    }

    /** The editorial subject, as written in the composer. */
    protected function subjectLine(): string
    {
        return (string) $this->editorialSubject;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.broadcast', with: [
            'body' => $this->styledBody(),
            'preheader' => $this->preheader(),
        ]);
    }

    /**
     * Lets inboxes show their own unsubscribe button (it opens the confirmation page).
     * One-click unsubscribe (List-Unsubscribe-Post, RFC 8058) is left out: the link opens the public site (praxis_site),
     * whose POST route needs a CSRF token and passes the form guard, neither of which an inbox can send. Adding it needs
     * a site route that takes the POST with only the link's token (campaign and t) and calls HQ's unsubscribe API;
     * then this header can add 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'.
     */
    public function headers(): Headers
    {
        return new Headers(text: $this->unsubscribeUrl ? ['List-Unsubscribe' => '<' . $this->unsubscribeUrl . '>'] : []);
    }

    private function styledBody(): string
    {
        return preg_replace_callback('~<(p|ul|ol|li|h[1-3]|strong|b|a)\b(?![^>]*\bstyle\s*=)([^>]*)>~i', function ($m) {
            return '<' . $m[1] . ' style="' . self::BODY_STYLES[strtolower($m[1])] . '"' . $m[2] . '>';
        }, (string) $this->htmlBody);
    }

    /** The opening words of the body, without the greeting, for the inbox preview line. */
    private function preheader(): string
    {
        $text = preg_replace('~</(p|li|h[1-6]|div)>|<br\s*/?>~i', ' ', (string) $this->htmlBody);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $text = preg_replace('/^(dear|hello|hi|bonjour|chère|cher)\b(?:\s+madame,\s*monsieur)?[^,.]{0,40},\s*/iu', '', $text);

        return Str::limit($text, 140, '…');
    }

    public static function samples(): array
    {
        $unsubscribe = route('tracking.unsubscribe', ['user_id' => '00000000-0000-4000-8000-000000000001']);
        $pixel = route('tracking.open', ['campaign_id' => '00000000-0000-4000-8000-000000000002', 'user_id' => '00000000-0000-4000-8000-000000000001']);

        return [
            'campaign-en' => new static( // a sample named -en or -fr renders only in that language
                'Three checks before your month-end close',
                '<p>Hello Ada,</p>'
                . '<p>Most month-end delays we see at organisations like Acme Ltd (demo) come from three places: late supplier invoices, manual bank reconciliations and approvals that wait in someone’s inbox.</p>'
                . '<ul><li><strong>Agree a cut-off date</strong> with your 10 largest suppliers.</li><li><strong>Reconcile the main bank accounts</strong> every week, not once a month.</li><li><strong>Give every approval</strong> a named owner and a deadline.</li></ul>'
                . '<p>If you would like to see where your own close slows down, reply to this email and we will arrange a free audit and demo.</p>',
                $unsubscribe,
                $pixel,
            ),
            'campaign-fr' => new static(
                'Trois vérifications avant votre clôture mensuelle',
                '<p>Bonjour,</p>'
                . "<p>La plupart des retards de clôture que nous observons dans des organisations comme Acme Ltd (demo) ont trois origines\u{00A0}: les factures fournisseurs tardives, les rapprochements bancaires manuels et les approbations qui attendent dans une boîte de réception.</p>"
                . '<ul><li><strong>Convenez d’une date limite</strong> avec vos 10 principaux fournisseurs.</li><li><strong>Rapprochez les principaux comptes bancaires</strong> chaque semaine, et non une fois par mois.</li><li><strong>Confiez chaque approbation</strong> à une personne nommée, avec une échéance.</li></ul>'
                . "<p>Pour savoir où votre propre clôture ralentit, répondez à cet e-mail\u{00A0}: nous organiserons un audit et une démonstration offerts.</p>",
                $unsubscribe,
                $pixel,
            ),
        ];
    }
}
