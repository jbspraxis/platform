<?php

namespace Tests\Feature;

use App\Livewire\Hq\Account\MyProfile;
use App\Support\Hosts;
use App\Support\Mail\Compose\Signatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** The avatar menu and My profile (owner, 30 Oct 2026). */
class MyProfileTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_the_top_bar_has_the_avatar_menu_and_no_separate_sign_out(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $html = $this->actingAs($me)->get('https://' . Hosts::hq() . '/dashboard/workspace')->assertOk()->getContent();

        $this->assertStringContainsString('id="hq-user-menu"', $html);
        $this->assertStringContainsString(route('hq.profile'), $html);
        $this->assertSame(1, substr_count($html, route('hq.logout')), 'Sign out only inside the menu');
        $this->assertMatchesRegularExpression('/btn btn-danger"[^>]*>\s*<i data-lucide="log-out"><\/i> Sign out/', $html);
    }

    public function test_every_staff_member_sees_their_own_profile(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $me->forceFill(['job_title' => 'Engineer II'])->save();

        $this->actingAs($me)->get('https://' . Hosts::hq() . '/profile')->assertOk()
            ->assertSee('My profile')->assertSee('Engineer II')->assertSee($me->email);
    }

    public function test_numbers_are_the_signatures_numbers(): void
    {
        $me = $this->staff('eng', ['engineer']);

        Livewire::actingAs($me)->test(MyProfile::class)
            ->set('mobile', 'not a number')->call('saveNumbers')->assertHasErrors('mobile')
            ->set('mobile', '+234 801 234 5678')->set('office', '+237 6 99 00 11 22')->call('saveNumbers')
            ->assertHasNoErrors()->assertSee('Phone numbers saved');

        $this->assertSame(['phone' => '+237 6 99 00 11 22', 'mobile' => '+234 801 234 5678'], Signatures::numbers($me->fresh()));
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $me = $this->staff('eng', ['engineer']);
        $me->forceFill(['password' => Hash::make('old-password-1')])->save();

        Livewire::actingAs($me)->test(MyProfile::class)
            ->set('currentPassword', 'wrong-one')->set('newPassword', 'new-password-2')->set('newPasswordConfirmation', 'new-password-2')
            ->call('changePassword')->assertHasErrors('currentPassword')
            ->set('currentPassword', 'old-password-1')->set('newPassword', 'new-password-2')->set('newPasswordConfirmation', 'new-password-2')
            ->call('changePassword')->assertHasNoErrors()
            ->assertSet('currentPassword', '')->assertSet('newPassword', '');

        $this->assertTrue(Hash::check('new-password-2', $me->fresh()->password));
        $this->assertSame(1, DB::table('system_audit_logs')->where('user_id', $me->id)->where('action', 'PASSWORD_CHANGED')->count());
    }
}
