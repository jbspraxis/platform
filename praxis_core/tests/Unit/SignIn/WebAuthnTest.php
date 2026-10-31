<?php

namespace Tests\Unit\SignIn;

use App\Support\SignIn\Cbor;
use App\Support\SignIn\WebAuthn;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\CborBytes;
use Tests\Support\SoftAuthenticator;

/** The passkey checks (docs/SIGN_IN.md section 3), against a software authenticator with real keys. */
class WebAuthnTest extends TestCase
{
    private const RP = 'hq.jbspraxis.com';
    private const ORIGIN = 'https://hq.jbspraxis.com';

    private function created(SoftAuthenticator $device, ?string $challenge = null): array
    {
        $options = WebAuthn::creationOptions($challenge ?? WebAuthn::challenge(), self::RP, 'JBS Praxis HQ', 'web:abc', 'ada@jbspraxis.com', 'Ada Obi', []);

        return WebAuthn::checkCreation($device->create($options), $options['challenge'], self::RP, self::ORIGIN);
    }

    private function assertion(SoftAuthenticator $device, array $stored, ?string $origin = null, int $storedCount = 0): int
    {
        $options = WebAuthn::requestOptions(WebAuthn::challenge(), self::RP);

        return WebAuthn::checkAssertion($device->get($options, 'web:abc'), $options['challenge'], self::RP, $origin ?? self::ORIGIN,
            $stored['public_key'], $stored['algorithm'], $storedCount, 'web:abc');
    }

    public function test_a_p256_passkey_is_created_and_then_signs_in(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($device);

        $this->assertSame($device->id(), $stored['credential_id']);
        $this->assertSame(WebAuthn::ES256, $stored['algorithm']);
        $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $stored['public_key']);
        $this->assertSame(['internal', 'hybrid'], $stored['transports']);
        $this->assertSame(1, $this->assertion($device, $stored));
    }

    public function test_an_rsa_passkey_from_windows_hello_works_too(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN, 'rs256');
        $stored = $this->created($device);

        $this->assertSame(WebAuthn::RS256, $stored['algorithm']);
        $this->assertSame(1, $this->assertion($device, $stored));
    }

    public function test_the_options_ask_for_a_discoverable_verified_passkey_and_name_no_credentials_to_sign_in(): void
    {
        $create = WebAuthn::creationOptions('c', self::RP, 'JBS Praxis HQ', 'web:abc', 'ada@x.test', '', [['credential_id' => 'AAAA', 'transports' => ['internal']]]);
        $this->assertSame(['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'], $create['authenticatorSelection']);
        $this->assertSame('none', $create['attestation']);
        $this->assertSame([['type' => 'public-key', 'id' => 'AAAA', 'transports' => ['internal']]], $create['excludeCredentials']);
        $this->assertSame('ada@x.test', $create['user']['displayName']);
        $this->assertSame(WebAuthn::b64('web:abc'), $create['user']['id']);

        $get = WebAuthn::requestOptions('c', self::RP);
        $this->assertSame([], $get['allowCredentials']);
        $this->assertSame('required', $get['userVerification']);
    }

    public function test_an_answer_from_another_site_is_refused(): void
    {
        $device = new SoftAuthenticator('https://evil.example');
        $this->expectException(RuntimeException::class);
        $this->created($device);
    }

    public function test_a_portal_passkey_does_not_work_on_hq(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $options = WebAuthn::creationOptions(WebAuthn::challenge(), self::RP, 'x', 'web:abc', 'a@b.test', '', []);
        $this->expectExceptionMessage('relying party differs');
        WebAuthn::checkCreation($device->create($options, 'portal.jbspraxis.com'), $options['challenge'], self::RP, self::ORIGIN);
    }

    public function test_another_challenge_is_refused(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $options = WebAuthn::creationOptions(WebAuthn::challenge(), self::RP, 'x', 'web:abc', 'a@b.test', '', []);
        $this->expectExceptionMessage('challenge differs');
        WebAuthn::checkCreation($device->create($options), WebAuthn::challenge(), self::RP, self::ORIGIN);
    }

    public function test_without_the_fingerprint_or_face_it_is_refused(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($device);
        $device->verified = false;

        $this->expectExceptionMessage('user not verified');
        $this->assertion($device, $stored);
    }

    public function test_another_key_cannot_sign_for_a_passkey(): void
    {
        $owner = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($owner);
        $thief = new SoftAuthenticator(self::ORIGIN, 'es256', $owner->credentialId);

        $this->expectExceptionMessage('signature does not verify');
        $this->assertion($thief, $stored);
    }

    public function test_a_creation_answer_cannot_be_used_to_sign_in(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($device);
        $options = WebAuthn::requestOptions(WebAuthn::challenge(), self::RP);

        $this->expectExceptionMessage('wrong ceremony type');
        WebAuthn::checkAssertion($device->get($options, null, null, 'webauthn.create'), $options['challenge'], self::RP, self::ORIGIN, $stored['public_key'], $stored['algorithm'], 0);
    }

    public function test_a_counter_that_goes_back_is_a_cloned_key(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($device);

        $this->assertSame(1, $this->assertion($device, $stored, null, 0));
        $device->counter = 3;
        $this->expectExceptionMessage('signature counter went back');
        $this->assertion($device, $stored, null, 7);
    }

    public function test_a_synced_passkey_that_always_says_zero_is_allowed(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($device);
        $device->counter = -1; // the next get() sends 0

        $this->assertSame(0, $this->assertion($device, $stored, null, 0));
    }

    public function test_the_user_handle_must_be_the_owner(): void
    {
        $device = new SoftAuthenticator(self::ORIGIN);
        $stored = $this->created($device);
        $options = WebAuthn::requestOptions(WebAuthn::challenge(), self::RP);

        $this->expectExceptionMessage('user handle differs');
        WebAuthn::checkAssertion($device->get($options, 'web:someone-else'), $options['challenge'], self::RP, self::ORIGIN, $stored['public_key'], $stored['algorithm'], 0, 'web:abc');
    }

    public function test_cbor_reads_what_authenticators_send_and_refuses_the_rest(): void
    {
        $this->assertSame(['fmt' => 'none', 'n' => [1, -7, 500, 70000], 'b' => "\x00\xff", 't' => true, 'z' => null],
            Cbor::decode(SoftAuthenticator::cbor(['fmt' => 'none', 'n' => [1, -7, 500, 70000], 'b' => new CborBytes("\x00\xff"), 't' => true, 'z' => null])));

        foreach (["\x5f", "\xc0\x01", "\x43ab", "\x01\x02"] as $bad) { // indefinite, tag, short string, trailing byte
            try {
                Cbor::decode($bad);
                $this->fail('Accepted ' . bin2hex($bad));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
