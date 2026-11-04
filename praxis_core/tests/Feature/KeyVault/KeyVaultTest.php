<?php

namespace Tests\Feature\KeyVault;

use App\Livewire\Hq\System\KeyVaultPage;
use App\Mail\KeyVaultMail;
use App\Models\KeyVaultItem;
use App\Models\User;
use App\Support\Access;
use App\Support\KeyVault\KeyVault;
use App\Support\Signing\SigningCodes;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionProperty;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ › Settings and access › Key vault (docs/KEY_VAULT.md): owner only, zero-knowledge, and a reveal that needs a
 * fresh sign-in, then the emailed code, then the passphrase (in the browser). The envelopes here are made with
 * OpenSSL exactly as crypto.js makes them; no real key or passphrase is anywhere near these tests.
 */
class KeyVaultTest extends TestCase
{
    use KeyVaultFixtures, UsesThrowawayDatabase;

    /** Adds an item through the page, as the browser does once it has locked it */
    private function addItem(User $owner, string $name = 'Server backup private key (test)', ?array $envelope = null): KeyVaultItem
    {
        $envelope ??= $this->lock();
        Livewire::actingAs($owner)->test(KeyVaultPage::class)->call('add', $name, $envelope)->assertReturned(['ok' => true, 'id' => $envelope['id']]);

        return KeyVaultItem::findOrFail($envelope['id']);
    }

    /** The code the last KeyVaultMail carried */
    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(KeyVaultMail::class, function (KeyVaultMail $mail) use (&$code) {
            if ($mail->kind === 'code') {
                $code = $mail->data['code'];
            }

            return true;
        });
        $this->assertNotNull($code, 'a code was emailed');

