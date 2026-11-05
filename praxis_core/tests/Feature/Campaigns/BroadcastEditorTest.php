<?php

namespace Tests\Feature\Campaigns;

use App\Livewire\Hq\Campaigns\Audiences;
use App\Livewire\Hq\Campaigns\BroadcastEditor;
use App\Livewire\Hq\Campaigns\BroadcastList;
use App\Mail\BroadcastEmail;
use App\Models\Campaigns\Broadcast;
use App\Support\Ai\AiRequest;
use App\Support\Campaigns\Blocks;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Marketing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * HQ › Campaigns › Broadcasts (docs/CAMPAIGNS.md 4.4 and 4.6): the area and its permissions, the editor (layouts,
 * blocks, rich text, AI draft and translation), the test send, and Send now / Schedule, in both languages.
 */
class BroadcastEditorTest extends TestCase
{
    use CampaignFixtures;

    private function sales()
    {
        return $this->staff('sales', ['sales']);
    }

    /** A draft ready to go: both versions written, a segment of one English and one French lead */
    private function readyDraft($by): Broadcast
    {
        $broadcast = Broadcasts::create($by, 'announcement');
        $broadcast->forceFill([
            'content' => [
                'en' => ['subject' => 'We have opened in Douala', 'blocks' => [['type' => 'text', 'html' => '<p>Read <a href="https://jbspraxis.com/work">our work</a>.</p>'], Blocks::blank('button', 'en')]],
                'fr' => ['subject' => 'Nous ouvrons à Douala', 'blocks' => [['type' => 'text', 'html' => '<p>Lisez <a href="https://jbspraxis.com/fr/work">nos travaux</a>.</p>'], Blocks::blank('button', 'fr')]],
            ],
            'segment_id' => $this->segment(['who' => ['leads']])->id,
        ])->save();
        $this->contact($this->org(['country' => 'Nigeria']), ['email' => 'en.lead@example.test', 'first_name' => 'Ada']);
        $this->contact($this->org(['country' => 'Cameroon', 'preferred_language' => null]), ['email' => 'fr.lead@example.test', 'first_name' => 'Brice']);

        return $broadcast;
    }

    public function test_campaigns_is_its_own_area_and_the_old_broadcasts_address_redirects(): void
    {
        $this->actingAs($this->sales());

        $this->get($this->hqUrl('/intelligence/broadcast-ledger'))->assertRedirect('/campaigns/broadcasts');
        $this->get($this->hqUrl('/campaigns'))->assertRedirect('/campaigns/broadcasts');
        $this->get($this->hqUrl('/campaigns/broadcasts'))->assertOk()
            ->assertSee('Broadcasts')->assertSee('No broadcasts yet')
            ->assertSee(route('hq.campaigns.audiences'), false)
            ->assertSeeHtml('<i data-lucide="send"></i> Campaigns');
        $this->get($this->hqUrl('/campaigns/audiences'))->assertOk()->assertSee('Do not email');

        // No Campaigns without broadcasts.view
        $this->actingAs($this->staffWith(['crm.view']));
        $this->get($this->hqUrl('/campaigns/broadcasts'))->assertForbidden();
    }

    public function test_someone_who_may_only_view_reads_drafts_but_cannot_write_or_send(): void
    {
        $draft = Broadcasts::create($this->sales(), 'letter');
        $this->actingAs($this->staffWith(['broadcasts.view']));

        Livewire::test(BroadcastList::class)->assertDontSee('New broadcast')->call('create', 'letter')->assertForbidden();
        Livewire::test(BroadcastEditor::class, ['broadcast' => $draft->id])
            ->assertSet('canEdit', false)->assertSet('canSend', false)
            ->assertSee('You can read this draft')
            ->call('addBlock', 'heading')
            ->call('sendNow')->assertForbidden();
        $this->assertCount(1, $draft->fresh()->content['en']['blocks']);
    }

    public function test_a_new_broadcast_starts_from_its_layout_in_english_and_french(): void
    {
        $this->actingAs($this->sales());
        Livewire::test(BroadcastList::class)->call('create', 'event')->assertRedirect();

        $broadcast = Broadcast::sole();
        $this->assertSame('event', $broadcast->layout);
        $this->assertSame('both', $broadcast->language);
        $this->assertSame(['text', 'details', 'button'], array_column($broadcast->content['en']['blocks'], 'type'));
        $this->assertSame('Lieu', $broadcast->content['fr']['blocks'][1]['rows'][2]['label']);
    }

