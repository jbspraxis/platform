<?php

namespace App\Support\Letters;

use App\Models\Letters\LetterDocument;
use App\Support\Campaigns\RichText;
use App\Support\CompanyProfile\ProfileCopy;
use Carbon\CarbonImmutable;

/**
 * What goes between the letterhead's header and its footer (docs/DOCUMENTS.md section 4). A template builds its
 * body from the few fields the author filled; a free letter and a Claude draft carry a rich-text body, cleaned by
 * App\Support\Campaigns\RichText so nothing but paragraphs, lists, bold, italics and links survives.
 *
 * Blocks: recipient, reference, subject, salutation, para, clauses (numbered "RESOLVED that ..."), points (a
 * short bulleted list), list, html, closing. The same blocks are drawn on screen and in the PDF, so the
 * preview is the document.
 *
 * An agreement (docs/DOCUMENTS.md section 12) prints a title block — the title, the reference and the date —
 * and then nothing but the body the author wrote, so a contract reads exactly as it was drafted, parties and
 * all. Who the other party is prints under their signature at the end, not twice.
 *
 * Every template but one writes English. The introduction letter (company_introduction) writes English or
 * French, following the language the author picked, and takes its words from the published company profile.
 */
final class LetterBody
{
    /** @return array<int, array<string, mixed>> */
    public static function blocks(LetterDocument $document, array $details): array
    {
        $f = (array) $document->fields;
        $date = CarbonImmutable::parse($document->document_date)->locale(self::locale($document));
        $blocks = [];

        if (! in_array($document->type, ['board_resolution', 'agreement'], true)) {
            $blocks[] = ['kind' => 'recipient', 'lines' => self::recipientLines($document)];
        }

        $blocks = array_merge($blocks, match ($document->type) {
            'board_resolution' => self::resolution($document, $details, $f, $date),
            'introduction' => self::introduction($document, $details, $f),
            'company_introduction' => self::companyIntroduction($document, $details, $f),
            'employment' => self::employment($document, $details, $f),
            'authority' => self::authority($document, $details, $f),
            'agreement' => self::agreement($document, $date),
            default => self::freeLetter($document, $f),
        });

        return array_values(array_filter($blocks));
    }

    /** The language one document is written in: 'fr' only where the template offers it, otherwise 'en' */
    public static function locale(LetterDocument $document): string
    {
        $locale = trim((string) (((array) $document->fields)['locale'] ?? ''));

        return $locale === 'fr' ? 'fr' : 'en';
    }

    private static function recipientLines(LetterDocument $document): array
    {
        $lines = [];
        if (filled($document->addressed_to)) {
            $lines[] = (string) $document->addressed_to;
        }
        if ($document->type === 'company_introduction') {
            // The contact and their email belong in the address block, as on any letter to a named person
            foreach (['contact_person', 'contact_email'] as $key) {
                $value = trim((string) (((array) $document->fields)[$key] ?? ''));
                if ($value !== '') {
                    $lines[] = $value;
                }
            }
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $document->addressed_to_address) as $line) {
            if (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }

        return $lines;
    }

    private static function resolution(LetterDocument $document, array $details, array $f, CarbonImmutable $date): array
    {
        $company = $details['name'];
        $place = self::text($f, 'place');
        $what = $document->subtype === 'appoint_signatory'
            ? 'Appointment of a signatory to the company’s bank account'
            : 'Opening of a corporate bank account';

        $blocks = [];
        $blocks[] = [
            'kind' => 'heading',
            'company' => $company,
            'registration' => $details['registration'] ? $details['registration_label'] . ' ' . $details['registration'] : null,
            'title' => 'Written resolution of the sole director',
            'subtitle' => $what,
        ];

        $blocks[] = ['kind' => 'para', 'text' => 'Passed on ' . $date->format('j F Y')
            . ($place !== '' ? ' at ' . $place : '') . '.'];

        if ($basis = self::text($f, 'legal_basis')) {
            $blocks[] = ['kind' => 'para', 'text' => $basis];
        }

        $blocks[] = ['kind' => 'para', 'text' => 'It was resolved as follows.'];

        $clauses = $document->subtype === 'appoint_signatory'
            ? self::appointSignatoryClauses($company, $f)
            : self::openAccountClauses($company, $f);

        $clauses[] = 'These resolutions remain in force until ' . self::bank($f) . ' receives written notice of their amendment or revocation, signed in the same manner.';
        $blocks[] = ['kind' => 'clauses', 'items' => $clauses];

        $blocks[] = ['kind' => 'para', 'text' => 'Dated this ' . self::ordinal((int) $date->format('j')) . ' day of ' . $date->format('F Y') . '.'];

        return $blocks;
    }

