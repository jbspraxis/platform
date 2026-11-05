<?php

namespace Tests\Feature\CompanyProfile;

use App\Livewire\Hq\CompanyProfile\ProfileEditor;
use App\Models\CompanyProfile\ProfileSection;
use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\Editor;
use App\Support\CompanyProfile\Profile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Draft to review to published (docs/COMPANY_PROFILE.md section 3). The rule the whole module turns on:
 * publishing never changes a published version, it makes a new one.
 */
class ProfileWorkflowTest extends TestCase
{
    use ProfileFixtures, UsesThrowawayDatabase;

    public function test_a_draft_starts_as_a_copy_of_the_published_version(): void
    {
        $published = $this->published();
        $this->actingAs($this->person(['view', 'edit']));

        $draft = Profile::startDraft('Autumn refresh');

        $this->assertSame(2, $draft->number);
        $this->assertSame(ProfileVersion::DRAFT, $draft->status);
        $this->assertSame('Autumn refresh', $draft->note);
        $was = Profile::dataFor($published, 'en');
        $now = Profile::dataFor($draft, 'en');
        unset($was['meta'], $now['meta']);
        $this->assertSame($was, $now, 'a fresh draft says exactly what the published version says');
        $this->assertSame(ProfileVersion::PUBLISHED, $published->fresh()->status);
    }

    public function test_only_one_draft_is_open_at_a_time(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $first = Profile::startDraft();
        $second = Profile::startDraft();

        $this->assertTrue($first->is($second));
        $this->assertSame(2, ProfileVersion::count());
    }

    public function test_publishing_supersedes_the_old_version_and_leaves_it_readable(): void
    {
        $old = $this->published();
        $this->actingAs($this->person(['view', 'edit', 'approve']));

        $draft = Profile::startDraft();
        $this->rename($draft, 'A new headline');
        Profile::submit($draft);
        Profile::publish($draft);

        $this->assertSame(ProfileVersion::SUPERSEDED, $old->fresh()->status);
        $this->assertNotNull($old->fresh()->superseded_at);
        $this->assertSame(ProfileVersion::PUBLISHED, $draft->fresh()->status);
        $this->assertTrue(Profile::published()->is($draft));

        // The superseded version still reads exactly as it was sent
        $this->assertSame('A new headline', Profile::data('en', 2)['cover']['headline']);
        $this->assertNotSame('A new headline', Profile::data('en', 1)['cover']['headline']);
        $this->assertSame('v1', Profile::data('en', 1)['meta']['version']);
    }

    public function test_the_review_step_records_who_wrote_it_and_who_approved_it(): void
    {
        $author = $this->person(['view', 'edit']);
        $approver = $this->person(['view', 'approve']);

        $this->actingAs($author);
        $draft = Profile::startDraft();
        Profile::submit($draft, 'Ready for the Friday meeting');

        $this->assertSame(ProfileVersion::IN_REVIEW, $draft->fresh()->status);
        $this->assertSame($author->id, $draft->fresh()->submitted_by);

        $this->actingAs($approver);
        Profile::publish($draft);

        $draft = $draft->fresh();
        $this->assertSame($author->id, $draft->created_by);
        $this->assertSame($approver->id, $draft->reviewed_by);
        $this->assertNotNull($draft->published_at);
    }

    public function test_a_version_in_review_can_be_sent_back(): void
    {
        $this->actingAs($this->person(['view', 'edit', 'approve']));

        $draft = Profile::startDraft();
        Profile::submit($draft);
        Profile::returnToDraft($draft, 'The French cover line is wrong.');

        $this->assertSame(ProfileVersion::DRAFT, $draft->fresh()->status);
        $this->assertSame('The French cover line is wrong.', $draft->fresh()->note);
    }

    public function test_a_published_version_cannot_be_published_again_or_thrown_away(): void
    {
        $published = $this->published();

        $this->expectException(\RuntimeException::class);
        Profile::discard($published);
    }

    public function test_a_published_version_is_never_republished(): void
    {
        $published = $this->published();

        $this->expectException(\RuntimeException::class);
        Profile::publish($published);
    }

    public function test_throwing_a_draft_away_leaves_the_published_version_alone(): void
    {
        $published = $this->published();
        $this->actingAs($this->person(['view', 'edit', 'manage']));

        $draft = Profile::startDraft();
        $this->rename($draft, 'Never published');
        Profile::discard($draft);

        $this->assertSame(1, ProfileVersion::count());
        $this->assertSame(0, ProfileSection::where('version_id', $draft->id)->count());
        $this->assertTrue(Profile::published()->is($published));
    }

    public function test_the_editor_will_not_write_to_a_published_version(): void
    {
        $this->actingAs($this->person(['view', 'edit']));
        $published = $this->published();
        $before = Profile::dataFor($published, 'en')['cover']['headline'];

        Livewire::test(ProfileEditor::class)
            ->assertSet('versionId', $published->id)
            ->assertSet('editable', false)
            ->set('en.fields.headline', 'Sneaked in')
            ->call('save');

        $this->assertSame($before, Profile::dataFor($published->fresh(), 'en')['cover']['headline']);
    }

    public function test_the_editor_writes_both_languages_and_keeps_them_in_step(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $component = Livewire::test(ProfileEditor::class)
            ->call('startDraft')
            ->call('openSection', 'teams')
            ->call('addItem', 'items');

        $structure = $component->get('structure');
        $id = $structure['lists']['items'][count($structure['lists']['items']) - 1]['id'];

        $component->set("en.lists.items.{$id}.text.name", 'Design')
            ->set("fr.lists.items.{$id}.text.name", 'Conception')
            ->call('save');

        $draft = Profile::open();
        $en = Profile::dataFor($draft, 'en', forRender: false)['teams']['items'];
        $fr = Profile::dataFor($draft, 'fr', forRender: false)['teams']['items'];

        $this->assertCount(count($en), $fr);
        $this->assertSame('Design', end($en)['name']);
        $this->assertSame('Conception', end($fr)['name']);
        $this->assertSame(array_column($en, 'id'), array_column($fr, 'id'), 'both languages hold the same items');
    }

