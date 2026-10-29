<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Finds retired wording and shouting capitals in what people read on HQ, the Client Portal and in emails
 * (docs/BRAND.md section 5; the list is docs/glossary.csv). Only visible text is checked: template text,
 * placeholder/title/label attributes, and messages in flash(), addError() and notification titles.
 * Code names (routes, variables, classes, database columns) are ignored.
 *
 *   php artisan app:copy-check                       every HQ and portal screen
 *   php artisan app:copy-check --path=resources/views/livewire/hq/finance --path=app/Livewire/Hq/Finance
 */
class CopyCheck extends Command
{
    protected $signature = 'app:copy-check {--path=* : files or folders, relative to praxis_core} {--summary : counts per file only}';

    protected $description = 'Find retired wording and all-caps text in HQ, portal and email screens';

    private const DEFAULT_PATHS = [
        'resources/views/livewire/hq', 'resources/views/components/hq', 'resources/views/livewire/client-os',
        'resources/views/livewire/portal', 'resources/views/livewire/tracker', 'resources/views/components/portal',
        'resources/views/components/layouts', 'resources/views/layouts/portal.blade.php', 'resources/views/layouts/client-light.blade.php',
        'resources/views/emails', 'resources/views/components/mail', 'resources/views/partials',
        'app/Livewire/Hq', 'app/Livewire/ClientOs', 'app/Livewire/Portal', 'app/Livewire/Tracker', 'app/Mail', 'lang/en/emails', 'lang/fr/emails',
    ];

    /** Capitalised words that are fine: acronyms and codes */
    private const ACRONYMS = ['JBS', 'HQ', 'IFRS', 'OHADA', 'COPQ', 'PDF', 'CSV', 'VAT', 'PAYE', 'WAT', 'UTC', 'NGN', 'XAF', 'FCFA', 'USD', 'EUR', 'GBP',
        'KPI', 'KPIS', 'QA', 'ID', 'IDS', 'API', 'SHA', 'CAC', 'RCCM', 'TIN', 'NHF', 'NSITF', 'ITF', 'CIT', 'WHT', 'PIT', 'NIN', 'BVN', 'CRM', 'ERP', 'AI',
        'GL', 'COA', 'SOW', 'OTP', 'URL', 'HTML', 'ISO', 'IBAN', 'SWIFT', 'BIC', 'CEO', 'CFO', 'COO', 'HR', 'IT', 'UK', 'US', 'FAQ', 'PNG', 'JPG', 'JPEG',
        'MB', 'KB', 'GB', 'SMS', 'PIN', 'NDPA', 'DPA', 'EN', 'FR', 'ES', 'OK', 'RC', 'BN', 'LTD', 'PLC', 'SA', 'SARL', 'CNPS', 'IRPP', 'TVA', 'NIU', 'IRS',
        'ROAS', 'CPA', 'CPC', 'CTR', 'SEO', 'UTM', 'GPS', 'WIP', 'MVP', 'UAT', 'SLA', 'ETA', 'PO', 'GRN', 'AP', 'AR', 'FX', 'YTD', 'MTD', 'QTD', 'P&L'];

    public function handle(): int
    {
        $rules = $this->rules();
        $paths = $this->option('path') ?: self::DEFAULT_PATHS;
        $hits = [];
        foreach ($this->files($paths) as $file) {
            $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file);
            foreach ($this->visibleText(File::get($file), str_ends_with($file, '.blade.php')) as [$line, $text]) {
                foreach ($rules as [$retired, $pattern, $use]) {
                    if (preg_match('~' . $pattern . '~iu', $text, $m)) {
                        $hits[] = [$rel, $line, "\"{$m[0]}\" → {$use}", $text];
                    }
                }
                if ($caps = $this->shouting($text)) {
                    $hits[] = [$rel, $line, "capitals: {$caps}", $text];
                }
            }
        }

        if ($this->option('summary')) {
            foreach (collect($hits)->countBy(0)->sortDesc() as $file => $n) {
                $this->line(sprintf('%4d  %s', $n, $file));
            }
        } else {
            foreach ($hits as [$file, $line, $what, $text]) {
                $this->line("{$file}:{$line}  {$what}  | " . mb_strimwidth(trim(preg_replace('/\s+/', ' ', $text)), 0, 110, '…'));
            }
        }
        $this->info(count($hits) . ' finding(s)');

