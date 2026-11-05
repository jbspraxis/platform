<?php

namespace Tests\Feature\Campaigns;

use App\Livewire\Hq\Campaigns\Audiences;
use App\Models\Campaigns\Broadcast;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Marketing;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HQ › Campaigns › Audiences (docs/CAMPAIGNS.md 4.1 and 4.2): adding subscribers with how they agreed, the
 * do-not-email list by hand, and segments that scheduled broadcasts depend on.
 */
class AudiencesScreenTest extends TestCase
{
    use CampaignFixtures;

    public function test_subscribers_are_pasted_with_how_they_agreed_and_listed_addresses_are_skipped(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        Marketing::suppress('stopped@example.test', 'unsubscribed');
        $this->subscriber(['email' => 'already@example.test']);

        Livewire::test(Audiences::class)->set('tab', 'subscribers')
            ->set('addLines', "ada@example.test")->call('addSubscribers')->assertHasErrors('addConsent')
            ->set('addLines', "Ada@Example.test\nBrice Ngono <brice@example.test>\nchantal@example.test, Chantal, Mbida, Beta Santé (demo)\nalready@example.test\nstopped@example.test\nnot an address\n\n")
            ->set('addCountry', 'Cameroon')->set('addLanguage', '')->set('addConsent', 'Signed up at the Douala breakfast (test)')
            ->call('addSubscribers')->assertHasNoErrors()
            ->assertDispatched('praxis-toast', $this->toast('3 subscribers added; 1 already on the list; 1 skipped because they are on the do-not-email list; 1 line wasn’t an email address.'))
            ->assertSee('Brice Ngono')->assertSee('Beta Santé (demo)')->assertSee('Signed up at the Douala breakfast (test)');

        $brice = DB::table('marketing_subscribers')->where('email', 'brice@example.test')->first();
        $this->assertSame(['Brice', 'Ngono', 'Cameroon', 'hq'], [$brice->first_name, $brice->last_name, $brice->country, $brice->source]);
        $this->assertTrue(DB::table('marketing_subscribers')->where('email', 'ada@example.test')->exists());
        $this->assertFalse(DB::table('marketing_subscribers')->where('email', 'stopped@example.test')->exists());
    }

    public function test_an_address_added_by_hand_can_be_taken_off_but_an_unsubscribe_cannot(): void
    {
        $this->actingAs($this->staffWith(['broadcasts.view', 'broadcasts.create']));
        Livewire::test(Audiences::class)->set('tab', 'suppressed')
            ->set('blockEmail', 'not-an-email')->call('block')->assertHasErrors('blockEmail')
            ->set('blockEmail', ' Asked.By.Phone@Example.test ')->set('blockNote', 'Asked by phone (test)')->call('block')
            ->assertDispatched('praxis-toast', $this->toast('asked.by.phone@example.test will get no more marketing'))
            ->assertDontSee('Take off the list');
        $this->assertSame('manual', Marketing::entry('asked.by.phone@example.test')->reason);
        $id = DB::table('marketing_suppressions')->value('id');
        Livewire::test(Audiences::class)->call('unblock', $id)->assertForbidden();

        $this->actingAs($this->staff('sales', ['sales']));
        Marketing::suppress('gone@example.test', 'unsubscribed');
        $gone = DB::table('marketing_suppressions')->where('email', 'gone@example.test')->value('id');
        Livewire::test(Audiences::class)->set('tab', 'suppressed')
            ->call('unblock', $gone)->assertDispatched('praxis-toast', $this->toast('only they can change that'))
            ->call('unblock', $id)->assertDispatched('praxis-toast', $this->toast('can receive marketing again'));
        $this->assertTrue(Marketing::canEmail('asked.by.phone@example.test'));
        $this->assertFalse(Marketing::canEmail('gone@example.test'));
    }

    public function test_a_segment_a_scheduled_broadcast_goes_to_cannot_be_deleted(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $segment = $this->segment(['who' => ['leads']], 'Leads (test)');
        $draft = Broadcasts::create($sales, 'letter');
        $draft->forceFill(['segment_id' => $segment->id])->save();
        $scheduled = Broadcasts::create($sales, 'letter');
        $scheduled->forceFill(['segment_id' => $segment->id, 'status' => 'Scheduled', 'scheduled_at' => now()->addDay()])->save();

        Livewire::test(Audiences::class)->assertSee('Leads (test)')->call('deleteSegment', $segment->id)->assertDispatched('praxis-toast', $this->toast('a scheduled broadcast goes to it'));
        $this->assertNotNull($segment->fresh());

        $scheduled->forceFill(['status' => 'Draft'])->save();
        Livewire::test(Audiences::class)->call('deleteSegment', $segment->id)->assertDispatched('praxis-toast', $this->toast('Segment “Leads (test)” deleted'));
        $this->assertNull($segment->fresh());
        $this->assertNull(Broadcast::find($draft->id)->segment_id);
    }
}
