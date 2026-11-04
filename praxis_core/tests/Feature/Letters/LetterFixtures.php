<?php

namespace Tests\Feature\Letters;

use App\Models\Letters\LetterSignature;
use App\Models\User;
use App\Support\Letters\Letterhead;
use App\Support\Letters\Signatures;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * What the Documents tests need (docs/DOCUMENTS.md): the Nigerian legal entity with the company's own public
 * registration details, an owner to act as, and a signature that is plainly not anybody's real one.
 */
trait LetterFixtures
{
    /** Every fixture account shares one password, so a test can type it again for the fresh sign-in check */
    protected const PASSWORD = 'letters-fixture-password';

    protected function owner(): User
    {
        $user = $this->staff('owner', ['owner']);
        $user->forceFill(['first_name' => 'Ada', 'last_name' => 'Test', 'job_title' => 'Director', 'password' => Hash::make(self::PASSWORD)])->save();
        SignIn::markFresh();

        return $user->fresh();
    }

    /** The Nigerian legal entity, as Master data › Legal entities holds it */
    protected function company(): array
    {
        $id = DB::table('mdm_legal_entities')->where('region_code', 'NG')->value('id') ?? (string) Str::uuid();
        DB::table('mdm_legal_entities')->updateOrInsert(['id' => $id], [
            'region_code' => 'NG',
            'legal_name' => 'JBS Praxis Limited',
            'registration_number' => '9985884',
            'tax_identification_number' => '2622490936068',
            'registered_address' => "21B Bode Ajakaiye Street\nLekki, Lagos\nNigeria",
            'contact_email' => 'info@jbspraxis.com',
            'contact_phone' => '+234 816 978 9106',
            'legal_representative_name' => 'Ada Test',
            'legal_representative_title' => 'Director',
            'is_active' => 1,
        ]);

        return Letterhead::company($id);
    }

    /**
     * The parts of a zip, read without the zip extension (the live PHP has not got it, which is why
     * App\Support\Letters\DocxLetterhead writes the archive itself).
     *
     * @return array<string, string> path inside the archive => its bytes
     */
    protected function unzip(string $bytes): array
    {
        $out = [];
        $at = 0;
        while (($at = strpos($bytes, "PK\x03\x04", $at)) !== false) {
            $header = unpack('vversion/vflag/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname/vextra', substr($bytes, $at + 4, 26));
            $start = $at + 30 + $header['name'] + $header['extra'];
            $name = substr($bytes, $at + 30, $header['name']);
            $data = substr($bytes, $start, $header['compressed']);
            $contents = $header['method'] === 8 ? (string) gzinflate($data) : $data;

            $this->assertSame($header['crc'], crc32($contents), "the archive's copy of {$name} is whole");
            $this->assertSame($header['uncompressed'], strlen($contents), "the archive's size for {$name} is right");
            $out[$name] = $contents;
            $at = $start + $header['compressed'];
        }

        return $out;
    }

    protected function signatureFor(User $user, string $name = 'Ada Test', string $title = 'Director'): LetterSignature
    {
        return Signatures::save($user, $this->jpeg(), $name, $title, 'drawn', 60, 20);
    }

    /**
     * A tiny JPEG, written out byte by byte so the fixture needs no gd (which the live PHP has not got) and
     * carries nobody's signature: a 1 by 1 grey square.
     */
    protected function jpeg(): string
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAA'
            . 'AQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIh'
            . 'MUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpT'
            . 'VFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5'
            . 'usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iii'
            . 'gD//2Q=='
        );

        return 'data:image/jpeg;base64,' . base64_encode((string) $bytes);
    }
}
