<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\CompanyProfile\ProfileVersion;
use App\Models\User;
use App\Support\CompanyProfile\ClientLogos;
use App\Support\CompanyProfile\Profile;
use App\Support\Hosts;
use Illuminate\Support\Facades\DB;

/**
 * Shared ground for the company profile tests (docs/COMPANY_PROFILE.md). The database arrives with v1
 * published, because CompanyProfileSeeder runs in DatabaseSeeder.
 */
trait ProfileFixtures
{
    protected function hqUrl(string $path): string
    {
        return 'https://'.Hosts::hq().$path;
    }

    /** Someone whose only role grants exactly these company_profile actions */
    protected function person(array $actions): User
    {
        $slug = 'cp-'.implode('-', $actions ?: ['none']).'-'.\Illuminate\Support\Str::random(4);
        $this->insertRow('roles', ['slug' => $slug, 'name' => 'Test '.$slug, 'audience' => 'staff', 'is_protected' => 0]);
        $roleId = DB::table('roles')->where('audience', 'staff')->where('slug', $slug)->value('id');

        foreach ($actions as $action) {
            $permission = DB::table('permissions')->where('slug', 'company_profile.'.$action)->value('id');
            $this->assertNotNull($permission, "company_profile.{$action} is not in the catalogue");
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permission]);
        }

        return $this->staff($slug, [$slug]);
    }

    protected function owner(): User
    {
        return $this->staff('owner', ['owner']);
    }

    protected function published(): ProfileVersion
    {
        $version = Profile::published();
        $this->assertNotNull($version, 'CompanyProfileSeeder should have published v1');

        return $version;
    }

    /** A logo wall with known contents, in place of whatever is on disk */
    protected function fakeLogos(array $logos): void
    {
        $this->app->bind(ClientLogos::class, fn () => new class($logos) implements ClientLogos
        {
            public function __construct(private readonly array $logos) {}

            public function all(): array
            {
                return $this->logos;
            }
        });
    }
}