    private static function openAccountClauses(string $company, array $f): array
    {
        $bank = self::bank($f);
        $account = self::text($f, 'account_type') ?: 'corporate current account';
        $currency = self::text($f, 'currency');
        $mandate = self::text($f, 'mandate') ?: 'any one signatory';
        $clauses = [];

        $clauses[] = 'The company shall open a ' . self::lcfirst($account) . ($currency !== '' ? ' denominated in ' . $currency : '')
            . ' with ' . $bank . ' (the bank), in the name of ' . $company . '.';

        $people = self::people($f, 'signatories');
        if ($people) {
            $named = count($people) === 1
                ? 'the following person is appointed a signatory to the account:'
                : 'the following persons are appointed signatories to the account:';
            $clauses[] = ['text' => 'The account shall be operated in accordance with the mandate given to the bank, and ' . $named, 'people' => $people];
        }

        $clauses[] = 'The account shall be operated by ' . self::lcfirst($mandate) . '.';

        $who = self::text($f, 'authorised_name');
        $whoTitle = self::text($f, 'authorised_title');
        if ($who !== '') {
            $clauses[] = $who . ($whoTitle !== '' ? ', ' . $whoTitle . ',' : '')
                . ' is authorised to complete and sign the bank’s account opening forms and mandate, to give the bank the documents and information it requires, including the company’s registration documents and the identification of its directors, shareholders and signatories, and to do everything else needed to give effect to these resolutions.';
        }

        return $clauses;
    }

    private static function appointSignatoryClauses(string $company, array $f): array
    {
        $bank = self::bank($f);
        $account = self::text($f, 'account_type') ?: 'corporate current account';
        $number = self::text($f, 'account_number');
        $name = self::text($f, 'person_name');
        $title = self::text($f, 'person_title');
        $mandate = self::text($f, 'mandate') ?: 'any one signatory';
        $clauses = [];

        $clauses[] = $name . ($title !== '' ? ', ' . $title . ',' : '') . ' is appointed a signatory to the company’s '
            . self::lcfirst($account) . ' with ' . $bank . ($number !== '' ? ', account number ' . $number : '') . '.';
        $clauses[] = 'The mandate held by the bank shall be amended accordingly, and the account shall be operated by ' . self::lcfirst($mandate) . '.';

        $who = self::text($f, 'authorised_name');
        $whoTitle = self::text($f, 'authorised_title');
        if ($who !== '') {
            $clauses[] = $who . ($whoTitle !== '' ? ', ' . $whoTitle . ',' : '')
                . ' is authorised to give this resolution to the bank, to sign the forms it requires and to do everything else needed to give effect to it.';
        }

        return $clauses;
    }

    private static function introduction(LetterDocument $document, array $details, array $f): array
    {
        $name = self::text($f, 'person_name');
        $title = self::text($f, 'person_title');
        $blocks = [
            ['kind' => 'subject', 'text' => $document->title ?: 'Letter of introduction'],
            ['kind' => 'salutation', 'text' => self::text($f, 'salutation') ?: 'Dear Sir or Madam,'],
            ['kind' => 'para', 'text' => 'This letter introduces ' . $name . ($title !== '' ? ', ' . $title . ' of ' . $details['name'] : ' of ' . $details['name']) . '.'],
        ];

        if ($purpose = self::text($f, 'purpose')) {
            $blocks[] = ['kind' => 'para', 'text' => $purpose];
        }

        $until = self::date($f, 'valid_until');
        $blocks[] = ['kind' => 'para', 'text' => 'Any assistance you can give ' . ($name !== '' ? $name : 'them') . ' is appreciated. '
            . ($until ? 'This letter is good until ' . $until->format('j F Y') . '. ' : '')
            . 'If you would like to confirm it, please write to ' . $details['email'] . ' or call ' . ($details['phone'] ?: $details['email']) . '.'];
        $blocks[] = ['kind' => 'closing', 'text' => self::text($f, 'closing') ?: 'Yours faithfully,'];

        return $blocks;
    }

