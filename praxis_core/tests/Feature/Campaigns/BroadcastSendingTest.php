<?php

namespace Tests\Feature\Campaigns;

use App\Console\Commands\ProcessBroadcastQueue;
use App\Livewire\Hq\Campaigns\BroadcastReport;
use App\Mail\BroadcastEmail;
use App\Mail\IntelligenceBroadcast;
use App\Models\Campaigns\Broadcast;
use App\Support\Campaigns\Blocks;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Tracking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * broadcast:process (docs/CAMPAIGNS.md 4.6): each person gets their language with counted links and a one-click
 * unsubscribe; 30 a run, oldest first; the do-not-email list is checked at the last moment; refusals bounce;
 * broadcasts from before Campaigns still go out; the report's numbers.
 */
class BroadcastSendingTest extends TestCase
{
    use CampaignFixtures;

    private function started(array $people, string $language = 'both'): Broadcast
    {
        $by = $this->staff('sales', ['sales']);
        $broadcast = Broadcasts::create($by, 'insight');
        $broadcast->forceFill([
            'language' => $language,
            'content' => [
                'en' => ['subject' => 'Three checks for {{ company_name }}', 'blocks' => [['type' => 'text', 'html' => '<p>See <a href="https://jbspraxis.com/work">our work</a>.</p>'], Blocks::blank('button', 'en')]],
                'fr' => ['subject' => 'Trois vérifications pour {{ company_name }}', 'blocks' => [['type' => 'text', 'html' => '<p>Voir <a href="https://jbspraxis.com/fr/work">nos travaux</a>.</p>'], Blocks::blank('button', 'fr')]],
            ],
            'segment_id' => $this->segment(['who' => ['leads', 'subscribers']])->id,
        ])->save();
        foreach ($people as [$email, $country]) {
            $this->contact($this->org(['country' => $country, 'preferred_language' => null, 'name' => 'Org of ' . $email]), ['email' => $email]);
        }
        Broadcasts::sendNow($broadcast, $by);

        return $broadcast->fresh();
    }

    public function test_each_person_gets_their_language_counted_links_and_a_one_click_unsubscribe(): void
    {
        Mail::fake();
        $broadcast = $this->started([['ada@example.test', 'Nigeria'], ['brice@example.test', 'Cameroon']]);

        $this->artisan('broadcast:process')->assertSuccessful();

        $logs = DB::table('broadcast_logs')->get()->keyBy('email');
        foreach (['ada@example.test' => 'en', 'brice@example.test' => 'fr'] as $email => $locale) {
            $log = $logs[$email];
            $this->assertSame('Sent', $log->status);
            $this->assertNotNull($log->sent_at);
            Mail::assertSent(BroadcastEmail::class, function (BroadcastEmail $mail) use ($email, $locale, $log) {
                if (! $mail->hasTo($email)) {
                    return false;
                }
                $this->assertSame($locale, $mail->locale);
                $parts = $mail->renderParts();
                $headers = $mail->headers()->text;
                $this->assertSame('<' . Tracking::unsubscribeUrl($log->token) . '>', $headers['List-Unsubscribe']);
                $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
                $this->assertStringContainsString(e(Tracking::openUrl($log->token)), $parts['html']);
                $this->assertStringContainsString('/email/click/' . $log->token . '/', $parts['html']);
                $this->assertStringNotContainsString('href="https://jbspraxis.com/work"', $parts['html'], 'Body links are counted');
                $this->assertSame($locale === 'fr' ? 'Trois vérifications pour Org of brice@example.test' : 'Three checks for Org of ada@example.test', $parts['subject']);
                $this->assertStringContainsString($locale === 'fr' ? 'Bien cordialement,' : 'Kind regards,', $parts['html']);
                $this->assertStringContainsString(e(__('emails.broadcast.reasons.lead', [], $locale)), $parts['html']);
                $this->assertStringContainsString(e(Tracking::unsubscribeUrl($log->token)), $parts['html']);

                return true;
            });
        }
        $this->assertSame('Completed', $broadcast->fresh()->status);
        $this->assertSame(2, (int) $broadcast->fresh()->sent_count);
        $this->assertTrue(DB::table('notifications')->where('title', 'Broadcast sent')->where('user_id', $broadcast->sent_by)->exists());
    }

    public function test_a_one_language_broadcast_goes_to_everyone_in_that_language(): void
    {
        Mail::fake();
        $this->started([['ada@example.test', 'Nigeria'], ['brice@example.test', 'Cameroon']], 'en');
        $this->artisan('broadcast:process')->assertSuccessful();

        $this->assertSame(['en'], DB::table('broadcast_logs')->distinct()->pluck('locale')->all());
        Mail::assertSent(BroadcastEmail::class, fn (BroadcastEmail $m) => $m->hasTo('brice@example.test') && $m->locale === 'en');
    }

