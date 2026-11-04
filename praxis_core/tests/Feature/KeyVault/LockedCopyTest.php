<?php

namespace Tests\Feature\KeyVault;

use App\Models\KeyVaultItem;
use App\Support\KeyVault\Envelope;
use App\Support\KeyVault\KeyVault;
use App\Support\KeyVault\LockedCopy;
use Tests\TestCase;

/**
 * "Download a locked copy" (docs/KEY_VAULT.md section 5): one HTML file that must open with HQ gone. So it may
 * load nothing, reach nothing and run nothing but its own script; and its envelope is the item's, byte for byte.
 * No database: the item is built in memory. KeyVaultCryptoTest opens a copy with the file's own script.
 */
class LockedCopyTest extends TestCase
{
    use KeyVaultFixtures;

    private function item(string $name = 'Server backup private key (test)'): KeyVaultItem
    {
        $envelope = Envelope::fromBrowser($this->lock());
        $item = new KeyVaultItem(['id' => $envelope->id, 'user_id' => '00000000-0000-4000-8000-000000000000', 'name' => $name] + $envelope->columns());
        $item->created_at = $item->updated_at = now();

        return $item;
    }

    public function test_it_is_one_self_contained_page_that_can_reach_nothing(): void
    {
        $item = $this->item();
        $html = LockedCopy::html($item);

        // Nothing loaded from anywhere: no src, no href, no link, no address at all
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        foreach (['src=', 'href=', '<link', '<iframe', '<img', '<object', '<embed', '<form action', 'http://', 'https://', '//cdn', '@import', 'url(', 'fetch(', 'XMLHttpRequest', 'sendBeacon', 'WebSocket', 'EventSource', 'import('] as $never) {
            $this->assertStringNotContainsString($never, $html, "the locked copy has no {$never}");
        }

        // Exactly two scripts: the envelope (data, not run) and the inline page
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/s', $html, $scripts, PREG_SET_ORDER);
        $this->assertCount(2, $scripts);
        $this->assertSame(' type="application/json" id="envelope"', $scripts[0][1]);
        $this->assertSame('', $scripts[1][1]);
        $script = $scripts[1][2];
        $this->assertSame(LockedCopy::script(), $script);
        $this->assertStringContainsString(trim(str_replace("\r\n", "\n", file_get_contents(resource_path('key-vault/crypto.js')))), $script, 'the same lock as the HQ page, word for word');

        // The policy lets only that script and that style run, by hash, and allows no connection of any kind
        $this->assertMatchesRegularExpression('/<meta http-equiv="Content-Security-Policy" content="([^"]+)">/', $html);
        preg_match('/<meta http-equiv="Content-Security-Policy" content="([^"]+)">/', $html, $csp);
        $policy = html_entity_decode($csp[1], ENT_QUOTES);
        preg_match('/<style>(.*?)<\/style>/s', $html, $style);
        $hash = fn (string $text) => "'sha256-" . base64_encode(hash('sha256', $text, true)) . "'";
        $this->assertStringContainsString("default-src 'none'", $policy);
        $this->assertStringContainsString("connect-src 'none'", $policy);
        $this->assertStringContainsString("form-action 'none'", $policy);
        $this->assertStringContainsString('script-src ' . $hash($script) . ';', $policy);
        $this->assertStringContainsString('style-src ' . $hash($style[1]) . ';', $policy);
        $this->assertStringNotContainsString('unsafe', $policy);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $html, 'no inline event handlers (the policy would block them)');
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html, 'no inline style attributes (the policy would block them)');
    }

    public function test_its_envelope_is_the_items_and_nothing_readable_is_in_it(): void
    {
        $item = $this->item();
        $html = LockedCopy::html($item);

        preg_match('/<script type="application\/json" id="envelope">(.*?)<\/script>/s', $html, $m);
        $envelope = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($item->envelope()->toArray(), array_diff_key($envelope, array_flip(['name', 'made_at'])));
        $this->assertSame('Server backup private key (test)', $envelope['name']);
        $this->assertSame(self::SECRET, $this->open($envelope, self::PASSPHRASE), 'it opens with the passphrase, with OpenSSL too');

        foreach (array_merge(array_filter(explode("\n", self::SECRET)), [self::PASSPHRASE, 'NOT A KEY']) as $piece) {
            $this->assertStringNotContainsString($piece, $html);
        }

        // What the owner needs to tell copies apart, and how to open it without this page
        $this->assertStringContainsString(KeyVault::fingerprint($item), $html);
        $this->assertStringContainsString('PBKDF2-HMAC-SHA-256, 600,000 iterations, then AES-256-GCM', $html);
        $this->assertStringContainsString('hashlib.pbkdf2_hmac("sha256"', $html);
        $this->assertStringContainsString('Bitwarden', $html);
    }

    public function test_a_name_cannot_break_out_of_the_page(): void
    {
        $html = LockedCopy::html($this->item('</script><script>alert(1)</script><img src=x onerror=alert(2)> & "quotes"'));

        $this->assertSame(2, substr_count($html, '</script>'), 'still only the two scripts');
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;/script&gt;&lt;script&gt;alert(1)', $html, 'escaped in the title and the heading');
        preg_match('/<script type="application\/json" id="envelope">(.*?)<\/script>/s', $html, $m);
        $this->assertStringNotContainsString('<', $m[1]);
        $this->assertStringStartsWith('</script><script>alert(1)', json_decode($m[1], true)['name'], 'and the name reads back as it was');
    }

    public function test_the_file_is_named_after_the_item_and_the_day(): void
    {
        $this->travelTo(now()->setTimezone('Africa/Lagos')->setDate(2026, 11, 5)->setTime(9, 0));
        $this->assertSame('key-vault-server-backup-private-key-test-locked-copy-2026-11-05.html', LockedCopy::filename($this->item()));
    }
}
