<?php

namespace Tests\Feature\Letters;

use App\Support\Letters\Letterhead;
use App\Support\Letters\LetterPdf;
use App\Support\Letters\Letters;
use App\Support\Letters\Qr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\PhpWithout;
use Tests\TestCase;

/**
 * The letterhead on a PHP like the live web PHP: without intl, fileinfo, exif, sodium, gmp, gd or imagick
 * (docs/DOCUMENTS.md section 3). This is the reason the logo and the QR code are SVG and a signature is a
 * JPEG: php-svg-lib draws SVG and dompdf carries a JPEG as it is, while a PNG needs gd, which is not there.
 *
 * A whole document is built here — logo, QR code and a signature on it — and rendered by a second PHP that
 * leaves those extensions out. An extension compiled into the PHP running the tests cannot be left out and is
 * reported as such.
 */
class LetterPdfWithoutExtensionsTest extends TestCase
{
    use LetterFixtures, PhpWithout, UsesThrowawayDatabase;

    private const MISSING_ON_LIVE = ['intl', 'fileinfo', 'exif', 'sodium', 'gmp', 'gd', 'imagick'];

    public function test_a_signed_document_with_its_qr_code_renders_without_the_extensions_live_lacks(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $company = $this->company();

        $document = Letters::start('board_resolution', 'open_bank_account', $company, $owner);
        $document->forceFill([
            'title' => 'Resolution of the sole director: opening of a corporate bank account',
            'show_qr' => true,
            'verify_token' => str_repeat('a', 48),
            'fields' => array_merge((array) $document->fields, [
                'bank_name' => 'Example Microfinance Bank',
                'signatories' => [['name' => 'Ada Test', 'title' => 'Director']],
            ]),
        ])->save();
        Letters::sign($document, $owner);

        $details = Letterhead::details($company);
        $html = LetterPdf::html($document->fresh(), $details, final: true);

        // Everything the page draws is carried in the page itself: nothing is read off disk while it renders
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html, 'the logo and the QR code are SVG');
        $this->assertStringContainsString('data:image/jpeg;base64,', $html, 'the signature is a JPEG');
        $this->assertStringNotContainsString('data:image/png', $html, 'a PNG would need gd, which the live PHP has not got');

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

        $dir = storage_path('framework/testing/letter-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/page.json", json_encode([
            'html' => $html, 'legal' => $details['footer_legal'], 'contact' => $details['footer_contact'],
        ]));

        $code = 'require ' . var_export(base_path('vendor/autoload.php'), true) . ';'
            . '$in = json_decode(file_get_contents(' . var_export("{$dir}/page.json", true) . '), true);'
            . '$pdf = App\Support\Letters\LetterPdf::fromHtml($in["html"], $in["legal"], $in["contact"], ' . var_export($dir, true) . ');'
            . 'echo json_encode(["loaded" => array_values(array_filter(' . var_export(self::MISSING_ON_LIVE, true) . ', "extension_loaded")),'
            . ' "head" => substr($pdf, 0, 5), "size" => strlen($pdf), "fonts" => substr_count($pdf, "/FontFile2"), "jpeg" => substr_count($pdf, "DCTDecode")]);';

        $run = Process::path(base_path())->timeout(180)->run([...$kept, '-r', $code]);
        File::deleteDirectory($dir);

        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());
        $this->assertSame($builtIn, $out['loaded'], 'The second PHP still has an extension that could have been left out');
        $this->assertSame('%PDF-', $out['head']);
        $this->assertGreaterThan(10000, $out['size']);
        $this->assertGreaterThan(0, $out['fonts'], 'DejaVu is embedded (subset), so the document reads on any reader');
        $this->assertGreaterThan(0, $out['jpeg'], 'the signature is in the file as a JPEG');
    }

    public function test_the_qr_code_and_the_logo_need_nothing_but_php_itself(): void
    {
        $svg = Qr::svg('https://jbspraxis.com/verify/' . str_repeat('a', 48));
        $this->assertStringContainsString('<path d="M', $svg);

        $logo = Letterhead::logoSvg();
        $this->assertStringContainsString('<path', $logo);
        // The brand kit's file nests one <svg> inside another, which php-svg-lib cannot place: the copy the
        // letterhead uses is flat (docs/DOCUMENTS.md section 3)
        $this->assertSame(1, substr_count($logo, '<svg'), 'the letterhead logo is one flat <svg>');
        $this->assertStringNotContainsString('<use', $logo);
        $this->assertStringNotContainsString('clip-path', $logo);
    }
}