    public function test_thirty_a_run_oldest_first_and_the_list_is_checked_at_the_last_moment(): void
    {
        Mail::fake();
        $people = array_map(fn ($n) => ["p{$n}@example.test", 'Nigeria'], range(1, 33));
        $broadcast = $this->started($people);
        // The first email in the queue is sure to be in the first run; its person unsubscribes after it was queued
        $first = DB::table('broadcast_logs')->where('campaign_id', $broadcast->id)->orderBy('id')->value('email');
        Marketing::suppress($first, 'unsubscribed');

        $this->artisan('broadcast:process')->assertSuccessful();
        $this->assertSame(Broadcasts::PER_RUN, DB::table('broadcast_logs')->whereNotIn('status', ['Queued'])->count());
        $this->assertSame('Skipped', DB::table('broadcast_logs')->where('email', $first)->value('status'));
        // Oldest first: the 3 still waiting are the last 3 queued
        $this->assertSame(DB::table('broadcast_logs')->orderByDesc('id')->limit(3)->pluck('id')->sort()->values()->all(),
            DB::table('broadcast_logs')->where('status', 'Queued')->orderBy('id')->pluck('id')->all());
        $this->assertSame('Sending', $broadcast->fresh()->status);
        Mail::assertSent(BroadcastEmail::class, 29);

        $this->artisan('broadcast:process')->assertSuccessful();
        $this->assertSame('Completed', $broadcast->fresh()->status);
        Mail::assertSent(BroadcastEmail::class, 32);
        $stats = Broadcasts::stats($broadcast);
        $this->assertSame([32, 1], [$stats['sent'], $stats['skipped']]);
    }

    public function test_an_address_the_server_refuses_bounces_and_goes_on_the_list(): void
    {
        $broadcast = $this->started([['gone@example.test', 'Nigeria'], ['slow@example.test', 'Nigeria']]);
        $pending = Mockery::mock();
        $pending->shouldReceive('send')->once()->andThrow(new TransportException('Expected response code "250" but got code "550", with message "550 5.1.1 <gone@example.test>: Recipient address rejected: User unknown".'));
        $pending->shouldReceive('send')->once()->andThrow(new TransportException('Expected response code "250" but got code "421", with message "421 4.7.0 Try again later".'));
        Mail::shouldReceive('to')->andReturn($pending);

        $this->artisan('broadcast:process')->assertSuccessful();

        $this->assertSame('Bounced', DB::table('broadcast_logs')->where('email', 'gone@example.test')->value('status'));
        $this->assertSame('bounced', Marketing::entry('gone@example.test')->reason);
        $this->assertSame('Failed', DB::table('broadcast_logs')->where('email', 'slow@example.test')->value('status'));
        $this->assertTrue(Marketing::canEmail('slow@example.test'), 'A temporary refusal is not a bounce');
        $this->assertSame('Completed', $broadcast->fresh()->status);

        $this->assertFalse(ProcessBroadcastQueue::isPermanent(new TransportException('Connection could not be established with host "mail.jbspraxis.com"')));
        $this->assertTrue(ProcessBroadcastQueue::isPermanent(new TransportException('550 mailbox unavailable')));
    }

    public function test_a_bug_in_hq_is_never_taken_for_a_bounce(): void
    {
        $this->started([['ada@example.test', 'Nigeria'], ['brice@example.test', 'Nigeria']]);
        $pending = Mockery::mock();
        $pending->shouldReceive('send')->once()->andThrow(new \BadMethodCallException('Method App\Mail\BroadcastEmail::nope does not exist.'));
        $pending->shouldReceive('send')->once()->andThrow(new \RuntimeException('Row 550 of the 554 rows: user unknown'));
        Mail::shouldReceive('to')->andReturn($pending);

        $this->artisan('broadcast:process')->assertSuccessful();

        foreach (['ada@example.test', 'brice@example.test'] as $email) {
            $this->assertSame('Failed', DB::table('broadcast_logs')->where('email', $email)->value('status'), $email);
            $this->assertTrue(Marketing::canEmail($email), "{$email} stays off the do-not-email list");
            $this->assertSame(1, (int) DB::table('client_users')->where('email', $email)->value('is_subscribed'));
        }
        $this->assertSame(0, DB::table('marketing_suppressions')->count());
        $this->assertFalse(ProcessBroadcastQueue::isPermanent(new \BadMethodCallException('Method X::y does not exist.')));
        $this->assertFalse(ProcessBroadcastQueue::isPermanent(new \RuntimeException('550 5.1.1 User unknown')));
    }