    /**
     * The one-page introduction of the firm to a company we would like to work with (docs/COMPANY_PROFILE.md
     * section 3). Everything but the greeting comes from the published company profile, so the letter and the
     * profile never drift apart; App\Support\CompanyProfile\ProfileCopy reads it in the right language.
     *
     * The shape, agreed with the lead chat on 5 November 2026, and built to hold one page in both languages:
     * the salutation, a short paragraph naming the company and why we write, a paragraph on what we build,
     * three proof lines, the call to action with the link, and the firm's own sign-off.
     */
    private static function companyIntroduction(LetterDocument $document, array $details, array $f): array
    {
        $locale = self::locale($document);
        $fr = $locale === 'fr';
        $copy = ProfileCopy::forLetter($locale);
        $person = self::text($f, 'contact_person');
        $company = trim((string) $document->addressed_to);
        $us = $details['name'];

        // 1. the salutation
        $salutation = self::text($f, 'salutation');
        if ($salutation === '') {
            $salutation = $fr
                ? 'Madame, Monsieur,'
                : ($person !== '' ? 'Dear ' . $person . ',' : 'Dear Sir or Madam,');
        }

        // 2. who we are, who we are writing to, and why
        $descriptor = rtrim($copy['descriptor'], '.');
        $opening = $fr
            ? 'Je vous écris au nom de ' . $us . ' — ' . self::lcfirst($descriptor) . ' — pour vous présenter notre maison'
                . ($company !== '' ? ' et ce que nous pourrions construire avec ' . $company : '') . '.'
            : 'I am writing on behalf of ' . $us . ' — ' . self::lcfirst($descriptor) . ' — to introduce the firm'
                . ($company !== '' ? ', and what we could build with ' . $company : '') . '.';

        $proofLead = $fr ? 'Trois systèmes qui tournent aujourd’hui :' : 'Three systems running today:';

        // 5. the call to action, with the link and a reply address
        $close = $fr
            ? $copy['cta'] . ' Vous pouvez prendre rendez-vous sur ' . $copy['cta_url']
                . ', ou simplement répondre à ' . $copy['email'] . ' et nous trouverons une heure qui vous convient.'
            : $copy['cta'] . ' You can book a time at ' . $copy['cta_url']
                . ', or simply reply to ' . $copy['email'] . ' and we will find one that suits you.';

        $blocks = [
            ['kind' => 'subject', 'text' => $copy['headline']],
            ['kind' => 'salutation', 'text' => $salutation],
            ['kind' => 'para', 'text' => $opening],
            ['kind' => 'para', 'text' => $copy['build']],
        ];

        if ($copy['proof'] !== []) {
            $blocks[] = ['kind' => 'para', 'text' => $proofLead];
            $blocks[] = ['kind' => 'points', 'items' => array_slice($copy['proof'], 0, ProfileCopy::PROOF_LINES)];
        }

        $blocks[] = ['kind' => 'para', 'text' => $close];
        $blocks[] = ['kind' => 'closing', 'text' => self::text($f, 'closing') ?: $copy['closing']];

        return $blocks;
    }

    private static function employment(LetterDocument $document, array $details, array $f): array
    {
        $name = self::text($f, 'employee_name');
        $job = self::text($f, 'job_title');
        $basis = self::text($f, 'employment_type');
        $started = self::date($f, 'started_on');
        $pay = self::text($f, 'pay');
        $purpose = self::text($f, 'purpose');

        $first = $name . ' has been employed by ' . $details['name'] . ' as ' . self::article($job) . ' ' . $job
            . ($started ? ' since ' . $started->format('j F Y') : '') . '.'
            . ($basis !== '' ? ' The appointment is ' . self::lcfirst($basis) . '.' : '');

        $blocks = [
            ['kind' => 'subject', 'text' => $document->title ?: 'Letter of employment'],
            ['kind' => 'salutation', 'text' => self::text($f, 'salutation') ?: 'Dear Sir or Madam,'],
            ['kind' => 'para', 'text' => $first],
        ];

        if ($pay !== '') {
            $blocks[] = ['kind' => 'para', 'text' => 'Their gross pay is ' . $pay . '.'];
        }
        if ($purpose !== '') {
            $blocks[] = ['kind' => 'para', 'text' => 'This letter is given at their request for the purpose of ' . self::lcfirst($purpose) . '.'];
        }

        $blocks[] = ['kind' => 'para', 'text' => 'If you would like to confirm anything in this letter, please write to ' . $details['email'] . '.'];
        $blocks[] = ['kind' => 'closing', 'text' => self::text($f, 'closing') ?: 'Yours faithfully,'];

        return $blocks;
    }

    private static function authority(LetterDocument $document, array $details, array $f): array
    {
        $name = self::text($f, 'holder_name');
        $title = self::text($f, 'holder_title');
        $from = self::date($f, 'valid_from');
        $until = self::date($f, 'valid_until');

        $blocks = [
            ['kind' => 'subject', 'text' => $document->title ?: 'Authority to act'],
            ['kind' => 'para', 'text' => $details['name'] . ' authorises ' . $name . ($title !== '' ? ', ' . $title . ',' : '')
                . ' to act on its behalf in the matters set out below'
                . ($from ? ', from ' . $from->format('j F Y') : '')
                . ($until ? ' until ' . $until->format('j F Y') : '') . '.'],
        ];

        $scope = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', self::text($f, 'scope')))));
        if ($scope) {
            $blocks[] = ['kind' => 'clauses', 'items' => $scope];
        }
        if ($limits = self::text($f, 'limits')) {
            $blocks[] = ['kind' => 'para', 'text' => 'This authority does not extend to ' . self::lcfirst(rtrim($limits, '.')) . '.'];
        }
        $blocks[] = ['kind' => 'para', 'text' => 'This authority stays in force until it is withdrawn in writing by the company.'];

        return $blocks;
    }

