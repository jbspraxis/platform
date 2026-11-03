<?php

namespace Tests\Feature\Campaigns;

use App\Services\Site\TrackingService;
use App\Support\Campaigns\Marketing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The do-not-email list and its contract (docs/CAMPAIGNS.md 4.1): Marketing::canEmail, suppress and lift, which
 * Outreach (C2) calls too; the backfill of earlier unsubscribes; old broadcast links feeding the list.
 */
class MarketingSuppressionTest extends TestCase
{
    use CampaignFixtures;

    public function test_an_address_on_the_list_gets_no_marketing_whatever_its_case(): void
    {
        $this->assertTrue(Marketing::canEmail('ada@example.test'));
        $this->assertFalse(Marketing::canEmail('not an address'));
        $this->assertFalse(Marketing::canEmail(null));

        $this->assertTrue(Marketing::suppress('  Ada@Example.TEST ', 'unsubscribed', ['source' => 'outreach']));
        $this->assertFalse(Marketing::canEmail('ada@example.test'));
        $this->assertFalse(Marketing::canEmail('ADA@example.test'));
        $this->assertSame('ada@example.test', DB::table('marketing_suppressions')->value('email'));
        $this->assertSame('outreach', DB::table('marketing_suppressions')->value('source'));

        // Again: still one row
        $this->assertFalse(Marketing::suppress('ada@example.test', 'bounced'));
        $this->assertSame(1, DB::table('marketing_suppressions')->count());
        $this->assertFalse(Marketing::suppress('ada@example.test', 'no such reason'));
    }

    public function test_suppressing_stops_the_address_everywhere_and_an_unsubscribe_outranks_a_bounce(): void
    {
        $email = 'shared.' . Str::lower(Str::random(5)) . '@example.test';
        $contact = $this->contact($this->org(), ['email' => $email]);
        $subscriber = $this->subscriber(['email' => $email]);

        Marketing::suppress($email, 'bounced', ['source' => 'broadcast']);
        $this->assertSame(0, (int) DB::table('client_users')->where('id', $contact)->value('is_subscribed'));
        $this->assertNotNull(DB::table('marketing_subscribers')->where('id', $subscriber)->value('unsubscribed_at'));

        Marketing::suppress($email, 'unsubscribed', ['source' => 'broadcast']);
        $this->assertSame('unsubscribed', Marketing::entry($email)->reason);

        // A manual entry later never weakens it
        Marketing::suppress($email, 'manual');
        $this->assertSame('unsubscribed', Marketing::entry($email)->reason);
    }

    public function test_hq_can_lift_a_manual_or_bounced_entry_but_never_an_unsubscribe(): void
    {
        $contact = $this->contact($this->org(), ['email' => 'lift.me@example.test']);
        Marketing::suppress('lift.me@example.test', 'manual', ['source' => 'hq']);
        $this->assertTrue(Marketing::lift('lift.me@example.test'));
        $this->assertTrue(Marketing::canEmail('lift.me@example.test'));
        $this->assertSame(1, (int) DB::table('client_users')->where('id', $contact)->value('is_subscribed'));

        Marketing::suppress('gone@example.test', 'unsubscribed');
        Marketing::suppress('spam@example.test', 'complained');
        $this->assertFalse(Marketing::lift('gone@example.test'));
        $this->assertFalse(Marketing::lift('spam@example.test'));
        $this->assertFalse(Marketing::canEmail('gone@example.test'));
        $this->assertFalse(Marketing::lift('never.listed@example.test'));
    }

    public function test_the_backfill_lists_everyone_who_unsubscribed_before(): void
    {
        $org = $this->org();
        $this->contact($org, ['email' => 'Old.Unsub@example.test', 'is_subscribed' => 0]);
        $this->contact($org, ['email' => 'still.here@example.test']);
        Marketing::suppress('old.unsub@example.test', 'manual'); // already listed: left as it is

        (require database_path('migrations/2026_11_03_700001_backfill_marketing_suppressions.php'))->up();

        $this->assertFalse(Marketing::canEmail('old.unsub@example.test'));
        $this->assertTrue(Marketing::canEmail('still.here@example.test'));
        $this->assertSame(1, DB::table('marketing_suppressions')->count());
    }

    public function test_an_old_broadcast_unsubscribe_link_puts_the_address_on_the_list(): void
    {
        $staff = $this->staffWith(['broadcasts.view']);
        $contact = $this->contact($this->org(), ['email' => 'legacy.link@example.test']);
        $campaign = (string) Str::uuid();
        $this->insertRow('broadcast_campaigns', ['id' => $campaign, 'subject' => 'News', 'body_html' => '<p>News</p>', 'language' => 'en', 'created_by' => $staff->id]);
        DB::table('broadcast_logs')->insert(['campaign_id' => $campaign, 'user_id' => $contact, 'status' => 'Sent', 'signed_links' => 1]);

        $this->assertTrue(app(TrackingService::class)->unsubscribe($contact, $campaign, TrackingService::token($campaign, $contact)));

        $this->assertFalse(Marketing::canEmail('legacy.link@example.test'));
        $this->assertSame('legacy_link', Marketing::entry('legacy.link@example.test')->source);
    }
}
