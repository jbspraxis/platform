<?php

namespace Tests\Unit;

use App\Support\Polyfill\Finfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypes;

/**
 * The finfo stand-in for the live web PHP (no fileinfo extension), the same as HQ's: the types HQ, the portal and
 * the website accept come out as the real finfo says, from content alone (uploads sit in PHP's temporary files with
 * no extension). Tests\Feature\WithoutFileinfoTest runs the careers CV check in a PHP without fileinfo at all.
 */
class FinfoPolyfillTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_common_uploads_match_the_real_finfo(): void
    {
        $samples = [
            'pdf' => "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='),
            'gif' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='),
            'txt' => "Hello,\nthis is a plain note. Café, déjà vu.\n",
            'docx' => $this->office('word/document.xml'),
            'xlsx' => $this->office('xl/workbook.xml'),
        ];

        $real = class_exists(\finfo::class) && ! is_a(\finfo::class, Finfo::class, true) ? new \finfo(FILEINFO_MIME_TYPE) : null;
        foreach ($samples as $kind => $bytes) {
            $path = $this->temp($bytes); // no extension, like an upload's temporary file
            $ours = (new Finfo(FILEINFO_MIME_TYPE))->file($path);
            if ($real) {
                $this->assertSame($real->file($path), $ours, "{$kind}: should match the real finfo");
            }
            $this->assertNotSame('application/octet-stream', $ours, $kind);
        }
    }

    public function test_laravel_extensions_come_out_right(): void
    {
        $mimes = MimeTypes::getDefault();
        $ext = fn (string $bytes) => $mimes->getExtensions((new Finfo(FILEINFO_MIME_TYPE))->file($this->temp($bytes)))[0] ?? null;

        $this->assertSame('pdf', $ext('%PDF-1.7 rest'));
        $this->assertSame('docx', $ext($this->office('word/document.xml')));
        $this->assertSame('png', $ext("\x89PNG\r\n\x1a\n" . str_repeat("\0", 20)));
        $this->assertSame('webm', $ext("\x1A\x45\xDF\xA3" . str_repeat("\0", 20)));
        $this->assertSame('wav', $ext('RIFF' . pack('V', 36) . 'WAVEfmt ' . str_repeat("\0", 20)));
        $this->assertSame('m4a', $ext("\0\0\0\x20ftypM4A \0\0\0\0" . str_repeat("\0", 16)));
        $this->assertSame('doc', $ext("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 64)));
    }

    public function test_flags_empty_files_and_names(): void
    {
        $f = new Finfo(FILEINFO_MIME);
        $this->assertSame('text/plain; charset=utf-8', $f->buffer("plain text"));
        $this->assertSame('application/pdf', (new Finfo(FILEINFO_MIME_TYPE))->buffer('%PDF-1.4'));
        $this->assertSame('application/x-empty', (new Finfo(FILEINFO_MIME_TYPE))->file($this->temp('')));
        $this->assertFalse((new Finfo(FILEINFO_MIME_TYPE))->file('/no/such/file'));
        // Unknown bytes: the name decides, else octet-stream
        $this->assertSame('application/octet-stream', Finfo::detect("\0\1\2\3binary"));
        $this->assertSame('application/vnd.ms-excel', Finfo::detect("\0\1\2\3binary", 'old.xls'));
    }

    private function temp(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ff');
        file_put_contents($path, $bytes);
        $this->files[] = $path;

        return $path;
    }

    /** A minimal Office Open XML file (a stored zip with the parts a real one starts with), built by hand */
    public static function office(string $main): string
    {
        $entries = [
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            '_rels/.rels' => '<?xml version="1.0"?><Relationships/>',
            $main => '<?xml version="1.0"?><x/>',
        ];
        $out = '';
        $central = '';
        foreach ($entries as $name => $data) {
            $offset = strlen($out);
            $crc = crc32($data);
            $len = strlen($data);
            $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $len, $len, strlen($name), 0) . $name . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }

        return $out . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($out), 0);
    }
}
