<?php

namespace Tests\Feature\Leads;

use App\Models\Organization;
use App\Services\Site\BookingService;
use App\Services\Site\CostReportService;
use App\Services\Site\EnquiryService;
use App\Support\Leads\Contacts;
use App\Support\Leads\Copq;
use App\Support\Leads\Duplicates;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\Leads;
use App\Support\Leads\LeadStatus;
use App\Support\Leads\LeadTimeline;
use App\Support\Leads\NextStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Leads PR 1's rules (docs/LEADS.md section 6): what a lead needs to be saved, contacts and the main contact,
 * duplicates, status (independent of opportunities; Lost with a reason; Client by signing or by hand), owners and the
 * default owner of website leads, the website's three forms, the next step, the timeline, and the backfill.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadRulesTest extends TestCase
{
    use UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.gemini.api_key' => null, 'services.leads.alert_email' => null]);
    }

    private function lead(array $values = [], array $contacts = [], $by = null): Organization
    {
        return Leads::create($values + ['name' => 'Kilo Logistics (test)', 'country' => 'Nigeria', 'industry' => 'Logistics', 'phone' => '+234 800 000 0001'],
            $contacts, $by);
    }

    public function test_a_lead_needs_a_name_a_country_an_industry_and_a_way_to_reach_it(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $errors = fn (array $lead, array $contacts = []) => array_keys(Leads::validate($lead, $contacts));

        $this->assertEqualsCanonicalizing(['lead.name', 'lead.country', 'lead.industry', 'lead.phone'], $errors([]));
        // A phone or an email, on the organisation or a contact, is enough; address, contacts and the rest are optional
        $base = ['name' => 'Kilo (test)', 'country' => 'Cameroon', 'industry' => 'Banking'];
        $this->assertSame([], $errors($base + ['email' => 'info@kilo.test']));
        $this->assertSame([], $errors($base, [['name' => 'Ada Test', 'phone' => '+237 600 000 000']]));
        $this->assertSame(['lead.phone'], $errors($base, [['name' => 'Ada Test']]));
        $this->assertSame(['contacts.0.name'], $errors($base + ['email' => 'info@kilo.test'], [['email' => 'ada@kilo.test']]));
        // An empty spare contact block is ignored
        $this->assertSame([], $errors($base + ['email' => 'info@kilo.test'], [['name' => '', 'email' => ' ']]));
        $this->assertSame(['lead.next_step_due'], $errors($base + ['email' => 'a@kilo.test', 'next_step' => 'Call them']));

        $this->expectException(ValidationException::class);
        Leads::create($base, [['name' => 'Ada Test']], $sales);
    }

    public function test_creating_a_lead_with_contacts(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $org = $this->lead(['source' => 'referral', 'source_detail' => 'Via a partner (test)', 'next_step' => 'Call Ada', 'next_step_due' => '2026-11-10', 'country' => 'Cameroon'], [
            ['name' => 'Ada Test', 'job_title' => 'CFO', 'email' => 'ADA@kilo.test', 'deal_role' => 'finance'],
            ['name' => 'Chidi Example Second', 'phone' => '+234 800 000 0002', 'deal_role' => 'decision_maker', 'main' => true],
            ['name' => '', 'email' => ''],
        ], $sales);

        $this->assertSame(['Lead', 'new', $sales->id, 'referral', 'Via a partner (test)', 'FR'],
            [$org->status, $org->lead_status, $org->assigned_to, $org->source, $org->source_detail, $org->preferred_language]);
        $this->assertSame(['Call Ada', '2026-11-10'], [$org->next_step, $org->next_step_due->format('Y-m-d')]);
        $contacts = Contacts::of($org->id);
        $this->assertCount(2, $contacts);
        // The one marked main is the main contact; names are split; emails are kept lower case; the email may be empty
        $this->assertSame(['Chidi', 'Example Second', null, 'decision_maker', true, 'Lead', 'hq'],
            [$contacts[0]->first_name, $contacts[0]->last_name, $contacts[0]->email, $contacts[0]->deal_role, (bool) $contacts[0]->is_main_contact, $contacts[0]->lifecycle_stage, $contacts[0]->source]);
        $this->assertSame(['ada@kilo.test', false, null], [$contacts[1]->email, (bool) $contacts[1]->is_main_contact, $contacts[1]->password]);

        $created = DB::table('lead_timeline')->where('organization_id', $org->id)->where('type', 'created')->sole();
        $this->assertSame('Lead added (referral), with Ada Test, Chidi Example Second.', $created->summary);
        $this->assertSame($sales->id, $created->actor_id);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $org->id, 'type' => 'next_step_set']);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LEAD_CREATED', 'target_id' => $org->id, 'user_id' => null]);
        $this->assertNotNull($org->last_activity_at);

        // An email that is already someone's contact is refused, naming where they are
        try {
            $this->lead(['name' => 'Other Co (test)'], [['name' => 'Ada Again', 'email' => 'ada@kilo.test']], $sales);
            $this->fail('the email is taken');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already a contact of Kilo Logistics (test)', $e->errors()['contacts.0.email'][0]);
        }
        $this->expectException(LeadRuleBroken::class);
        Contacts::add($org, ['name' => 'Ada Twice', 'email' => 'Ada@Kilo.test'], $sales);
    }

    public function test_contacts_main_contact_and_portal_accounts(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $org = $this->lead([], [['name' => 'Ada Test', 'email' => 'ada@kilo.test']], $sales);
        $ada = Contacts::of($org->id)->first();
        $this->assertTrue((bool) $ada->is_main_contact, 'the first contact is the main contact');

        $bola = Contacts::add($org, ['name' => 'Bola Example', 'email' => 'bola@kilo.test', 'deal_role' => 'technical'], $sales);
        $this->assertFalse((bool) $bola->is_main_contact);
        Contacts::makeMain($bola, $sales);
        $this->assertSame([$bola->id], Contacts::of($org->id)->where('is_main_contact', true)->pluck('id')->all());

        Contacts::update($bola, ['name' => 'Bola Example-Renamed', 'email' => 'bola@kilo.test', 'deal_role' => 'user', 'job_title' => 'CTO'], $sales);
        $this->assertSame(['Bola', 'Example-Renamed', 'user', 'CTO'], [$bola->fresh()->first_name, $bola->fresh()->last_name, $bola->fresh()->deal_role, $bola->fresh()->job_title]);

        // Removing the main contact hands the role to the next one; a portal account is never removed from here
        Contacts::remove($bola->fresh(), $sales);
        $this->assertSoftDeleted('client_users', ['id' => $bola->id]);
        $this->assertTrue((bool) $ada->fresh()->is_main_contact);
        $ada->forceFill(['password' => 'secret-hash'])->save();
        try {
            Contacts::remove($ada->fresh(), $sales);
            $this->fail('a portal account stays');
        } catch (LeadRuleBroken $e) {
            $this->assertStringContainsString('Client Portal account', $e->getMessage());
        }
        // and keeps its name, email and phone (only job title, role and main change here)
        Contacts::update($ada->fresh(), ['name' => 'Someone Else', 'email' => 'other@kilo.test', 'job_title' => 'COO', 'deal_role' => 'decision_maker'], $sales);
        $this->assertSame(['Ada', 'ada@kilo.test', 'COO', 'decision_maker'], [$ada->fresh()->first_name, $ada->fresh()->email, $ada->fresh()->job_title, $ada->fresh()->deal_role]);
        $this->assertSame(['contact_updated', 'contact_removed', 'contact_updated', 'contact_updated', 'contact_added'],
            DB::table('lead_timeline')->where('organization_id', $org->id)->where('type', 'like', 'contact_%')->orderByDesc('occurred_at')->orderByDesc('created_at')->pluck('type')->all());
    }

    public function test_duplicates_match_by_name_email_or_phone(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $kilo = $this->lead(['name' => 'The Kilo Logistics Ltd.', 'phone' => '0800 000 0001'], [['name' => 'Ada Test', 'email' => 'ada@kilo.test', 'phone' => '+234 803 111 2222']], $sales);
        $client = Organization::create(['name' => 'Lima Bank Plc (test)', 'status' => 'Active Client', 'country' => 'Nigeria']);
        DB::table('organizations')->where('id', $client->id)->update(['email' => 'hello@lima.test']);

        $ids = fn ($name, $emails = [], $phones = []) => Duplicates::find($name, $emails, $phones)->pluck('id')->all();
        $this->assertSame([$kilo->id], $ids('kilo logistics limited'));
        $this->assertSame([$kilo->id], $ids('Kilo Log'), 'what is typed, once 4 characters or more');
        $this->assertSame([$kilo->id], $ids(null, ['ADA@kilo.test']));
        $this->assertSame([$kilo->id], $ids(null, [], ['0803 111 2222']), 'the last 9 digits: +234 803… is 0803…');
        $this->assertSame([$kilo->id], $ids(null, [], ['+234 800 000 0001']), 'the organisation’s own phone');
        $this->assertSame([$client->id], $ids(null, ['hello@lima.test']), 'clients too');
        $this->assertSame([], $ids('Mike Unrelated'));
        $this->assertSame([], $ids('JBS Praxis'), 'never JBS Praxis itself');
        $match = Duplicates::find('Kilo Logistics', ['ada@kilo.test'])->first();
        $this->assertSame(['name', 'email'], $match['matched']);
        $this->assertSame('Sales Test', $match['owner']);
        $this->assertSame([], Duplicates::find('Kilo Logistics', [], [], $kilo->id)->all(), 'the lead itself is left out');
    }

    public function test_status_is_independent_lost_needs_a_reason_and_clients_come_by_signing_or_by_hand(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $org = $this->lead([], [], $sales);

        $this->assertTrue(LeadStatus::change($org, 'contacted', $sales));
        $this->assertFalse(LeadStatus::change($org, 'contacted', $sales), 'nothing changed');
        $this->assertSame('contacted', $org->lead_status);

        foreach ([['', null, 'lostReason'], ['other', ' ', 'lostNote'], ['made_up', null, 'lostReason']] as [$reason, $note, $field]) {
            try {
                LeadStatus::change($org, 'lost', $sales, $reason, $note);
                $this->fail("lost without a valid reason ({$reason})");
            } catch (LeadRuleBroken $e) {
                $this->assertSame($field, $e->field);
            }
        }
        LeadStatus::change($org, 'lost', $sales, 'no_budget', 'Next year maybe.');
        $org->refresh();
        $this->assertSame(['lost', 'no_budget', 'Next year maybe.', 'Lost'], [$org->lead_status, $org->lost_reason, $org->lost_note, $org->pipeline_stage]);
        $this->assertNotNull($org->lost_at);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LEAD_LOST', 'target_id' => $org->id]);

        // Back to qualified: the reason goes
        LeadStatus::change($org, 'qualified', $sales);
        $this->assertSame(['qualified', null, null], [$org->lead_status, $org->lost_reason, $org->lost_at]);

        // By hand: an Active Client from now, and it can be undone (a mistake)
        LeadStatus::change($org, 'client', $sales);
        $org->refresh();
        $this->assertSame(['client', 'Active Client', 'manual'], [$org->lead_status, $org->status, $org->client_via]);
        $this->assertNotNull($org->client_since);
        try {
            LeadStatus::markLost($org, 'no_need', null, $sales);
            $this->fail('a client is never lost');
        } catch (LeadRuleBroken) {
        }
        LeadStatus::change($org, 'qualified', $sales);
        $this->assertSame(['qualified', 'Lead', null, null], [$org->lead_status, $org->status, $org->client_via, $org->client_since]);

        // Signing: the first signature makes it a client "signed"; a later one only adds its line
        $at = Carbon::parse('2026-11-02 10:00:00');
        LeadStatus::markClientFromSigning($org->id, 'sow', 'SOW-20261102-001', null, $at);
        $org->refresh();
        $this->assertSame(['client', 'Active Client', 'signed', 'SOW-20261102-001', '2026-11-02 10:00:00'],
            [$org->lead_status, $org->status, $org->client_via, $org->client_reference, $org->client_since->format('Y-m-d H:i:s')]);
        LeadStatus::markClientFromSigning($org->id, 'proposal', 'PRP-20261103-001');
        $this->assertSame('SOW-20261102-001', $org->fresh()->client_reference);
        $this->assertSame(['signed', 'marked_client'], DB::table('lead_timeline')->where('organization_id', $org->id)->whereIn('type', ['signed', 'marked_client'])
            ->orderByDesc('occurred_at')->limit(2)->pluck('type')->all());
        // A client made by signing stays one
        $this->expectException(LeadRuleBroken::class);
        LeadStatus::change($org->fresh(), 'contacted', $sales);
    }

    public function test_owners_and_the_default_owner_of_website_leads(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $sales = $this->staff('gerald', ['sales']);
        $partner = $this->staff('partner', ['partner']);

        // Only the owner and partners (opportunities.approve) choose it; Sales doesn't
        $this->assertFalse(LeadOwner::canSetDefault($sales));
        $this->assertTrue(LeadOwner::canSetDefault($partner));
        $this->assertNull(LeadOwner::defaultOwnerId());
        $this->actingAs($owner);
        LeadOwner::setDefault($sales->id, $owner);
        $this->assertSame($sales->id, LeadOwner::defaultOwnerId());
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LEADS_DEFAULT_OWNER_CHANGED', 'user_id' => $owner->id]);

        // A website enquiry: the new lead is theirs, with a bell opening its page; the usual crm.edit bells stay
        app(EnquiryService::class)->submit(['full_name' => 'Ada Test', 'email' => 'ada@kilo.test', 'company_name' => 'Kilo Web (test)', 'message' => 'Hello there.',
            'country_code' => '+237', 'phone' => '600000000'] + EnquiryService::defaults(), false, '198.51.100.10');
        $org = Organization::where('name', 'Kilo Web (test)')->sole();
        $this->assertSame([$sales->id, 'website_enquiry', 'new', 'Cameroon', 'Reply to their enquiry', NextStep::suggestion()['due']],
            [$org->assigned_to, $org->source, $org->lead_status, $org->country, $org->next_step, $org->next_step_due->format('Y-m-d')]);
        $bell = DB::table('notifications')->where('user_id', $sales->id)->where('title', 'A website lead was assigned to you')->sole();
        $this->assertSame(route('hq.crm.lead', ['organization' => $org->id], false), $bell->link_url);
        $this->assertStringContainsString('Ada Test (Kilo Web (test)) sent a message through the contact form', $bell->message);
        $this->assertDatabaseHas('notifications', ['user_id' => $partner->id, 'title' => 'New website enquiry']);
        $contact = Contacts::of($org->id)->sole();
        $this->assertTrue((bool) $contact->is_main_contact);
        $enquiry = DB::table('lead_timeline')->where('organization_id', $org->id)->where('type', 'website_enquiry')->sole();
        $this->assertSame(['client', $contact->id, 'Hello there.'], [$enquiry->actor_type, $enquiry->client_user_id, $enquiry->body]);

        // A second enquiry from the same organisation adds to the lead and rings nobody again; the owner stays
        app(EnquiryService::class)->submit(['full_name' => 'Bola Test', 'email' => 'bola@kilo.test', 'company_name' => 'kilo web (test)', 'message' => 'Again.']
            + EnquiryService::defaults(), false, '198.51.100.11');
        $this->assertSame(2, DB::table('lead_timeline')->where('organization_id', $org->id)->where('type', 'website_enquiry')->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $sales->id)->where('title', 'A website lead was assigned to you')->count());

        // A default owner who left: website leads stay unowned
        DB::table('users')->where('id', $sales->id)->update(['status' => 'Suspended']);
        $this->assertNull(LeadOwner::defaultOwnerId());
        $this->expectException(LeadRuleBroken::class);
        LeadOwner::setDefault($sales->id, $owner);
    }

    public function test_bookings_and_the_cost_calculator_feed_the_lead(): void
    {
        $sales = $this->staff('gerald', ['sales']);
        $this->actingAs($this->staff('owner', ['owner']));
        LeadOwner::setDefault($sales->id, \Illuminate\Support\Facades\Auth::user());

        $date = Carbon::today('Africa/Lagos')->addDays(3);
        while ($date->isWeekend()) {
            $date->addDay();
        }
        app(BookingService::class)->book(['full_name' => 'Ada Test', 'email' => 'ada@lima.test', 'phone' => '+234 800 000 0003', 'enterprise_name' => 'Lima Booking (test)',
            'focus' => 'Performance Audit', 'date' => $date->format('Y-m-d'), 'time' => '10:30'], '198.51.100.20');
        $booked = Organization::where('name', 'Lima Booking (test)')->sole();
        $this->assertSame([$sales->id, 'website_booking'], [$booked->assigned_to, $booked->source]);
        $row = DB::table('lead_timeline')->where('organization_id', $booked->id)->where('type', 'website_booking')->sole();
        $this->assertSame(DB::table('appointments')->where('organization_id', $booked->id)->value('id'), json_decode($row->payload, true)['appointment_id']);

        $token = app(CostReportService::class)->generate(['email' => 'ada@mike.test', 'name' => 'Ada Test', 'company' => 'Mike Calc (test)', 'headcount' => 50,
            'turnover' => 500000, 'currency' => 'USD', 'manual_hrs' => 5, 'hourly_cost' => 20, 'error_rate' => 3], '198.51.100.30');
        $calc = Organization::where('name', 'Mike Calc (test)')->sole();
        $this->assertSame([$sales->id, 'cost_report'], [$calc->assigned_to, $calc->source]);
        $assessment = DB::table('copq_assessments')->where('report_token', $token)->sole();
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $calc->id, 'type' => 'cost_report']);

        // HQ's estimate gives the website's figure for the same inputs (one formula: Copq::calculate)
        $same = Copq::calculate((string) $assessment->copq_industry, 'excel', 'Operations',
            ['headcount' => 50, 'turnover' => 500000, 'manual_hrs' => 5, 'hourly_cost' => 20, 'error_rate' => 3, 'acv' => 0], 'USD');
        $this->assertEqualsWithDelta((float) $assessment->calculated_leakage, $same['total'], 0.01);
        $this->assertSame((int) $assessment->lead_tier, $same['tier']);
    }

    public function test_the_next_step_and_the_timeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 09:00:00', 'Africa/Lagos')); // a Friday
        $sales = $this->staff('sales', ['sales']);
        $org = $this->lead([], [], $sales);

        $this->assertSame(['what' => 'Make first contact', 'due' => '2026-11-09'], NextStep::suggestion(), 'the next working day');
        $this->assertTrue(NextStep::set($org, 'Send the brochure', '2026-11-05', $sales));
        $this->assertTrue(NextStep::isOverdue($org), 'its date has passed');
        LeadStatus::change($org, 'lost', $sales, 'no_reply');
        $this->assertFalse(NextStep::isOverdue($org->fresh()), 'only open leads are overdue');
        try {
            NextStep::set($org, 'Call', null, $sales);
            $this->fail('a next step needs a date');
        } catch (LeadRuleBroken) {
        }
        NextStep::set($org, '', '', $sales);
        $this->assertNull($org->fresh()->next_step);

        // record(): the same dedupe key records once; last_activity_at only moves forward and updated_at doesn't move
        $updated = DB::table('organizations')->where('id', $org->id)->value('updated_at');
        $first = LeadTimeline::record($org->id, 'imported', 'Imported from a sheet.', ['dedupe_key' => 'import:row-1', 'at' => '2026-11-07 08:00:00']);
        $this->assertSame($first, LeadTimeline::record($org->id, 'imported', 'Imported again.', ['dedupe_key' => 'import:row-1']));
        LeadTimeline::record($org->id, 'note', 'Older note.', ['at' => '2026-01-01 08:00:00', 'actor' => $sales]);
        $this->assertSame('2026-11-07 08:00:00', DB::table('organizations')->where('id', $org->id)->value('last_activity_at'));
        $this->assertSame($updated, DB::table('organizations')->where('id', $org->id)->value('updated_at'));
        $rows = LeadTimeline::for($org->id);
        $this->assertSame('imported', $rows->first()->type);
        $this->assertSame(['HQ', 'Sales Test'], [$rows->first()->actor_name, $rows->firstWhere('type', 'note')->actor_name]);
        $this->assertSame(1, DB::table('lead_timeline')->where('dedupe_key', 'import:row-1')->count());
        Carbon::setTestNow();
    }

    public function test_a_meeting_can_belong_to_a_lead_alone(): void
    {
        $org = $this->lead();
        DB::table('meetings')->insert(['id' => (string) Str::uuid(), 'opportunity_id' => null, 'organization_id' => $org->id, 'kind' => 'client',
            'title' => 'First call (test)', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, DB::table('meetings')->where('organization_id', $org->id)->whereNull('opportunity_id')->count());
    }

    public function test_the_backfill_maps_the_old_board_and_is_idempotent(): void
    {
        $staff = $this->staff('sales', ['sales']);
        $org = fn (array $v) => tap((string) Str::uuid(), fn ($id) => $this->insertRow('organizations', ['id' => $id, 'name' => 'Org ' . $id, 'status' => 'Lead',
            'assigned_to' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null, 'created_at' => now()->subMonth(), 'updated_at' => now()->subWeek()] + $v));
        $captured = $org(['pipeline_stage' => 'Lead Captured', 'country' => '+234']);
        $qualifying = $org(['pipeline_stage' => 'Qualifying']);
        $audit = $org(['pipeline_stage' => 'In Consultation']);
        $won = $org(['pipeline_stage' => 'Won']);
        $lost = $org(['pipeline_stage' => 'Lost', 'disqualification_reason' => 'Went quiet.']);
        $client = $org(['status' => 'Active Client', 'pipeline_stage' => 'Won']);
        foreach ([$captured, $qualifying, $audit, $won, $lost, $client] as $id) {
            DB::table('lead_timeline')->where('organization_id', $id)->delete(); // as before the migration
            DB::table('organizations')->where('id', $id)->update(['lead_status' => 'new', 'source' => null, 'updated_at' => DB::raw('updated_at')]);
        }
        $contact = (string) Str::uuid();
        $this->insertRow('client_users', ['id' => $contact, 'organization_id' => $captured, 'first_name' => 'Ada', 'last_name' => 'Test', 'email' => 'ada@old.test',
            'source' => 'contact_form', 'created_by' => null, 'restricted_project_id' => null, 'deal_role' => null]);
        $this->insertRow('crm_notes', ['id' => (string) Str::uuid(), 'organization_id' => $captured, 'author_id' => $contact, 'author_type' => 'client',
            'note_text' => ">> INTAKE MESSAGE:\nWe need help with our reports.", 'interaction_type' => 'Internal Note', 'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        $this->insertRow('crm_notes', ['id' => (string) Str::uuid(), 'organization_id' => $qualifying, 'author_id' => $staff->id, 'author_type' => 'staff',
            'note_text' => 'Called, they want a demo.', 'interaction_type' => 'Call', 'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $client, 'proposal_ref' => 'PRP-OLD-001', 'status' => 'Authorized',
            'signed_at' => '2026-10-01 10:00:00', 'created_by' => null, 'updated_by' => null, 'deleted_by' => null, 'opportunity_id' => null, 'previous_version_id' => null,
            'approved_by' => null, 'ai_run_id' => null, 'changes_run_id' => null, 'billing_entity_id' => null]);
        $updatedBefore = DB::table('organizations')->where('id', $captured)->value('updated_at');

        $migration = require database_path('migrations/2026_11_03_200001_backfill_leads.php');
        $migration->up();
        $count = DB::table('lead_timeline')->count();
        $migration->up();
        $this->assertSame($count, DB::table('lead_timeline')->count(), 'a second run adds nothing');

        $get = fn ($id) => DB::table('organizations')->where('id', $id)->first();
        $this->assertSame(['new', 'website_enquiry', 'Nigeria'], [$get($captured)->lead_status, $get($captured)->source, $get($captured)->country]);
        $this->assertSame($updatedBefore, $get($captured)->updated_at, 'not counted as an edit');
        $this->assertSame('contacted', $get($qualifying)->lead_status);
        $this->assertSame('qualified', $get($audit)->lead_status);
        $this->assertSame(['client', 'Active Client', 'manual'], [$get($won)->lead_status, $get($won)->status, $get($won)->client_via]);
        $this->assertSame(['lost', 'other', 'Went quiet.'], [$get($lost)->lead_status, $get($lost)->lost_reason, $get($lost)->lost_note]);
        $this->assertSame(['client', 'signed', 'PRP-OLD-001', '2026-10-01 10:00:00'],
            [$get($client)->lead_status, $get($client)->client_via, $get($client)->client_reference, $get($client)->client_since]);
        $this->assertNull(DB::table('organizations')->where('status', 'Internal')->value('lead_status'));
        $this->assertSame(1, (int) DB::table('client_users')->where('id', $contact)->value('is_main_contact'));

        $types = fn ($id) => DB::table('lead_timeline')->where('organization_id', $id)->orderBy('occurred_at')->pluck('type')->all();
        $this->assertSame(['created', 'website_enquiry'], $types($captured));
        $this->assertSame(['created', 'call'], $types($qualifying));
        $this->assertContains('marked_client', $types($client));
        $this->assertContains('marked_lost', $types($lost));
        $enquiry = DB::table('lead_timeline')->where('organization_id', $captured)->where('type', 'website_enquiry')->sole();
        $this->assertSame(['client', 'Website enquiry: We need help with our reports.'], [$enquiry->actor_type, $enquiry->summary]);
        $this->assertNotNull($get($qualifying)->last_activity_at);
    }
}
