<?php

namespace Tests\Feature;

use App\Livewire\Hq\Letters\DocumentPage;
use App\Livewire\Hq\Letters\DocumentRegister;
use App\Livewire\Hq\Mdm\LegalEntityRegistry;
use App\Livewire\Hq\System\RoleManagement;
use App\Livewire\Hq\System\RolesPermissions;
use App\Livewire\Hq\System\SystemHealth;
use App\Support\Letters\Letters;
use App\Support\Pwa\PushDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Letters\LetterFixtures;
use Tests\TestCase;

/**
 * HQ's admin screens do less on each request (performance pass, 11 Nov 2026): Documents lists without reading a
 * letter's body or a signature image, and a document is read once per action; Accounts keeps no organisation or
 * account rows in the page; Roles and permissions and Legal entities count in the same few queries however many
 * rows there are; System health runs its twelve checks only when they are shown or the errors change; and the
 * push dispatcher checks for its tables with one query.
 */
class HqAdminScreensSpeedTest extends TestCase
{
    use LetterFixtures, UsesThrowawayDatabase;

    /** @return list<string> the SQL run by $fn */
    private function queries(callable $fn): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $fn();
        } finally {
            $sql = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
        }

        return $sql;
    }

    public function test_the_documents_register_reads_no_body_or_signature_image_and_no_query_per_row(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $company = $this->company();
        $make = function (int $n) use ($owner, $company) {
            for ($i = 0; $i < $n; $i++) {
                $old = Letters::start('formal_letter', null, $company, $owner);
                $new = Letters::start('formal_letter', null, $company, $owner);
                Letters::sign($old, $owner);
                DB::table('letter_documents')->where('id', $old->id)->update(['status' => 'replaced', 'replaced_by_id' => $new->id, 'body' => '<p>The body.</p>']);
            }
        };

        $make(1);
        Livewire::test(DocumentRegister::class); // The signed-in person's roles are read once per test
        $few = count($this->queries(fn () => Livewire::test(DocumentRegister::class)->set('show', 'replaced')));
        $make(4);
        $sql = $this->queries(fn () => Livewire::test(DocumentRegister::class)->set('show', 'replaced')
            ->assertSee('Ada Test')->assertSee('by JBS/LET/'));

        $this->assertSame($few, count($sql), 'One query per list, not one per replaced document');
        $lists = implode("\n", array_filter($sql, fn ($q) => str_contains($q, 'letter_document')));
        $this->assertStringNotContainsString('select *', $lists, 'Only the columns the list shows');
        $this->assertStringNotContainsString('`image`', $lists);
        $this->assertStringNotContainsString('`body`', $lists);
    }

    public function test_a_document_is_read_once_per_action(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $page = Livewire::test(DocumentPage::class, ['document' => $document->id])->set('title', 'A letter to the bank');

        $sql = $this->queries(fn () => $page->call('save')->assertHasNoErrors());

        $reads = array_filter($sql, fn ($q) => str_starts_with($q, 'select') && str_contains($q, 'from `letter_documents` where `letter_documents`.`id`'));
        $this->assertCount(1, $reads, 'The save and the render after it share one read');
        $this->assertSame('A letter to the bank', $document->fresh()->title);
    }

    public function test_accounts_keeps_no_organisation_or_account_rows_in_the_page(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $rows[] = ['id' => (string) Str::uuid(), 'name' => sprintf('Lead organisation %03d', $i), 'status' => 'Lead',
                'ai_history_summary' => str_repeat('A long summary of the lead. ', 20), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('organizations')->insert($rows);

        $page = Livewire::test(RoleManagement::class)->assertSee('Lead organisation 299');
        $this->assertLessThan(5_000, strlen(json_encode($page->snapshot)), 'The organisations are drawn, not kept in the page');
        $this->assertStringNotContainsString('A long summary', json_encode($page->snapshot));

        $page->call('setTab', 'INTERNAL')->assertSee('Owner');
        $this->assertLessThan(5_000, strlen(json_encode($page->snapshot)), 'Nor the accounts');
    }

    public function test_roles_and_permissions_counts_in_the_same_queries_however_many_roles(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        Livewire::test(RolesPermissions::class); // The signed-in person's roles are read once per test
        $few = count($this->queries(fn () => Livewire::test(RolesPermissions::class)->html()));
        foreach (range(1, 5) as $i) {
            DB::table('roles')->insert(['name' => "Extra role {$i}", 'slug' => "extra-role-{$i}", 'audience' => 'staff', 'created_at' => now(), 'updated_at' => now()]);
        }

        $sql = $this->queries(fn () => Livewire::test(RolesPermissions::class)->assertSee('Extra role 5'));

        $this->assertSame($few, count($sql));
    }

    public function test_legal_entities_counts_in_the_same_queries_however_many_companies(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $this->company();
        Livewire::test(LegalEntityRegistry::class); // The signed-in person's roles are read once per test
        $few = count($this->queries(fn () => Livewire::test(LegalEntityRegistry::class)->html()));
        foreach (['CM', 'GH', 'KE'] as $region) {
            $this->insertRow('mdm_legal_entities', ['id' => (string) Str::uuid(), 'region_code' => $region, 'legal_name' => "JBS Praxis {$region}", 'is_active' => 1]);
        }

        $sql = $this->queries(fn () => Livewire::test(LegalEntityRegistry::class)->assertSee('JBS Praxis KE'));

        $this->assertSame($few, count($sql));
    }

    public function test_system_health_runs_its_checks_only_when_they_are_shown_or_the_errors_change(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        foreach (range(1, 3) as $i) {
            $this->insertRow('monitor_error_groups', ['fingerprint' => md5("e{$i}"), 'level' => 'critical', 'message' => "Something broke {$i}",
                'status' => 'new', 'occurrences' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }
        $ids = DB::table('monitor_error_groups')->pluck('id')->all();

        $page = Livewire::test(SystemHealth::class)->assertSee('Errors (last 24 h)')->assertSet('overall', 'bad');
        $page->call('setTab', 'errors')->assertSee('Something broke 1');

        // Ticking an error and typing a search draw the list, not the twelve checks
        $tick = $this->queries(fn () => $page->set('selected', [(string) $ids[0]]));
        $search = $this->queries(fn () => $page->set('search', 'broke 2')->assertSee('Something broke 2')->assertDontSee('Something broke 1'));
        $this->assertLessThanOrEqual(3, count($tick), implode("\n", $tick));
        $this->assertLessThanOrEqual(3, count($search), implode("\n", $search));
        $this->assertStringNotContainsString('push_subscriptions', implode("\n", [...$tick, ...$search]));

        // Marking them fixed changes the errors check, so the checks run again for the pill at the top
        $page->set('search', '')->set('selected', array_map('strval', $ids));
        $fixed = $this->queries(fn () => $page->call('bulkStatus', 'fixed'));
        $this->assertStringContainsString('push_subscriptions', implode("\n", $fixed));
    }

    public function test_the_push_dispatcher_checks_for_its_tables_with_one_query(): void
    {
        PushDispatcher::flush();
        $sql = $this->queries(fn () => $this->assertTrue(PushDispatcher::ready()));
        $this->assertCount(1, $sql);
        $this->assertCount(0, $this->queries(fn () => PushDispatcher::ready()), 'Once per process');
    }
}
