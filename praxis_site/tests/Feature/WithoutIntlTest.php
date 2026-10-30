<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The live web PHP has no intl extension, so App\Support\Format writes digits without NumberFormatter. French pages
 * must still read "1 250 000" and "1 234,6" (docs/BRAND.md: U+202F between thousands, a decimal comma), not the
 * English "1,250,000". tests/Fixtures/without-intl.php runs in this PHP and in a second PHP without intl (php -n,
 * then every other extension this PHP loads); both must write the same.
 */
class WithoutIntlTest extends TestCase
{
    public function test_numbers_read_the_same_without_intl_in_english_and_french(): void
    {
        $php = $this->phpWithout('intl');
        if ($php === null) {
            $this->markTestSkipped('intl is built into this PHP: it cannot be left out');
        }

        $without = $this->runFixture($php);
        $this->assertFalse($without['intl'], 'the second PHP still has intl');

        $nnbsp = "\u{202F}";
        $this->assertSame([
            'en' => [
                'number' => '1,250,000', 'decimals' => '1,234.6', 'negative' => '−2,500,000',
                'percent' => '3.3%', 'naira' => '₦1,250,000', 'fcfa' => '2,500,000 FCFA',
            ],
            'fr' => [
                'number' => "1{$nnbsp}250{$nnbsp}000", 'decimals' => "1{$nnbsp}234,6", 'negative' => "−2{$nnbsp}500{$nnbsp}000",
                'percent' => '3,3%', 'naira' => "₦1{$nnbsp}250{$nnbsp}000", 'fcfa' => "2{$nnbsp}500{$nnbsp}000 FCFA",
            ],
        ], $without['formats']);

        // With intl (this PHP, when it has it), the same text: older ICU versions group French with U+00A0
        if (extension_loaded('intl')) {
            $with = $this->runFixture([PHP_BINARY]);
            $this->assertTrue($with['intl']);
            array_walk_recursive($with['formats'], fn (&$v) => $v = str_replace("\u{00A0}", $nnbsp, $v));
            $this->assertSame($with['formats'], $without['formats']);
        }
    }

    private function runFixture(array $php): array
    {
        $run = Process::path(base_path())
            ->env(['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null', 'SESSION_DRIVER' => 'array'])
            ->run([...$php, base_path('tests/Fixtures/without-intl.php')]);
        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());

        return $out;
    }

    /**
     * A PHP without $extension: this one without php.ini (-n) plus the extensions it loads except that one (so a
     * suite already run with -n and fewer extensions keeps them that way: the bare binary would read php.ini again
     * and could get the extension back), as HQ's tests/Support/PhpWithout.php does; null when it is compiled in.
     */
    private function phpWithout(string $extension): ?array
    {
        $dir = (string) ini_get('extension_dir');
        if (! is_dir($dir)) {
            $dir = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $dir;
        }
        $shared = fn (string $name) => is_file("{$dir}/php_{$name}.dll") || is_file("{$dir}/{$name}.so");
        if (extension_loaded($extension) && ! $shared($extension)) {
            return null;
        }

        $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . $dir, '-d', 'memory_limit=512M'];
        $zend = array_map('strtolower', get_loaded_extensions(true));
        foreach (get_loaded_extensions() as $loaded) {
            $name = strtolower($loaded);
            // Compiled-in extensions stay without asking; Zend ones (OPcache, Xdebug) are not needed here
            if ($name !== $extension && ! in_array($name, $zend, true) && $shared($name)) {
                array_push($command, '-d', 'extension=' . $name);
            }
        }

        return $command;
    }
}
