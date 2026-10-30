<?php

namespace Tests\Unit\Pwa;

use App\Support\Pwa\VapidKeys;
use App\Support\Pwa\WebPush;
use PHPUnit\Framework\TestCase;
use Tests\Feature\Pwa\PushTestKit;

/** RFC 8291 encryption and RFC 8292 VAPID, without the framework or a database. */
class WebPushTest extends TestCase
{
    use PushTestKit;

    // RFC 8291 section 5 and Appendix A
    private const RFC = [
        'plaintext' => 'V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24',
        'as_public' => 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
        'as_private' => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
        'ua_public' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
        'ua_private' => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
        'salt' => 'DGv6ra1nlYgDCS1FRnbzlw',
        'auth' => 'BTBZMqHH6r4Tts7J_aSIgg',
        'header' => 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
        'ciphertext' => '8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ',
        'body' => 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
    ];

    private function rfc(string $name): string
    {
        return self::b64uDecode(self::RFC[$name]);
    }

    public function test_it_reproduces_the_rfc_8291_example_byte_for_byte(): void
    {
        $serverKey = self::keyFromRaw($this->rfc('as_private'), $this->rfc('as_public'));

        $body = WebPush::encrypt($this->rfc('plaintext'), $this->rfc('ua_public'), $this->rfc('auth'), $this->rfc('salt'), $serverKey);

        $this->assertSame('When I grow up, I want to be a watermelon', $this->rfc('plaintext'));
        $this->assertSame(86, strlen($this->rfc('header')));
        $this->assertSame(bin2hex($this->rfc('header')), bin2hex(substr($body, 0, 86)), 'header: salt, rs 4096, idlen 65, as_public');
        $this->assertSame(bin2hex($this->rfc('ciphertext')), bin2hex(substr($body, 86)), 'ciphertext and tag');
        $this->assertSame(self::RFC['body'], self::b64u($body));
        $this->assertSame(144, strlen($body));
    }

    public function test_the_decrypt_helper_reads_the_rfc_example_with_the_receiver_key(): void
    {
        $receiver = self::keyFromRaw($this->rfc('ua_private'), $this->rfc('ua_public'));

        $this->assertSame(
            'When I grow up, I want to be a watermelon',
            self::decryptPush($this->rfc('body'), $receiver, $this->rfc('ua_public'), $this->rfc('auth')),
        );
    }

    public function test_a_message_survives_the_round_trip_with_a_fresh_salt_and_key_each_time(): void
    {
        $device = self::deviceKeys();
        $payload = json_encode(['title' => 'Proof of payment uploaded', 'body' => 'Acme Ltd uploaded proof of a ₦1.2m payment for INV-0042. Verify it in Payments.'], JSON_UNESCAPED_UNICODE);

        $first = WebPush::encrypt($payload, $device['public'], $device['auth']);
        $second = WebPush::encrypt($payload, $device['public'], $device['auth']);

        $this->assertSame($payload, self::decryptPush($first, $device['private'], $device['public'], $device['auth']));
        $this->assertSame($payload, self::decryptPush($second, $device['private'], $device['public'], $device['auth']));
        $this->assertNotSame(substr($first, 0, 16), substr($second, 0, 16), 'a new salt per message');
        $this->assertNotSame(substr($first, 21, 65), substr($second, 21, 65), 'a new key pair per message');
        $this->assertSame(4096, unpack('N', substr($first, 16, 4))[1]);
        $this->assertSame(65, ord($first[20]));
        $this->assertSame("\x04", $first[21]);
        $this->assertSame(86 + strlen($payload) + 1 + 16, strlen($first), 'one record, delimiter only, no padding');
    }

    public function test_the_largest_message_still_fits_a_push(): void
    {
        $device = self::deviceKeys();
        $payload = str_repeat('a', WebPush::MAX_PAYLOAD);

        $body = WebPush::encrypt($payload, $device['public'], $device['auth']);

        $this->assertSame(4096, strlen($body));
        $this->assertSame($payload, self::decryptPush($body, $device['private'], $device['public'], $device['auth']));

        $this->expectException(\LengthException::class);
        WebPush::encrypt($payload . 'a', $device['public'], $device['auth']);
    }

