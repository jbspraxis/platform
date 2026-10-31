<?php

namespace Tests\Feature\Opportunities;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The backfill (docs/OPPORTUNITIES.md section 7) on records shaped like live's today: one opportunity per
 * organisation with proposals, projects or appointments; stage, lead and language from the data; past free
 * audits with a link become meetings. Run twice, it changes nothing the second time.
 */
class OpportunityBackfillTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private function backfill(): void
    {
        (require database_path('migrations/2026_10_31_800001_backfill_opportunities.php'))->up();
    }

    private function proposal(string $org, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('proposals', $values + ['id' => $id, 'organization_id' => $org, 'status' => 'Draft', 'subtotal' => 0, 'total_value' => 0,
            'payment_schedule_type' => 'Standard', 'created_at' => now()->subDays(20), 'updated_at' => now()]);

        return $id;
    }

    private function appointment(string $org, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('appointments', $values + ['id' => $id, 'organization_id' => $org, 'title' => 'Free operations audit',
            'start_time' => '2026-10-01 10:00:00', 'end_time' => '2026-10-01 11:00:00', 'status' => 'Completed', 'created_at' => now()->subDays(40)]);

        return $id;
    }

    public function test_backfill_builds_one_opportunity_per_organisation(): void
    {
        $alice = $this->staff('alice', ['sales']);
        $bola = $this->staff('bola', ['delivery']);

        // A: signed proposal and a project → won, led by the proposal's author
        $a = $this->organisation(['name' => 'Alpha Logistics', 'status' => 'Active Client']);
        $aProposal = $this->proposal($a, ['status' => 'Authorized', 'created_by' => $alice->id, 'title' => 'Finance for Alpha', 'signed_at' => '2026-09-10 09:00:00']);
        $aProject = (string) Str::uuid();
        $this->insertRow('projects', ['id' => $aProject, 'organization_id' => $a, 'title' => 'Alpha finance system', 'total_budget' => 1000, 'created_at' => '2026-09-12 08:00:00']);

        // B: a draft proposal and a past free audit booked on the website (French) → proposal_sent
        $b = $this->organisation(['name' => 'Beta Santé', 'preferred_language' => 'FR']);
        $client = $this->clientContact($b, ['first_name' => 'Chantal', 'last_name' => 'Mbida']);
        $this->proposal($b, ['created_by' => $bola->id]);
        $bAppt = $this->appointment($b, ['created_by' => $client, 'host_id' => $bola->id, 'meeting_link' => 'https://meet.google.com/abc-defg-hij']);

        // C: only a declined request without a link → no opportunity
        $c = $this->organisation(['name' => 'Gamma Spam']);
        $this->appointment($c, ['status' => 'Cancelled', 'meeting_link' => null]);

        // D: an upcoming audit, booked by the client, hosted by Alice → discovery, no meeting yet
        $d = $this->organisation(['name' => 'Delta Oil']);
        $dClient = $this->clientContact($d);
        $future = Carbon::now('Africa/Lagos')->addDays(5)->setTime(10, 0);
        $this->appointment($d, ['status' => 'Scheduled', 'created_by' => $dClient, 'host_id' => $alice->id, 'meeting_link' => 'https://meet.google.com/zzz-zzzz-zzz',
            'start_time' => $future->format('Y-m-d H:i:s'), 'end_time' => $future->copy()->addHour()->format('Y-m-d H:i:s')]);

        // E: JBS Praxis itself → skipped
        $e = $this->organisation(['name' => 'Internal', 'status' => 'Internal']);
        $this->proposal($e);

        // F: disqualified after the free audit, no proposal → lost
        $f = $this->organisation(['name' => 'Foxtrot Retail', 'disqualification_reason' => 'No budget this year']);
        $this->appointment($f, ['status' => 'Completed', 'meeting_link' => null]);

        $this->backfill();

        $opps = DB::table('opportunities')->get()->keyBy('organization_id');
        $this->assertSame([$a, $b, $d, $f], collect([$a, $b, $d, $f])->filter(fn ($id) => $opps->has($id))->values()->all());
        $this->assertFalse($opps->has($c), 'A declined request without a link is no opportunity');
        $this->assertFalse($opps->has($e), 'JBS Praxis itself is no opportunity');

        $oa = $opps[$a];
        $this->assertSame(['won', $alice->id, 'en', 'backfill', $aProject, 'Alpha finance system'], [$oa->stage, $oa->lead_user_id, $oa->language, $oa->source, $oa->project_id, $oa->title]);
        $this->assertSame('2026-09-10 09:00:00', $oa->closed_at);
        $this->assertSame($oa->id, DB::table('proposals')->where('id', $aProposal)->value('opportunity_id'));
        $this->assertSame($oa->id, DB::table('projects')->where('id', $aProject)->value('opportunity_id'));

        $ob = $opps[$b];
        $this->assertSame(['proposal_sent', $bola->id, 'fr'], [$ob->stage, $ob->lead_user_id, $ob->language]);
        $meeting = DB::table('meetings')->where('appointment_id', $bAppt)->first();
        $this->assertSame([$ob->id, 'client', 'held', 'manual', 'abc-defg-hij'], [$meeting->opportunity_id, $meeting->kind, $meeting->status, $meeting->transcript_status, $meeting->meet_code]);
        $this->assertSame('2026-10-01 09:00:00', $meeting->starts_at, 'Lagos 10:00 is 09:00 UTC');
        $attendees = DB::table('meeting_attendees')->where('meeting_id', $meeting->id)->get()->sortBy('role')->values();
        $this->assertSame(['client', 'staff'], $attendees->pluck('role')->all());
        $this->assertSame('Chantal Mbida', $attendees[0]->name);

        $od = $opps[$d];
        $this->assertSame(['discovery', $alice->id], [$od->stage, $od->lead_user_id], 'The host leads when the client booked');
        $this->assertSame(0, DB::table('meetings')->where('opportunity_id', $od->id)->count(), 'Upcoming audits are not copied');

        $of = $opps[$f];
        $this->assertSame(['lost', 'No budget this year'], [$of->stage, $of->lost_reason]);

        // Twice: nothing new; a proposal added since joins the same opportunity
        $counts = fn () => [DB::table('opportunities')->count(), DB::table('meetings')->count(), DB::table('meeting_attendees')->count()];
        $before = $counts();
        $this->backfill();
        $this->assertSame($before, $counts());

        $late = $this->proposal($a, ['created_by' => $alice->id]);
        $this->backfill();
        $this->assertSame($oa->id, DB::table('proposals')->where('id', $late)->value('opportunity_id'));
        $this->assertSame($before, $counts());
    }

    public function test_rollback_unlinks_and_removes_only_backfilled_records(): void
    {
        $org = $this->organisation();
        $proposal = $this->proposal($org);
        $manual = $this->opportunityFor($this->organisation(), ['title' => 'Opened by hand']);
        $this->backfill();
        $this->assertNotNull(DB::table('proposals')->where('id', $proposal)->value('opportunity_id'));

        (require database_path('migrations/2026_10_31_800001_backfill_opportunities.php'))->down();

        $this->assertNull(DB::table('proposals')->where('id', $proposal)->value('opportunity_id'));
        $this->assertSame([$manual->id], DB::table('opportunities')->pluck('id')->all());
    }
}
