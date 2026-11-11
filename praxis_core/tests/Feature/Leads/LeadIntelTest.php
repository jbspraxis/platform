<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Intelligence\LiveConsultation;
use App\Livewire\Hq\Leads\AddLead;
use App\Livewire\Hq\Leads\IntelTab;
use App\Livewire\Hq\Leads\LeadPage;
use App\Models\LeadIntelReport;
use App\Models\Organization;
use App\Models\User;
use App\Support\Access;
use App\Support\Leads\LeadIntel;
use App\Support\Leads\Leads;
use App\Support\Opportunities\OpportunityContext;
use Database\Seeders\CoreSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Opportunities\OpportunityFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Intel reports on a lead (docs/LEADS.md section 13): upload or paste, Claude's layout, editing, the AI reading it,
 * the three ways into Add a lead with "Fill from the report", the pitch brief and the consultation line. Claude is
 * faked (FakeAi): nothing reaches Anthropic. Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadIntelTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const REPORT = [
        'snapshot' => ['summary' => 'A jewellery retailer selling online and in two shops.', 'size' => 'About 25 staff', 'location' => 'Lagos', 'owners' => 'Paul (business) and Kma (creative)'],
        'decision_makers' => [
            ['name' => 'Paul', 'role' => 'Co-founder, business', 'deal_role' => 'decision_maker', 'contact' => 'paul@jewel.test', 'approach' => 'Write to him by email first.'],
            ['name' => 'Kma', 'role' => 'Co-founder, creative', 'deal_role' => 'user', 'contact' => '', 'approach' => ''],
        ],
        'tools' => ['currency' => 'USD', 'items' => [
            ['tool' => 'Notion', 'used_for' => 'System of record', 'cost_amount' => 96, 'cost_period' => 'year', 'cost_basis' => 'estimated', 'note' => ''],
            ['tool' => 'Shopify', 'used_for' => 'Sales', 'cost_amount' => 79, 'cost_period' => 'month', 'cost_basis' => 'stated', 'note' => ''],
            ['tool' => 'Figma', 'used_for' => 'Design', 'cost_amount' => 0, 'cost_period' => 'none', 'cost_basis' => 'free', 'note' => ''],
            ['tool' => 'N8N', 'used_for' => 'Automations', 'cost_amount' => 0, 'cost_period' => 'none', 'cost_basis' => 'unknown', 'note' => ''],
        ]],
        'pain_points' => [['point' => 'Staff turnover', 'detail' => 'The content manager and ad buyer roles change often.']],
        'how_we_can_help' => 'One platform for content and ads.',
        'meeting_approach' => 'Informal and direct.',
        'source' => ['how' => 'An interview for a role at the company', 'confidence' => 'medium', 'note' => 'Costs were not seen.'],
        'open_questions' => ['What does the team spend on ads?'],
        'other_findings' => 'The founders are active on Instagram.',
    ];

    private const PASTED = 'Notion is their system of record, Shopify runs sales, Figma is for design and N8N for automations. Turnover is high in content roles.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function lead(User $by, string $name = 'Kilo Retail (test)'): Organization
    {
        return Leads::create(['name' => $name, 'country' => 'Nigeria', 'industry' => 'Retail', 'phone' => '+234 800 000 0001'], [['name' => 'Ada Test', 'email' => 'ada@kilo.test']], $by);
    }

    /** A staff member who may view leads but not edit them */
    private function viewer(): User
    {
        $id = (string) Str::uuid();
        $this->insertRow('users', ['id' => $id, 'organization_id' => CoreSeeder::INTERNAL_ORG_ID, 'first_name' => 'Vera', 'last_name' => 'Viewer',
            'email' => 'vera.' . Str::random(6) . '@local.test', 'password' => Hash::make(Str::random(24)), 'status' => 'Active']);
        $roleId = DB::table('roles')->insertGetId(['name' => 'Viewer ' . Str::random(6), 'slug' => 'viewer-' . Str::lower(Str::random(8)), 'audience' => 'staff',
            'is_protected' => false, 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('permissions')->whereIn('slug', ['crm.view', 'crm.consult'])->pluck('id') as $permissionId) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('staff_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
        Access::flush();

        return User::findOrFail($id);
    }

    private function tab(Organization $lead)
    {
        return Livewire::test(IntelTab::class, ['leadId' => $lead->id]);
    }

    private function addReport(Organization $lead, User $by, string $text = self::PASTED): LeadIntelReport
    {
        return LeadIntel::add($lead, LeadIntel::textSource($text), $by, 'Findings');
    }

    public function test_a_pasted_report_is_read_into_the_layout(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $ai = FakeAi::install()->respond('lead_intel_read', self::REPORT);

        $this->tab($lead)->assertSee('No intel on this lead yet')
            ->call('openForm', 'paste')->assertSet('form', 'paste')
            ->call('savePaste')->assertHasErrors('pasteText')
            ->set('pasteText', self::PASTED)->set('title', 'Findings')
            ->call('savePaste')->assertHasNoErrors()->assertSet('form', '')->assertDispatched('lead-changed')
            ->assertSee('Findings')->assertSee('Notion')->assertSee('Staff turnover')->assertSee('Paul')
            ->assertSee('About USD 87 a month, 1,044 a year')->assertSee('partly estimated')->assertSee('1 is free')->assertSee('1 has no price')
            ->assertSee('nothing here reaches the client', false);

        $report = LeadIntelReport::where('organization_id', $lead->id)->sole();
        $this->assertSame(['text', true], [$report->input_kind, $report->use_for_ai]);
        $this->assertSame('Paul', $report->structured['decision_makers'][0]['name']);
        $this->assertSame(self::PASTED, $report->raw_text);
        $ai->assertRan('lead_intel_read', fn ($r) => str_contains($r->prompt, 'Notion is their system of record') && str_contains($r->prompt, 'COMPANY: Kilo Retail'));
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $lead->id, 'type' => 'intel_added', 'summary' => 'An intel report was added.']);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LEAD_INTEL_ADDED', 'target_id' => $lead->id]);
    }

    public function test_a_word_file_goes_to_claude_as_text_and_a_pdf_as_the_file_itself(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $ai = FakeAi::install()->respond('lead_intel_read', self::REPORT);

        // A Word file is a zip holding word/document.xml (built with the app's own zip writer: no zip extension needed)
        $docx = UploadedFile::fake()->createWithContent('Findings round one.docx', \App\Support\Import\Xlsx\Zip::build([
            'word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>Their system of record is Notion.</w:t></w:r></w:p></w:body></w:document>',
        ]));

        $tab = $this->tab($lead)->call('openForm', 'upload')->set('file', $docx)->call('saveUpload')->assertHasNoErrors();
        $report = LeadIntelReport::where('organization_id', $lead->id)->sole();
        $this->assertSame(['file', 'Findings round one.docx', 'Findings round one'], [$report->input_kind, $report->original_name, $report->title]);
        $this->assertSame("lead-intel/{$lead->id}/{$report->id}.docx", $report->path);
        Storage::disk('local')->assertExists($report->path);
        $ai->assertRan('lead_intel_read', fn ($r) => str_contains($r->prompt, 'Their system of record is Notion.') && $r->attachments === []);

        $pdf = UploadedFile::fake()->createWithContent('Brief.pdf', "%PDF-1.4\n1 0 obj << /Type /Page >> endobj\n%%EOF");
        $tab->call('openForm', 'upload')->set('file', $pdf)->call('saveUpload')->assertHasNoErrors();
        $ai->assertRan('lead_intel_read', fn ($r) => count($r->attachments) === 1 && $r->attachments[0]['media_type'] === 'application/pdf');

        // Anything else is refused before it is stored
        $tab->call('openForm', 'upload')->set('file', UploadedFile::fake()->create('macro.exe', 10))->call('saveUpload')->assertHasErrors('file');
        $this->assertSame(2, LeadIntelReport::where('organization_id', $lead->id)->count());
    }

    public function test_a_reading_that_fails_says_why_and_can_be_tried_again(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $ai = FakeAi::install()->fail('lead_intel_read', 'budget');

        $tab = $this->tab($lead)->call('openForm', 'paste')->set('pasteText', self::PASTED)->call('savePaste')
            ->assertSee('The report was added, but Claude could not read it yet')->assertSee('AI budget is spent')->assertSee('Could not be read');
        $report = LeadIntelReport::where('organization_id', $lead->id)->sole();
        $this->assertNull($report->structured);
        $this->assertSame(self::PASTED, $report->raw_text, 'The original is kept');

        // The budget is raised: Read it again works, and the answer is filed
        $ai->respond('lead_intel_read', self::REPORT);
        $this->actingAs($sales);
        FakeAi::install()->respond('lead_intel_read', self::REPORT);
        $tab->call('readAgain', $report->id)->assertSee('Notion');
        $this->assertSame('Paul', $report->fresh()->structured['decision_makers'][0]['name']);
    }

    public function test_edits_are_checked_and_kept_in_the_one_layout(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        FakeAi::install()->respond('lead_intel_read', self::REPORT);
        $report = $this->addReport($lead, $sales);

        $tab = $this->tab($lead)->call('startEdit', $report->id)->assertSet('editing', $report->id)->assertSet('edit.snapshot.size', 'About 25 staff')
            ->assertSet('edit.open_questions', 'What does the team spend on ads?');

        $tab->set('edit.tools.items.0.cost_amount', 'lots')->call('saveEdit')->assertHasErrors('edit.tools.items.0.cost_amount')
            ->set('edit.tools.items.0.cost_amount', '120')
            ->set('edit.title', 'Renamed findings')->set('edit.snapshot.summary', 'A jewellery brand.')
            ->call('addRow', 'tools')->set('edit.tools.items.4.tool', 'Slack')->set('edit.tools.items.4.cost_basis', 'stated')
            ->set('edit.tools.items.4.cost_amount', '12')->set('edit.tools.items.4.cost_period', 'month')
            ->call('removeRow', 'pain_points', 0)
            ->set('edit.open_questions', "First question\n\nSecond question")
            ->call('saveEdit')->assertHasNoErrors()->assertSet('editing', null)->assertSee('Renamed findings');

        $report = $report->fresh();
        $this->assertSame('Renamed findings', $report->title);
        $this->assertSame('A jewellery brand.', $report->structured['snapshot']['summary']);
        $this->assertSame([], $report->structured['pain_points']);
        $this->assertSame(['First question', 'Second question'], $report->structured['open_questions']);
        // Notion is now 120 a year (10 a month), Shopify 79, Slack 12
        $this->assertSame(101.0, LeadIntel::totals($report->structured)['monthly']);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LEAD_INTEL_EDITED', 'target_id' => $lead->id]);
    }

    public function test_the_ai_reads_only_reports_that_are_read_and_switched_on(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $org = $this->organisation(['name' => 'Jewel Co']);
        $o = $this->opportunityFor($org);
        $this->assertStringNotContainsString('CONFIDENTIAL INTEL', OpportunityContext::for($o), 'No intel, no block');

        $report = LeadIntelReport::create(['organization_id' => $org, 'title' => 'Findings', 'input_kind' => 'text', 'raw_text' => 'x',
            'structured' => LeadIntel::normalise(self::REPORT), 'use_for_ai' => true]);

        $text = OpportunityContext::for($o);
        foreach (['CONFIDENTIAL INTEL', 'Never quote it', 'INTEL REPORT 1: Findings', 'Notion: System of record. 96 a year (an estimate)', 'Shopify: Sales. 79 a month (stated)',
            'Figma: Design. Free', 'N8N: Automations. Cost not known', 'about USD 87 a month, 1,044 a year', '1 more have no price', 'Staff turnover',
            'Source (internal, never to be revealed)'] as $needle) {
            $this->assertStringContainsString($needle, $text, "Missing: {$needle}");
        }
        $this->assertSame($text, OpportunityContext::for($o->fresh()), 'The same records give the same text (prompt caching)');
        $this->assertStringNotContainsString('CONFIDENTIAL INTEL', OpportunityContext::for($o, ['profile']), 'Only where the part is asked for');

        $report->update(['use_for_ai' => false]);
        $this->assertStringNotContainsString('CONFIDENTIAL INTEL', OpportunityContext::for($o), 'Switched off');
        $report->update(['use_for_ai' => true, 'structured' => null]);
        $this->assertStringNotContainsString('CONFIDENTIAL INTEL', OpportunityContext::for($o), 'Not read yet');
    }

    public function test_switching_off_and_removing_a_report(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        FakeAi::install()->respond('lead_intel_read', self::REPORT);
        $tab = $this->tab($lead);
        $report = $this->addReport($lead, $sales);

        $tab->call('toggleAi', $report->id)->assertSee('The AI ignores this');
        $this->assertFalse($report->fresh()->use_for_ai);
        $this->assertNull(LeadIntel::contextText($lead->id));
        $tab->call('toggleAi', $report->id)->assertSee('The AI reads this');

        // A file report: it moves from the inbox to the lead, and goes with the report
        Storage::disk('local')->put($inbox = 'lead-intel/inbox/' . Str::uuid() . '.txt', 'Their tools: Notion.');
        $stored = LeadIntel::add($lead, ['kind' => 'file', 'path' => $inbox, 'name' => 'notes.txt', 'mime' => 'text/plain', 'size' => 20], $sales);
        Storage::disk('local')->assertExists($stored->path);
        Storage::disk('local')->assertMissing($inbox);
        $tab->call('remove', $stored->id)->assertSee('The report and its file were removed.');
        Storage::disk('local')->assertMissing($stored->path);
        $this->assertSoftDeleted('lead_intel_reports', ['id' => $stored->id]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $lead->id, 'type' => 'intel_removed']);
    }

    public function test_only_people_who_can_edit_leads_reach_the_intel(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $lead = $this->lead($sales);
        FakeAi::install()->respond('lead_intel_read', self::REPORT);
        $this->actingAs($sales);
        $this->addReport($lead, $sales);

        Livewire::test(LeadPage::class, ['organization' => $lead])->call('show', 'intel')->assertSet('tab', 'intel')->assertSee('Intel reports')->assertSee('Intel (1)');

        $this->actingAs($this->viewer());
        Livewire::withQueryParams(['tab' => 'intel'])->test(LeadPage::class, ['organization' => $lead])->assertSet('tab', 'overview')->assertDontSee('Intel reports')
            ->call('show', 'intel')->assertSet('tab', 'overview');
        Livewire::test(IntelTab::class, ['leadId' => $lead->id])->assertForbidden();
        // The timeline everyone reads says that one exists, never what it holds
        $this->assertStringNotContainsString('Notion', (string) DB::table('lead_timeline')->where('type', 'intel_added')->value('summary'));
    }

    public function test_add_a_lead_asks_what_you_have_and_shows_only_what_that_needs(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);

        Livewire::test(AddLead::class)->assertSet('path', '')->assertSee('What do you have on this company?')->assertSee('An intel report')
            ->assertSee('Cost of poor quality discussed')->assertSee('A blank lead')->assertDontSee('Organisation name')
            ->call('choose', 'blank')->assertSet('path', 'blank')->assertSee('Organisation name')->assertDontSee('More details')->assertDontSee('Intel report')
            ->call('changePath')->assertSet('path', '')
            ->call('choose', 'copq')->assertSet('moreOpen', true)->assertSee('More details')->assertSee('Cost of poor quality')
            ->call('changePath')->call('choose', 'intel')->assertSee('Fill the empty fields from the report')->assertSee('More details')
            ->call('choose', 'nonsense')->assertSet('path', 'intel');

        Livewire::withQueryParams(['start' => 'blank'])->test(AddLead::class)->assertSet('path', 'blank')->assertSee('Organisation name');

        $this->actingAs($this->viewer());
        Livewire::test(AddLead::class)->assertForbidden();
    }

    public function test_fill_from_the_report_fills_only_empty_fields_and_the_report_is_attached_on_save(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $industry = (string) DB::table('mdm_copq_industries')->orderBy('industry_name')->value('industry_name');
        $ai = FakeAi::install()
            ->respond('lead_intel_prefill', ['company_name' => 'Jewel Co', 'country' => 'cameroon', 'city' => 'Douala', 'industry' => $industry,
                'phone' => '+237 600 000 000', 'email' => 'not an email',
                'contacts' => [
                    ['name' => 'Kma', 'job_title' => 'Creative director', 'email' => '', 'phone' => '', 'deal_role' => 'user'],
                    ['name' => 'Paul', 'job_title' => 'Managing director', 'email' => 'paul@jewel.test', 'phone' => '', 'deal_role' => 'decision_maker'],
                ]])
            ->respond('lead_intel_read', self::REPORT);

        $form = Livewire::test(AddLead::class)->call('choose', 'intel')
            ->call('fillFromReport')->assertHasErrors('intelText')
            ->set('intelText', 'short')->call('fillFromReport')->assertHasErrors('intelText')
            ->set('lead.phone', '+234 800 111 2222')   // typed by hand: must survive
            ->set('intelText', self::PASTED)->set('intelTitle', 'Findings')
            ->call('fillFromReport')->assertHasNoErrors()
            ->assertSet('lead.name', 'Jewel Co')->assertSet('lead.phone', '+234 800 111 2222')->assertSet('lead.city', 'Douala')
            ->assertSet('lead.country', 'Cameroon')->assertSet('lead.preferred_language', 'FR')->assertSet('lead.industry', $industry)
            ->assertSet('lead.email', '')   // a value that is not an email address is not put in the form
            ->assertSet('contacts.0.name', 'Paul')->assertSet('contacts.0.email', 'paul@jewel.test')->assertSet('contacts.0.main', true)
            ->assertSet('contacts.1.name', 'Kma')->assertSet('contacts.1.main', false)
            ->assertSee('Filled from the report: the name, the city, the country, the industry, 2 contacts')->assertSee('from report');
        $this->assertContains('lead.name', $form->get('fromReport'));
        $this->assertNotContains('lead.phone', $form->get('fromReport'));

        // A field someone changes is theirs from then on; filling again adds no second Paul and never overwrites
        $form->set('lead.name', 'Jewel Company Ltd')->assertSet('fromReport', array_values(array_diff($form->get('fromReport'), ['lead.name'])))
            ->call('fillFromReport')->assertSet('lead.name', 'Jewel Company Ltd')->assertSet('contacts.2', null);

        $form->call('save')->assertHasNoErrors()->assertRedirect();
        $org = Organization::where('name', 'Jewel Company Ltd')->sole();
        $report = LeadIntelReport::where('organization_id', $org->id)->sole();
        $this->assertSame(['text', 'Findings', 'Paul'], [$report->input_kind, $report->title, $report->structured['decision_makers'][0]['name']]);
        $this->assertCount(2, DB::table('client_users')->where('organization_id', $org->id)->get());
        $this->assertSame(2, count($ai->requestsFor('lead_intel_prefill')));
        $ai->assertRan('lead_intel_prefill', fn ($r) => str_contains($r->prompt, 'INDUSTRIES:') && str_contains($r->prompt, $industry));
    }

    public function test_a_file_chosen_on_add_a_lead_is_stored_once_and_moved_to_the_new_lead(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        FakeAi::install()->respond('lead_intel_prefill', ['company_name' => '', 'country' => '', 'city' => '', 'industry' => '', 'phone' => '', 'email' => '', 'contacts' => []])
            ->respond('lead_intel_read', self::REPORT);

        $form = Livewire::test(AddLead::class)->call('choose', 'intel')
            ->set('intelFile', UploadedFile::fake()->create('macro.exe', 10))->assertHasErrors('intelFile')
            ->set('intelFile', UploadedFile::fake()->createWithContent('Findings.md', "# Findings\nNotion is their system of record."))->assertHasNoErrors()
            ->call('fillFromReport')->assertSee('The report had nothing new for the empty fields');
        $inbox = Storage::disk('local')->files('lead-intel/inbox');
        $this->assertCount(1, $inbox);

        $form->set('lead.name', 'Kilo Foods (test)')->set('lead.industry', (string) DB::table('mdm_copq_industries')->value('industry_name'))->set('lead.phone', '+234 800 000 0009')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $report = LeadIntelReport::whereHas('organization', fn ($q) => $q->where('name', 'Kilo Foods (test)'))->sole();
        $this->assertSame([], Storage::disk('local')->files('lead-intel/inbox'), 'The inbox copy moved to the lead');
        Storage::disk('local')->assertExists($report->path);
        $this->assertSame('Findings.md', $report->original_name);
    }

    public function test_the_pitch_brief_is_written_from_the_lead_and_its_intel(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $ai = FakeAi::install()->respond('lead_intel_read', self::REPORT)
            ->respond('lead_pitch_brief', ['talking_points' => ['Open with the turnover in content roles'], 'demo_ideas' => ['Show one dashboard for ads and content'],
                'questions_to_ask' => ['What do you spend on ads each month?'], 'avoid' => ['Do not mention the insider'], 'opening' => 'Thank you for making the time, Paul.']);
        $this->addReport($lead, $sales);

        $this->tab($lead)->assertSee('Write the pitch brief')->call('makeBrief')
            ->assertSee('Open with the turnover in content roles')->assertSee('How to open')->assertSee('Thank you for making the time, Paul.')->assertSee('Write a new brief');
        $ai->assertRan('lead_pitch_brief', fn ($r) => str_contains($r->prompt, 'Kilo Retail (test)') && str_contains($r->prompt, 'Notion') && str_contains($r->prompt, 'Ada Test'));
        $this->assertSame('done', LeadIntel::latestBrief($lead->id)->status);
    }

    public function test_the_consultation_line_uses_claude_and_the_intel_only_for_those_who_may_see_it(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $ai = FakeAi::install()->respond('lead_intel_read', self::REPORT)->respond('consultation_prep', ['summary' => 'Paul decides: open with staff turnover.']);
        $this->addReport($lead, $sales);

        // The free audit opens without waiting for the AI; the open terminal then asks for the line (wire:init)
        $page = Livewire::test(LiveConsultation::class)->call('enterTerminal', $lead->id, (string) Str::uuid())->assertSet('lineWaiting', true)
            ->assertSeeHtml('wire:init="loadConsultationLine"');
        $this->assertSame([], $ai->requestsFor('consultation_prep'));
        $page->call('loadConsultationLine')->assertSet('historySummary', 'Paul decides: open with staff turnover.')->assertSet('lineWaiting', false)
            ->assertSee('Paul decides: open with staff turnover.')->assertDontSeeHtml('wire:init="loadConsultationLine"');
        $ai->assertRan('consultation_prep', fn ($r) => str_contains($r->prompt, 'CONFIDENTIAL INTEL') && str_contains($r->prompt, 'Staff turnover'));
        $this->assertSame('Paul decides: open with staff turnover.', DB::table('organizations')->where('id', $lead->id)->value('ai_history_summary'));

        // The same notes and intel again cost nothing, and the line shows at once; someone who cannot edit leads gets a
        // line without the intel
        $before = count($ai->requestsFor('consultation_prep'));
        Livewire::test(LiveConsultation::class)->call('enterTerminal', $lead->id, (string) Str::uuid())
            ->assertSet('lineWaiting', false)->assertSet('historySummary', 'Paul decides: open with staff turnover.');
        $this->assertSame($before, count($ai->requestsFor('consultation_prep')));

        DB::table('organizations')->where('id', $lead->id)->update(['ai_history_summary' => null]);
        foreach (['First call went well.', 'They count stock on paper.', 'Asked for a demo.'] as $note) {
            $this->insertRow('crm_notes', ['id' => (string) Str::uuid(), 'organization_id' => $lead->id, 'author_id' => $sales->id, 'note_text' => $note, 'interaction_type' => 'Call']);
        }
        $this->actingAs($this->viewer());
        Livewire::test(LiveConsultation::class)->call('enterTerminal', $lead->id, (string) Str::uuid())->call('loadConsultationLine');
        $last = collect($ai->requestsFor('consultation_prep'))->last();
        $this->assertStringContainsString('They count stock on paper.', $last->prompt);
        $this->assertStringNotContainsString('CONFIDENTIAL INTEL', $last->prompt);
    }
}