    public function test_blocks_are_added_moved_removed_and_text_is_cleaned_as_it_is_saved(): void
    {
        $this->actingAs($this->sales());
        $broadcast = Broadcasts::create(auth()->user(), 'letter');
        $editor = Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id]);
        $textId = $editor->get('content')['en']['blocks'][0]['id'];

        $editor->call('setText', 'en', $textId, '<p onclick="x()">Hello <b>all</b><script>alert(1)</script> <a href="javascript:x()">no</a></p>')
            ->set('content.en.subject', 'A note  ')
            ->call('addBlock', 'heading')
            ->call('moveBlock', 1, -1)
            ->call('addBlock', 'divider')
            ->call('removeBlock', 2);

        $stored = $broadcast->fresh()->content['en'];
        $this->assertSame('A note', $stored['subject']);
        $this->assertSame(['heading', 'text'], array_column($stored['blocks'], 'type'));
        $this->assertSame('<p>Hello <strong>all</strong> no</p>', $stored['blocks'][1]['html']);
        $this->assertSame('A note  ', $editor->get('content')['en']['subject'], 'The field keeps what was typed');

        // A new layout replaces the blocks only of a version nobody has written yet
        $editor->call('setLayout', 'case_study');
        $this->assertSame(['heading', 'text'], array_column($broadcast->fresh()->content['en']['blocks'], 'type'));
        $this->assertSame(['heading', 'text', 'figures', 'quote', 'button'], array_column($broadcast->fresh()->content['fr']['blocks'], 'type'));
        $this->assertSame('case_study', $broadcast->fresh()->layout);
    }

    public function test_ai_drafts_a_version_from_the_brief_in_that_language(): void
    {
        $ai = FakeAi::install()->respond('broadcast_draft', [
            'subject' => 'Petit-déjeuner sur la trésorerie, le 20 novembre',
            'preheader' => 'Une heure pour voir sa trésorerie chaque matin.',
            'headline' => '',
            'blocks' => [
                ['type' => 'text', 'text' => "Nous vous invitons à un **petit-déjeuner**.\n\n- Trois cas\n- Une heure", 'source' => '', 'label' => '', 'items' => []],
                ['type' => 'details', 'text' => '', 'source' => '', 'label' => '', 'items' => [['value' => '20 novembre', 'label' => 'Date']]],
                ['type' => 'button', 'text' => '', 'source' => '', 'label' => 'Réserver votre place', 'items' => []],
            ],
        ]);
        $this->actingAs($this->sales());
        $broadcast = Broadcasts::create(auth()->user(), 'event');

        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])
            ->set('tab', 'fr')
            ->set('brief', 'short')->call('writeWithAi')->assertHasErrors('brief')
            ->set('brief', 'Breakfast for finance directors in Douala on 20 November about cash visibility.')
            ->call('writeWithAi')->assertHasNoErrors()
            ->assertDispatched('praxis-toast', $this->toast('Draft written'));

        $ai->assertRan('broadcast_draft', fn (AiRequest $r) => str_contains($r->prompt, 'Breakfast for finance directors') && str_contains($r->prompt, 'French') && str_contains($r->prompt, 'Event invite'));
        $fr = $broadcast->fresh()->content['fr'];
        $this->assertSame('Petit-déjeuner sur la trésorerie, le 20 novembre', $fr['subject']);
        $this->assertSame(['text', 'details', 'button'], array_column($fr['blocks'], 'type'));
        $this->assertSame('<p>Nous vous invitons à un <strong>petit-déjeuner</strong>.</p><ul><li>Trois cas</li><li>Une heure</li></ul>', $fr['blocks'][0]['html']);
        $this->assertSame(Blocks::scheduleUrl(), $fr['blocks'][2]['url'], 'The AI never sets an address');
        $this->assertSame('', $broadcast->fresh()->content['en']['subject'], 'The other version is untouched');
    }

    public function test_ai_translates_one_version_into_the_other_keeping_pictures_and_links(): void
    {
        $ai = FakeAi::install()->respond('broadcast_translate', [
            'subject' => 'Nous ouvrons à Douala', 'preheader' => '', 'headline' => '',
            'blocks' => [
                ['index' => 0, 'text' => '<p>Lisez <a href="https://jbspraxis.com/work">nos travaux</a>.</p>', 'source' => '', 'label' => '', 'items' => []],
                ['index' => 1, 'text' => 'Notre équipe à Douala', 'source' => '', 'label' => '', 'items' => []],
            ],
        ]);
        $this->actingAs($this->sales());
        $broadcast = Broadcasts::create(auth()->user(), 'letter');
        $file = (string) \Illuminate\Support\Str::uuid() . '.png';
        @mkdir(storage_path('app/campaign-images'), 0775, true);
        file_put_contents(storage_path('app/campaign-images/' . $file), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        $picture = route('email.image', ['file' => $file]);
        $broadcast->forceFill(['content' => ['en' => ['subject' => 'We open in Douala', 'blocks' => [
            ['type' => 'text', 'html' => '<p>Read <a href="https://jbspraxis.com/work">our work</a>.</p>'],
            ['type' => 'image', 'src' => $picture, 'alt' => 'Our team in Douala', 'link' => ''],
        ]], 'fr' => []]])->save();

        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->call('translateFrom', 'en')->assertSet('tab', 'fr');

        $ai->assertRan('broadcast_translate', fn (AiRequest $r) => str_contains($r->prompt, 'Our team in Douala') && str_contains($r->prompt, 'French'));
        $fr = $broadcast->fresh()->content['fr'];
        $this->assertSame('Nous ouvrons à Douala', $fr['subject']);
        $this->assertSame('<p>Lisez <a href="https://jbspraxis.com/work">nos travaux</a>.</p>', $fr['blocks'][0]['html']);
        $this->assertSame($picture, $fr['blocks'][1]['src']);
        $this->assertSame('Notre équipe à Douala', $fr['blocks'][1]['alt']);
        @unlink(storage_path('app/campaign-images/' . $file));
    }

    public function test_a_test_send_goes_to_me_in_each_language_with_nothing_counted(): void
    {
        Mail::fake();
        $me = $this->sales();
        $this->actingAs($me);
        $broadcast = $this->readyDraft($me);

        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'send')
            ->assertSee('Send a test to me')->call('sendTest')->assertDispatched('praxis-toast', $this->toast('The English and French versions are on the way'));

        Mail::assertSent(BroadcastEmail::class, 2);
        foreach (['en' => 'Test: We have opened in Douala', 'fr' => "Test\u{00A0}: Nous ouvrons à Douala"] as $locale => $subject) {
            Mail::assertSent(BroadcastEmail::class, function (BroadcastEmail $mail) use ($me, $locale, $subject) {
                if ($mail->locale !== $locale) {
                    return false;
                }
                $parts = $mail->renderParts();
                $this->assertTrue($mail->hasTo($me->email));
                $this->assertSame($subject, $parts['subject']);
                $this->assertStringNotContainsString('/email/click/', $parts['html']);
                $this->assertSame([], $mail->headers()->text, 'A test has no one-click unsubscribe');

                return true;
            });
        }
        $this->assertSame(0, DB::table('broadcast_logs')->count());
        $this->assertSame('Draft', $broadcast->fresh()->status);
    }

    public function test_writing_a_broadcast_does_not_mean_sending_it(): void
    {
        $writer = $this->staffWith(['broadcasts.view', 'broadcasts.create']);
        $broadcast = $this->readyDraft($writer);
        $this->actingAs($writer);
        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'send')
            ->assertSee('needs the “Approve broadcasts” permission')->assertDontSee('Send now')
            ->call('sendNow')->assertForbidden();
        $this->assertSame('Draft', $broadcast->fresh()->status);
        $this->assertSame(0, DB::table('broadcast_logs')->count());
    }

    public function test_send_now_queues_each_person_in_their_language(): void
    {
        $approver = $this->sales();
        $broadcast = $this->readyDraft($approver);
        $this->actingAs($approver);
        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'send')
            ->assertSee('Ready to send: 2 people')
            ->call('sendNow')->assertRedirect(route('hq.campaigns.report', ['broadcast' => $broadcast->id]));

        $broadcast->refresh();
        $this->assertSame('Sending', $broadcast->status);
        $this->assertSame(2, $broadcast->total_recipients);
        $this->assertSame($approver->id, $broadcast->sent_by);
        $this->assertSame(['who' => ['leads'], 'filters' => []], $broadcast->audience);
        $logs = DB::table('broadcast_logs')->where('campaign_id', $broadcast->id)->pluck('locale', 'email')->all();
        $this->assertSame(['en.lead@example.test' => 'en', 'fr.lead@example.test' => 'fr'], $logs);
        $this->assertSame(2, DB::table('broadcast_logs')->whereNotNull('token')->distinct()->count('token'));
        $this->assertSame(['en' => ['https://jbspraxis.com/work', Blocks::scheduleUrl()], 'fr' => ['https://jbspraxis.com/fr/work', Blocks::scheduleUrl()]],
            DB::table('broadcast_links')->where('campaign_id', $broadcast->id)->orderBy('id')->get()->groupBy('locale')->map(fn ($l) => $l->pluck('url')->all())->all());
        $this->assertTrue(DB::table('notifications')->where('user_id', $approver->id)->where('title', 'Broadcast sending')->exists());

        $this->get($this->hqUrl('/campaigns/broadcasts/' . $broadcast->id))->assertRedirect(route('hq.campaigns.report', ['broadcast' => $broadcast->id]));
        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->assertRedirect(route('hq.campaigns.report', ['broadcast' => $broadcast->id]));
        $this->assertSame(0, Broadcasts::start($broadcast->fresh()));
        $this->assertSame(2, DB::table('broadcast_logs')->count());
    }

    public function test_what_is_missing_stops_the_send_in_plain_words(): void
    {
        $this->actingAs($this->sales());
        $broadcast = Broadcasts::create(auth()->user(), 'announcement');

        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'send')
            ->assertSee('English: Add a subject.')->assertSee('French: Add a subject.')->assertSee('Choose who gets it: pick a segment.')
            ->call('sendNow')->assertDispatched('praxis-toast', $this->toast('It isn’t ready yet'));
        $this->assertSame('Draft', $broadcast->fresh()->status);

        // English only: no French version needed
        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('language', 'en')->set('step', 'send')->assertDontSee('French: Add a subject.');
    }

    public function test_schedule_waits_for_its_time_then_starts_with_the_audience_of_that_moment(): void
    {
        Mail::fake();
        $approver = $this->sales();
        $this->actingAs($approver);
        $broadcast = $this->readyDraft($approver);
        $at = now('Africa/Lagos')->addDay()->setTime(9, 0);

        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'send')
            ->set('scheduleDate', now('Africa/Lagos')->subDay()->format('Y-m-d'))->call('schedule')->assertHasErrors('scheduleDate')
            ->set('scheduleDate', $at->format('Y-m-d'))->set('scheduleTime', '09:00')->call('schedule')->assertRedirect(route('hq.campaigns.broadcasts'));
        $this->assertSame('Scheduled', $broadcast->fresh()->status);
        $this->assertTrue($broadcast->fresh()->scheduled_at->equalTo($at));

        // Read-only while scheduled; unscheduling makes it a draft again
        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->assertSet('canEdit', false)->assertSee('Unschedule')->call('unschedule');
        $this->assertSame('Draft', $broadcast->fresh()->status);
        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'send')->set('scheduleDate', $at->format('Y-m-d'))->call('schedule');

        $this->artisan('broadcast:process')->assertSuccessful();
        $this->assertSame('Scheduled', $broadcast->fresh()->status, 'Not before its time');

        // Someone unsubscribes before it starts: left out
        Marketing::suppress('fr.lead@example.test', 'unsubscribed');
        $this->travelTo($at->copy()->addMinute());
        $this->artisan('broadcast:process')->assertSuccessful();
        $this->assertSame('Completed', $broadcast->fresh()->status);
        $this->assertSame(['en.lead@example.test'], DB::table('broadcast_logs')->pluck('email')->all());
        Mail::assertSent(BroadcastEmail::class, 1);
    }

    public function test_segments_are_saved_with_a_live_count_and_chosen_for_the_draft(): void
    {
        $this->actingAs($this->sales());
        $this->contact($this->org(['status' => 'Active Client', 'industry' => 'Banking']), ['email' => 'bank@example.test']);
        $this->contact($this->org(['status' => 'Active Client', 'industry' => 'Retail']), ['email' => 'shop@example.test']);
        $broadcast = Broadcasts::create(auth()->user(), 'insight');

        Livewire::test(BroadcastEditor::class, ['broadcast' => $broadcast->id])->set('step', 'audience')
            ->assertSee('No segments yet')
            ->call('newSegment')->set('segmentWho', ['clients'])
            ->assertSee('2</strong> people today', false)
            ->set('segmentFilters.industry', ['Banking'])
            ->assertSee('1</strong> person today', false)
            ->call('saveSegmentAndUse')->assertHasErrors('segmentName')
            ->set('segmentName', 'Banking clients')->call('saveSegmentAndUse')->assertHasNoErrors()
            ->assertSee('Banking clients: 1 person today')->assertSee('bank@example.test')->assertDontSee('shop@example.test');

        $this->assertSame(['who' => ['clients'], 'filters' => ['industry' => ['Banking']]], $broadcast->fresh()->segment->rules);

        Livewire::test(Audiences::class)->assertSee('Banking clients')->assertSee('Clients · Industry: Banking');
    }
}
