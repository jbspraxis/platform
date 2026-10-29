<?php

namespace App\Console\Commands;

use App\Mail\BrandedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

/**
 * Renders every branded email with fictional data, in English and French, for review;
 * or sends them to one address to check them in a real inbox.
 *
 *   php artisan app:email-samples                         files in storage/app/email-samples
 *   php artisan app:email-samples --only=invitation --locale=fr
 *   php artisan app:email-samples --send=someone@example.com --only=enquiry
 */
class EmailSamples extends Command
{
    protected $signature = 'app:email-samples
        {--locale=* : en and/or fr (default both)}
        {--only= : part of a class or sample name}
        {--send= : send the samples to this address instead of writing files}
        {--out= : folder for the files (default storage/app/email-samples)}';

    protected $description = 'Render or send every branded email with sample data, in English and French';

    public function handle(): int
    {
        $locales = $this->option('locale') ?: ['en', 'fr'];
        $only = strtolower((string) $this->option('only'));
        $send = $this->option('send');
        $out = $this->option('out') ?: storage_path('app/email-samples');
        if (!$send) {
            File::ensureDirectoryExists($out);
        }

        $index = [];
        foreach ($this->mailClasses($only) as $class) {
            foreach (array_keys($class::samples()) as $name) {
                $slug = class_basename($class) . '-' . $name;
                if ($only !== '' && !str_contains(strtolower($slug), $only)) {
                    continue;
                }
                // English-only emails stay English; a sample named "...-en" or "...-fr" exists in that language only
                $sampleLocales = preg_match('/-(en|fr)$/', $name, $m) ? [$m[1]] : $class::locales();
                foreach (array_intersect($locales, $sampleLocales) as $locale) {
                    $mail = $class::samples()[$name]->locale($locale); // a fresh instance each time
                    if ($send) {
                        Mail::to($send)->send($mail);
                        $this->line("sent  {$slug} ({$locale})");
                        continue;
                    }
                    $parts = $mail->renderParts();
                    File::put("{$out}/{$slug}.{$locale}.html", $this->inlineLogo($parts['html']));
                    File::put("{$out}/{$slug}.{$locale}.txt", "Subject: {$parts['subject']}\n\n{$parts['text']}");
                    $index[] = ['slug' => $slug, 'locale' => $locale, 'subject' => $parts['subject']];
                    $this->line("wrote {$slug}.{$locale}: {$parts['subject']}");
                }
            }
        }

        if (!$send) {
            File::put("{$out}/index.json", json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info(count($index) . " samples in {$out}");
        }

        return self::SUCCESS;
    }

    /**
     * When --only names a class, only that file is loaded, so a broken mail class elsewhere can't stop the preview.
     *
     * @return list<class-string<BrandedMail>>
     */
    private function mailClasses(string $only = ''): array
    {
        $files = File::files(app_path('Mail'));
        $named = array_filter($files, fn ($f) => $only !== '' && str_contains(strtolower($f->getFilenameWithoutExtension()), $only));
        $classes = [];
        foreach ($named ?: $files as $file) {
            $class = 'App\\Mail\\' . $file->getFilenameWithoutExtension();
            if (class_exists($class) && is_subclass_of($class, BrandedMail::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }

    /** Files open offline, so the hosted logo is swapped for the local copy. */
    private function inlineLogo(string $html): string
    {
        $url = config('mail.brand.logo');
        foreach ([base_path('../public_html_PRAXIS'), base_path('../public_html/PRAXIS')] as $root) {
            $file = $root . '/assets/img/email/jbs-praxis-email-logo@2x.png';
            if (is_file($file)) {
                return str_replace($url, 'data:image/png;base64,' . base64_encode(file_get_contents($file)), $html);
            }
        }

        return $html;
    }
}
