<?php

namespace Tests\Feature\CompanyProfile;

use App\Livewire\Hq\CompanyProfile\ProfileEditor;
use App\Models\CompanyProfile\ProfileVersion;
use App\Support\Access;
use App\Support\CompanyProfile\Profile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Who may read, write and approve the company profile (docs/COMPANY_PROFILE.md section 2). It carries the
 * teams' credentials, so it never leaves HQ and no starter role's wildcard sweeps it up.
 */
class ProfileAccessTest extends TestCase
{
    use ProfileFixtures, UsesThrowawayDatabase;

    public function test_the_four_permissions_are_in_the_catalogue(): void
    {
        foreach (['view', 'edit', 'approve', 'manage'] as $action) {
            $this->assertTrue(Access::isPermission("company_profile.{$action}"));
            $this->assertDatabaseHas('permissions', ['slug' => "company_profile.{$action}", 'audience' => 'staff']);
        }
    }

    public function test_a_wildcard_role_does_not_sweep_the_module_up(): void
    {
        $this->assertSame([], array_filter(
            Access::expandGrant(['*'], 'staff'),
            fn ($slug) => str_starts_with($slug, 'company_profile.'),
        ), 'a starter role granted "*" must not get the company profile');

        $this->assertSame([], array_filter(
            Access::expandGrant(['*.view'], 'staff'),
            fn ($slug) => str_starts_with($slug, 'company_profile.'),
        ), 'the Auditor role reads everything else, not the teams credentials');

        $this->assertContains('company_profile.view', Access::expandGrant(['company_profile.view'], 'staff'));
    }

    public function test_the_owner_has_every_action(): void
    {
        $owner = $this->owner();

        foreach (['view', 'edit', 'approve', 'manage'] as $action) {
            $this->assertTrue($owner->can("company_profile.{$action}"), "the owner should have company_profile.{$action}");
        }
    }

    public function test_sales_may_read_and_download_but_not_write(): void
    {
        $sales = $this->staff('sales', ['sales']);

        $this->assertTrue($sales->can('company_profile.view'));
        $this->assertFalse($sales->can('company_profile.edit'));
        $this->assertFalse($sales->can('company_profile.approve'));
        $this->assertFalse($sales->can('company_profile.manage'));

        $this->actingAs($sales)
            ->get($this->hqUrl('/company-profile/download?locale=en'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_someone_without_the_view_permission_sees_nothing(): void
    {
        $nobody = $this->person([]);
        $version = $this->published();

        $this->actingAs($nobody)->get($this->hqUrl('/company-profile'))->assertForbidden();
        $this->actingAs($nobody)->get($this->hqUrl('/company-profile/versions'))->assertForbidden();
        $this->actingAs($nobody)->get($this->hqUrl('/company-profile/versions/'.$version->id.'/preview'))->assertForbidden();
        $this->actingAs($nobody)->get($this->hqUrl('/company-profile/download?locale=en'))->assertForbidden();
    }

    public function test_a_reader_may_open_every_screen(): void
    {
        $reader = $this->person(['view']);
        $version = $this->published();

        $this->actingAs($reader)->get($this->hqUrl('/company-profile'))->assertOk()->assertSee('Company profile');
        $this->actingAs($reader)->get($this->hqUrl('/company-profile/versions'))->assertOk();
        $this->actingAs($reader)->get($this->hqUrl('/company-profile/versions/'.$version->id.'/preview'))->assertOk();
    }

    public function test_a_reader_cannot_start_a_draft_or_publish(): void
    {
        $this->actingAs($this->person(['view']));

        Livewire::test(ProfileEditor::class)->call('startDraft')->assertForbidden();
        $this->assertSame(1, ProfileVersion::count());
    }

    public function test_an_editor_cannot_publish_and_an_approver_can(): void
    {
        $this->actingAs($this->person(['view', 'edit']));
        $draft = Profile::startDraft();
        Livewire::test(ProfileEditor::class)->call('publish')->assertForbidden();
        $this->assertSame(ProfileVersion::DRAFT, $draft->fresh()->status);

        $this->actingAs($this->person(['view', 'approve']));
        Livewire::test(ProfileEditor::class)->call('publish')->assertOk();
        $this->assertSame(ProfileVersion::PUBLISHED, $draft->fresh()->status);
    }

    public function test_only_manage_throws_a_draft_away(): void
    {
        $this->actingAs($this->person(['view', 'edit']));
        $draft = Profile::startDraft();

        Livewire::test(ProfileEditor::class)->call('discardDraft')->assertForbidden();
        $this->assertDatabaseHas('company_profile_versions', ['id' => $draft->id]);

        $this->actingAs($this->person(['view', 'edit', 'manage']));
        Livewire::test(ProfileEditor::class)->call('discardDraft')->assertOk();
        $this->assertDatabaseMissing('company_profile_versions', ['id' => $draft->id]);
    }

    public function test_a_reader_cannot_download_an_unpublished_version(): void
    {
        $this->actingAs($this->person(['view', 'edit']));
        $draft = Profile::startDraft();

        $this->actingAs($this->person(['view']))
            ->get($this->hqUrl('/company-profile/download?locale=en&version='.$draft->number))
            ->assertForbidden();

        $this->actingAs($this->person(['view', 'edit']))
            ->get($this->hqUrl('/company-profile/download?locale=en&version='.$draft->number))
            ->assertOk();
    }

    public function test_the_rail_entry_is_hidden_from_people_without_the_permission(): void
    {
        $this->actingAs($this->person(['view']))->get($this->hqUrl('/company-profile'))
            ->assertSee('Company profile');

        // Someone with another module and not this one never sees the link
        $other = $this->staff('engineer', ['engineer']);
        $this->assertFalse($other->can('company_profile.view'));
        $this->actingAs($other)->get($this->hqUrl('/'))->assertDontSee('/company-profile');
    }

    public function test_every_section_renders_in_the_editor_and_in_the_preview(): void
    {
        $this->actingAs($this->person(['view', 'edit']));
        Profile::startDraft();
        $version = \App\Support\CompanyProfile\Profile::open();

        foreach (\App\Support\CompanyProfile\Sections::KEYS as $key) {
            Livewire::test(ProfileEditor::class)
                ->call('openSection', $key)
                ->assertOk()
                ->assertSee(\App\Support\CompanyProfile\Sections::label($key));
        }

        // The nested lists: a credential inside a team, a problem inside a group
        Livewire::test(ProfileEditor::class)->call('openSection', 'teams')
            ->assertSee('Credentials')->assertSee('Held')->assertSee('Disciplines');
        Livewire::test(ProfileEditor::class)->call('openSection', 'problems')->assertSee('Problems');
        Livewire::test(ProfileEditor::class)->call('openSection', 'facts')->assertSee('Show');

        foreach (['en', 'fr'] as $locale) {
            $this->get($this->hqUrl('/company-profile/versions/'.$version->id.'/preview?lang='.$locale))
                ->assertOk()
                ->assertSee(\App\Support\CompanyProfile\Profile::dataFor($version, $locale)['cover']['headline']);
        }
    }

    public function test_the_migration_grants_only_sales_the_view(): void
    {
        $granted = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('permissions.module', 'company_profile')
            ->where('roles.is_protected', 0)
            ->get(['roles.slug as role', 'permissions.slug as permission'])
            ->map(fn ($row) => $row->role.' => '.$row->permission)
            ->sort()->values()->all();

        $this->assertSame(['sales => company_profile.view'], $granted);
    }
}
