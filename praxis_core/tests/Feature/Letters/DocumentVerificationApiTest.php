<?php

namespace Tests\Feature\Letters;

use App\Services\Site\SiteSignature;
use App\Support\Hosts;
use App\Support\Letters\Letters;
use App\Support\Letters\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * GET /api/site/v1/documents/{token} and …/file (docs/DOCUMENTS.md section 7, docs/SITE_API.md): what
 * jbspraxis.com's verify page reads. Signed like every other site call; an unknown token says nothing.
 */
class DocumentVerificationApiTest extends TestCase
{
    use AgreementFixtures, LetterFixtures, UsesThrowawayDatabase;

    private const KEY_ID = 'test-key';

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['site_api.keys' => [self::KEY_ID => self::SECRET]]);
    }

    public function test_a_countersigned_agreement_names_both_parties_and_both_fingerprints(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);

        $document = $this->agreement($owner, $this->company());
        $document->forceFill(['show_qr' => true])->save();
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        $party = \App\Support\Letters\Agreements::send($document->fresh(), $owner);
        $code = \App\Support\Signing\SigningCodes::newCode();
        \App\Support\Letters\Agreements::holdSignature($party, 'Jonathan Ndamzi Destiny', substr($this->jpeg(), 23), $code);
        $this->assertTrue(\App\Support\Letters\Agreements::countersign($party->fresh(), $code, '102.89.33.10'));

        $signed = $document->fresh();
        auth()->logout();
        $data = $this->signedGet('/documents/' . $signed->verify_token)->assertOk()->json('data');

        $this->assertSame('fully_signed', $data['status']);
        $this->assertTrue($data['countersigned']);
        $this->assertSame($signed->file_sha256, $data['file_sha256'], 'the issued fingerprint never changes');
        $this->assertSame($signed->signed_file_sha256, $data['signed_file_sha256']);
        $this->assertNotSame($data['file_sha256'], $data['signed_file_sha256']);
        $this->assertSame([
            ['name' => 'Ada Test', 'title' => 'Director'],
            ['name' => 'Jonathan Ndamzi Destiny', 'title' => 'Full-Stack Engineer'],
        ], $data['signatories']);

        // Nothing the other party was given privately: not their address, not their link
        $json = json_encode($data);
        $this->assertStringNotContainsString('jonathan@example.test', $json);
        $this->assertStringNotContainsString((string) $party->fresh()->token, $json);
    }

    public function test_an_issued_document_answers_with_what_it_says_in_print(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);

        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $document->forceFill(['title' => 'Confirmation of our bank details', 'addressed_to' => 'Foxtrot Foods Limited (test)', 'show_qr' => true, 'copy_public' => true])->save();
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);
        $issued = $document->fresh();
        auth()->logout();

        $data = $this->signedGet('/documents/' . $issued->verify_token)->assertOk()->json('data');

        $this->assertSame($issued->reference, $data['reference']);
        $this->assertSame('Confirmation of our bank details', $data['title']);
        $this->assertSame('JBS Praxis Limited', $data['issued_by']);
        $this->assertSame('Foxtrot Foods Limited (test)', $data['addressed_to']);
        $this->assertSame('issued', $data['status']);
        $this->assertSame([['name' => 'Ada Test', 'title' => 'Director']], $data['signatories']);
        $this->assertSame($issued->file_sha256, $data['file_sha256']);
        $this->assertTrue($data['copy_available']);

        // Nothing internal: no ids, no email addresses, not the token itself
        $json = json_encode($data);
        $this->assertStringNotContainsString($issued->id, $json);
        $this->assertStringNotContainsString($issued->verify_token, $json);
        $this->assertStringNotContainsString($owner->email, $json);
    }

    public function test_the_copy_is_served_only_when_the_document_allows_it(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $open = $this->issued($owner, ['title' => 'Open letter', 'copy_public' => true]);
        $closed = $this->issued($owner, ['title' => 'Confidential letter', 'copy_public' => false]);
        auth()->logout();

        $response = $this->signedGet('/documents/' . $open->verify_token . '/file');
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($open->file_sha256, hash('sha256', (string) $response->getContent()));

        $this->signedGet('/documents/' . $closed->verify_token . '/file')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_a_revoked_document_says_so_with_its_date_and_a_replaced_one_names_what_took_its_place(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $revoked = $this->issued($owner, ['title' => 'Letter of introduction']);
        Letters::revoke($revoked->fresh(), 'The visit was moved.', $owner);

        $original = $this->issued($owner, ['title' => 'Authority to act']);
        $replacement = Letters::replace($original->fresh(), $owner);
        Letters::issue($replacement->fresh(), $owner);
        Letters::markReplaced($replacement->fresh());
        auth()->logout();

        $data = $this->signedGet('/documents/' . $revoked->verify_token)->assertOk()->json('data');
        $this->assertSame('revoked', $data['status']);
        $this->assertSame(now('Africa/Lagos')->toDateString(), $data['revoked_at']);
        $this->assertSame('The visit was moved.', $data['revoked_reason']);

        $data = $this->signedGet('/documents/' . $original->verify_token)->assertOk()->json('data');
        $this->assertSame('replaced', $data['status']);
        $this->assertSame($replacement->reference, $data['replaced_by']);
        $this->assertSame(now('Africa/Lagos')->toDateString(), $data['replaced_at']);

        $data = $this->signedGet('/documents/' . $replacement->fresh()->verify_token)->assertOk()->json('data');
        $this->assertSame($original->reference, $data['replaces']);
    }

    public function test_an_unknown_or_unissued_token_is_not_found_and_an_unsigned_call_is_refused(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $draft = Letters::start('formal_letter', null, $this->company(), $owner);
        $draft->forceFill(['verify_token' => Str::random(48)])->save();
        auth()->logout();

        $this->signedGet('/documents/' . Str::random(48))->assertNotFound()->assertJsonPath('error.code', 'not_found');
        // A draft has no public life, even if it is given a token
        $this->signedGet('/documents/' . $draft->verify_token)->assertNotFound();

        $this->call('GET', 'https://' . Hosts::hq() . '/api/site/v1/documents/' . Str::random(48), [], [], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthorised');
    }

    public function test_too_many_scans_of_one_token_are_turned_away(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = $this->issued($owner, ['title' => 'Authority to act']);
        auth()->logout();

        $rows = [];
        for ($i = 0; $i < Verification::HITS_PER_HOUR; $i++) {
            $rows[] = ['verify_token' => $document->verify_token, 'ip_address' => '203.0.113.5', 'created_at' => now()->subMinutes(5)];
        }
        DB::table('letter_verification_hits')->insert($rows);

        $this->signedGet('/documents/' . $document->verify_token)
            ->assertStatus(429)->assertJsonPath('error.code', 'too_many');
    }

    // ------------------------------------------------------------------ helpers

    private function issued($owner, array $values)
    {
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $document->forceFill($values + ['show_qr' => true])->save();
        Letters::issue($document->fresh(), $owner);

        return $document->fresh();
    }

    /** A signed GET, as App\Support\HqApi signs one on the site (docs/SITE_API.md section 6.1) */
    private function signedGet(string $path, string $locale = 'en')
    {
        $full = '/api/site/v1' . $path;
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(12));
        $ip = '203.0.113.5';
        $canonical = SiteSignature::canonical('GET', $full, $timestamp, $nonce, $ip, $locale, self::KEY_ID, '', hash('sha256', ''));

        return $this->call('GET', 'https://' . Hosts::hq() . $full, [], [], [], [
            'HTTP_X_SITE_KEY' => self::KEY_ID,
            'HTTP_X_SITE_TIMESTAMP' => $timestamp,
            'HTTP_X_SITE_NONCE' => $nonce,
            'HTTP_X_SITE_VISITOR_IP' => $ip,
            'HTTP_X_SITE_LOCALE' => $locale,
            'HTTP_X_SITE_SIGNATURE' => SiteSignature::sign(self::SECRET, $canonical),
        ]);
    }
}