        return $hits ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<array{0:string,1:string,2:string}> retired word, regex, replacement */
    private function rules(): array
    {
        $csv = base_path('../docs/glossary.csv');
        if (!is_file($csv)) {
            $this->warn('docs/glossary.csv not found next to praxis_core: checking capitals only');
            return [];
        }
        $rows = array_map('str_getcsv', array_filter(explode("\n", File::get($csv))));
        array_shift($rows);

        return array_map(fn ($r) => [$r[0], $r[1], $r[2] ?? ''], array_filter($rows, fn ($r) => count($r) >= 3));
    }

    private function files(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            $full = base_path($p);
            if (is_file($full)) {
                $out[] = $full;
            } elseif (is_dir($full)) {
                foreach (Finder::create()->files()->in($full)->name(['*.php']) as $f) {
                    $out[] = $f->getRealPath();
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Text a person reads, with its line number. Blade: text between tags plus placeholder, title, alt,
     * aria-label and value attributes, without Blade/PHP code. PHP: messages in flash(), addError(),
     * and 'title'/'message' entries of notifications.
     *
     * @return list<array{0:int,1:string}>
     */
    private function visibleText(string $src, bool $blade): array
    {
        $out = [];
        $lineOf = fn (int $offset) => substr_count(substr($src, 0, $offset), "\n") + 1;

        if ($blade) {
            // Blank out code but keep offsets, so line numbers stay right
            $mask = fn ($m) => preg_replace('/[^\n]/', ' ', $m[0]);
            $s = preg_replace_callback('~\{\{--.*?--\}\}|@php\b.*?@endphp|<\?php.*?\?>|<script\b.*?</script>|<style\b.*?</style>|\{\{.*?\}\}|\{!!.*?!!\}|@[a-zA-Z]+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?~s', $mask, $src);
            foreach (['placeholder', 'title', 'alt', 'aria-label', 'value', 'data-tooltip'] as $attr) {
                preg_match_all('~\b' . $attr . '="([^"]+)"~', $s, $m, PREG_OFFSET_CAPTURE);
                foreach ($m[1] as [$text, $off]) {
                    if (trim($text) !== '') $out[] = [$lineOf($off), $text];
                }
            }
            $s = preg_replace_callback('~<[^>]*>~s', $mask, $s);
            preg_match_all('~[^\s<>][^<>\n]*~u', $s, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as [$text, $off]) {
                if (preg_match('/\p{L}{2,}/u', $text)) $out[] = [$lineOf($off), $text];
            }

            return $out;
        }

        // PHP: the human messages, not identifiers
        $patterns = [
            '~(?:flash|addError|abort)\s*\(\s*(?:[\'"][^\'"]*[\'"]\s*,\s*)?(["\'])((?:\\\\.|(?!\1).)*)\1~s',
            '~[\'"](?:title|message|label|body)[\'"]\s*=>\s*(["\'])((?:\\\\.|(?!\1).)*)\1~s',
            '~new\s+\\\\?Exception\s*\(\s*(["\'])((?:\\\\.|(?!\1).)*)\1~s',
        ];
        foreach ($patterns as $p) {
            preg_match_all($p, $src, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[2] as [$text, $off]) {
                if (preg_match('/\p{L}{2,}/u', $text)) $out[] = [$lineOf($off), $text];
            }
        }

        return $out;
    }

    /** Two or more capitalised words in a row that are not acronyms (sentence case everywhere, section 4) */
    private function shouting(string $text): ?string
    {
        preg_match_all('/\b\p{Lu}{3,}\b/u', $text, $m);
        $words = array_filter($m[0], fn ($w) => !in_array($w, self::ACRONYMS, true));
        if (count($words) >= 2 || (count($words) === 1 && mb_strlen(trim($text)) === mb_strlen(reset($words)) && mb_strlen(reset($words)) > 4)) {
            return implode(' ', array_slice($words, 0, 4));
        }

        return null;
    }
}
