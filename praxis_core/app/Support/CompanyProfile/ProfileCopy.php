<?php

namespace App\Support\CompanyProfile;

/**
 * The few sentences the introduction letter borrows from the company profile (company-profile/CONTRACT.md):
 * the headline and the descriptor, what we build, three proof lines, and the call to action.
 *
 * It reads the **published** profile through the engine's own Profile::data(), so the letter never says
 * anything the profile does not. The copy itself belongs to the profile (docs/COMPANY_PROFILE.md section 7);
 * none of it is kept here. While nothing is published, Profile::data() raises MissingProfile and the letter
 * falls back to the short house copy at the bottom of this file, which carries **no client work at all**: a
 * letter is never empty, and it never claims anything the profile has not published.
 *
 * Nothing here invents a figure, names a person or mentions a price, and a client is named only as the profile
 * names them: the contract's rules hold here too.
 */
final class ProfileCopy
{
    /** How many proof lines a one-page letter has room for */
    public const PROOF_LINES = 3;

    /**
     * @return array{headline: string, descriptor: string, build: string, proof: list<string>, cta: string, cta_url: string, email: string, website: string, closing: string, published: bool}
     */
    public static function forLetter(string $locale = 'en'): array
    {
        $locale = $locale === 'fr' ? 'fr' : 'en';
        $published = self::published($locale);
        $data = $published ?? [];
        $fallback = self::fallback($locale);

        $proof = self::proofLines($data);

        return [
            'headline' => self::first([$data['cover']['headline'] ?? null], $fallback['headline']),
            'descriptor' => self::first([$data['cover']['descriptor'] ?? null], $fallback['descriptor']),
            'build' => self::whatWeBuild($data) ?: $fallback['build'],
            'proof' => $proof,
            'cta' => self::first([$data['contact']['cta'] ?? null], $fallback['cta']),
            'cta_url' => self::first([$data['contact']['cta_url'] ?? null], $fallback['cta_url']),
            'email' => self::first([$data['contact']['sales_email'] ?? null, $data['contact']['email'] ?? null], $fallback['email']),
            'website' => self::first([$data['contact']['website'] ?? null], $fallback['website']),
            // The letter is signed by the firm, not with a personal valediction (the lead chat, 5 Nov)
            'closing' => 'JBS Praxis',
            'published' => $published !== null,
        ];
    }

    /**
     * The published profile, or null when nothing has been published. Profile::data() raises MissingProfile
     * in that case, which is not an error here: the letter writes on the house copy below instead.
     */
    private static function published(string $locale): ?array
    {
        try {
            $data = Profile::data($locale);
        } catch (MissingProfile) {
            return null;
        }

        return $data !== [] ? $data : null;
    }

    /**
     * What we build, in the profile's own words: the point of view's closing paragraph, without the sentence
     * that only makes sense after the two before it ("We close it differently."). A paragraph of fewer than
     * three sentences is used whole.
     */
    private static function whatWeBuild(array $data): string
    {
        $paragraphs = (array) ($data['pov']['paragraphs'] ?? []);
        $paragraph = trim((string) (end($paragraphs) ?: ''));
        if ($paragraph === '') {
            return '';
        }

        $sentences = self::sentences($paragraph);

        return count($sentences) >= 3 ? implode(' ', array_slice($sentences, 1, 2)) : $paragraph;
    }

    /**
     * Three proof lines: a client and what the work did for them, from the profile's selected work. Our own
     * products are not proof of what we do for a client, so they are left out. A client is named exactly as
     * the profile names them, which is where the naming rules are kept.
     *
     * @return list<string>
     */
    private static function proofLines(array $data): array
    {
        $lines = [];
        foreach ((array) ($data['work']['items'] ?? []) as $item) {
            $client = trim((string) ($item['client'] ?? ''));
            $outcome = trim((string) ($item['outcome'] ?? ''));
            if ($client === '' || $outcome === '' || self::isOurOwn((string) ($item['sector'] ?? ''))) {
                continue;
            }
            $lines[] = $client . ' — ' . $outcome;
            if (count($lines) === self::PROOF_LINES) {
                break;
            }
        }

        return $lines;
    }

    private static function isOurOwn(string $sector): bool
    {
        $sector = mb_strtolower($sector);

        return str_contains($sector, 'our product') || str_contains($sector, 'notre produit');
    }

    /** @return list<string> */
    private static function sentences(string $paragraph): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÀ-ÖØ-Þ])/u', $paragraph) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $part) => $part !== ''));
    }

    private static function first(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $fallback;
    }

    /**
     * The last resort, when no profile has been published: short house copy in the brand's own voice
     * (docs/BRAND.md sections 4 and 5), so a letter is never empty. It carries **no proof lines**, because a
     * client's work is the profile's to publish, not this file's to remember; the letter leaves that block
     * out until there is a published profile to take it from.
     */
    private static function fallback(string $locale): array
    {
        if ($locale === 'fr') {
            return [
                'headline' => 'Pilotez tout. Interrogez tout.',
                'descriptor' => 'Des systèmes de pilotage intelligents pour les organisations.',
                'build' => 'Nous concevons le fonctionnement de l’organisation, puis le système unique qui la fait '
                    . 'fonctionner ainsi et qui en rend compte. Posez-lui toutes vos questions : il répond.',
                'cta' => 'Réservez un audit et une démonstration gratuits.',
                'cta_url' => 'https://jbspraxis.com/fr/schedule',
                'email' => 'sales@jbspraxis.com',
                'website' => 'jbspraxis.com',
            ];
        }

        return [
            'headline' => 'Run everything. Ask anything.',
            'descriptor' => 'Intelligent operating systems for organisations.',
            'build' => 'We design how the organisation should run, then build the one system that runs it that way '
                . 'and answers for it. Ask it anything, and it answers.',
            'cta' => 'Book a free audit and demo.',
            'cta_url' => 'https://jbspraxis.com/schedule',
            'email' => 'sales@jbspraxis.com',
            'website' => 'jbspraxis.com',
        ];
    }
}
