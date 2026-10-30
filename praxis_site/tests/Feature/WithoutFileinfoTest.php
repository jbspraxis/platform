<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use Tests\Unit\FinfoPolyfillTest;

/**
 * The live web PHP has no fileinfo extension, and the careers CV's "mimes" rule needs finfo to read a file's type
 * (without it Symfony throws "Unable to guess the MIME type"). A second PHP is started here without fileinfo
 * (php -n, then every other extension this PHP loads) and runs the rules on real files: tests/Fixtures/without-fileinfo.php.
 */
class WithoutFileinfoTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_the_careers_cv_check_works_in_a_php_without_fileinfo(): void
    {
        $php = $this->phpWithoutFileinfo();
        if ($php === null) {
            $this->markTestSkipped('fileinfo is built into this PHP: it cannot be left out');
        }

        $files = [
            'cv.pdf' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
            'cv.docx' => FinfoPolyfillTest::office('word/document.xml'),
            'cv.doc' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 504),
            'photo.pdf' => "\x89PNG\r\n\x1a\n" . str_repeat("\0", 32),   // a picture renamed .pdf
            'setup.pdf' => "MZ\x90\0\3\0\0\0\4\0\0\0\xFF\xFF\0\0" . str_repeat("\0", 48),   // a program renamed .pdf
        ];
        $args = [];
        foreach ($files as $name => $bytes) {
            $path = tempnam(sys_get_temp_dir(), 'cv');   // no extension, like an upload's temporary file
            file_put_contents($path, $bytes);
            $this->files[] = $path;
            $args[] = $name . '=' . $path;
        }

        $run = Process::path(base_path())
            ->env(['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null', 'SESSION_DRIVER' => 'array'])
            ->run([...$php, base_path('tests/Fixtures/without-fileinfo.php'), ...$args]);
        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());

        $this->assertFalse($out['fileinfo'], 'the second PHP still has fileinfo');
        $this->assertSame('App\Support\Polyfill\Finfo', $out['finfo'], 'the stand-in is in place');
        $this->assertSame([
            'cv.pdf' => 'passes',
            'cv.docx' => 'passes',
            'cv.doc' => 'passes',
            'photo.pdf' => 'fails mimes',
            'setup.pdf' => 'fails mimes',
        ], $out['files']);
    }

    /**
     * A PHP without fileinfo: this one as it is when it already lacks it, else this one without php.ini (-n) plus the
     * extensions it loads except fileinfo; null when fileinfo is compiled in. (A PHP 8.5 without fileinfo, started
     * with -n and its own extensions, hung here on Windows; as it is, it runs.)
     */
    private function phpWithoutFileinfo(): ?array
    {
        if (! extension_loaded('fileinfo')) {
            return [PHP_BINARY];
        }

        $dir = (string) ini_get('extension_dir');
        if (! is_dir($dir)) {
            $dir = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $dir;
        }
        $shared = fn (string $name) => is_file("{$dir}/php_{$name}.dll") || is_file("{$dir}/{$name}.so");
        if (! $shared('fileinfo')) {
            return null;
        }

        $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . $dir, '-d', 'memory_limit=512M'];
        $zend = array_map('strtolower', get_loaded_extensions(true));
        foreach (get_loaded_extensions() as $extension) {
            $name = strtolower($extension);
            // Compiled-in extensions stay without asking; Zend ones (OPcache, Xdebug) are not needed here
            if ($name !== 'fileinfo' && ! in_array($name, $zend, true) && $shared($name)) {
                array_push($command, '-d', 'extension=' . $name);
            }
        }

        return $command;
    }
}
