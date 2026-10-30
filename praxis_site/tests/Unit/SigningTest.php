<?php

namespace Tests\Unit;

use App\Support\HqApi;
use PHPUnit\Framework\TestCase;

/** The signing test vectors of docs/SITE_API.md section 6.1 (they replace section 1's): both sides must reproduce them. */
class SigningTest extends TestCase
{
    public function test_the_json_body_hash_matches_the_contract(): void
    {
        $this->assertSame('9815d6fc0ef9ca98be55b9208af405483e24ba8739309bb7cf77d4bd74747b84', hash('sha256', '{"email":"a@b.co"}'));
    }

    public function test_the_json_vector_reproduces_the_contract(): void
    {
        $canonical = HqApi::canonical('POST', '/api/site/v1/enquiries', '1700000000', 'abcdef0123456789', '203.0.113.5', 'en', 'test-key', 'application/json', '{"email":"a@b.co"}');

        $this->assertSame(
            "POST\n/api/site/v1/enquiries\n1700000000\nabcdef0123456789\n203.0.113.5\nen\ntest-key\napplication/json\n9815d6fc0ef9ca98be55b9208af405483e24ba8739309bb7cf77d4bd74747b84",
            $canonical
        );
        $this->assertSame('7ce906b3ff5b4ecc10ee6d0b21a4c588ab73e451ecf18e93ff79118320b55948', HqApi::signature('test-secret', $canonical));
    }

    public function test_the_multipart_vector_reproduces_the_contract(): void
    {
        $this->assertSame('d663640088750cf16276d623c2588d7233f2b84b45f4b2e20832f47b16aa5618', hash('sha256', '%PDF-1.4 test'));

        $bodyHash = HqApi::multipartHash(['full_name' => 'Test Person', 'email' => 'a@b.co'], ['cv' => hash('sha256', '%PDF-1.4 test')]);
        $this->assertSame(
            hash('sha256', "f:cv=d663640088750cf16276d623c2588d7233f2b84b45f4b2e20832f47b16aa5618\nt:email=YUBiLmNv\nt:full_name=VGVzdCBQZXJzb24="),
            $bodyHash,
            'one line per field, sorted by field name: f:<name>=<sha256>, t:<name>=<base64>'
        );
        $this->assertSame('bfc9b253f09bd08d200f77e3a7d9afaee3c0cbbf2fdd3aeb54ea92443ee5d308', $bodyHash);

        $canonical = HqApi::canonicalWithHash('POST', '/api/site/v1/careers/applications', '1700000000', '0123456789abcdef', '2001:db8::7', 'fr', 'test-key', 'multipart/form-data', $bodyHash);
        $this->assertSame('23a66b9f55b39fa51646cfcd0fd23f93f4ad29ec3f34762dce03533ea84521ac', HqApi::signature('test-secret', $canonical));
    }

    public function test_multipart_lines_are_sorted_by_field_name_not_by_line(): void
    {
        // "a" (text) sorts before "b" (file) by name, although "f:" would sort before "t:" as a line
        $expected = hash('sha256', 't:a=' . base64_encode('x') . "\nf:b=" . hash('sha256', 'y'));

        $this->assertSame($expected, HqApi::multipartHash(['a' => 'x'], ['b' => hash('sha256', 'y')]));
    }

    public function test_a_value_with_a_newline_cannot_forge_another_line(): void
    {
        $forged = HqApi::multipartHash(['a' => "x\nt:b=" . base64_encode('y')], []);
        $honest = HqApi::multipartHash(['a' => 'x', 'b' => 'y'], []);

        $this->assertNotSame($honest, $forged);
    }

    public function test_the_media_type_drops_parameters_and_is_lower_case(): void
    {
        $this->assertSame('multipart/form-data', HqApi::mediaType('Multipart/Form-Data; boundary=abc'));
        $this->assertSame('application/json', HqApi::mediaType('application/json; charset=utf-8'));
        $this->assertSame('', HqApi::mediaType(null));
    }

    public function test_the_method_is_upper_cased_and_an_empty_body_hashes_the_empty_string(): void
    {
        $canonical = HqApi::canonical('get', '/api/site/v1/booking/slots?month=2026-11', '1700000000', 'abcdef0123456789', '203.0.113.5', 'fr', 'test-key', '', '');

        $this->assertStringStartsWith("GET\n/api/site/v1/booking/slots?month=2026-11\n", $canonical);
        $this->assertStringEndsWith("\nfr\ntest-key\n\n" . hash('sha256', ''), $canonical);
        $this->assertCount(9, explode("\n", $canonical));
    }
}
