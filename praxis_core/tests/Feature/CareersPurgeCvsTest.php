<?php

namespace Tests\Feature;

use App\Console\Commands\CareersPurgeCvs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * careers:purge-cvs (the privacy policy's 12 months for CVs) on fixtures: what goes, what stays, the dry run,
 * and the nightly schedule only behind CV_RETENTION_PURGE. MySQL only (UsesThrowawayDatabase).
 */
class CareersPurgeCvsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['praxis.cv_retention_months' => 12]);

        // Received 13 months ago, one CV: anonymised, CV gone
        $this->ids['old'] = $this->application('old@example.com', now()->subMonths(13));
        $this->cv($this->ids['old'], 'vault/dossiers/old.pdf', now()->subMonths(13));

        // Received 2 months ago: kept
        $this->ids['recent'] = $this->application('recent@example.com', now()->subMonths(2));
        $this->cv($this->ids['recent'], 'vault/dossiers/recent.pdf', now()->subMonths(2));

        // First sent 14 months ago, again last month: the application and the new CV stay, the first CV goes
        $this->ids['repeat'] = $this->application('repeat@example.com', now()->subMonths(14));
        $this->cv($this->ids['repeat'], 'vault/dossiers/repeat-first.pdf', now()->subMonths(14));
        $this->cv($this->ids['repeat'], 'vault/dossiers/repeat-again.pdf', now()->subMonth());

        // Joined us (Drafted), and one with an employment contract: part of a staff record, kept
        $this->ids['drafted'] = $this->application('drafted@example.com', now()->subMonths(20), 'Drafted');
        $this->cv($this->ids['drafted'], 'vault/dossiers/drafted.pdf', now()->subMonths(20));
        $this->ids['hired'] = $this->application('hired@example.com', now()->subMonths(20));
        $this->cv($this->ids['hired'], 'vault/dossiers/hired.pdf', now()->subMonths(20));
        // (the old schema also wants an mdm_employees row with the same id, which this test doesn't need)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->insertRow('employment_contracts', ['id' => (string) Str::uuid(), 'mdm_employee_id' => $this->ids['hired'], 'hourly_rate_offered' => 10, 'contract_pdf_path' => 'x.pdf']);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // A marketing picture (no talent_id) is not a CV
        $this->insertRow('media_assets', ['id' => (string) Str::uuid(), 'talent_id' => null, 'file_name' => 'banner.png', 'file_path' => 'marketing/banner.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'created_at' => now()->subYears(3)]);

        // Stray files no row points at: an old one goes, a new one stays (its save may still be under way)
        $this->file('vault/dossiers/stray-old.pdf', now()->subMonths(13));
        $this->file('vault/dossiers/stray-new.pdf', now()->subDay());
    }

    private function application(string $email, $at, string $status = 'Pending'): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('talent_pool', [
            'id' => $id, 'full_name' => 'Ada Applicant', 'email' => $email, 'phone' => '+234 8012345678', 'city' => 'Lagos',
            'primary_weapon' => 'Data engineering', 'secondary_skillset' => 'SQL', 'linkedin_url' => 'https://www.linkedin.com/in/ada',
            'portfolio_url' => 'https://ada.example.com', 'praxis_answer' => 'Why I want to join.', 'status' => $status,
            'created_at' => $at, 'updated_at' => $at,
        ]);

        return $id;
    }

    private function cv(string $talentId, string $path, $at): void
    {
        $this->file($path, $at);
        $this->insertRow('media_assets', [
            'id' => (string) Str::uuid(), 'talent_id' => $talentId, 'file_name' => basename($path), 'file_path' => $path,
            'mime_type' => 'application/pdf', 'size_bytes' => 3, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function file(string $path, $at): void
    {
        Storage::disk('local')->put($path, 'pdf');
        touch(Storage::disk('local')->path($path), $at->getTimestamp());
    }

    private function applicationRow(string $key): object
    {
        return DB::table('talent_pool')->where('id', $this->ids[$key])->first();
    }

    public function test_the_dry_run_counts_and_changes_nothing(): void
    {
        $this->artisan('careers:purge-cvs', ['--dry-run' => true])
            ->expectsOutputToContain('would delete 2 CV(s), anonymise 1 application(s) and remove 1 stray file(s). Nothing was changed.')
            ->assertSuccessful();

        $this->assertSame(7, DB::table('media_assets')->count());
        $this->assertSame('old@example.com', $this->applicationRow('old')->email);
        Storage::disk('local')->assertExists(['vault/dossiers/old.pdf', 'vault/dossiers/repeat-first.pdf', 'vault/dossiers/stray-old.pdf']);
        $this->assertSame(0, DB::table('system_audit_logs')->where('action', 'CV_RETENTION_PURGE')->count());
    }

    public function test_old_cvs_go_and_old_applications_are_anonymised(): void
    {
        $this->artisan('careers:purge-cvs')
            ->expectsOutputToContain('2 CV(s) deleted (2 file(s)), 1 application(s) anonymised, 1 stray file(s) removed.')
            ->assertSuccessful();

        $disk = Storage::disk('local');
        $disk->assertMissing(['vault/dossiers/old.pdf', 'vault/dossiers/repeat-first.pdf', 'vault/dossiers/stray-old.pdf']);
        $disk->assertExists(['vault/dossiers/recent.pdf', 'vault/dossiers/repeat-again.pdf', 'vault/dossiers/drafted.pdf', 'vault/dossiers/hired.pdf', 'vault/dossiers/stray-new.pdf']);
        $this->assertSame(5, DB::table('media_assets')->count());
        $this->assertSame(0, DB::table('media_assets')->where('talent_id', $this->ids['old'])->count());

        // The old application: nothing that identifies the person is left, and it is hidden
        $old = $this->applicationRow('old');
        $this->assertSame(CareersPurgeCvs::ANONYMISED_NAME, $old->full_name);
        $this->assertSame('anonymised-' . $this->ids['old'] . '@' . CareersPurgeCvs::ANONYMISED_DOMAIN, $old->email);
        $this->assertSame(['', '', null, null, null, ''], [$old->phone, $old->city, $old->secondary_skillset, $old->linkedin_url, $old->portfolio_url, $old->praxis_answer]);
        $this->assertNotNull($old->deleted_at);
        $this->assertSame('Data engineering', $old->primary_weapon);

        // Everyone else keeps their application
        foreach (['recent', 'repeat', 'drafted', 'hired'] as $key) {
            $row = $this->applicationRow($key);
            $this->assertSame('Ada Applicant', $row->full_name, $key);
            $this->assertNull($row->deleted_at, $key);
        }
        $this->assertSame(1, DB::table('employment_contracts')->where('mdm_employee_id', $this->ids['hired'])->count());

        $this->assertStringContainsString('1 application(s) anonymised', (string) DB::table('system_audit_logs')->where('action', 'CV_RETENTION_PURGE')->value('description'));

        // A second run finds nothing more to do and writes no audit line
        $this->artisan('careers:purge-cvs')
            ->expectsOutputToContain('0 CV(s) deleted (0 file(s)), 0 application(s) anonymised, 0 stray file(s) removed.')
            ->assertSuccessful();
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'CV_RETENTION_PURGE')->count());
    }

    public function test_it_runs_every_night_only_when_the_owner_switches_it_on(): void
    {
        $scheduled = fn () => collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'careers:purge-cvs'));

        $this->assertFalse(config('praxis.cv_retention_purge'));
        $this->assertCount(0, $scheduled());

        config(['praxis.cv_retention_purge' => true]);
        require base_path('routes/console.php');

        $this->assertCount(1, $scheduled());
        $this->assertSame('25 3 * * *', $scheduled()->first()->expression);
    }
}
