<?php

namespace Tests\Feature\Campaigns;

use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Tracking;
use App\Support\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The links inside broadcasts, on the portal host (docs/CAMPAIGNS.md 4.5): the unsubscribe page in the email's
 * language, the inbox's one-click unsubscribe without a CSRF token, opens, clicks and pictures.
 */
class EmailLinksTest extends TestCase
{
    use CampaignFixtures;

    /** One sent broadcast email; returns its log row */
    private function sentEmail(string $locale = 'en', string $email = 'reader@example.test'): object
    {
        $staff = $this->staffWith(['broadcasts.view']);
        $campaign = (string) Str::uuid();
        $this->insertRow('broadcast_campaigns', ['id' => $campaign, 'subject' => 'News', 'layout' => 'letter', 'body_html' => null, 'language' => 'both', 'status' => 'Completed', 'created_by' => $staff->id]);
        $contact = $this->contact($this->org(), ['email' => $email]);
        $id = DB::table('broadcast_logs')->insertGetId(['campaign_id' => $campaign, 'user_id' => $contact, 'kind' => 'lead', 'email' => $email,
            'locale' => $locale, 'token' => Tracking::newToken(), 'status' => 'Sent', 'signed_links' => 1]);

        return DB::table('broadcast_logs')->find($id);
    }

    public function test_the_unsubscribe_page_speaks_the_emails_language_and_needs_a_click(): void
    {
        $log = $this->sentEmail('fr', 'brice@example.test');
        $page = $this->portalUrl('/email/unsubscribe/' . $log->token);

        $this->get($page)->assertOk()->assertSee('Vous désabonner des nouvelles de JBS Praxis')->assertSee('b••••@example.test')
            ->assertSee('Me désabonner')->assertSee('English')->assertSee('noindex', false);
        $this->assertTrue(Marketing::canEmail('brice@example.test'), 'Opening the page never unsubscribes: scanners open links');
        $this->get($page . '?lang=en')->assertSee('Unsubscribe from JBS Praxis news?');

        $this->post($page)->assertOk()->assertSee('Vous êtes désabonné');
        $this->assertFalse(Marketing::canEmail('brice@example.test'));
        $this->assertSame('broadcast', Marketing::entry('brice@example.test')->source);
        $this->assertNotNull(DB::table('broadcast_logs')->where('id', $log->id)->value('unsubscribed_at'));
        $this->get($page)->assertSee('Vous êtes désabonné');
    }

    public function test_the_inboxs_one_click_unsubscribe_works_without_a_csrf_token_and_stops_the_address_everywhere(): void
    {
        $log = $this->sentEmail('en', 'shared@example.test');
        $other = $this->contact($this->org(), ['email' => 'Shared@Example.test.uk']);
        $subscriber = $this->subscriber(['email' => 'shared@example.test']);

        // Gmail and Apple Mail post this body to the List-Unsubscribe address (RFC 8058), with no session or CSRF token
        // (tests skip CSRF checks, so the exception itself is checked)
        $never = (new \ReflectionClass(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class))->getStaticPropertyValue('neverVerify');
        $this->assertContains('email/unsubscribe/*', $never);
        $this->call('POST', $this->portalUrl('/email/unsubscribe/' . $log->token), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()->assertSee('Unsubscribed');

        $this->assertFalse(Marketing::canEmail('shared@example.test'));
        $this->assertNotNull(DB::table('marketing_subscribers')->where('id', $subscriber)->value('unsubscribed_at'));
        $this->assertSame(1, (int) DB::table('client_users')->where('id', $other)->value('is_subscribed'), 'A different address is not touched');
    }

    public function test_an_unknown_or_preview_link_unsubscribes_nobody(): void
    {
        $this->sentEmail();
        $this->get($this->portalUrl('/email/unsubscribe/' . str_repeat('x', 40)))->assertNotFound()->assertSee('We couldn’t use this link');
        $this->post($this->portalUrl('/email/unsubscribe/' . str_repeat('x', 40)), ['List-Unsubscribe' => 'One-Click'])->assertNotFound();

        $this->get($this->portalUrl('/email/unsubscribe/preview'))->assertOk()->assertSee('This is a preview from a test email');
        $this->post($this->portalUrl('/email/unsubscribe/preview'))->assertOk();
        $this->assertSame(0, DB::table('marketing_suppressions')->count());

        // Only the portal answers
        $this->get('https://' . Hosts::hq() . '/email/unsubscribe/preview')->assertNotFound();
    }

    public function test_a_click_is_counted_then_goes_only_to_the_broadcasts_own_address(): void
    {
        $log = $this->sentEmail();
        $link = DB::table('broadcast_links')->insertGetId(['campaign_id' => $log->campaign_id, 'locale' => 'en', 'url' => 'https://jbspraxis.com/work', 'created_at' => now()]);
        $foreign = DB::table('broadcast_links')->insertGetId(['campaign_id' => (string) Str::uuid(), 'locale' => 'en', 'url' => 'https://evil.test', 'created_at' => now()]);

        $this->get($this->portalUrl("/email/click/{$log->token}/{$link}"))->assertRedirect('https://jbspraxis.com/work');
        $this->get($this->portalUrl("/email/click/{$log->token}/{$link}"))->assertRedirect('https://jbspraxis.com/work');
        $row = DB::table('broadcast_logs')->find($log->id);
        $this->assertSame(2, (int) $row->click_count);
        $this->assertNotNull($row->first_clicked_at);
        $this->assertNotNull($row->first_opened_at, 'A click counts as an open');
        $this->assertSame(2, (int) DB::table('broadcast_links')->where('id', $link)->value('clicks'));
        $this->assertSame(2, DB::table('broadcast_clicks')->where('log_id', $log->id)->count());

        // Another broadcast's link, or an unknown token: the home page, never someone else's address
        $home = $this->get($this->portalUrl("/email/click/{$log->token}/{$foreign}"));
        $this->assertSame(Hosts::site(), parse_url($home->headers->get('Location'), PHP_URL_HOST));
        $this->get($this->portalUrl('/email/click/' . str_repeat('y', 40) . "/{$link}"))->assertRedirect();
        $this->assertSame(0, (int) DB::table('broadcast_links')->where('id', $foreign)->value('clicks'));
    }

    public function test_the_open_pixel_counts_the_first_open_once(): void
    {
        $log = $this->sentEmail();
        $this->get($this->portalUrl("/email/open/{$log->token}.png"))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->portalUrl("/email/open/{$log->token}.png"))->assertOk();
        $this->get($this->portalUrl('/email/open/' . str_repeat('z', 40) . '.png'))->assertOk(); // the image always comes back

        $row = DB::table('broadcast_logs')->find($log->id);
        $this->assertSame(2, (int) $row->open_count);
        $this->assertNotNull($row->first_opened_at);
        $this->assertSame(1, (int) DB::table('broadcast_campaigns')->where('id', $log->campaign_id)->value('opened_count'));
    }

    public function test_pictures_are_public_and_only_by_their_random_names(): void
    {
        $file = (string) Str::uuid() . '.png';
        @mkdir(storage_path('app/campaign-images'), 0775, true);
        file_put_contents(storage_path('app/campaign-images/' . $file), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        $this->get($this->portalUrl('/email/img/' . $file))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->portalUrl('/email/img/' . Str::uuid() . '.png'))->assertNotFound();
        $this->get($this->portalUrl('/email/img/..%2F..%2F.env'))->assertNotFound();
        @unlink(storage_path('app/campaign-images/' . $file));
    }
}
