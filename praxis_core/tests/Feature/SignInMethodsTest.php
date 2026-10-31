<?php

namespace Tests\Feature;

use App\Livewire\Account\PasskeyOffer;
use App\Livewire\Account\SignInMethods;
use App\Livewire\ClientOs\LoginMatrix;
use App\Livewire\Hq\Auth\LoginTerminal;
use App\Models\ClientUser;
use App\Models\User;
use App\Support\Hosts;
use App\Support\SignIn\Passkeys;
use App\Support\SignIn\Pins;
use App\Support\SignIn\SignIn;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\SoftAuthenticator;
use Tests\TestCase;

/** Passkeys and the PIN on HQ and the Client Portal (owner, 31 Oct 2026; docs/SIGN_IN.md). */
class SignInMethodsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function hqDevice(): SoftAuthenticator
    {
        return new SoftAuthenticator('https://' . Hosts::hq());
    }

    private function portalDevice(): SoftAuthenticator
    {
        return new SoftAuthenticator('https://' . Hosts::portal());
    }

    private function client(): ClientUser
    {
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'Northwind Ltd']);

        return ClientUser::forceCreate([
            'organization_id' => $org,
            'role_id' => DB::table('roles')->where('audience', 'client')->value('id'),
            'first_name' => 'Chidi', 'last_name' => 'Eze',
            'email' => Str::lower(Str::random(8)) . '@northwind.test',
            'password' => Hash::make('client-password-1'),
            'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => true,
        ]);
    }

    /** Adds a passkey through My profile / Sign-in settings, as the browser would */
    private function addPasskey(string $guard, $account, SoftAuthenticator $device): void
    {
        $component = Livewire::actingAs($account, $guard)->test(SignInMethods::class, ['guard' => $guard]);
        $options = $component->call('passkeyCreationOptions')->effects['returns'][0];
        $component->call('savePasskey', $device->create($options))->assertHasNoErrors()->assertSee(__('sign_in.settings.passkey_added'));
    }

    public function test_hq_staff_add_a_passkey_and_sign_in_with_it_without_typing_anything(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $device = $this->hqDevice();
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
        $this->addPasskey('web', $me, $device);

        $row = DB::table('passkeys')->where('owner_id', $me->id)->first();
        $this->assertSame('web', $row->guard);
        $this->assertSame($device->id(), $row->credential_id);
        $this->assertSame(1, DB::table('system_audit_logs')->where('user_id', $me->id)->where('action', 'PASSKEY_ADDED')->count());

        Auth::guard('web')->logout();
        $login = Livewire::test(LoginTerminal::class);
        $options = $login->call('passkeyOptions')->effects['returns'][0];
        $this->assertSame([], $options['allowCredentials'], 'Options name no account');
        $login->call('signInWithPasskey', $device->get($options, SignIn::userHandle('web', $me->id)))
            ->assertHasNoErrors()->assertRedirect(route('hq.dash.workspace'));

        $this->assertSame($me->id, Auth::guard('web')->id());
        $this->assertSame('Signed in with a passkey.', DB::table('system_audit_logs')->where('user_id', $me->id)->where('action', 'AUTH_LOGIN')->value('description'));
        $this->assertSame(1, (int) DB::table('passkeys')->where('id', $row->id)->value('sign_count'));
        $this->assertNotNull(DB::table('passkeys')->where('id', $row->id)->value('last_used_at'));
    }

    public function test_one_answer_signs_in_once(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $device = $this->hqDevice();
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
        $this->addPasskey('web', $me, $device);
        Auth::guard('web')->logout();

        $login = Livewire::test(LoginTerminal::class);
        $answer = $device->get($login->call('passkeyOptions')->effects['returns'][0]);
        $login->call('signInWithPasskey', $answer)->assertHasNoErrors();
        Auth::guard('web')->logout();

        Livewire::test(LoginTerminal::class)->call('signInWithPasskey', $answer)->assertHasErrors('auth');
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_a_suspended_account_cannot_sign_in_with_its_passkey(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $device = $this->hqDevice();
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
        $this->addPasskey('web', $me, $device);
        Auth::guard('web')->logout();
        $me->forceFill(['status' => 'Suspended'])->save();

        $login = Livewire::test(LoginTerminal::class);
        $login->call('signInWithPasskey', $device->get($login->call('passkeyOptions')->effects['returns'][0]))
            ->assertHasErrors('auth')->assertSee(__('sign_in.passkey.inactive'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_a_client_passkey_does_not_open_hq_and_a_staff_one_does_not_open_the_portal(): void
    {
        $client = $this->client();
        $device = $this->portalDevice();
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
        $this->addPasskey('client', $client, $device);
        Auth::guard('client')->logout();

        // The portal passkey on HQ's sign-in: unknown there (its own guard's passkeys only)
        $hq = Livewire::test(LoginTerminal::class);
        $hq->call('signInWithPasskey', $device->get($hq->call('passkeyOptions')->effects['returns'][0], null, Hosts::hq()))
            ->assertHasErrors('auth');
        $this->assertFalse(Auth::guard('web')->check());

        // And on the portal it works
        $portal = Livewire::test(LoginMatrix::class);
        $portal->call('signInWithPasskey', $device->get($portal->call('passkeyOptions')->effects['returns'][0]))
            ->assertHasNoErrors()->assertRedirect(route('client.dashboard'));
        $this->assertSame($client->id, Auth::guard('client')->id());
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_hq_staff_set_a_pin_and_sign_in_with_their_email_and_pin(): void
    {
        $me = $this->staff('eng', ['engineer']);
        session(['sign_in.fresh_at' => now()->getTimestamp()]);

        $settings = Livewire::actingAs($me)->test(SignInMethods::class, ['guard' => 'web'])->call('startPin')->assertSet('settingPin', true);
        $settings->set('newPin', '1234')->set('newPinAgain', '1234')->call('savePin')->assertHasErrors('newPin');
        $settings->set('newPin', '4729')->set('newPinAgain', '4720')->call('savePin')->assertHasErrors('newPinAgain');
        $settings->set('newPin', '4729')->set('newPinAgain', '4729')->call('savePin')->assertHasNoErrors()->assertSee(__('sign_in.settings.pin_saved'));
        $this->assertSame('on', Pins::status('web', $me->id));
        Auth::guard('web')->logout();

        Livewire::test(LoginTerminal::class)->call('choosePin', true)->set('email', $me->email)->set('pin', '4729')
            ->call('signInWithPin')->assertHasNoErrors()->assertRedirect(route('hq.dash.workspace'));
        $this->assertSame($me->id, Auth::guard('web')->id());
        $this->assertSame('Signed in with their PIN.', DB::table('system_audit_logs')->where('user_id', $me->id)->where('action', 'AUTH_LOGIN')->value('description'));
    }

    public function test_ten_wrong_pins_in_a_row_switch_it_off_and_the_password_still_works(): void
    {
        $me = $this->staff('eng', ['engineer']);
        Pins::set('web', $me->id, '4729');

        for ($i = 1; $i <= 9; $i++) {
            // Step past the doubling wait so every try is counted
            DB::table('sign_in_pins')->where('owner_id', $me->id)->update(['last_failed_at' => now()->subHour()]);
            Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('pin', '1111')->call('signInWithPin')->assertHasErrors('auth');
        }
        $this->assertSame('on', Pins::status('web', $me->id));

        DB::table('sign_in_pins')->where('owner_id', $me->id)->update(['last_failed_at' => now()->subHour()]);
        Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('pin', '1111')->call('signInWithPin')
            ->assertSee(__('sign_in.errors.pin_switched_off'));
        $this->assertSame('off', Pins::status('web', $me->id));

        // Even the right PIN does nothing now
        Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('pin', '4729')->call('signInWithPin')->assertHasErrors('auth');
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame(1, DB::table('system_audit_logs')->where('user_id', $me->id)->where('action', 'PIN_SWITCHED_OFF')->count());
    }

    public function test_after_five_wrong_pins_the_next_try_waits(): void
    {
        $me = $this->staff('eng', ['engineer']);
        Pins::set('web', $me->id, '4729');
        DB::table('sign_in_pins')->where('owner_id', $me->id)->update(['failed_count' => 5, 'last_failed_at' => now()]);

        Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('pin', '1111')->call('signInWithPin')->assertHasErrors('auth');
        // The sixth was counted; the seventh, straight after, must wait (even with the right PIN)
        Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('pin', '4729')->call('signInWithPin')
            ->assertSee('before you try the PIN again');
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame(6, (int) DB::table('sign_in_pins')->where('owner_id', $me->id)->value('failed_count'));
    }

    public function test_an_email_without_a_pin_gets_the_same_answer_as_a_wrong_pin(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $other = $this->staff('ops', ['engineer']);
        Pins::set('web', $other->id, '4729');

        $none = Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('pin', '4729')->call('signInWithPin');
        $nobody = Livewire::test(LoginTerminal::class)->set('email', 'nobody@local.test')->set('pin', '4729')->call('signInWithPin');
        $wrong = Livewire::test(LoginTerminal::class)->set('email', $other->email)->set('pin', '8051')->call('signInWithPin');

        foreach ([$none, $nobody, $wrong] as $answer) {
            $answer->assertHasErrors('auth')->assertSee(__('sign_in.errors.pin_failed'));
        }
    }

    public function test_setting_a_pin_or_adding_a_passkey_needs_the_password_when_the_sign_in_is_old(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $me->forceFill(['password' => Hash::make('right-password-1')])->save();
        session(['sign_in.fresh_at' => now()->subMinutes(30)->getTimestamp()]);

        $settings = Livewire::actingAs($me)->test(SignInMethods::class, ['guard' => 'web']);
        $this->assertNull($settings->call('passkeyCreationOptions')->effects['returns'][0]);
        $settings->assertSet('needsPassword', 'passkey');

        $settings->call('startPin')->assertSet('settingPin', false)->assertSet('needsPassword', 'pin')
            ->set('confirmPassword', 'wrong')->call('confirmIdentity')->assertHasErrors('confirmPassword')->assertSet('settingPin', false)
            ->set('confirmPassword', 'right-password-1')->call('confirmIdentity')->assertHasNoErrors()
            ->assertSet('needsPassword', null)->assertSet('settingPin', true);
        $this->assertTrue(SignIn::isFresh());
    }

    public function test_people_remove_only_their_own_passkeys(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $other = $this->staff('ops', ['engineer']);
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
        $this->addPasskey('web', $other, $this->hqDevice());
        $theirs = DB::table('passkeys')->where('owner_id', $other->id)->value('id');

        Livewire::actingAs($me)->test(SignInMethods::class, ['guard' => 'web'])->call('removePasskey', $theirs);
        $this->assertTrue(DB::table('passkeys')->where('id', $theirs)->exists());

        Livewire::actingAs($other)->test(SignInMethods::class, ['guard' => 'web'])->call('removePasskey', $theirs)
            ->assertSee(__('sign_in.settings.passkey_removed'));
        $this->assertFalse(DB::table('passkeys')->where('id', $theirs)->exists());
    }

    public function test_the_same_device_cannot_add_its_passkey_twice(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $device = $this->hqDevice();
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
        $this->addPasskey('web', $me, $device);

        $settings = Livewire::actingAs($me)->test(SignInMethods::class, ['guard' => 'web']);
        $options = $settings->call('passkeyCreationOptions')->effects['returns'][0];
        $this->assertSame($device->id(), $options['excludeCredentials'][0]['id']);
        $settings->call('savePasskey', $device->create($options))->assertHasErrors('passkey');
        $this->assertSame(1, DB::table('passkeys')->where('owner_id', $me->id)->count());
    }

    public function test_a_password_sign_in_offers_a_passkey_until_one_is_added(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $me->forceFill(['password' => Hash::make('right-password-1')])->save();

        Livewire::test(LoginTerminal::class)->set('email', $me->email)->set('password', 'right-password-1')->call('authenticate')->assertHasNoErrors();
        $this->assertTrue(session('sign_in.offer_passkey.web'));
        $html = $this->actingAs($me)->get('https://' . Hosts::hq() . '/dashboard/workspace')->assertOk()->getContent();
        $this->assertStringContainsString(__('sign_in.offer.title'), $html);

        $offer = Livewire::actingAs($me)->test(PasskeyOffer::class, ['guard' => 'web']);
        $offer->call('save', $this->hqDevice()->create($offer->call('creationOptions')->effects['returns'][0]))
            ->assertHasNoErrors()->assertSee(__('sign_in.offer.done'));
        $this->assertNull(session('sign_in.offer_passkey.web'));
        $this->assertTrue(Passkeys::has('web', $me->id));
    }

    public function test_my_profile_and_the_sign_in_page_show_the_new_ways_in(): void
    {
        $this->get('https://' . Hosts::hq() . '/login')->assertOk()
            ->assertSee(__('sign_in.login.passkey'))->assertSee(__('sign_in.login.pin'));
        $this->get('https://' . Hosts::portal() . '/login')->assertOk()
            ->assertSee(__('sign_in.login.passkey'));

        $me = $this->staff('eng', ['engineer']);
        $this->actingAs($me)->get('https://' . Hosts::hq() . '/profile')->assertOk()
            ->assertSee(__('sign_in.settings.passkeys_title'))->assertSee(__('sign_in.settings.set_pin'));
    }

    public function test_a_client_signs_in_with_a_pin_in_french(): void
    {
        $client = $this->client();
        Pins::set('client', $client->id, '8051');
        session(['locale' => 'fr']); // chosen with the language switch

        Livewire::test(LoginMatrix::class)->call('choosePin', true)->set('email', $client->email)->set('pin', '8052')->call('signInWithPin')
            ->assertHasErrors('email')->assertSee('ne correspondent pas');
        Livewire::test(LoginMatrix::class)->call('choosePin', true)->set('email', $client->email)->set('pin', '8051')->call('signInWithPin')
            ->assertHasNoErrors()->assertRedirect(route('client.dashboard'));
        $this->assertSame($client->id, Auth::guard('client')->id());
        $this->assertSame(1, DB::table('system_audit_logs')->where('target_id', $client->id)->where('action', 'AUTH_LOGIN')->count());
    }

    public function test_the_settings_reach_only_the_signed_in_guard(): void
    {
        $client = $this->client();
        // A client session asking for HQ's settings is refused
        Livewire::actingAs($client, 'client')->test(SignInMethods::class, ['guard' => 'web'])->assertForbidden();
        Livewire::actingAs($client, 'client')->test(SignInMethods::class, ['guard' => 'admin'])->assertNotFound();
    }
}
