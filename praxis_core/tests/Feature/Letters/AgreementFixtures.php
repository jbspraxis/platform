<?php

namespace Tests\Feature\Letters;

use App\Models\Letters\LetterDocument;
use App\Models\User;
use App\Support\Campaigns\RichText;
use App\Support\Letters\Agreements;
use App\Support\Letters\Letters;

/**
 * What the Agreement tests work with (docs/DOCUMENTS.md section 12): the first real agreement, the project
 * contractor agreement the firm drafted on 5 November 2026, and a counterparty to send it to.
 *
 * The body is held here as Markdown and turned into the editor's HTML by markdownToHtml(), because the real
 * screen is given HTML by a browser and the fixture has to arrive the same way. It is only a test helper: the
 * module itself never reads Markdown.
 */
trait AgreementFixtures
{
    protected function contractMarkdown(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/contractor-agreement.md');
    }

    /** The fixture contract as the rich-text body the editor would have sent, cleaned exactly as the screen cleans it */
    protected function contractBody(): string
    {
        return RichText::clean($this->markdownToHtml($this->contractMarkdown()));
    }

    /** A draft agreement with the contract in it and the engineer as the other party */
    protected function agreement(User $owner, array $company, array $party = []): LetterDocument
    {
        $document = Letters::start('agreement', null, $company, $owner);
        $document->forceFill([
            'title' => 'Project Contractor Agreement',
            'body' => $this->contractBody(),
        ])->save();

        Agreements::saveCounterparty($document, $party + [
            'name' => 'Jonathan Ndamzi Destiny',
            'role_title' => 'Full-Stack Engineer',
            'email' => 'jonathan@example.test',
            'address' => "Port Harcourt\nRivers State\nNigeria",
        ]);

        return $document->fresh();
    }

    /**
     * The little of Markdown the fixture uses, as the editor's HTML: headings, paragraphs, bold, nested bullet
     * lists and pipe tables. The first "# " line is the title and is left out of the body.
     */
    protected function markdownToHtml(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($markdown));
        $html = '';
        $paragraph = [];
        $depths = [];        // the indents of the lists open right now, outermost first

        $flush = function () use (&$html, &$paragraph) {
            if ($paragraph) {
                $html .= '<p>' . implode('<br>', $paragraph) . '</p>';
                $paragraph = [];
            }
        };
        $closeTo = function (int $depth) use (&$html, &$depths) {
            while ($depths && end($depths) > $depth) {
                $html .= '</li></ul>';
                array_pop($depths);
            }
        };
        $closeAll = function () use (&$html, &$depths) {
            while ($depths) {
                $html .= '</li></ul>';
                array_pop($depths);
            }
        };
        $inline = fn (string $text) => preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e(trim($text)));
        $cells = fn (string $row) => array_map('trim', array_slice(explode('|', trim($row)), 1, -1));

        for ($i = 0; $i < count($lines); $i++) {
            $line = rtrim($lines[$i]);
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed === '---') {
                $flush();
                $closeAll();
                continue;
            }

            // A table: the header row, the ---|--- rule under it, then the body rows
            if (str_starts_with($trimmed, '|') && isset($lines[$i + 1]) && preg_match('/^\|[\s:|-]+\|$/', trim($lines[$i + 1]))) {
                $flush();
                $closeAll();
                $html .= '<table><thead><tr>';
                foreach ($cells($trimmed) as $cell) {
                    $html .= '<th>' . $inline($cell) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                $i += 2;
                while ($i < count($lines) && str_starts_with(trim($lines[$i]), '|')) {
                    $html .= '<tr>';
                    foreach ($cells(trim($lines[$i])) as $cell) {
                        $html .= '<td>' . $inline($cell) . '</td>';
                    }
                    $html .= '</tr>';
                    $i++;
                }
                $i--;
                $html .= '</tbody></table>';
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
                $flush();
                $closeAll();
                $level = strlen($m[1]);
                if ($level === 1) {
                    continue;   // the title: the document carries it, not the body
                }
                $tag = $level === 2 ? 'h2' : 'h3';
                $html .= '<' . $tag . '>' . $inline($m[2]) . '</' . $tag . '>';
                continue;
            }

            if (preg_match('/^(\s*)[-*]\s+(.*)$/', $line, $m)) {
                $flush();
                $depth = strlen($m[1]);
                if (! $depths || $depth > end($depths)) {
                    $html .= '<ul>';
                    $depths[] = $depth;
                } else {
                    $closeTo($depth);
                    $html .= '</li>';
                }
                $html .= '<li>' . $inline($m[2]);
                continue;
            }

            $closeAll();
            $paragraph[] = $inline($trimmed);
        }
        $flush();
        $closeAll();

        return $html;
    }
}
