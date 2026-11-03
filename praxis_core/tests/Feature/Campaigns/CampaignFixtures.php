<?php

namespace Tests\Feature\Campaigns;

use App\Models\Campaigns\Segment;
use App\Models\User;
use App\Support\Access;
use App\Support\Hosts;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\UsesThrowawayDatabase;

/** Fictional organisations, contacts, subscribers and staff for the Campaigns tests (docs/CAMPAIGNS.md section 8) */
trait CampaignFixtures
{
    use UsesThrowawayDatabase;

    /** An organisation; returns its id */
    protected function org(array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('organizations', $values + [
            'id' => $id, 'name' => 'Org ' . Str::random(5) . ' (test)', 'status' => 'Lead', 'industry' => 'Logistics',
            'country' => 'Nigeria', 'preferred_language' => 'EN', 'deleted_at' => null,
        ]);

        return $id;
    }

    /** A contact of that organisation; returns its id */
    protected function contact(string $orgId, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('client_users', $values + [
            'id' => $id, 'organization_id' => $orgId, 'first_name' => 'Ada', 'last_name' => 'Test',
            'email' => 'c.' . Str::lower(Str::random(8)) . '@example.test', 'status' => 'Active', 'is_subscribed' => 1,
            'lifecycle_stage' => 'Lead', 'deleted_at' => null,
        ]);

        return $id;
    }

    protected function subscriber(array $values = []): int
    {
        return DB::table('marketing_subscribers')->insertGetId($values + [
            'email' => 's.' . Str::lower(Str::random(8)) . '@example.test', 'first_name' => 'Sam', 'last_name' => 'Test',
            'source' => 'hq', 'consent' => 'Signed up at a test event', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function segment(array $rules, string $name = 'Test segment'): Segment
    {
        return Segment::create(['name' => $name, 'rules' => $rules]);
    }

    /** A staff member whose one role has exactly these permissions (slugs such as broadcasts.view) */
    protected function staffWith(array $permissions, string $first = 'Tess'): User
    {
        $id = (string) Str::uuid();
        $this->insertRow('users', [
            'id' => $id, 'organization_id' => CoreSeeder::INTERNAL_ORG_ID, 'first_name' => $first, 'last_name' => 'Test',
            'email' => Str::lower($first) . '.' . Str::lower(Str::random(6)) . '@local.test', 'password' => Hash::make(Str::random(24)), 'status' => 'Active',
        ]);
        $roleId = DB::table('roles')->insertGetId(['name' => 'Test ' . Str::random(6), 'slug' => 'test-' . Str::lower(Str::random(8)), 'audience' => 'staff',
            'is_protected' => false, 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('permissions')->whereIn('slug', $permissions)->pluck('id') as $permissionId) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('staff_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
        Access::flush();

        return User::findOrFail($id);
    }

    protected function hqUrl(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    protected function portalUrl(string $path): string
    {
        return 'https://' . Hosts::portal() . $path;
    }
}
