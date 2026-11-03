<?php

namespace Tests\Feature\Campaigns;

use App\Livewire\Hq\Import\ImportDialog;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Outreach\ProspectImportSchema;
use App\Support\Import\Importer;
use App\Support\Import\Xlsx\XlsxReader;
use App\Support\Import\Xlsx\XlsxWriter;
use App\Support\Leads\Leads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Importing prospects through the shared import engine (docs/CAMPAIGNS.md section 10; owner's decisions 5 and 6,
 * 3 Nov 2026): the template with its guide, the required columns and the "What we found or website" rule, the language
 * from the country, "Already exists" for prospects, leads' and clients' contacts and the do-not-email list, the two
 * emails waiting for Claude, and who may import.
 */
class OutreachImportTest extends TestCase
{
    use OutreachFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOutreach();
        Storage::fake('local');
    }

    private function workbook(array $rows): string
    {
        $headers = array_map(fn ($c) => $c->header(), (new ProspectImportSchema())->columns());

        return (new XlsxWriter())->sheet('Prospects', [$headers, ...$rows])->build();
    }

    private function row(array $values): array
    {
        $keys = array_map(fn ($c) => $c->key, (new ProspectImportSchema())->columns());

        return array_values(array_replace(array_fill_keys($keys, ''), [
            'company' => 'Mike Haulage Ltd (test)', 'first_name' => 'Ngozi', 'email' => 'ngozi@mike.test', 'country' => 'Nigeria',
            'industry' => 'Logistics & Supply Chain', 'findings' => 'Won the Lagos port contract in October.',
        ], $values));
    }

    public function test_the_template_and_its_guide(): void
    {
        $gerald = $this->salesperson();
        $this->outreachCampaign($gerald);
        $this->actingAs($gerald);
        Livewire::test(ImportDialog::class, ['schema' => 'prospects'])->call('show')->assertSee('Import prospects')
            ->call('downloadTemplate')->assertFileDownloaded('JBS Praxis import prospects template.xlsx');

        file_put_contents($path = tempnam(sys_get_temp_dir(), 'tpl'), Importer::template(new ProspectImportSchema()));
        $sheets = XlsxReader::read($path, 100);
        unlink($path);
        $this->assertSame(['How to fill it in', 'Prospects'], array_column($sheets, 'name'));
        $this->assertSame(['Company *', 'Contact first name *', 'Contact last name', 'Email *', 'Job title', 'Country *', 'Industry *', 'What we found +',
            'Company website +', 'Language'], $sheets[1]['rows'][1]);
        $guide = collect($sheets[0]['rows'])->map(fn ($r) => implode(' | ', $r))->implode("\n");
        $this->assertStringContainsString('What we found + | At least one of What we found or Company website |', $guide);
        $this->assertStringContainsString('Not like this: “Good prospect”', $guide); // a bad example
        $this->assertStringContainsString('Good prospect, big company: say what you found', $guide);
        $this->assertStringContainsString('Opened a second warehouse in Onne in September', $guide); // a good one
        $this->assertStringContainsString('Language | Optional', $guide);
        $this->assertStringContainsString('French', $guide);
    }

    public function test_importing_good_bad_and_existing_rows_into_a_campaign(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald, ['name' => 'Lagos logistics (test)']);
        $other = $this->outreachCampaign($gerald, ['name' => 'Douala banks (test)']);
        $known = $this->prospect($other, 'draft', ['email' => 'known@kilo.test']);
        Leads::create(['name' => 'Delta Oil (test)', 'country' => 'Nigeria', 'industry' => 'Energy & Oil/Gas', 'phone' => '+234 800 000 0002'],
            [['name' => 'Ada Delta', 'email' => 'ada@delta.test']], $gerald);
        Marketing::suppress('gone@echo.test', 'unsubscribed', ['source' => 'broadcast']);
        $this->actingAs($gerald);
        // The campaign page sets the dialog's default campaign
        session([ProspectImportSchema::SESSION_KEY => $campaign->id]);

        $bytes = $this->workbook([
            /* 2 */ $this->row(['last_name' => 'Okafor', 'job_title' => 'CEO', 'email' => 'Ngozi@Mike.test', 'website' => 'mike.test']),
            /* 3 */ $this->row(['company' => 'Lima Freight SARL (test)', 'first_name' => 'Brice', 'email' => 'brice@lima.test', 'country' => 'Cameroon', 'findings' => '', 'website' => 'https://www.lima.test']),
            /* 4 */ $this->row(['company' => 'November Foods (test)', 'email' => 'n@november.test', 'findings' => '', 'website' => '']),
            /* 5 */ $this->row(['company' => 'Oscar Bank (test)', 'email' => 'oscar at bank', 'country' => '']),
            /* 6 */ $this->row(['company' => 'Papa Ltd (test)', 'email' => 'ngozi@mike.test']),
            /* 7 */ $this->row(['company' => 'Quebec (test)', 'email' => 'known@kilo.test']),
            /* 8 */ $this->row(['company' => 'Somewhere Else (test)', 'email' => 'ada@delta.test']),
            /* 9 */ $this->row(['company' => 'Delta Oil Ltd (test)', 'email' => 'new.person@delta.test']),
            /* 10 */ $this->row(['company' => 'Echo (test)', 'email' => 'gone@echo.test']),
            /* 11 */ $this->row(['company' => 'Romeo SA (test)', 'email' => 'r@romeo.test', 'country' => 'Ghana', 'language' => 'Français']),
            /* 12 */ $this->row(['company' => 'Sierra (test)', 'email' => 's@sierra.test', 'language' => 'German', 'website' => 'ftp://sierra.test']),
        ]);

        $screen = Livewire::test(ImportDialog::class, ['schema' => 'prospects'])->call('show')
            ->set('file', UploadedFile::fake()->createWithContent('prospects.xlsx', $bytes))->assertHasNoErrors()
            ->assertSet('options.campaign_id', $campaign->id)->assertSee('Needs fixing');
        $analysis = Importer::recall($screen->get('token'), $gerald);
        $this->assertSame([2 => 'ready', 3 => 'ready', 4 => 'fix', 5 => 'fix', 6 => 'fix', 7 => 'exists', 8 => 'exists', 9 => 'exists', 10 => 'exists', 11 => 'ready', 12 => 'fix'],
            array_map(fn ($r) => $r['status'], $analysis->rows));
        $this->assertSame(['findings', 'website'], $analysis->rows[4]['red']);
        $this->assertSame(['country', 'email'], $analysis->rows[5]['red']);
        $this->assertSame(['Row 2 has the same email. Each prospect needs their own.'], $analysis->rows[6]['reasons']);
        $this->assertSame('Already a prospect in ‘Douala banks (test)’', $analysis->rows[7]['note']);
        $this->assertStringStartsWith('A lead in HQ: Delta Oil (test)', $analysis->rows[8]['note']); // a lead's contact
        $this->assertStringStartsWith('A lead in HQ: Delta Oil (test)', $analysis->rows[9]['note']); // the same company
        $this->assertSame('On the do-not-email list (unsubscribed)', $analysis->rows[10]['note']);
        $this->assertSame(['website', 'language'], $analysis->rows[12]['red']);
        $this->assertSame(0, OutreachProspect::where('campaign_id', $campaign->id)->count(), 'The preview writes nothing');

        $screen->call('import')->assertHasNoErrors()->assertSet('done', true)->assertSee('3 prospects added.');
        $prospects = OutreachProspect::where('campaign_id', $campaign->id)->orderBy('id')->get();
        $this->assertSame(['ngozi@mike.test', 'brice@lima.test', 'r@romeo.test'], $prospects->pluck('email')->all());
        // The language follows the country unless the row says otherwise (Cameroon → French; "Français" read as French)
        $this->assertSame(['en', 'fr', 'fr'], $prospects->pluck('language')->all());
        $this->assertSame(['https://mike.test', 'https://www.lima.test', null], $prospects->pluck('website')->all());
        $this->assertSame(['Okafor', 'CEO'], [$prospects[0]->last_name, $prospects[0]->job_title]);
        $this->assertSame($gerald->id, $prospects[0]->imported_by);
        // Each gets its first email and follow-up, waiting for Claude, each with its own unsubscribe token
        $emails = OutreachEmail::whereIn('prospect_id', $prospects->pluck('id'))->get();
        $this->assertCount(6, $emails);
        $this->assertSame(['writing'], $emails->pluck('status')->unique()->values()->all());
        $this->assertCount(6, $emails->pluck('token')->unique());
        $this->assertSame('draft', $this->firstOf($known)->status, 'The prospect already in another campaign is left alone');

        // The same file again adds nothing
        Livewire::test(ImportDialog::class, ['schema' => 'prospects'])->call('show')
            ->set('file', UploadedFile::fake()->createWithContent('prospects.xlsx', $bytes))->call('import')->assertSee('No new prospects added');
        $this->assertSame(3, OutreachProspect::where('campaign_id', $campaign->id)->count());
    }

    public function test_who_may_import_and_into_which_campaign(): void
    {
        $gerald = $this->salesperson();
        $mine = $this->outreachCampaign($gerald);
        $nina = $this->salesperson('nina');
        $hers = $this->outreachCampaign($nina);

        // Gerald's choices are his own campaigns; a campaign id of someone else's is refused
        $this->actingAs($gerald);
        $schema = new ProspectImportSchema();
        $this->assertSame([$mine->id], array_keys($schema->options($gerald, 'x.xlsx')[0]->choices));
        $this->assertSame(['campaign_id' => 'Choose one of your campaigns.'], $schema->checkOptions(['campaign_id' => $hers->id], $gerald));
        // The owner may import into any
        $owner = $this->staff('owner', ['owner']);
        $this->assertSame([], $schema->checkOptions(['campaign_id' => $hers->id], $owner));

        // An auditor reads Outreach but can't import
        $this->actingAs($this->staff('audrey', ['auditor']));
        Livewire::test(ImportDialog::class, ['schema' => 'prospects'])->assertForbidden();
    }
}