    public function test_keys_that_can_never_work_are_refused(): void
    {
        $device = self::deviceKeys();
        $cases = [
            'short key' => [substr($device['public'], 0, 64), $device['auth']],
            'compressed key' => ["\x02" . substr($device['public'], 1), $device['auth']],
            'point not on the curve' => ["\x04" . str_repeat("\x01", 64), $device['auth']],
            'short auth secret' => [$device['public'], substr($device['auth'], 0, 15)],
        ];
        foreach ($cases as $name => [$public, $auth]) {
            try {
                WebPush::encrypt('{}', $public, $auth);
                $this->fail("Accepted: {$name}");
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage(), $name);
            }
        }
    }

    public function test_the_vapid_jwt_is_es256_over_aud_exp_and_sub(): void
    {
        $pair = VapidKeys::generate();
        $expires = time() + 43200;

        $jwt = WebPush::jwt('https://fcm.googleapis.com', $pair['private'], 'mailto:system@jbspraxis.com', $expires);

        [$header, $claims, $signature] = explode('.', $jwt);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(self::b64uDecode($header), true));
        $this->assertSame(['aud' => 'https://fcm.googleapis.com', 'exp' => $expires, 'sub' => 'mailto:system@jbspraxis.com'], json_decode(self::b64uDecode($claims), true));
        $this->assertStringNotContainsString('\/', self::b64uDecode($claims));

        $raw = self::b64uDecode($signature);
        $this->assertSame(64, strlen($raw), 'R || S, not DER');
        $publicPem = self::publicPem(self::b64uDecode($pair['public']));
        $this->assertSame(1, openssl_verify("{$header}.{$claims}", self::rawToDer($raw), $publicPem, OPENSSL_ALGO_SHA256));

        // Any change to the signed part fails
        $forged = self::b64u(json_encode(['aud' => 'https://fcm.googleapis.com', 'exp' => $expires + 1, 'sub' => 'mailto:system@jbspraxis.com'], JSON_UNESCAPED_SLASHES));
        $this->assertSame(0, openssl_verify("{$header}.{$forged}", self::rawToDer($raw), $publicPem, OPENSSL_ALGO_SHA256));
    }

    public function test_der_signatures_become_fixed_width_r_and_s(): void
    {
        // r has a sign byte (33 bytes in DER), s is short (31 bytes): both come out as exactly 32
        $r = "\x00\x80" . str_repeat("\x11", 31);
        $s = str_repeat("\x22", 31);
        $der = "\x30" . chr(4 + 33 + 31) . "\x02\x21" . $r . "\x02\x1f" . $s;

        $raw = WebPush::derToRaw($der);

        $this->assertSame(64, strlen($raw));
        $this->assertSame("\x80" . str_repeat("\x11", 31), substr($raw, 0, 32));
        $this->assertSame("\x00" . str_repeat("\x22", 31), substr($raw, 32));
        $this->assertSame(bin2hex($der), bin2hex(self::rawToDer($raw)));
    }

    public function test_the_audience_is_the_push_service_origin(): void
    {
        $this->assertSame('https://fcm.googleapis.com', WebPush::audience('https://fcm.googleapis.com/fcm/send/abc:def'));
        $this->assertSame('https://wns2-par02p.notify.windows.com', WebPush::audience('https://WNS2-PAR02P.notify.windows.com/w/?token=BQYAAA%2b'));
        $this->assertSame('https://web.push.apple.com', WebPush::audience('https://web.push.apple.com:443/QGx'));
        $this->assertSame('https://push.example.net:8443', WebPush::audience('https://push.example.net:8443/x'));
        $this->assertNull(WebPush::audience('http://fcm.googleapis.com/fcm/send/abc'));
        $this->assertNull(WebPush::audience('fcm.googleapis.com/fcm/send/abc'));
        $this->assertNull(WebPush::audience(''));
        // parse_url() reads port 443 here, but the HTTP client can't send to it
        $this->assertNull(WebPush::audience('https://fcm.googleapis.com:+443/fcm/send/abc'));
        // The HTTP client would expand the braces as a URI template and send somewhere else
        $this->assertNull(WebPush::audience('https://fcm.googleapis.com/fcm/send/{token}'));
    }

    public function test_only_uncompressed_points_on_p256_are_device_keys(): void
    {
        $device = self::deviceKeys();

        $this->assertTrue(WebPush::isDeviceKey($device['public']));
        $this->assertTrue(WebPush::isDeviceKey($this->rfc('ua_public')));
        $this->assertFalse(WebPush::isDeviceKey("\x04" . str_repeat("\x01", 64)), 'not on the curve');
        $this->assertFalse(WebPush::isDeviceKey("\x02" . substr($device['public'], 1, 32)), 'compressed');
        $this->assertFalse(WebPush::isDeviceKey(substr($device['public'], 0, 64)), 'short');
        $this->assertFalse(WebPush::isDeviceKey(''));
        $this->assertFalse(openssl_error_string(), 'no OpenSSL errors left behind');
    }
}