    public function test_an_email_a_dead_run_left_sending_fails_after_15_minutes_and_is_never_sent_again(): void
    {
        Mail::fake();
        $broadcast = $this->started([['dead@example.test', 'Nigeria'], ['recent@example.test', 'Nigeria'], ['next@example.test', 'Nigeria']]);
        // A run died after claiming these two: one 20 minutes ago, one 5 minutes ago (that run may still be going)
        DB::table('broadcast_logs')->where('email', 'dead@example.test')->update(['status' => 'Sending', 'updated_at' => now()->subMinutes(20)]);
        DB::table('broadcast_logs')->where('email', 'recent@example.test')->update(['status' => 'Sending', 'updated_at' => now()->subMinutes(5)]);

        $this->artisan('broadcast:process')->assertSuccessful();

        $dead = DB::table('broadcast_logs')->where('email', 'dead@example.test')->first();
        $this->assertSame('Failed', $dead->status);
        $this->assertSame(ProcessBroadcastQueue::INTERRUPTED_MESSAGE, $dead->error_message);
        $this->assertSame('Sending', DB::table('broadcast_logs')->where('email', 'recent@example.test')->value('status'));
        $this->assertSame('Sent', DB::table('broadcast_logs')->where('email', 'next@example.test')->value('status'));
        $this->assertSame(1, (int) $broadcast->fresh()->failed_count);
        $this->assertSame('Sending', $broadcast->fresh()->status, 'Not complete while a row may still be on its way');
        Mail::assertSent(BroadcastEmail::class, 1);
        Mail::assertNotSent(BroadcastEmail::class, fn ($m) => $m->hasTo('dead@example.test'));

        $this->travel(15)->minutes();
        $this->artisan('broadcast:process')->assertSuccessful();

        $this->assertSame('Failed', DB::table('broadcast_logs')->where('email', 'recent@example.test')->value('status'));
        $this->assertSame(2, (int) $broadcast->fresh()->failed_count);
        $this->assertSame('Completed', $broadcast->fresh()->status);
        $this->assertTrue(DB::table('notifications')->where('title', 'Broadcast sent')->where('user_id', $broadcast->sent_by)->exists());
        Mail::assertSent(BroadcastEmail::class, 1);
        $this->assertTrue(Marketing::canEmail('dead@example.test'), 'An interruption is not a bounce');
    }

    public function test_a_broadcast_from_before_campaigns_still_goes_out_the_old_way(): void
    {
        Mail::fake();
        $staff = $this->staff('sales', ['sales']);
        $contact = $this->contact($this->org(['name' => 'Legacy Ltd (test)']), ['email' => 'legacy@example.test']);
        $id = (string) Str::uuid();
        $this->insertRow('broadcast_campaigns', ['id' => $id, 'subject' => 'Old news', 'body_html' => '<p>Hello {{ first_name }}, news for {{ company_name }}.</p>',
            'language' => 'fr', 'status' => 'Sending', 'layout' => null, 'created_by' => $staff->id]);
        DB::table('broadcast_logs')->insert(['campaign_id' => $id, 'user_id' => $contact, 'status' => 'Queued']);

        $this->artisan('broadcast:process')->assertSuccessful();

        Mail::assertSent(IntelligenceBroadcast::class, fn ($m) => $m->hasTo('legacy@example.test') && $m->locale === 'fr' && str_contains($m->htmlBody, 'Legacy Ltd (test)'));
        $this->assertSame('Sent', DB::table('broadcast_logs')->value('status'));
        $this->assertSame('Completed', Broadcast::find($id)->status);

        // Its report opens
        $this->actingAs($staff);
        Livewire::test(BroadcastReport::class, ['broadcast' => $id])->assertSee('Old news')->assertSee('legacy@example.test');
    }

    public function test_the_report_counts_opens_clicks_and_unsubscribes(): void
    {
        Mail::fake();
        $broadcast = $this->started([['a@example.test', 'Nigeria'], ['b@example.test', 'Nigeria'], ['c@example.test', 'Cameroon'], ['d@example.test', 'Nigeria']]);
        $this->artisan('broadcast:process');
        $logs = DB::table('broadcast_logs')->get()->keyBy('email');
        $link = DB::table('broadcast_links')->where('campaign_id', $broadcast->id)->where('locale', 'en')->value('id');

        Tracking::opened($logs['a@example.test']);
        Tracking::opened(DB::table('broadcast_logs')->find($logs['a@example.test']->id));
        Tracking::clicked($logs['b@example.test'], $link);
        Tracking::unsubscribe($logs['d@example.test'], 'page');

        $stats = Broadcasts::stats($broadcast);
        $this->assertSame(4, $stats['sent']);
        $this->assertSame(2, $stats['opened'], 'A click counts as an open');
        $this->assertSame(50, $stats['open_rate']);
        $this->assertSame(1, $stats['clicked']);
        $this->assertSame(1, $stats['unsubscribed']);
        $this->assertSame([3, 1], [$stats['en'], $stats['fr']]);

        $this->actingAs($this->staffWith(['broadcasts.view']));
        Livewire::test(BroadcastReport::class, ['broadcast' => $broadcast->id])
            ->assertSee('Opened, approximately')->assertSee('50%')->assertSee('https://jbspraxis.com/work')
            ->set('who', 'clicked')->assertSee('b@example.test')->assertDontSee('c@example.test')
            ->set('who', 'unsubscribed')->assertSee('d@example.test')
            ->set('who', 'all')->set('search', 'c@ex')->assertSee('c@example.test')->assertDontSee('a@example.test')
            ->set('search', '')->set('showEmail', true)->assertSee('The email, as sent');
    }
}
