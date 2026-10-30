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

    public function test_one_primary_role_shows_and_the_signature_has_no_department(): void
    {
        // An owner who is also Partner / Director: the migration's rule makes Partner / Director primary
        $me = $this->staff('own', ['owner', 'partner']);
        $partner = DB::table('roles')->where('slug', 'partner')->value('id');
        DB::table('staff_roles')->where('user_id', $me->id)->update(['is_primary' => 0]);
        $me->forceFill(['job_title' => 'Managing Partner'])->save();

        $this->assertSame('Partner / Director', $me->fresh()->primary_role_name, 'Without a choice: the first role that isn’t Owner');

        DB::table('staff_roles')->where('user_id', $me->id)->where('role_id', $partner)->update(['is_primary' => 1]);
        $html = $this->actingAs($me)->get('https://' . Hosts::hq() . '/profile')->assertOk()->getContent();
        $this->assertStringContainsString('<span class="user-chip-role">Partner / Director</span>', $html);
        $this->assertStringContainsString('Also has access as</dt><dd>Owner</dd>', $html);
        $this->assertStringNotContainsString('Owner, Partner / Director', $html);

        $person = Signatures::person($me->fresh());
        $this->assertSame('Managing Partner', $person['title']);
        $this->assertNull($person['department']);
    }

    public function test_the_primary_role_is_chosen_on_accounts(): void
    {
        $owner = $this->staff('own', ['owner']);
        $ada = $this->staff('ada', ['sales']);
        $sales = (string) DB::table('roles')->where('slug', 'sales')->value('id');
        $engineer = (string) DB::table('roles')->where('slug', 'engineer')->value('id');

        Livewire::actingAs($owner)->test(\App\Livewire\Hq\System\RoleManagement::class)
            ->call('editUser', $ada->id)
            ->assertSet('primary_role_id', $sales)
            ->set('role_ids', [$sales, $engineer])
            ->set('primary_role_id', $engineer)
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertSame((int) $engineer, (int) DB::table('staff_roles')->where('user_id', $ada->id)->where('is_primary', 1)->value('role_id'));
        $this->assertSame(1, DB::table('staff_roles')->where('user_id', $ada->id)->where('is_primary', 1)->count());

        // Unticking the primary role moves it to one that is still ticked
        Livewire::actingAs($owner)->test(\App\Livewire\Hq\System\RoleManagement::class)
            ->call('editUser', $ada->id)->set('role_ids', [$sales])->assertSet('primary_role_id', $sales);
    }

    public function test_a_photo_is_cropped_stored_privately_shown_and_removed(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $me = $this->staff('eng', ['engineer']);

        Livewire::actingAs($me)->test(MyProfile::class)
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('me.png', 900, 600))
            ->call('savePhoto')
            ->assertHasNoErrors()->assertSee('Photo saved');

        $me->refresh();
        $this->assertNotNull($me->avatar_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($me->avatar_path);
        [$w, $h] = getimagesizefromstring(\Illuminate\Support\Facades\Storage::disk('local')->get($me->avatar_path));
        $this->assertSame([512, 512], [$w, $h]);

        $page = $this->actingAs($me)->get('https://' . Hosts::hq() . '/profile')->assertOk();
        $page->assertSee($me->avatarUrl(), false);
        $this->actingAs($me)->get('https://' . Hosts::hq() . $me->avatarUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Not a photo
        Livewire::actingAs($me)->test(MyProfile::class)
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->createWithContent('notes.png', 'just text'))
            ->call('savePhoto')->assertHasErrors('photo');

        $old = $me->avatar_path;
        Livewire::actingAs($me)->test(MyProfile::class)->call('removePhoto')->assertSee('Photo removed');
        $this->assertNull($me->fresh()->avatar_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($old);
    }
}
