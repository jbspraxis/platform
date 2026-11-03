<?php

namespace Tests\Feature\Leads;

use App\Console\Commands\LeadsMorningBell;
use App\Livewire\Hq\Leads\FollowUps;
use App\Models\Organization;
use App\Support\Hosts;
use App\Support\Leads\Leads;
use App\Support\Leads\LeadStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Leads PR 3's My follow-ups and its morning bell (owner's decision 17, docs/LEADS.md section 9): open leads' next steps,
 * overdue first and in red, leads with no next step flagged, changing a next step there, and one bell a working day.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class FollowUpsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function lead(string $name, $by, ?int $dueInDays, array $options = []): Organization
    {
        $step = $dueInDays === null ? [] : ['next_step' => "Follow up {$name}", 'next_step_due' => Carbon::now('Africa/Lagos')->addDays($dueInDays)->format('Y-m-d')];

        return Leads::create($step + ['name' => $name, 'country' => 'Nigeria', 'industry' => 'Telecommunications', 'phone' => '+234 800 111 ' . random_int(1000, 9999)],
            [['name' => "{$name} Contact", 'email' => strtolower(preg_replace('/\W+/', '', $name)) . '@follow.test', 'phone' => '+234 803 555 0199']], $by, $options);
    }

    public function test_my_follow_ups(): void
    {
        $hq = 'http://' . Hosts::hq();
        $gerald = $this->staff('gerald', ['sales']);
        $other = $this->staff('ifeoma', ['sales']);
        $this->actingAs($gerald);
        $this->lead('Late Two', $gerald, -2);
        $this->lead('Late Five', $gerald, -5);
        $this->lead('Due Today', $gerald, 0);
        $this->lead('This Week', $gerald, 4);
        $this->lead('Next Month', $gerald, 30);
        $none = $this->lead('No Step', $gerald, null);
        $lost = $this->lead('Lost Late', $gerald, -3);
        LeadStatus::markLost($lost, 'no_reply', null, $gerald);
        $this->lead('Someone Else', $other, -1);

        $this->actingAs($gerald)->get($hq . '/intelligence/leads/follow-ups')->assertOk()->assertSee('My follow-ups');
        $this->actingAs($this->staff('engineer', ['engineer']))->get($hq . '/intelligence/leads/follow-ups')->assertForbidden();
        $this->actingAs($gerald);

        $screen = Livewire::test(FollowUps::class);
        $groups = $screen->viewData('groups');
        $this->assertSame(['Late Five', 'Late Two'], $groups['overdue']->pluck('name')->all()); // the oldest first
        $this->assertSame(['Due Today'], $groups['today']->pluck('name')->all());
        $this->assertSame(['This Week'], $groups['week']->pluck('name')->all());
        $this->assertSame(['Next Month'], $groups['later']->pluck('name')->all());
        $this->assertSame(['No Step'], $groups['none']->pluck('name')->all());
        $screen->assertSeeInOrder(['Overdue (2)', 'Late Five', '5 days overdue', 'Late Two', '2 days overdue', 'Due today (1)', 'In the next 7 days (1)', 'Later (1)', 'No next step (1)'])
            ->assertSee('Late Five Contact')->assertSee('tel:+2348035550199', false)->assertSee('mailto:latefive@follow.test', false)
            ->assertDontSee('Lost Late')->assertDontSee('Someone Else');
        $screen->set('owner', 'all')->assertSee('Everyone’s follow-ups')->assertSee('Someone Else')->assertSee('Ifeoma Test');

        // Giving the lead with no next step one: through NextStep, with its timeline line
        $screen->set('owner', 'mine')->call('edit', $none->id)->assertSet('editing', $none->id)->set('stepWhat', '')->call('save')->assertHasErrors('stepWhat')
            ->set('stepWhat', 'Call to introduce us')->set('stepDue', '2026-02-30')->call('save')->assertHasErrors('stepDue')
            ->set('stepDue', Carbon::now('Africa/Lagos')->addDay()->format('Y-m-d'))->call('save')->assertHasNoErrors()->assertSet('editing', null);
        $this->assertSame('Call to introduce us', $none->fresh()->next_step);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $none->id, 'type' => 'next_step_set', 'actor_id' => $gerald->id]);
        $this->assertCount(0, Livewire::test(FollowUps::class)->viewData('groups')['none']);

        // Leads access without changing leads (crm.view): the list, no changes
        $this->actingAs($this->staff('delivery', ['delivery']));
        Livewire::test(FollowUps::class, [])->set('owner', 'all')->assertSee('Late Five')->assertDontSee('Change next step')->call('edit', $none->id)->assertForbidden();
    }

    public function test_the_morning_bell(): void
    {
        $gerald = $this->staff('gerald', ['sales']);
        $other = $this->staff('ifeoma', ['sales']);
        $gone = $this->staff('former', ['sales']);
        $this->actingAs($gerald);
        $late = $this->lead('Late One', $gerald, -1);
        $this->lead('Today One', $gerald, 0);
        $this->lead('No Step One', $gerald, null);
        $this->lead('Later One', $other, 10); // nothing due: no bell
        $this->lead('Their Late One', $gone, -4);
        DB::table('users')->where('id', $gone->id)->update(['status' => 'Suspended']);

        $this->assertSame(0, Artisan::call('leads:morning-bell', ['--dry-run' => true]));
        $this->assertStringContainsString('[dry run] Gerald Test: 1 overdue, 1 due today and 1 lead with no next step.', Artisan::output());
        $this->assertSame(0, DB::table('notifications')->where('title', LeadsMorningBell::TITLE)->count());

        Artisan::call('leads:morning-bell');
        $bell = DB::table('notifications')->where('title', LeadsMorningBell::TITLE)->sole();
        $this->assertSame($gerald->id, $bell->user_id);
        $this->assertSame('/intelligence/leads/follow-ups', $bell->link_url);
        $this->assertStringStartsWith('1 overdue, 1 due today and 1 lead with no next step. First: Late One, Follow up Late One (by ', $bell->message);

        // Once a working day: running it again rings nobody
        Artisan::call('leads:morning-bell');
        $this->assertSame(1, DB::table('notifications')->where('title', LeadsMorningBell::TITLE)->count());
        $this->assertStringContainsString('Rang 0 people.', Artisan::output());
        $this->assertNotNull($late->fresh()); // nothing about the leads changes

        // Scheduled on working days at 07:30 Lagos time
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'leads:morning-bell'));
        $this->assertNotNull($event);
        $this->assertSame('30 7 * * 1-5', $event->expression);
        $this->assertSame('Africa/Lagos', $event->timezone);
    }
}
