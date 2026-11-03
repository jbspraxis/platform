<?php

namespace Tests\Feature\Leads;

use App\Livewire\Hq\Import\ImportDialog;
use App\Livewire\Hq\Intelligence\LeadsRegister;
use App\Models\ClientUser;
use App\Models\Organization;
use App\Support\Import\Importer;
use App\Support\Import\Xlsx\XlsxReader;
use App\Support\Import\Xlsx\XlsxWriter;
use App\Support\Leads\LeadImportSchema;
use App\Support\Leads\Leads;
use App\Support\Leads\NextStep;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Leads PR 3's Excel import (owner's decisions 5–7, docs/LEADS.md sections 6 and 9) through the dialog: the template,
 * the preview (Ready / Needs fixing / Already exists), the owner, source and next step chosen in the dialog, an Owner
 * email per row, new contacts added to leads already in HQ, the rows to fix, and a file imported twice.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadImportTest extends TestCase
{
    use UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** An .xlsx like a filled template: the Leads sheet with the schema's headers and these rows */
    private function workbook(array $rows): string
    {
        $headers = array_map(fn ($c) => $c->header(), (new LeadImportSchema())->columns());

        return (new XlsxWriter())->sheet('Leads', [$headers, ...$rows])->build();
    }

    private function upload(string $name, string $bytes)
    {
        return Livewire::test(ImportDialog::class, ['schema' => 'leads'])->call('show')
            ->set('file', UploadedFile::fake()->createWithContent($name, $bytes));
    }

    private function row(string $org, array $values = []): array
    {
        $keys = array_map(fn ($c) => $c->key, (new LeadImportSchema())->columns());

        return array_values(array_replace(array_fill_keys($keys, ''), ['organisation' => $org], $values));
    }

    public function test_the_template(): void
    {
        $sales = $this->staff('gerald', ['sales']);
        $this->actingAs($sales);
        Livewire::test(ImportDialog::class, ['schema' => 'leads'])->call('show')->assertSee('Download the template')
            ->call('downloadTemplate')->assertFileDownloaded('JBS Praxis import leads template.xlsx');

        file_put_contents($path = tempnam(sys_get_temp_dir(), 'tpl'), Importer::template(new LeadImportSchema()));
        $sheets = XlsxReader::read($path, 100);
        unlink($path);
        $this->assertSame(['How to fill it in', 'Leads'], array_column($sheets, 'name'));
        $this->assertSame(['Organisation name *', 'Country *', 'Industry *', 'Organisation phone +', 'Organisation email +', 'City', 'Address', 'Contact name',
            'Job title', 'Contact email +', 'Contact phone +', 'Deal role', 'Owner email'], $sheets[1]['rows'][1]);
        $guide = collect($sheets[0]['rows'])->map(fn ($r) => implode(' | ', $r))->implode("\n");
        $this->assertStringContainsString('one row per contact. Rows with the same organisation name become one lead', $guide);
        $this->assertStringContainsString('Organisation name * | Required |', $guide);
        $this->assertStringContainsString('Contact email + | A phone or an email: at least one of the four columns marked +', $guide);
        $this->assertStringContainsString('NG or +234: write the country’s name', $guide); // a bad example
        $this->assertStringContainsString('Logistics & Supply Chain', $guide); // the industries from Master data
        $this->assertStringContainsString('Decision maker', $guide);
        $this->assertStringContainsString('Kilo Logistics Ltd', $guide); // the examples stay on the guide
    }

    public function test_importing_a_file_with_good_bad_and_existing_rows(): void
    {
        $sales = $this->staff('gerald', ['sales']);
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($sales);
        $delta = Leads::create(['name' => 'Delta Oil (test)', 'country' => 'Nigeria', 'industry' => 'Energy & Oil/Gas', 'phone' => '+234 800 000 0002'],
            [['name' => 'Ada Delta', 'email' => 'ada@delta.test']], $sales);
        Leads::create(['name' => 'Echo Retail (test)', 'country' => 'Ghana', 'industry' => 'Luxury Retail & Textiles'], [['name' => 'Kofi Echo', 'email' => 'kofi@echo.test']], $sales);

        $bytes = $this->workbook([
            /* 2 */ $this->row('Mike Haulage Ltd (test)', ['country' => 'Nigeria', 'industry' => 'Logistics & Supply Chain', 'org_phone' => '+234 1 555 0200', 'city' => 'Lagos',
                'contact_name' => 'Ngozi Test', 'job_title' => 'CEO', 'contact_email' => 'Ngozi@Mike.test', 'contact_phone' => '+234 803 000 1111', 'deal_role' => 'Decision maker']),
            /* 3 */ $this->row('MIKE HAULAGE LTD (test)', ['contact_name' => 'Femi Test', 'job_title' => 'CFO', 'contact_email' => 'femi@mike.test', 'deal_role' => 'finance']),
            /* 4 */ $this->row('November Foods (test)', ['country' => 'cameroon', 'industry' => 'Agriculture & Agritech', 'org_email' => 'hello@november.test']),
            /* 5 */ $this->row('Delta Oil Ltd (test)', ['contact_name' => 'Chidi Delta', 'job_title' => 'Engineer', 'contact_email' => 'chidi@delta.test', 'deal_role' => 'Technical']),
            /* 6 */ $this->row('Delta Oil (test)', ['contact_name' => 'Ada Delta', 'contact_email' => 'ada@delta.test']),
            /* 7 */ $this->row('Oscar Bank (test)', ['country' => 'Ghana', 'industry' => 'Finance & Banking', 'contact_name' => 'Kwame Test', 'deal_role' => 'Boss', 'owner_email' => 'nobody@nowhere.test']),
            /* 8 */ $this->row('Papa Logistics (test)', ['industry' => 'Logistics & Supply Chain', 'org_phone' => '+234 1 555 0300']),
            /* 9 */ $this->row('Quebec Partners (test)', ['country' => 'Nigeria', 'industry' => 'Professional Services', 'contact_name' => 'Kofi Again', 'contact_email' => 'kofi@echo.test']),
            /* 10 */ $this->row('Romeo Agency (test)', ['country' => '+234', 'industry' => 'Professional Services', 'org_phone' => '+234 1 555 0400', 'contact_name' => 'Rita Test',
                'contact_email' => 'rita@romeo.test', 'owner_email' => strtoupper($owner->email)]),
            /* 11 */ $this->row('Sierra Group (test)', ['country' => 'Nigeria', 'industry' => 'Telecommunications', 'contact_name' => 'Sam Test', 'contact_email' => 'sam@sierra.test']),
            /* 12 */ $this->row('Sierra Group (test)', ['contact_name' => 'Sade Test', 'contact_email' => 'sam@sierra.test']),
        ]);

        // The preview: nothing written yet
        $screen = $this->upload('leads.xlsx', $bytes)->assertHasNoErrors()->assertSet('fileName', 'leads.xlsx')->assertSet('filter', 'fix')
            ->assertSee('leads.xlsx')->assertSee('Needs fixing')->assertSee('Choose the country.')->assertSeeText('Import 4 leads and 1 contact');
        $analysis = Importer::recall($screen->get('token'), $sales);
        $this->assertSame([2 => 'ready', 3 => 'ready', 4 => 'ready', 5 => 'add', 6 => 'exists', 7 => 'fix', 8 => 'fix', 9 => 'fix', 10 => 'ready', 11 => 'ready', 12 => 'fix'],
            array_map(fn ($r) => $r['status'], $analysis->rows));
        $this->assertSame(['deal_role', 'org_phone', 'org_email', 'contact_email', 'contact_phone', 'owner_email'], $analysis->rows[7]['red']);
        $this->assertContains(Leads::UNREACHABLE, $analysis->rows[7]['reasons']);
        $this->assertContains('Choose a role from the list.', $analysis->rows[7]['reasons']);
        $this->assertSame(['country'], $analysis->rows[8]['red']);
        $this->assertSame(['This email is already a contact of Echo Retail (test). Open that lead, or use another address.'], $analysis->rows[9]['reasons']);
        $this->assertSame(['Row 11 has the same email. Each person needs their own.'], $analysis->rows[12]['reasons']);
        $this->assertSame('Delta Oil (test)', $analysis->rows[5]['note']);
        $this->assertSame(route('hq.crm.lead', ['organization' => $delta->id]), $analysis->rows[5]['url']);
        $this->assertSame(0, Organization::where('name', 'like', 'Mike Haulage%')->count());
        // The dialog's choices start from the person importing, the file's name and a first next step
        $screen->assertSet('options.owner_id', $sales->id)->assertSet('options.source', 'import')->assertSet('options.source_detail', 'leads.xlsx')
            ->assertSet('options.next_step', 'Make first contact')->assertSet('options.next_step_due', NextStep::suggestion()['due']);

        $screen->set('options.source', 'event')->set('options.source_detail', 'Lagos trade fair list (test)')->call('import')
            ->assertHasNoErrors()->assertSet('done', true)->assertSee('4 leads added.')->assertSee('1 contact added to 1 lead already in HQ.')
            ->assertSee('4 rows weren’t imported.');

        $mike = Organization::where('name', 'Mike Haulage Ltd (test)')->sole();
        $this->assertSame(['new', 'Lead', $sales->id, 'event', 'Lagos trade fair list (test)', 'Make first contact', 'Nigeria', 'Lagos', '+234 1 555 0200'],
            [$mike->lead_status, $mike->status, $mike->assigned_to, $mike->source, $mike->source_detail, $mike->next_step, $mike->country, $mike->city, $mike->phone]);
        $this->assertSame(NextStep::suggestion()['due'], $mike->next_step_due->format('Y-m-d'));
        $contacts = ClientUser::where('organization_id', $mike->id)->orderBy('created_at')->get();
        $this->assertSame(['ngozi@mike.test', 'femi@mike.test'], $contacts->pluck('email')->all());
        $this->assertSame([true, false], $contacts->map(fn ($c) => (bool) $c->is_main_contact)->all());
        $this->assertSame(['decision_maker', 'finance'], $contacts->pluck('deal_role')->all());
        $this->assertSame(['import', 'import'], $contacts->pluck('source')->all());
        $hash = sha1($bytes);
        $this->assertSame('created', DB::table('lead_timeline')->where('dedupe_key', "import:{$hash}:2")->where('organization_id', $mike->id)->value('type'));

        $november = Organization::where('name', 'November Foods (test)')->sole();
        $this->assertSame(['Cameroon', 'FR', 'hello@november.test', 0], [$november->country, $november->preferred_language, $november->email, $november->contacts()->count()]);

        // A row's Owner email overrides the dialog's owner, who gets a bell; a dialling code reads as its country
        $romeo = Organization::where('name', 'Romeo Agency (test)')->sole();
        $this->assertSame([$owner->id, 'Nigeria'], [$romeo->assigned_to, $romeo->country]);
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'A lead was imported for you',
            'link_url' => route('hq.crm.lead', ['organization' => $romeo->id], false)]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $sales->id, 'title' => 'A lead was imported for you']);

        // An existing lead is left as it is; its new contact is added, with one imported line
        $this->assertSame(['ada@delta.test', 'chidi@delta.test'], ClientUser::where('organization_id', $delta->id)->orderBy('created_at')->pluck('email')->all());
        $this->assertSame('Imported from leads.xlsx: 1 new contact, Chidi Delta.', DB::table('lead_timeline')->where('organization_id', $delta->id)->where('type', 'imported')->value('summary'));
        $this->assertSame('Energy & Oil/Gas', $delta->fresh()->industry);
        // A lead with one bad row still comes in with its good ones
        $sierra = Organization::where('name', 'Sierra Group (test)')->sole();
        $this->assertSame(['sam@sierra.test'], $sierra->contacts()->pluck('email')->all());
        $this->assertSame(0, Organization::whereIn('name', ['Oscar Bank (test)', 'Papa Logistics (test)', 'Quebec Partners (test)'])->count());

        // The rows to fix come back as an Excel file
        $screen->call('downloadFixes')->assertFileDownloaded('leads (rows to fix).xlsx');
        $fix = Importer::fixFile(new LeadImportSchema(), Importer::recall($screen->get('token'), $sales));
        file_put_contents($path = tempnam(sys_get_temp_dir(), 'fix'), $fix);
        $rows = XlsxReader::read($path, 100)[1]['rows'];
        unlink($path);
        $this->assertSame(['Oscar Bank (test)', 'Papa Logistics (test)', 'Quebec Partners (test)', 'Sierra Group (test)'], array_values(array_map(fn ($r) => $r[0], array_slice($rows, 1))));
        $this->assertStringStartsWith('Row 12: Row 11 has the same email.', end($rows)[13]);
        $screen->call('close')->assertDispatched('import-finished', schema: 'leads');

        // The same file again: nothing comes in twice
        $count = Organization::count();
        $again = $this->upload('leads.xlsx', $bytes)->assertSee('Nothing to import yet');
        $this->assertSame(0, Importer::recall($again->get('token'), $sales)->counts()['ready']);
        $again->call('import');
        $this->assertSame($count, Organization::count());
        $this->assertSame(2, ClientUser::where('organization_id', $delta->id)->count());

        // The fixed row, uploaded again, adds its contact to the lead its group made
        $this->upload('leads (rows to fix).xlsx', $this->workbook([$this->row('Sierra Group (test)', ['contact_name' => 'Sade Test', 'contact_email' => 'sade@sierra.test'])]))
            ->assertSee('Add 1 contact')->call('import')->assertSee('1 contact added to 1 lead already in HQ.');
        $this->assertSame(['sam@sierra.test', 'sade@sierra.test'], $sierra->contacts()->orderBy('created_at')->pluck('email')->all());
    }

    public function test_who_may_import_and_what_the_dialog_checks(): void
    {
        $sales = $this->staff('gerald', ['sales']);
        $delivery = $this->staff('delivery', ['delivery']);

        // Leads access without adding (crm.view): no import
        $this->actingAs($delivery);
        Livewire::test(LeadsRegister::class)->assertDontSee('Import leads');
        Livewire::test(ImportDialog::class, ['schema' => 'leads'])->assertForbidden();
        $this->actingAs($sales);
        Livewire::test(LeadsRegister::class)->assertSee('Import leads');
        Livewire::test(ImportDialog::class, ['schema' => 'nothing'])->assertNotFound();

        // Files the dialog refuses, with what to do
        $this->upload('leads.pdf', '%PDF-1.4')->assertHasErrors(['file' => 'extensions']);
        $this->upload('leads.csv', "Name;Phone\nAda;0803\n")->assertSee('This file has no ‘Organisation name’, ‘Country’, ‘Industry’ columns.')->assertSet('token', null);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));

        // The choices
        $file = $this->workbook([$this->row('Tango Ltd (test)', ['country' => 'Nigeria', 'industry' => 'Telecommunications', 'org_phone' => '+234 1 555 0500'])]);
        $this->upload('one.xlsx', $file)->set('options.next_step', 'Call them')->set('options.next_step_due', '')->call('import')
            ->assertHasErrors('options.next_step_due')->assertSet('done', false)
            ->set('options.next_step_due', '2026-02-30')->call('import')->assertHasErrors('options.next_step_due')
            ->set('options.next_step_due', '2026-12-01')->set('options.owner_id', $delivery->id . 'x')->call('import')->assertHasErrors('options.owner_id')
            ->set('options.owner_id', $sales->id)->set('options.source', 'website_enquiry')->call('import')->assertHasErrors('options.source')
            // No owner: anyone can take the lead; no next step: it is flagged in My follow-ups
            ->set('options.source', 'referral')->set('options.owner_id', '')->set('options.next_step', '')->call('import')->assertHasNoErrors()->assertSet('done', true);
        $tango = Organization::where('name', 'Tango Ltd (test)')->sole();
        $this->assertSame([null, 'referral', null, 'one.xlsx'], [$tango->assigned_to, $tango->source, $tango->next_step, $tango->source_detail]);
    }
}
