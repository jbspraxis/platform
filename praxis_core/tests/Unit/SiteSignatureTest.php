<?php

namespace Tests\Unit;

use App\Services\Site\SiteLimits;
use App\Services\Site\SiteSignature;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * The site API's request signature (docs/SITE_API.md section 6.1). Both test vectors of the contract;
 * praxis_site keeps the same vectors in its own SigningTest. No database or app needed.
 */
class SiteSignatureTest extends TestCase
{
    private const SECRET = 'test-secret';

    public function test_json_vector(): void
    {
        $body = '{"email":"a@b.co"}';
        $bodyHash = hash('sha256', $body);
        $this->assertSame('9815d6fc0ef9ca98be55b9208af405483e24ba8739309bb7cf77d4bd74747b84', $bodyHash);

        $canonical = SiteSignature::canonical('POST', '/api/site/v1/enquiries', '1700000000', 'abcdef0123456789', '203.0.113.5', 'en',
            'test-key', 'application/json', $bodyHash);

        $this->assertSame(9, count(explode("\n", $canonical)));
        $this->assertSame('7ce906b3ff5b4ecc10ee6d0b21a4c588ab73e451ecf18e93ff79118320b55948', SiteSignature::sign(self::SECRET, $canonical));
    }

    public function test_multipart_vector(): void
    {
        $cv = $this->file('%PDF-1.4 test');
        $this->assertSame('d663640088750cf16276d623c2588d7233f2b84b45f4b2e20832f47b16aa5618', hash_file('sha256', $cv->getRealPath()));

        $bodyHash = SiteSignature::multipartHash(['full_name' => 'Test Person', 'email' => 'a@b.co'], ['cv' => $cv]);
        $this->assertSame(hash('sha256', implode("\n", [
            'f:cv=d663640088750cf16276d623c2588d7233f2b84b45f4b2e20832f47b16aa5618',
            't:email=YUBiLmNv',
            't:full_name=VGVzdCBQZXJzb24=',
        ])), $bodyHash);
        $this->assertSame('bfc9b253f09bd08d200f77e3a7d9afaee3c0cbbf2fdd3aeb54ea92443ee5d308', $bodyHash);

        $canonical = SiteSignature::canonical('POST', '/api/site/v1/careers/applications', '1700000000', '0123456789abcdef', '2001:db8::7', 'fr',
            'test-key', 'multipart/form-data', $bodyHash);
        $this->assertSame('23a66b9f55b39fa51646cfcd0fd23f93f4ad29ec3f34762dce03533ea84521ac', SiteSignature::sign(self::SECRET, $canonical));
    }

    public function test_ambiguous_forms_have_no_hash(): void
    {
        // An array field, a non-text field, and a text field named like a file field are refused (401)
        $this->assertNull(SiteSignature::multipartHash(['tags' => ['x']], []));
        $this->assertNull(SiteSignature::multipartHash(['flag' => true], []));
        $this->assertNull(SiteSignature::multipartHash(['cv' => 'f:' . str_repeat('0', 64)], ['cv' => $this->file('x')]));
        $this->assertNull(SiteSignature::multipartHash([], ['cv' => [$this->file('x')]]));

        // A value with a line break can't forge another line: it is base64
        $this->assertNotSame(
            SiteSignature::multipartHash(['a' => "1\nt:b=Mg=="], []),
            SiteSignature::multipartHash(['a' => '1', 'b' => '2'], []),
        );
    }

    public function test_visitor_buckets_ipv6_to_64(): void
    {
        $this->assertSame('203.0.113.5', SiteLimits::visitor('203.0.113.5'));
        $this->assertSame('2001:db8:1:2::/64', SiteLimits::visitor('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        $this->assertSame(SiteLimits::visitor('2001:db8::1'), SiteLimits::visitor('2001:db8::ffff:2'));
        $this->assertNotSame(SiteLimits::visitor('2001:db8:0:1::1'), SiteLimits::visitor('2001:db8:0:2::1'));
        $this->assertSame('203.0.113.5', SiteLimits::visitor('::ffff:203.0.113.5'));
    }

    private function file(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'cv.pdf', 'application/pdf', null, true);
    }
}