    /**
     * An agreement: a centred title, the reference and the date under it, then the body. Headings are kept with
     * the text under them (keepHeadingsWithText), because a contract that leaves "7. Intellectual property"
     * alone at the foot of a page reads as though a clause went missing.
     */
    private static function agreement(LetterDocument $document, CarbonImmutable $date): array
    {
        $blocks = [[
            'kind' => 'agreement_head',
            'title' => (string) ($document->title ?: 'Agreement'),
            'reference' => (string) $document->reference,
            'date' => $date->format('j F Y'),
        ]];

        $html = self::keepHeadingsWithText(RichText::clean((string) $document->body));
        if ($html !== '') {
            $blocks[] = ['kind' => 'html', 'html' => $html, 'agreement' => true];
        }

        return $blocks;
    }

    /** A block this short may be carried onto the next page with the heading above it, rather than left behind */
    private const KEEP_WITH_HEADING = 600;

    /**
     * Wraps every heading and the block under it in one <div class="keep">, which the document's CSS tells dompdf
     * not to break inside. dompdf does not understand page-break-after: avoid, so this is how a heading is kept
     * with its text. Only a short block is carried along: a long one is left to break where it will, so a heading
     * near the foot of a page never pushes half a page of white space ahead of it.
     */
    public static function keepHeadingsWithText(string $html): string
    {
        if ($html === '' || ! preg_match('~<h[23][^a-z]~i', $html)) {
            return $html;
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><html><body><div id="kp-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('kp-root');
        if (! $root) {
            return $html;
        }

        foreach (iterator_to_array($root->childNodes) as $node) {
            if (! $node instanceof \DOMElement || ! in_array(strtolower($node->tagName), ['h2', 'h3'], true)) {
                continue;
            }
            $keep = $doc->createElement('div');
            $keep->setAttribute('class', 'keep');
            $root->insertBefore($keep, $node);
            $keep->appendChild($node);

            $next = $keep->nextSibling;
            while ($next instanceof \DOMText && trim($next->nodeValue) === '') {
                $next = $next->nextSibling;
            }
            if ($next instanceof \DOMElement && ! in_array(strtolower($next->tagName), ['h2', 'h3'], true)
                && mb_strlen(trim($next->textContent)) <= self::KEEP_WITH_HEADING) {
                $keep->appendChild($next);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function freeLetter(LetterDocument $document, array $f): array
    {
        $blocks = [];
        if (filled($document->title)) {
            $blocks[] = ['kind' => 'subject', 'text' => (string) $document->title];
        }
        if ($salutation = self::text($f, 'salutation')) {
            $blocks[] = ['kind' => 'salutation', 'text' => $salutation];
        }
        $html = RichText::clean((string) $document->body);
        if ($html !== '') {
            $blocks[] = ['kind' => 'html', 'html' => $html];
        }
        if ($closing = self::text($f, 'closing')) {
            $blocks[] = ['kind' => 'closing', 'text' => $closing];
        }

        return $blocks;
    }

    // ------------------------------------------------------------------ small helpers

    private static function bank(array $f): string
    {
        return self::text($f, 'bank_name') ?: 'the bank';
    }

    private static function text(array $f, string $key): string
    {
        return trim((string) ($f[$key] ?? ''));
    }

    private static function date(array $f, string $key): ?CarbonImmutable
    {
        $value = self::text($f, $key);

        return $value === '' ? null : rescue(fn () => CarbonImmutable::parse($value), null, false);
    }

    /** @return array<int, array{name:string, title:string}> */
    private static function people(array $f, string $key): array
    {
        $rows = is_array($f[$key] ?? null) ? $f[$key] : [];
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $out[] = ['name' => $name, 'title' => trim((string) ($row['title'] ?? ''))];
            }
        }

        return $out;
    }

    private static function lcfirst(string $text): string
    {
        return $text === '' ? '' : mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private static function article(string $word): string
    {
        return in_array(mb_strtolower(mb_substr(trim($word), 0, 1)), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
    }

    /** "4th", "1st", "22nd": the day as a written resolution dates itself */
    public static function ordinal(int $day): string
    {
        $suffix = match (true) {
            $day % 100 >= 11 && $day % 100 <= 13 => 'th',
            $day % 10 === 1 => 'st',
            $day % 10 === 2 => 'nd',
            $day % 10 === 3 => 'rd',
            default => 'th',
        };

        return $day . $suffix;
    }
}