    public function test_removing_and_moving_an_item_moves_it_in_both_languages(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $component = Livewire::test(ProfileEditor::class)->call('startDraft')->call('openSection', 'facts');
        $items = $component->get('structure')['lists']['items'];
        $first = $items[0]['id'];

        $component->call('moveItem', 'items', $first, 1);
        $draft = Profile::open();
        $en = Profile::dataFor($draft, 'en', forRender: false)['facts']['items'];
        $fr = Profile::dataFor($draft, 'fr', forRender: false)['facts']['items'];
        $this->assertSame($first, $en[1]['id']);
        $this->assertSame(array_column($en, 'id'), array_column($fr, 'id'));

        $component->call('removeItem', 'items', $first);
        $draft = Profile::open();
        $en = Profile::dataFor($draft, 'en', forRender: false)['facts']['items'];
        $fr = Profile::dataFor($draft, 'fr', forRender: false)['facts']['items'];
        $this->assertNotContains($first, array_column($en, 'id'));
        $this->assertSame(array_column($en, 'id'), array_column($fr, 'id'));
    }

    public function test_a_switch_is_the_same_in_both_languages(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $component = Livewire::test(ProfileEditor::class)->call('startDraft')->call('openSection', 'facts');
        $items = $component->get('structure')['lists']['items'];
        $index = collect($items)->search(fn ($item) => $item['shared']['enabled'] === true);

        $component->set("structure.lists.items.{$index}.shared.enabled", false);

        $draft = Profile::open();
        $id = $items[$index]['id'];
        foreach (['en', 'fr'] as $locale) {
            $rendered = array_column(Profile::dataFor($draft, $locale, forRender: false)['facts']['items'], 'enabled', 'id');
            $this->assertFalse($rendered[$id], "the switch should be off in {$locale} too");
        }
    }

    public function test_one_click_ticks_a_whole_teams_credentials_in_both_languages(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $component = Livewire::test(ProfileEditor::class)->call('startDraft')->call('openSection', 'teams');
        $team = $component->get('structure')['lists']['items'][0];
        $path = 'items:' . $team['id'] . ':credentials';
        $this->assertGreaterThan(1, count($team['lists']['credentials']), 'the seed should carry several credentials');

        $component->call('setAll', $path, true);
        $draft = Profile::open();
        foreach (['en', 'fr'] as $locale) {
            $credentials = Profile::dataFor($draft, $locale)['teams']['items'][0]['credentials'];
            $this->assertCount(count($team['lists']['credentials']), $credentials, "all held in {$locale}");
        }

        $component->call('setAll', $path, false);
        $draft = Profile::open();
        foreach (['en', 'fr'] as $locale) {
            $this->assertSame([], Profile::dataFor($draft, $locale)['teams']['items'][0]['credentials']);
        }

        // Every other team is untouched
        $others = Profile::dataFor($draft, 'en', forRender: false)['teams']['items'];
        $this->assertNotSame([], array_slice($others, 1), 'there are other teams to leave alone');
        $this->assertGreaterThan(0, collect(array_slice($others, 1))->flatMap(fn ($t) => $t['credentials'])->count());
    }

    public function test_the_bulk_switch_does_nothing_to_a_list_with_no_switch(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $component = Livewire::test(ProfileEditor::class)->call('startDraft')->call('openSection', 'work');
        $before = $component->get('structure');

        $component->call('setAll', 'items', true);

        $this->assertSame($before, $component->get('structure'));
    }

    public function test_what_changed_since_the_published_version(): void
    {
        $this->actingAs($this->person(['view', 'edit']));

        $draft = Profile::startDraft();
        $this->assertSame([], Profile::changes($draft), 'a fresh copy has changed nothing');

        $this->rename($draft, 'Order, made visible, again');
        $changes = Profile::changes($draft->fresh());

        $english = collect($changes)->firstWhere('locale', 'en');
        $this->assertSame('cover', $english['section']);
        $this->assertSame('Order, made visible, again', $english['now']);
        $this->assertNotSame($english['was'], $english['now']);
    }

    public function test_every_step_of_the_workflow_reaches_the_audit_log(): void
    {
        $this->actingAs($this->person(['view', 'edit', 'approve']));

        $draft = Profile::startDraft();
        Profile::submit($draft);
        Profile::publish($draft);

        $actions = DB::table('system_audit_logs')->where('target_table', 'company_profile_versions')->pluck('action');

        foreach (['COMPANY_PROFILE_DRAFT_STARTED', 'COMPANY_PROFILE_SUBMITTED', 'COMPANY_PROFILE_PUBLISHED'] as $action) {
            $this->assertContains($action, $actions->all());
        }
    }

    /** Change the cover headline of a version, the way the editor would */
    private function rename(ProfileVersion $version, string $headline): void
    {
        $section = ProfileSection::where('version_id', $version->id)->where('key', 'cover')->firstOrFail();
        $structure = $section->structure;
        $en = $section->text_en;
        $fr = $section->text_fr;
        Editor::reconcile('cover', $structure, $en, $fr);
        $en['fields']['headline'] = $headline;
        $section->update(['structure' => $structure, 'text_en' => $en, 'text_fr' => $fr]);
    }
}
