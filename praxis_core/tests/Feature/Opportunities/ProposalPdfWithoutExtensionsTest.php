<?php

namespace Tests\Feature\Opportunities;

use App\Support\Opportunities\ProposalPdf;
use App\Support\Opportunities\Proposals;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\PhpWithout;
use Tests\TestCase;

/**
 * The proposal's PDF library, dompdf (docs/OPPORTUNITIES.md section 9 and section 12, PR 4), on a PHP like the live
 * web PHP: without intl, fileinfo, exif, sodium, gmp, gd or imagick. A real proposal in French (accents, « », the
 * narrow no-break space) is rendered by a second PHP that leaves those extensions out. An extension compiled into
 * the PHP running the tests can't be left out and is reported as such.
 */
class ProposalPdfWithoutExtensionsTest extends TestCase
{
    use OpportunityFixtures, PhpWithout, UsesThrowawayDatabase;

    private const MISSING_ON_LIVE = ['intl', 'fileinfo', 'exif', 'sodium', 'gmp', 'gd', 'imagick'];

    public function test_a_french_proposal_renders_without_the_extensions_live_lacks(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunityFor($this->organisation(['name' => 'Société Générale du Littoral (démo)']), ['language' => 'fr', 'lead_user_id' => $lead->id]);
        $p = Proposals::start($o, 'XAF', $lead);
        Proposals::save($p, ['title' => 'Une clôture mensuelle en cinq jours', 'sections' => [
            ['key' => 'executive_summary', 'title' => 'Synthèse', 'body' => "Votre clôture prend 15\u{202F}jours\u{202F}; nous recommandons «\u{202F}trois phases\u{202F}».\n\nÉtapes\n- Diagnostic\n- Conception"],
        ], 'lines' => [['code' => '#1292P', 'description' => 'Diagnostic et audit', 'quantity' => '2 phases', 'unit_price' => 612000]]], $lead);
        [$html, $footer, $pageLabel] = ProposalPdf::page($p->fresh());
        $this->assertStringContainsString("Préparée pour", $html);

        // Loaded here and removable (a shared library): left out of the second PHP; compiled in: kept and reported
        $loaded = array_values(array_filter(self::MISSING_ON_LIVE, 'extension_loaded'));
        $builtIn = array_values(array_filter($loaded, fn ($ext) => $this->phpWithout($ext) === null));
        $removable = array_values(array_diff($loaded, $builtIn));
        $php = $removable ? $this->phpWithout($removable[0]) : [PHP_BINARY];
        $kept = [];
        for ($i = 0; $i < count($php); $i++) {
            $next = $php[$i + 1] ?? '';
            if ($php[$i] === '-d' && str_starts_with($next, 'extension=') && in_array(substr($next, 10), $removable, true)) {
                $i++;

                continue;
            }
            $kept[] = $php[$i];
        }

        $dir = storage_path('framework/testing/pdf-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/page.json", json_encode(compact('html', 'footer', 'pageLabel')));
        $code = 'require ' . var_export(base_path('vendor/autoload.php'), true) . ';'
            . '$in = json_decode(file_get_contents(' . var_export("{$dir}/page.json", true) . '), true);'
            . '$pdf = App\Support\Opportunities\ProposalPdf::fromHtml($in["html"], $in["footer"], $in["pageLabel"], ' . var_export($dir, true) . ');'
            . 'echo json_encode(["loaded" => array_values(array_filter(' . var_export(self::MISSING_ON_LIVE, true) . ', "extension_loaded")), "head" => substr($pdf, 0, 5), "size" => strlen($pdf), "fonts" => substr_count($pdf, "/FontFile2")]);';

        $run = Process::path(base_path())->timeout(120)->run([...$kept, '-r', $code]);
        File::deleteDirectory($dir);

        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());
        $this->assertSame($builtIn, $out['loaded'], 'The second PHP still has an extension that could have been left out');
        $this->assertSame('%PDF-', $out['head']);
        $this->assertGreaterThan(5000, $out['size']);
        $this->assertGreaterThan(0, $out['fonts'], 'DejaVu is embedded (subset), so French renders on any reader');
    }
}
