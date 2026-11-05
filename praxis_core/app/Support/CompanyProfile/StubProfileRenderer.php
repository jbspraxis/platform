<?php

namespace App\Support\CompanyProfile;

/**
 * A placeholder renderer, so that the Download button, the route and the tests work before PR 2 lands the
 * designed one. It writes a single A4 landscape page — the headline, the descriptor and a line per section
 * saying what the real profile will carry — by hand, with no PDF library and no font files, so it cannot
 * break on a server missing gd, imagick or zip (the same ground DOCUMENTS.md found).
 *
 * PR 2 replaces the CompanyProfileRenderer binding in AppServiceProvider. Nothing else changes.
 */
class StubProfileRenderer implements CompanyProfileRenderer
{
    private const WIDTH = 842;   // A4 landscape, in points

    private const HEIGHT = 595;

    public function pdf(array $data): string
    {
        $meta = $data['meta'] ?? [];
        $lines = [
            ['size' => 22, 'text' => (string) ($data['cover']['headline'] ?? 'JBS Praxis')],
            ['size' => 12, 'text' => (string) ($data['cover']['descriptor'] ?? '')],
            ['size' => 9, 'text' => ''],
            ['size' => 9, 'text' => 'Company profile '.($meta['version'] ?? '').'  '.($meta['reference'] ?? '')
                .'  '.strtoupper((string) ($meta['locale'] ?? '')).'  '.($meta['published_at'] ?? 'not published')],
            ['size' => 9, 'text' => 'This is a placeholder. The designed profile is rendered by the company profile renderer.'],
            ['size' => 9, 'text' => ''],
        ];

        foreach (Sections::KEYS as $key) {
            $lines[] = ['size' => 9, 'text' => '- '.Sections::label($key).': '.$this->summarise($key, $data)];
        }

        return $this->document($this->content($lines));
    }

    private function summarise(string $key, array $data): string
    {
        $section = $data[$key] ?? [];
        $title = (string) ($section['title'] ?? $section['headline'] ?? $section['cta'] ?? '');

        $counts = [];
        foreach (['items', 'groups', 'pillars', 'stages', 'principles', 'paragraphs', 'logos', 'offices'] as $list) {
            if (is_array($section[$list] ?? null)) {
                $counts[] = count($section[$list]).' '.$list;
            }
        }

        return trim($title.($counts ? ' ('.implode(', ', $counts).')' : ''));
    }

    /** @param list<array{size: int, text: string}> $lines */
    private function content(array $lines): string
    {
        $y = self::HEIGHT - 70;
        $out = [];

        foreach ($lines as $line) {
            if ($y < 40) {
                break;
            }
            if ($line['text'] !== '') {
                $out[] = 'BT /F1 '.$line['size'].' Tf 56 '.$y.' Td ('.$this->escape($line['text']).') Tj ET';
            }
            $y -= (int) round($line['size'] * 1.7);
        }

        return implode("\n", $out)."\n";
    }

    /** WinAnsi, with the parentheses and backslashes a PDF string cannot carry raw */
    private function escape(string $text): string
    {
        $text = (string) preg_replace('/\s+/u', ' ', trim($text));
        $text = mb_substr($text, 0, 160);
        $winansi = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($winansi === false) {
            $winansi = (string) preg_replace('/[^\x20-\x7E]/', '', $text);
        }

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $winansi);
    }

    private function document(string $content): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT.']'
                .' /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $start = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n"."0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n"
            .'startxref'."\n".$start."\n".'%%EOF';

        return $pdf;
    }
}
