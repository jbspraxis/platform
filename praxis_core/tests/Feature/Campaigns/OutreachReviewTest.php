<?php

namespace Tests\Feature\Campaigns;

use App\Livewire\Hq\Campaigns\Outreach\OutreachCampaignPage;
use App\Livewire\Hq\Campaigns\Outreach\OutreachLimits;
use App\Livewire\Hq\Campaigns\Outreach\OutreachList;
use App\Models\Campaigns\OutreachCampaign;
use App\Support\Ai\AiRequest;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\Senders;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Reviewing outreach drafts (docs/CAMPAIGNS.md section 10; owner's decision 3, 3 Nov 2026): the clean table, the
 * dialog with the full email and its follow-up (Approve, Edit in rich text, Rewrite with an optional note, Skip), the
 * next draft opening after each decision, "Approve the rest" only after 10 approved by hand, the screens' permissions
 * and the owner's Sending limits.
 */
class OutreachReviewTest extends TestCase
{
    use OutreachFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOutreach();
    }

    public function test_the_dialog_shows_the_full_email_and_approve_opens_the_next_draft(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $a = $this->prospect($campaign, 'draft', ['first_name' => 'Ada', 'company' => 'Kilo Logistics (test)', 'website' => 'https://kilo.test', 'website_read_at' => now()]);
        $b = $this->prospect($campaign, 'draft', ['first_name' => 'Bola']);
        $this->actingAs($gerald);

        $page = Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id])
            ->assertSee('Kilo Logistics (test)')->assertSee('To review')->assertSee('Your second warehouse in Onne')
            ->call('openEmail', $this->firstOf($a)->id)
            ->assertSee('To Ada Obi')->assertSee('Congratulations on the new warehouse in Onne.')->assertSee('One more thought on Onne.')
            ->assertSee('Opened a second warehouse in Onne in September.')->assertSee('kilo.test')->assertSee('Approve')->assertSee('Rewrite')->assertSee('Skip this prospect');

        $page->call('approve')->assertSet('openId', $this->firstOf($b)->id)->assertSee('To Bola Obi')->assertSee('Approved.');
        $first = $this->firstOf($a);
        $this->assertSame(['approved', 'hand', $gerald->id], [$first->status, $first->approval, $first->approved_by]);
        $this->assertSame('approved', $this->followUpOf($a)->status);

        // Skip the last one: the dialog closes; Back to review undoes it
        $page->call('skip')->assertSet('openId', null);
        $this->assertSame(['skipped', 'skipped', 'skipped'], [$this->firstOf($b)->status, $this->followUpOf($b)->status, $b->fresh()->status]);
        $page->call('openEmail', $this->firstOf($b)->id)->assertSee('Back to review')->call('backToReview');
        $this->assertSame(['draft', 'draft', 'new'], [$this->firstOf($b)->status, $this->followUpOf($b)->status, $b->fresh()->status]);
        $page->call('openEmail', $this->firstOf($a)->id)->call('backToReview');
        $this->assertSame(['draft', null], [$this->firstOf($a)->status, $this->firstOf($a)->approval]);
    }

    public function test_edit_in_rich_text_and_rewrite_with_a_note(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $p = $this->prospect($campaign, 'draft');
        $id = $this->firstOf($p)->id;
        $this->actingAs($gerald);

        $page = Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id])->call('openEmail', $id)->call('startEdit')
            ->assertSet('mode', 'edit')->assertSet('editSubject', 'Your second warehouse in Onne')->assertSee('Leave it empty to send no follow-up.');
        // What the editor sends is cleaned: no script, no styles
        $page->set('editSubject', '  Onne,   and month-end ')
            ->call('setText', 'first', (string) $id, '<p>Hello Ada,</p><p><b>Short</b> note.<script>x()</script></p><p style="color:red">Bye</p>')
            ->call('setText', 'follow_up', (string) $id, '')
            ->call('saveEdit')->assertHasNoErrors()->assertSet('mode', 'read');
        $first = $this->firstOf($p);
        $this->assertSame(['Onne, and month-end', '<p>Hello Ada,</p><p><strong>Short</strong> note.</p><p>Bye</p>', true], [$first->subject, $first->body_html, $first->edited]);
        $this->assertSame('', (string) $this->followUpOf($p)->body_html);
        // An empty subject or text isn't saved
        $page->call('startEdit')->set('editSubject', '')->call('setText', 'first', (string) $id, '<p> </p>')->call('saveEdit')
            ->assertHasErrors(['editSubject', 'editBody.first']);
        $page->call('cancelMode');

        // Approving with an empty follow-up means no follow-up
        $page->call('approve');
        $this->assertSame(['approved', 'skipped', 'No follow-up written'], [$this->firstOf($p)->status, $this->followUpOf($p)->status, $this->followUpOf($p)->note]);

        // Rewrite: Claude again, with the note
        $q = $this->prospect($campaign, 'draft');
        $ai = FakeAi::install()->respond('outreach_email', ['subject' => 'Shorter subject', 'body' => "Hello Ada,\n\nShorter.\n\nKind regards,",
            'follow_up' => "Hello Ada,\n\nStill shorter.\n\nKind regards,", 'insight' => 'Onne warehouse (from What we found).']);
        $page->call('openEmail', $this->firstOf($q)->id)->call('startRewrite')->assertSet('mode', 'rewrite')
            ->set('rewriteNote', 'Shorter, and offer the audit')->call('rewrite')->assertHasNoErrors()->assertSee('Shorter subject');
        $ai->assertRan('outreach_email', fn (AiRequest $r) => str_contains($r->prompt, 'REWRITE NOTE: Shorter, and offer the audit'));
        $this->assertSame(['draft', 'Shorter subject', 'Onne warehouse (from What we found).'], [$this->firstOf($q)->status, $this->firstOf($q)->subject, $this->firstOf($q)->insight]);
        $this->assertSame('<p>Hello Ada,</p><p>Still shorter.</p><p>Kind regards,</p>', $this->followUpOf($q)->body_html);
    }

    public function test_approve_the_rest_unlocks_only_after_10_approved_by_hand(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $prospects = collect(range(1, 14))->map(fn () => $this->prospect($campaign, 'draft'));
        $this->prospect($campaign, 'writing'); // still being written: never approved unseen
        $this->actingAs($gerald);
        $page = Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id]);

        $page->assertSee('Unlocks after 10 approved by hand (0 of 10)')->call('askApproveRest')->assertSet('confirmingRest', false)
            ->call('approveRest')->assertSee('Approve 10 emails one by one first.');
        $this->assertSame(0, Outreach::approveRest($campaign, $gerald));

        foreach ($prospects->take(9) as $p) {
            $page->call('openEmail', $this->firstOf($p)->id)->call('approve');
        }
        $page->assertSee('(9 of 10)');
        $this->assertSame(0, Outreach::approveRest($campaign, $gerald));
        $page->call('openEmail', $this->firstOf($prospects[9])->id)->call('approve')->call('closeEmail')
            ->assertDontSee('Unlocks after')->call('askApproveRest')->assertSet('confirmingRest', true)
            ->assertSee('Approve the other 4 emails?')->assertSee('without anyone reading them first')
            ->call('approveRest')->assertSee('4 emails approved.');

        $firsts = $prospects->map(fn ($p) => $this->firstOf($p));
        $this->assertSame(array_fill(0, 10, 'hand'), $firsts->take(10)->pluck('approval')->all());
        $this->assertSame(array_fill(0, 4, 'bulk'), $firsts->slice(10)->pluck('approval')->values()->all());
        $this->assertSame(['approved'], $firsts->pluck('status')->unique()->values()->all());
        $this->assertSame('approved', $this->followUpOf($prospects[13])->status);
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'OUTREACH_APPROVED_REST')->where('target_id', $campaign->id)->exists());
        $this->assertSame(1, \App\Models\Campaigns\OutreachEmail::where('campaign_id', $campaign->id)->where('kind', 'first')->where('status', 'writing')->count());
    }

    public function test_who_may_see_review_and_change_the_limits(): void
    {
        $gerald = $this->salesperson();
        $campaign = $this->outreachCampaign($gerald);
        $p = $this->prospect($campaign, 'draft');
        $owner = $this->staff('owner', ['owner']);
        $nina = $this->salesperson('nina');
        $auditor = $this->staff('audrey', ['auditor']);
        $engineer = $this->staff('eric', ['engineer']);

        // The screens: Outreach for view; Sending limits only for outreach.approve (the owner)
        $this->actingAs($gerald)->get($this->hqUrl('/campaigns/outreach'))->assertOk()->assertSee('Outreach')->assertDontSee('Sending limits');
        $this->get($this->hqUrl('/campaigns/outreach/' . $campaign->id))->assertOk()->assertSee('Import prospects');
        $this->get($this->hqUrl('/campaigns/outreach/limits'))->assertForbidden();
        $this->actingAs($auditor)->get($this->hqUrl('/campaigns/outreach/' . $campaign->id))->assertOk()->assertDontSee('Import prospects');
        $this->actingAs($engineer)->get($this->hqUrl('/campaigns/outreach'))->assertForbidden();
        $this->actingAs($owner)->get($this->hqUrl('/campaigns/outreach/limits'))->assertOk()->assertSee('Emails a day in a sender’s first weeks');
        // The rail: Campaigns › Outreach
        $this->actingAs($gerald)->get($this->hqUrl('/campaigns/outreach'))->assertSee(route('hq.campaigns.outreach'), false);

        // Reviewing: the campaign's own salesperson, or the owner; not another salesperson, not an auditor
        $this->actingAs($auditor);
        Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id])->call('openEmail', $this->firstOf($p)->id)
            ->assertDontSee('Skip this prospect')->call('approve')->assertForbidden();
        $this->actingAs($nina);
        Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id])->call('openEmail', $this->firstOf($p)->id)->call('approve')->assertForbidden();
        $this->assertSame('draft', $this->firstOf($p)->status);
        $this->actingAs($owner);
        Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id])->call('openEmail', $this->firstOf($p)->id)->call('approve');
        $this->assertSame(['approved', $owner->id], [$this->firstOf($p)->status, $this->firstOf($p)->approved_by]);

        // A campaign: a salesperson's own, sent from their mailbox; only the owner may choose another sender
        $this->actingAs($nina);
        Livewire::test(OutreachList::class)->call('startCreating')->set('name', 'Ab')->call('create')->assertHasErrors('name')
            ->set('name', 'Abuja banks (test)')->set('brief', 'Offer the audit.')->set('senderId', $gerald->id)->call('create')->assertHasErrors('senderId')
            ->set('senderId', $nina->id)->call('create')->assertHasNoErrors();
        $made = OutreachCampaign::where('name', 'Abuja banks (test)')->sole();
        $this->assertSame([$nina->id, $nina->id, 'Offer the audit.', 'active'], [$made->owner_id, $made->sender_id, $made->brief, $made->status]);

        // The owner's limits: checked, saved, logged; Resume for a paused sender
        Senders::pause($gerald->id, 'bounces', '2 of 10 bounced.');
        $this->actingAs($owner);
        Livewire::test(OutreachLimits::class)->assertSee('Paused')->set('values.window_end', '08:00')->call('save')->assertHasErrors('values.window_end')
            ->set('values.window_end', '16:00')->set('values.first_weeks_limit', '10')->call('save')->assertHasNoErrors()->assertSee('Saved.')
            ->call('resumeSender', $gerald->id)->assertSee('Resumed');
        Limits::flush();
        $this->assertSame(['16:00', 10], [Limits::get('window_end'), Limits::get('first_weeks_limit')]);
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'OUTREACH_LIMITS_CHANGED')->exists());
        $this->assertFalse(Senders::isPaused($gerald->id));
        $this->actingAs($gerald);
        Livewire::test(OutreachLimits::class)->assertForbidden();
    }

    public function test_the_screens_say_how_to_connect_a_mailbox_and_show_the_numbers(): void
    {
        $nina = $this->salesperson('nina', mailbox: false);
        $campaign = $this->outreachCampaign($nina);
        $this->prospect($campaign, 'sent');
        $this->prospect($campaign, 'bounced');
        $this->prospect($campaign, 'draft');
        $this->actingAs($nina);

        Livewire::test(OutreachList::class)->assertSee('Connect your jbspraxis.com mailbox first: open Mail › Mailboxes')->assertSee('Lagos logistics (test)');
        $stats = Outreach::stats([$campaign->id])[$campaign->id];
        $this->assertSame([3, 1, 1, 1], [$stats['prospects'], $stats['to_review'], $stats['sent'], $stats['bounced']]);
        Livewire::test(OutreachCampaignPage::class, ['campaign' => $campaign->id])->assertSee('Connect your jbspraxis.com mailbox first')
            ->set('filter', 'sent')->assertSee('Sent')->set('filter', 'stopped')->assertSee('Bounced');
    }
}
