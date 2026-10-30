<?php

namespace Tests\Feature;

use App\Livewire\Hq\Intelligence\BroadcastLedger;
use App\Mail\IntelligenceBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HQ → Broadcasts: a campaign has a language (en or fr, English by default), chosen in the composer; the email
 * frame (sign-off, footer) of every email it sends follows it. Runs on a throwaway MySQL database (see
 * UsesThrowawayDatabase).
 */
class BroadcastLanguageTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'Acme Ltd (test)', 'status' => 'Lead', 'preferred_language' => 'EN', 'industry' => 'Logistics']);
        $this->contact = (string) Str::uuid();
        $this->insertRow('client_users', [
            'id' => $this->contact, 'organization_id' => $org, 'first_name' => 'Ada', 'last_name' => 'Test',
            'email' => 'ada.' . Str::random(6) . '@example.test', 'status' => 'Active', 'is_subscribed' => 1, 'deleted_at' => null,
        ]);
    }

    private function compose(?string $language)
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $composer = Livewire::test(BroadcastLedger::class)
            ->set('subject', 'Trois vérifications avant votre clôture')
            ->set('enhancedBody', '<p>Bonjour,</p><p>Voici trois vérifications pour {{ company_name }}.</p>')
            ->set('selectedTargets', [$this->contact]);
        if ($language !== null) {
            $composer->set('language', $language);
        }

        return $composer->call('dispatchCampaign');
    }

    public function test_the_composer_stores_the_language_english_by_default(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(BroadcastLedger::class)->assertSet('language', 'en')
            ->assertSeeHtml('<label class="label" for="bl-language">Language</label>')->assertSee('French');

        $this->compose(null)->assertHasNoErrors();
        $this->assertSame('en', DB::table('broadcast_campaigns')->value('language'));
    }

    public function test_a_french_broadcast_goes_out_in_a_french_frame(): void
    {
        Mail::fake();
        $this->compose('fr')->assertHasNoErrors();
        $campaign = DB::table('broadcast_campaigns')->first();
        $this->assertSame('fr', $campaign->language);

        $this->artisan('broadcast:process')->assertSuccessful();

        Mail::assertSent(IntelligenceBroadcast::class, function (IntelligenceBroadcast $mail) {
            $this->assertSame('fr', $mail->locale);
            $html = $mail->render();
            $this->assertStringContainsString(e(__('emails.broadcast.reason', [], 'fr')), $html);
            $this->assertStringNotContainsString(e(__('emails.broadcast.reason', [], 'en')), $html);
            $this->assertStringContainsString('Voici trois vérifications pour Acme Ltd (test).', $html);

            return true;
        });
        $this->assertSame('Sent', DB::table('broadcast_logs')->value('status'));
    }

    public function test_an_english_broadcast_goes_out_in_an_english_frame(): void
    {
        Mail::fake();
        $this->compose('en')->assertHasNoErrors();

        $this->artisan('broadcast:process')->assertSuccessful();

        Mail::assertSent(IntelligenceBroadcast::class, fn (IntelligenceBroadcast $mail) => $mail->locale === 'en'
            && str_contains($mail->render(), e(__('emails.broadcast.reason', [], 'en'))));
    }

    public function test_only_english_or_french(): void
    {
        $this->compose('de')->assertHasErrors(['language' => 'in']);
        $this->assertSame(0, DB::table('broadcast_campaigns')->count());
    }

    public function test_campaigns_sent_before_count_as_english(): void
    {
        $id = (string) Str::uuid();
        DB::table('broadcast_campaigns')->insert([
            'id' => $id, 'subject' => 'Old campaign', 'body_html' => '<p>Hello</p>', 'status' => 'Completed',
            'created_by' => $this->staff('sales', ['sales'])->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('en', DB::table('broadcast_campaigns')->where('id', $id)->value('language'));
    }
}
