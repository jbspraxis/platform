<?php

namespace Tests\Feature;

use App\Livewire\ClientOs\LoginMatrix;
use App\Livewire\ClientOs\ResetPassword as ClientResetPassword;
use App\Livewire\Hq\Auth\LoginTerminal;
use App\Livewire\Hq\Auth\ResetPassword as HqResetPassword;
use App\Livewire\Hq\System\RoleManagement;
use App\Mail\PasswordResetMail;
use App\Mail\UserInvitation;
use App\Models\ClientUser;
use App\Models\User;
use App\Support\Hosts;
use App\Support\SignIn\PasswordResets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** "Forgot your password?" on HQ and the Client Portal, and "Send reset link" in Accounts (owner, 3 Nov 2026; docs/SIGN_IN.md section 8). */
class PasswordResetTest extends TestCase
{
    use UsesThrowawayDatabase {
        staff as private fixtureStaff;
    }

    private function client(string $language = 'EN', array $values = []): ClientUser
    {
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'Northwind Ltd (test)', 'preferred_language' => $language]);

        return ClientUser::forceCreate($values + [
            'organization_id' => $org,
            'role_id' => DB::table('roles')->where('audience', 'client')->value('id'),
            'first_name' => 'Chidi', 'last_name' => 'Eze',
            'email' => Str::lower(Str::random(8)) . '@northwind.test',
            'password' => Hash::make('client-password-1'),
            'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => true,
        ]);
    }

    /** A staff member who has set up their account (a new users row starts at Onboarding, the "Invited" stage) */
    protected function staff(string $slug, array $roles): User
    {
        $user = $this->fixtureStaff($slug, $roles);
        DB::table('users')->where('id', $user->id)->update(['lifecycle_stage' => 'Active']);

        return $user->fresh();
    }

    /** The token in the link of the one reset email sent to this address */
    private function tokenSentTo(string $email): string
    {
        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($email, &$url) {
            $url = $mail->resetUrl;

            return $mail->hasTo($email);
        });

        return basename((string) parse_url($url, PHP_URL_PATH));
    }

    private function askHq(string $email)
    {
        return Livewire::test(LoginTerminal::class)->call('toggleView', 'recovery')->set('email', $email)->call('initiateRecovery');
    }

    public function test_staff_get_a_link_that_sets_a_new_password_once(): void
    {
        Mail::fake();
        $me = $this->staff('eng', ['engineer']);
        $rememberToken = $me->remember_token;

        $this->askHq($me->email)->assertHasNoErrors()->assertSee('we’ve sent it a link to choose a new password');
        $token = $this->tokenSentTo($me->email);
        Mail::assertSent(PasswordResetMail::class, fn ($mail) => ! $mail->forClient && $mail->locale === 'en');

        // Only a hash is kept, and the invitation column is never touched
        $link = DB::table('password_reset_links')->where('owner_id', $me->id)->sole();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertNull(DB::table('users')->where('id', $me->id)->value('invitation_token'));

        $this->get('https://' . Hosts::hq() . '/reset/' . $token)->assertOk()->assertSee('Choose a new password')->assertSee($me->email);
        Livewire::test(HqResetPassword::class, ['token' => $token])
            ->set('password', 'short')->set('passwordConfirmation', 'short')->call('save')->assertHasErrors('password')
            ->set('password', 'new-password-2')->set('passwordConfirmation', 'new-password-2')->call('save')
            ->assertHasNoErrors()->assertRedirect(route('hq.dash.workspace'));

        $me->refresh();
        $this->assertTrue(Hash::check('new-password-2', $me->password));
        $this->assertNotSame($rememberToken, $me->remember_token, '"Remember me" elsewhere ends');
        $this->assertSame($me->id, Auth::guard('web')->id());
        $this->assertNotNull(DB::table('password_reset_links')->where('id', $link->id)->value('used_at'));
        $this->assertSame(1, DB::table('system_audit_logs')->where('target_id', $me->id)->where('action', 'PASSWORD_RESET_REQUESTED')->count());
        $this->assertSame(1, DB::table('system_audit_logs')->where('target_id', $me->id)->where('action', 'PASSWORD_RESET')->count());

        // Used once: the same link now explains itself and changes nothing
        Auth::guard('web')->logout();
        Livewire::test(HqResetPassword::class, ['token' => $token])->assertSet('expired', true)->assertSee('This link no longer works')
            ->set('password', 'third-password-3')->set('passwordConfirmation', 'third-password-3')->call('save')->assertNoRedirect();
        $this->assertTrue(Hash::check('new-password-2', $me->fresh()->password));
    }

    public function test_unknown_and_suspended_addresses_get_the_same_answer_and_no_email(): void
    {
        Mail::fake();
        $me = $this->staff('eng', ['engineer']);
        $gone = $this->staff('old', ['engineer']);
        DB::table('users')->where('id', $gone->id)->update(['status' => 'Suspended']);

        $answers = [];
        foreach ([$me->email, 'nobody@local.test', $gone->email] as $email) {
            $answers[] = str_replace($email, 'EMAIL', $this->askHq($email)->assertHasNoErrors()->get('recoveryMessage'));
        }

        $this->assertCount(1, array_unique($answers));
        Mail::assertSentCount(1);
        $this->assertSame(0, DB::table('password_reset_links')->where('owner_id', $gone->id)->count());
    }

    public function test_someone_still_invited_gets_their_invitation_again_and_it_keeps_working(): void
    {
        Mail::fake();
        $new = $this->staff('new', ['sales']);
        DB::table('users')->where('id', $new->id)->update(['lifecycle_stage' => 'Onboarding', 'password' => '', 'invitation_token' => 'invite-token-1234567890']);

        $this->askHq($new->email)->assertHasNoErrors();

        Mail::assertSent(UserInvitation::class, fn ($mail) => $mail->hasTo($new->email) && $mail->token === 'invite-token-1234567890' && ! $mail->forClient);
        Mail::assertNotSent(PasswordResetMail::class);
        $this->assertSame('invite-token-1234567890', DB::table('users')->where('id', $new->id)->value('invitation_token'));
        $this->assertSame(0, DB::table('password_reset_links')->count());
        $this->get('https://' . Hosts::hq() . '/setup/invite-token-1234567890')->assertOk();
    }

    public function test_a_link_lasts_an_hour_and_a_new_one_replaces_it(): void
    {
        $me = $this->staff('eng', ['engineer']);

        $first = PasswordResets::issue('web', $me->id);
        $second = PasswordResets::issue('web', $me->id);
        $this->assertNull(PasswordResets::account('web', $first));
        $this->assertSame($me->id, PasswordResets::account('web', $second)?->id);
        $this->assertNull(PasswordResets::account('client', $second), 'An HQ link never opens the portal');

        $this->travel(PasswordResets::MINUTES + 1)->minutes();
        $this->assertNull(PasswordResets::account('web', $second));
        $this->assertNull(PasswordResets::complete('web', $second, 'new-password-2'));
        $this->assertFalse(Hash::check('new-password-2', $me->fresh()->password));
    }

    public function test_requests_are_limited_per_email_address(): void
    {
        Mail::fake();
        $me = $this->staff('eng', ['engineer']);

        foreach (range(1, 3) as $i) {
            $this->askHq($me->email)->assertHasNoErrors();
        }
        $this->askHq($me->email)->assertHasErrors('email')->assertSee('Too many requests');

        Mail::assertSentCount(3);
        $this->assertSame(1, DB::table('password_reset_links')->where('owner_id', $me->id)->whereNull('used_at')->count(), 'One live link at a time');
    }

    public function test_a_client_resets_their_password_in_their_language(): void
    {
        Mail::fake();
        $client = $this->client('FR');

        Livewire::test(LoginMatrix::class)->call('switchState', 'forgot_password')->set('resetEmail', $client->email)->call('sendResetLink')
            ->assertHasNoErrors()->assertSet('resetEmail', '');

        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->forClient && $mail->locale === 'fr');
        $token = $this->tokenSentTo($client->email);
        $this->assertSame(0, DB::table('notifications')->where('title', 'Client asked for a new setup link')->count(), 'No more alert to HQ');

        $this->get('https://' . Hosts::portal() . '/reset/' . $token)->assertOk()->assertSee('Choisissez un nouveau mot de passe');
        Livewire::test(ClientResetPassword::class, ['token' => $token])
            ->set('password', 'client-password-2')->set('passwordConfirmation', 'client-password-2')->call('save')
            ->assertHasNoErrors()->assertRedirect(route('client.dashboard'));

        $this->assertTrue(Hash::check('client-password-2', $client->fresh()->password));
        $this->assertSame($client->id, Auth::guard('client')->id());
        $this->assertSame(1, DB::table('system_audit_logs')->where('target_id', $client->id)->where('action', 'PASSWORD_RESET')->count());
    }

    public function test_leads_and_invited_clients(): void
    {
        Mail::fake();
        $lead = $this->client('EN', ['password' => null, 'lifecycle_stage' => 'Lead', 'is_primary_client' => false]);
        $invited = $this->client('EN', ['password' => null, 'lifecycle_stage' => 'Onboarding', 'invitation_token' => 'client-invite-1234567890']);

        foreach ([$lead, $invited] as $who) {
            Livewire::test(LoginMatrix::class)->call('switchState', 'forgot_password')->set('resetEmail', $who->email)->call('sendResetLink')->assertHasNoErrors();
        }

        Mail::assertNotSent(PasswordResetMail::class);
        Mail::assertSentCount(1);
        Mail::assertSent(UserInvitation::class, fn ($mail) => $mail->hasTo($invited->email) && $mail->forClient && $mail->token === 'client-invite-1234567890');
    }

    public function test_accounts_sends_a_reset_link_to_an_active_account(): void
    {
        Mail::fake();
        $owner = $this->staff('own', ['owner']);
        $ada = $this->staff('ada', ['sales']);
        $client = $this->client();

        Livewire::actingAs($owner)->test(RoleManagement::class)->call('setTab', 'INTERNAL')
            ->assertSee('Send reset link')->assertSee('Edit');

        Livewire::actingAs($owner)->test(RoleManagement::class)->call('sendPasswordReset', $ada->id)
            ->assertSet('showSuccessModal', true)->assertSee('a link to choose a new password at ' . $ada->email);
        Livewire::actingAs($owner)->test(RoleManagement::class)->call('sendPasswordReset', $client->id)->assertSet('showSuccessModal', true);

        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo($ada->email) && ! $mail->forClient);
        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo($client->email) && $mail->forClient);
        $this->assertSame($owner->id, DB::table('password_reset_links')->where('owner_id', $ada->id)->value('requested_by'));
        $this->assertSame(1, DB::table('system_audit_logs')->where('user_id', $owner->id)->where('action', 'PASSWORD_RESET_SENT')->where('target_id', $ada->id)->count());
        $this->assertSame($ada->password, $ada->fresh()->password, 'The current password keeps working until a new one is chosen');
    }

    public function test_accounts_sends_nothing_to_invited_suspended_or_higher_accounts(): void
    {
        Mail::fake();
        $owner = $this->staff('own', ['owner']);
        $partner = $this->staff('par', ['partner']);
        $invited = $this->staff('new', ['sales']);
        DB::table('users')->where('id', $invited->id)->update(['lifecycle_stage' => 'Onboarding', 'invitation_token' => Str::random(60)]);
        $suspended = $this->staff('old', ['sales']);
        DB::table('users')->where('id', $suspended->id)->update(['status' => 'Suspended']);

        foreach ([$invited, $suspended] as $who) {
            Livewire::actingAs($owner)->test(RoleManagement::class)->call('sendPasswordReset', $who->id)->assertSet('showSuccessModal', false);
        }
        Livewire::actingAs($partner)->test(RoleManagement::class)->call('sendPasswordReset', $owner->id)
            ->assertSet('showSuccessModal', false)->assertDispatched('praxis-toast', $this->toast('You can’t send a reset link to an account that has more access than yours.'));

        // The Auditor sees Accounts but changes nothing
        $auditor = $this->staff('aud', ['auditor']);
        Livewire::actingAs($auditor)->test(RoleManagement::class)->call('sendPasswordReset', $owner->id)->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_an_expired_link_leads_back_to_the_request_form(): void
    {
        $this->get('https://' . Hosts::hq() . '/reset/' . Str::random(64))->assertOk()->assertSee('This link no longer works')
            ->assertSee(route('login', ['forgot' => 1]), false);
        $this->get('https://' . Hosts::hq() . '/login?forgot=1')->assertOk()->assertSee('Email me a reset link');
        $this->get('https://' . Hosts::portal() . '/login?forgot=1')->assertOk()->assertSee(__('portal.login.reset_submit'));
    }

    public function test_both_sign_in_pages_have_the_eye_to_show_the_password(): void
    {
        $hq = $this->get('https://' . Hosts::hq() . '/login')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input type="password" :type="show \? \'text\' : \'password\'" id="password"/', $hq);
        $this->assertStringContainsString('aria-label="Show password"', $hq);
        $this->assertStringContainsString('data-lucide="eye-off"', $hq);

        $portal = $this->get('https://' . Hosts::portal() . '/login')->assertOk()->getContent();
        $this->assertStringContainsString(':type="show ? \'text\' : \'password\'" id="client-password"', $portal);
        $this->assertStringContainsString('data-lucide="eye-off"', $portal);
    }

    public function test_the_email_reads_well_in_both_languages(): void
    {
        foreach (PasswordResetMail::samples() as $name => $mail) {
            foreach (['en' => 'Choose a new password', 'fr' => 'Choisir un nouveau mot de passe'] as $locale => $button) {
                $parts = PasswordResetMail::samples()[$name]->locale($locale)->renderParts();
                $this->assertStringContainsString($button, $parts['html']);
                $this->assertStringContainsString($mail->resetUrl, $parts['text']);
                $this->assertStringContainsString((string) PasswordResets::MINUTES, $parts['text']);
            }
        }
    }
}