        return $code;
    }

    private function auditActions(KeyVaultItem|string $item): array
    {
        return DB::table('system_audit_logs')->where('target_table', 'key_vault_items')->where('target_id', is_string($item) ? $item : $item->id)
            ->orderBy('id')->pluck('action')->all();
    }

    public function test_only_the_protected_owner_role_opens_the_vault_not_even_a_role_with_everything(): void
    {
        $this->actingAs($this->owner())->get($this->hq('/system/key-vault'))->assertOk()->assertSee('Key vault')->assertSee('Where the backup key lives');

        // Partner / Director holds "*": every permission there is. Not the vault.
        $partner = $this->staff('partner', ['partner']);
        $this->actingAs($partner)->get($this->hq('/system/key-vault'))->assertForbidden();
        $this->actingAs($this->staff('auditor', ['auditor']))->get($this->hq('/system/key-vault'))->assertForbidden();

        // Nor a role the owner ticks every box for on Roles and permissions: the vault is not a box to tick
        $roleId = DB::table('roles')->insertGetId(['name' => 'Everything (test)', 'slug' => 'everything-test', 'audience' => 'staff', 'is_protected' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(DB::table('permissions')->where('audience', 'staff')->pluck('id')->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all());
        $everything = $this->staff('everything', []);
        DB::table('staff_roles')->insert(['user_id' => $everything->id, 'role_id' => $roleId]);
        Access::flush();
        $this->assertTrue($everything->can('access.edit') && $everything->can('letters.manage'));
        $this->actingAs($everything)->get($this->hq('/system/key-vault'))->assertForbidden();
        $this->assertFalse($everything->can('key-vault'));

        $this->assertFalse(Access::isPermission('key-vault'));
        $this->assertSame([], array_filter(array_keys(Access::catalogue()), fn ($slug) => str_contains($slug, 'vault') && ! str_starts_with($slug, 'portal.')));

        // An owner account that is no longer active is refused too
        $gone = $this->owner('Gone');
        $gone->forceFill(['status' => 'Suspended'])->save();
        $this->assertFalse(KeyVault::allows($gone->fresh()));

        // Every call on the component checks again, not only opening the page
        Livewire::actingAs($partner)->test(KeyVaultPage::class)->assertForbidden();
    }

    public function test_the_rail_shows_the_key_vault_to_the_owner_only(): void
    {
        $this->actingAs($this->owner())->get($this->hq('/system/integrations'))->assertOk()->assertSee('/system/key-vault', false);
        $this->actingAs($this->staff('partner', ['partner']))->get($this->hq('/system/integrations'))->assertOk()->assertDontSee('/system/key-vault', false);
    }

    public function test_only_the_locked_envelope_is_stored_and_it_opens_only_with_the_passphrase(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $item = $this->addItem($owner);

        // The table has no room for anything else
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'name', 'format_version', 'kdf', 'kdf_iterations', 'kdf_salt', 'cipher', 'iv', 'ciphertext', 'last_revealed_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('key_vault_items'),
        );

        $row = (array) DB::table('key_vault_items')->where('id', $item->id)->first();
        $this->assertSame(['PBKDF2-SHA-256', 600000, 'AES-256-GCM'], [$row['kdf'], (int) $row['kdf_iterations'], $row['cipher']]);

        // Neither the secret, nor any line of it, nor the passphrase is anywhere: the row, the audit log, the session, the emails
        $everything = json_encode($row) . json_encode(DB::table('system_audit_logs')->get()) . json_encode(session()->all());
        Mail::assertNothingSent();
        foreach (array_merge(array_filter(explode("\n", self::SECRET)), [self::PASSPHRASE, base64_encode(self::SECRET), 'NOT A KEY']) as $piece) {
            $this->assertStringNotContainsString($piece, $everything, "“{$piece}” is not stored");
        }

        // What is stored opens with the passphrase, and with nothing else
        $envelope = $item->envelope()->toArray();
        $this->assertSame(self::SECRET, $this->open($envelope, self::PASSPHRASE));
        $this->assertFalse($this->open($envelope, 'another passphrase entirely'));

        // The page lists it, but its envelope is not on the page until the emailed code
        $html = $this->actingAs($owner)->get($this->hq('/system/key-vault'))->assertOk()->assertSee('Server backup private key (test)')->getContent();
        $this->assertStringNotContainsString($row['ciphertext'], $html);
        $this->assertStringNotContainsString($row['kdf_salt'], $html);
        $this->assertArrayNotHasKey('ciphertext', $item->toArray());

        $this->assertSame(['key_vault.added'], $this->auditActions($item));
    }

    public function test_the_page_has_nowhere_to_hold_a_secret_or_a_passphrase(): void
    {
        $public = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(KeyVaultPage::class))->getProperties(ReflectionProperty::IS_PUBLIC));
        $mine = array_values(array_diff($public, array_map(fn ($p) => $p->getName(), (new ReflectionClass(\Livewire\Component::class))->getProperties(ReflectionProperty::IS_PUBLIC))));
        $this->assertEqualsCanonicalizing(['confirmPassword', 'needsPassword', 'pending', 'renaming', 'newName', 'confirmingDelete', 'replacing', 'flash'], $mine);

        // The secret's and the passphrase's inputs are Alpine's only, never Livewire's
        $views = file_get_contents(resource_path('views/livewire/hq/system/key-vault.blade.php'))
            . file_get_contents(resource_path('views/livewire/hq/system/partials/key-vault-lock-fields.blade.php'));
        foreach (['x-model="secret"', 'x-model="passphrase"', 'x-model="again"'] as $binding) {
            $this->assertStringContainsString($binding, $views);
        }
        $this->assertDoesNotMatchRegularExpression('/wire:model[.a-z]*="(secret|passphrase|again|code)"/', $views);
        $this->assertStringContainsString('spellcheck="false"', $views, 'browser spell checkers send text away: off');
    }

    public function test_a_reveal_needs_a_fresh_sign_in_then_the_emailed_code_and_hands_over_the_envelope_once(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $item = $this->addItem($owner);

        // Signed in a long time ago: no code is sent until the password is typed again
        session()->forget('sign_in.fresh_at');
        $page = Livewire::actingAs($owner)->test(KeyVaultPage::class);
        $page->call('ensureFresh')->assertReturned(false)->assertSet('needsPassword', true);
        $page->call('sendCode', $item->id)->assertReturned(['ok' => false, 'error' => 'Type your password again first.']);
        Mail::assertNothingSent();

        $page->set('confirmPassword', 'not-the-password')->call('confirmIdentity')->assertHasErrors('confirmPassword')->assertNotDispatched('key-vault-fresh');
        $page->set('confirmPassword', self::PASSWORD)->call('confirmIdentity')->assertHasNoErrors()->assertSet('needsPassword', false)->assertDispatched('key-vault-fresh');
        $this->assertTrue(SignIn::isFresh());

        // The code goes to the owner's own address; the subject never carries it
        $page->call('sendCode', $item->id)->assertReturned(['ok' => true, 'sentTo' => KeyVault::mask($owner->email)]);
        $code = $this->sentCode();
        Mail::assertSent(KeyVaultMail::class, function (KeyVaultMail $mail) use ($owner, $code) {
            $parts = $mail->renderParts();

            return $mail->hasTo($owner->email) && ! str_contains($parts['subject'], $code) && str_contains($parts['text'], $code)
                && str_contains($parts['subject'], 'key vault code');
        });

        // A wrong code: nothing
        $wrong = $code === '000000' ? '111111' : '000000';
        $result = $page->call('reveal', $item->id, $wrong)->effects['returns'][0] ?? null;
        $this->assertArrayNotHasKey('envelope', (array) $result);
        $this->assertStringContainsString('not right', $result['error']);

        // The right one: the envelope, exactly as stored, and nothing more
        $result = $page->call('reveal', $item->id, $code)->effects['returns'][0];
        $this->assertSame($item->envelope()->toArray(), $result['envelope']);
        $this->assertSame(self::SECRET, $this->open($result['envelope'], self::PASSPHRASE));
        $this->assertNotNull($item->fresh()->last_revealed_at);

        // The owner is told, without any part of the secret
        Mail::assertSent(KeyVaultMail::class, function (KeyVaultMail $mail) use ($owner) {
            if ($mail->kind !== 'revealed') {
                return false;
            }
            $parts = $mail->renderParts();

            return $mail->hasTo($owner->email) && str_contains($parts['subject'], 'Server backup private key (test)') && ! str_contains($parts['html'], 'NOT A KEY')
                && ! str_contains($parts['html'], $mail->data['code'] ?? 'no code here');
        });

        // A code works once
        $again = $page->call('reveal', $item->id, $code)->effects['returns'][0];
        $this->assertArrayNotHasKey('envelope', $again);
        $this->assertTrue($again['again']);

        $this->assertSame(['key_vault.added', 'key_vault.code_sent', 'key_vault.code_wrong', 'key_vault.revealed', 'key_vault.code_wrong'], $this->auditActions($item));
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'key_vault.revealed', 'user_id' => $owner->id, 'target_id' => $item->id]);
    }

    public function test_codes_are_limited_to_three_in_fifteen_minutes_and_five_wrong_tries_cancel_one(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $item = $this->addItem($owner);
        $page = Livewire::actingAs($owner)->test(KeyVaultPage::class);

        foreach (range(1, SigningCodes::MAX_CODES) as $i) {
            $page->call('sendCode', $item->id)->assertReturned(fn ($r) => $r['ok'] === true);
        }
        $refused = $page->call('sendCode', $item->id)->effects['returns'][0];
        $this->assertFalse($refused['ok']);
        $this->assertStringContainsString('Three codes were sent in the last 15 minutes', $refused['error']);
        Mail::assertSentCount(SigningCodes::MAX_CODES);
        $code = $this->sentCode();

        // Five wrong codes: the fifth cancels it, so the right one no longer works either
        $wrong = $code === '000000' ? '111111' : '000000';
        foreach (range(1, SigningCodes::MAX_WRONG_CODES - 1) as $i) {
            $this->assertStringContainsString('not right', $page->call('reveal', $item->id, $wrong)->effects['returns'][0]['error']);
        }
        $fifth = $page->call('reveal', $item->id, $wrong)->effects['returns'][0];
        $this->assertStringContainsString('fifth wrong code', $fifth['error']);
        $this->assertTrue($fifth['again']);
        $this->assertArrayNotHasKey('envelope', $page->call('reveal', $item->id, $code)->effects['returns'][0]);
        $this->assertNull($item->fresh()->last_revealed_at);
    }

    public function test_a_code_expires_after_ten_minutes_and_opens_only_its_own_item(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $first = $this->addItem($owner, 'First (test)');
        $second = $this->addItem($owner, 'Second (test)');
        $page = Livewire::actingAs($owner)->test(KeyVaultPage::class);

        $page->call('sendCode', $first->id);
        $code = $this->sentCode();
        $this->assertArrayNotHasKey('envelope', $page->call('reveal', $second->id, $code)->effects['returns'][0], 'a code for another item');

        $this->travel(SigningCodes::MINUTES + 1)->minutes();
        SignIn::markFresh();
        $late = $page->call('reveal', $first->id, $code)->effects['returns'][0];
        $this->assertArrayNotHasKey('envelope', $late);
        $this->assertStringContainsString('expired', $late['error']);
    }

    public function test_typing_the_password_again_is_limited_to_five_tries(): void
    {
        $owner = $this->owner();
        session()->forget('sign_in.fresh_at');
        $page = Livewire::actingAs($owner)->test(KeyVaultPage::class)->call('ensureFresh');
        foreach (range(1, 5) as $i) {
            $page->set('confirmPassword', 'wrong-' . $i)->call('confirmIdentity')->assertHasErrors('confirmPassword');
        }
        $page->set('confirmPassword', self::PASSWORD)->call('confirmIdentity')->assertHasErrors('confirmPassword')->assertNotDispatched('key-vault-fresh');
        $this->assertFalse(SignIn::isFresh());
    }

    public function test_adding_renaming_replacing_and_deleting_need_a_fresh_sign_in_and_are_audited(): void
    {
        Mail::fake();
        $owner = $this->owner();

        // Not fresh: nothing is added
        session()->forget('sign_in.fresh_at');
        $envelope = $this->lock();
        Livewire::actingAs($owner)->test(KeyVaultPage::class)->call('add', 'Too late (test)', $envelope)
            ->assertReturned(['ok' => false, 'error' => 'Type your password again, then save.'])->assertSet('needsPassword', true);
        $this->assertSame(0, KeyVaultItem::count());
        SignIn::markFresh();

        $item = $this->addItem($owner, 'Old name (test)');
        $page = Livewire::actingAs($owner)->test(KeyVaultPage::class);

        // A name already used, an empty one: refused
        $this->addItem($owner, 'Taken (test)');
        $page->call('add', 'Taken (test)', $this->lock())->assertReturned(fn ($r) => $r['ok'] === false && str_contains($r['error'], 'already has that name'));
        $page->call('startRename', $item->id)->set('newName', '  ')->call('rename')->assertHasErrors('newName');

        // Rename after the password again: finished as soon as it is typed
        session()->forget('sign_in.fresh_at');
        $page->set('newName', 'New name (test)')->call('rename')->assertSet('needsPassword', true)->assertSet('pending', 'rename');
        $this->assertSame('Old name (test)', $item->fresh()->name);
        $page->set('confirmPassword', self::PASSWORD)->call('confirmIdentity');
        $this->assertSame('New name (test)', $item->fresh()->name);

        // Replace: locked again under the same id, a new passphrase; another id is refused
        $page->call('replace', $item->id, $this->lock('a new test secret', 'other words for this test lock', 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d'))
            ->assertReturned(fn ($r) => $r['ok'] === false && str_contains($r['error'], 'another item'));
        $before = $item->fresh()->ciphertext;
        $page->call('replace', $item->id, $this->lock('a new test secret', 'other words for this test lock', $item->id))->assertReturned(['ok' => true]);
        $replaced = $item->fresh();
        $this->assertNotSame($before, $replaced->ciphertext);
        $this->assertSame('a new test secret', $this->open($replaced->envelope()->toArray(), 'other words for this test lock'));
        $this->assertFalse($this->open($replaced->envelope()->toArray(), self::PASSPHRASE));

        // Delete: named in the confirmation, gone, audited with the name it had
        $page->call('confirmDelete', $item->id)->assertSee('Delete “New name (test)” from the vault?');
        $page->call('delete');
        $this->assertNull(KeyVaultItem::find($item->id));

        $this->assertSame(['key_vault.added', 'key_vault.renamed', 'key_vault.replaced', 'key_vault.deleted'], $this->auditActions($item->id));
        $renamed = DB::table('system_audit_logs')->where('action', 'key_vault.renamed')->first();
        $this->assertEquals(['name' => 'Old name (test)'], json_decode($renamed->old_values, true));
        $this->assertEquals(['name' => 'New name (test)'], json_decode($renamed->new_values, true));
        $deleted = DB::table('system_audit_logs')->where('action', 'key_vault.deleted')->first();
        $this->assertSame('New name (test)', json_decode($deleted->old_values, true)['name']);
    }

    public function test_the_server_refuses_envelopes_that_are_not_the_vaults_format(): void
    {
        $owner = $this->owner();
        $page = Livewire::actingAs($owner)->test(KeyVaultPage::class);
        $good = $this->lock();

        $bad = [
            'too few iterations' => array_replace_recursive($good, ['kdf' => ['iterations' => 100000]]),
            'another cipher' => array_replace_recursive($good, ['cipher' => ['name' => 'AES-CBC']]),
            'a short IV' => array_replace_recursive($good, ['cipher' => ['iv' => base64_encode('short')]]),
            'not base64' => array_replace($good, ['ciphertext' => 'not*base64!']),
            'no tag' => array_replace($good, ['ciphertext' => base64_encode('0123456789')]),
            'not an id' => array_replace($good, ['id' => '../../etc/passwd']),
            'too long' => array_replace($good, ['ciphertext' => base64_encode(str_repeat('x', 20017))]),
            'nothing' => null,
        ];
        foreach ($bad as $why => $envelope) {
            $page->call('add', 'Bad (test)', $envelope)->assertReturned(fn ($r) => $r['ok'] === false && $r['error'] !== '');
        }
        $this->assertSame(0, KeyVaultItem::count(), 'none of them was kept');
    }

    public function test_one_owner_never_reaches_another_owners_items(): void
    {
        Mail::fake();
        $ada = $this->owner('Ada');
        $item = $this->addItem($ada);
        $bola = $this->owner('Bola');

        Livewire::actingAs($bola)->test(KeyVaultPage::class)->assertDontSee('Server backup private key (test)')
            ->call('sendCode', $item->id)->assertNotFound();
        Livewire::actingAs($bola)->test(KeyVaultPage::class)->call('confirmDelete', $item->id)->assertNotFound();
        $this->actingAs($bola)->withSession(['key_vault.unlocked' => [$item->id => now()->addMinutes(5)->getTimestamp()]])
            ->get($this->hq('/system/key-vault/' . $item->id . '/locked-copy'))->assertNotFound();
        Mail::assertNothingSent();
        $this->assertNotNull(KeyVaultItem::find($item->id));
    }

    public function test_the_locked_copy_downloads_only_for_a_few_minutes_after_the_code_and_is_audited(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $item = $this->addItem($owner);
        $url = $this->hq('/system/key-vault/' . $item->id . '/locked-copy');

        // Not unlocked in this session (adding it opened the download: closed here): back to the page with a word why
        session()->forget('key_vault.unlocked');
        $this->actingAs($owner)->get($url)->assertRedirect($this->hq('/system/key-vault'))->assertSessionHas('error');
        $this->assertNotContains('key_vault.locked_copy', $this->auditActions($item));

        // Unlocked (after the code, or right after adding it): one HTML file, as an attachment
        $response = $this->actingAs($owner)->withSession(['key_vault.unlocked' => [$item->id => now()->addMinutes(5)->getTimestamp()]])->get($url);
        $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->assertMatchesRegularExpression('/^attachment; filename="key-vault-server-backup-private-key-test-locked-copy-\d{4}-\d{2}-\d{2}\.html"$/', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString($item->ciphertext, $response->getContent());
        $this->assertStringNotContainsString('NOT A KEY', $response->getContent());
        $this->assertContains('key_vault.locked_copy', $this->auditActions($item));

        // The window closes
        $this->actingAs($owner)->withSession(['key_vault.unlocked' => [$item->id => now()->subSecond()->getTimestamp()]])->get($url)->assertRedirect();

        // Someone else entirely
        $this->actingAs($this->staff('partner', ['partner']))->get($url)->assertForbidden();
    }

    public function test_adding_and_a_right_code_open_the_download_in_that_session(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $this->actingAs($owner);
        $envelope = $this->lock();
        $page = Livewire::test(KeyVaultPage::class)->call('add', 'Server backup private key (test)', $envelope);
        $item = KeyVaultItem::findOrFail($envelope['id']);
        $this->assertTrue(KeyVault::isUnlocked($item), 'right after adding it');
        $page->assertSee('Download a locked copy');

        session()->forget('key_vault.unlocked');
        $this->assertFalse(KeyVault::isUnlocked($item));
        $page->call('sendCode', $item->id);
        $page->call('reveal', $item->id, $this->sentCode());
        $this->assertTrue(KeyVault::isUnlocked($item), 'after the code');
        $this->travel(KeyVault::UNLOCKED_MINUTES + 1)->minutes();
        $this->assertFalse(KeyVault::isUnlocked($item));
    }

    public function test_the_vault_script_is_served_to_the_owner(): void
    {
        $response = $this->actingAs($this->owner())->get($this->hq('/system/key-vault/vault.js'))->assertOk();
        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('root.KeyVaultCrypto = api', $response->getContent());
        $this->assertStringContainsString('window.keyVaultReveal', $response->getContent());
        $this->actingAs($this->staff('partner', ['partner']))->get($this->hq('/system/key-vault/vault.js'))->assertForbidden();
    }
}
