<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * JBS Praxis itself: the internal organisation every staff account belongs to, and the
 * "JBS Praxis Editorial" author used for website articles and case studies. The Editorial
 * account can never sign in (suspended, random password); it only gives content a name.
 */
class CoreSeeder extends Seeder
{
    public const INTERNAL_ORG_ID = 'c57cc72c-2073-11f1-b3a6-44a84221081c';
    public const EDITORIAL_USER_ID = '7e0d1a2b-0000-4000-8000-0000000ed170';

    public function run(): void
    {
        $orgFile = __DIR__ . '/private/internal_organization.php';
        $org = is_file($orgFile) ? require $orgFile : [
            'id' => self::INTERNAL_ORG_ID, 'name' => 'JBS Praxis', 'status' => 'Internal',
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('organizations')->insertOrIgnore($org);

        DB::table('users')->insertOrIgnore([
            'id' => self::EDITORIAL_USER_ID,
            'organization_id' => $org['id'],
            'first_name' => 'JBS Praxis',
            'last_name' => 'Editorial',
            'email' => 'editorial@jbspraxis.com',
            'password' => Hash::make(Str::random(64)),
            'job_title' => 'Editorial Team',
            'status' => 'Suspended',
            'lifecycle_stage' => 'System',
            'is_subscribed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
