<?php

namespace Tests\Feature\Campaigns;

use App\Support\Campaigns\Audience;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\SegmentFilters;
use App\Support\MailLocale;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who a segment reaches (docs/CAMPAIGNS.md 4.2): clients, leads, subscribers and Kaizen writers who haven't
 * unsubscribed, one per address, each in their language; filters narrow their own groups only.
 */
class AudienceTest extends TestCase
{
    use CampaignFixtures;

    protected function tearDown(): void
    {
        SegmentFilters::assumeLeadStatus(null);
        parent::tearDown();
    }

    private function emails(array $rules): array
    {
        return Audience::recipients($rules)->pluck('email')->sort()->values()->all();
    }

    public function test_only_people_who_know_us_and_have_not_unsubscribed(): void
    {
        $client = $this->org(['status' => 'Active Client']);
        $past = $this->org(['status' => 'Past Client']);
        $lead = $this->org(['status' => 'Lead']);
        $this->contact($client, ['email' => 'client@example.test']);
        $this->contact($past, ['email' => 'past@example.test']);
        $this->contact($lead, ['email' => 'lead@example.test']);
        $this->subscriber(['email' => 'sub@example.test']);
        $kaizen = $this->org(['name' => 'Kaizen Public Contributors', 'status' => 'Internal']);
        $this->contact($kaizen, ['email' => 'writer@example.test', 'lifecycle_stage' => 'Public Contributor']);

        // Never: suspended clients, JBS Praxis itself, test organisations, deleted or suspended contacts, no address,
        // unsubscribed contacts and subscribers, anything on the do-not-email list
        $this->contact($this->org(['status' => 'Suspended']), ['email' => 'suspended.org@example.test']);
        $this->contact($this->org(['status' => 'Internal']), ['email' => 'internal@example.test']);
        $this->contact($this->org(['status' => 'Lead', 'is_test' => 1]), ['email' => 'qa@example.test']);
        $this->contact($lead, ['email' => 'deleted@example.test', 'deleted_at' => now()]);
        $this->contact($lead, ['email' => 'suspended@example.test', 'status' => 'Suspended']);
        $this->contact($lead, ['email' => '']); // Leads PR 1 lets it be empty (null): both are left out
        $this->contact($lead, ['email' => 'old.unsub@example.test', 'is_subscribed' => 0]);
        $this->subscriber(['email' => 'sub.gone@example.test', 'unsubscribed_at' => now()]);
        $this->contact($client, ['email' => 'listed@example.test']);
        Marketing::suppress('listed@example.test', 'manual');

        $this->assertSame(['client@example.test', 'past@example.test'], $this->emails(['who' => ['clients']]));
        $this->assertSame(['lead@example.test'], $this->emails(['who' => ['leads']]));
        $this->assertSame(['sub@example.test', 'writer@example.test'], $this->emails(['who' => ['subscribers']]));

        $all = Audience::summary(['who' => ['clients', 'leads', 'subscribers']]);
        $this->assertSame(5, $all['total']);
        $this->assertSame(['client' => 2, 'lead' => 1, 'kaizen' => 1, 'subscriber' => 1], $all['by_kind']);
        $this->assertSame(3, $all['left_out'], 'The unsubscribed contact and subscriber, and the listed address');
        $this->assertSame(0, Audience::summary(['who' => []])['total']);
    }

    public function test_one_email_per_address_the_strongest_tie_wins(): void
    {
        $client = $this->org(['status' => 'Active Client', 'name' => 'Acme Ltd (test)']);
        $this->contact($client, ['email' => 'Same@Example.test', 'first_name' => 'Ada']);
        $this->subscriber(['email' => 'same@example.test', 'first_name' => 'Someone']);

        $people = Audience::recipients(['who' => ['clients', 'subscribers']]);
        $this->assertCount(1, $people);
        $this->assertSame('client', $people[0]['kind']);
        $this->assertSame('same@example.test', $people[0]['email']);
        $this->assertSame('Acme Ltd (test)', $people[0]['company']);
    }

    public function test_filters_narrow_their_groups_and_leave_out_people_who_lack_the_field(): void
    {
        $bank = $this->org(['status' => 'Lead', 'industry' => 'Banking', 'country' => 'Cameroon', 'preferred_language' => null]);
        $logistics = $this->org(['status' => 'Lead', 'industry' => 'Logistics', 'country' => 'Nigeria']);
        $active = $this->org(['status' => 'Active Client', 'industry' => 'Banking']);
        $past = $this->org(['status' => 'Past Client', 'industry' => 'Banking']);
        $this->contact($bank, ['email' => 'bank.lead@example.test']);
        $this->contact($logistics, ['email' => 'log.lead@example.test']);
        $this->contact($active, ['email' => 'active@example.test']);
        $this->contact($past, ['email' => 'past@example.test']);
        $this->subscriber(['email' => 'cm.sub@example.test', 'country' => 'Cameroon']);

        // Industry: subscribers have none, so they are left out while it is set
        $this->assertSame(['active@example.test', 'bank.lead@example.test', 'past@example.test'],
            $this->emails(['who' => ['clients', 'leads', 'subscribers'], 'filters' => ['industry' => ['Banking']]]));
        // Country applies to subscribers too
        $this->assertSame(['bank.lead@example.test', 'cm.sub@example.test'],
            $this->emails(['who' => ['clients', 'leads', 'subscribers'], 'filters' => ['country' => ['Cameroon']]]));
        // Client status narrows clients only: leads stay
        $this->assertSame(['active@example.test', 'bank.lead@example.test', 'log.lead@example.test'],
            $this->emails(['who' => ['clients', 'leads'], 'filters' => ['client_status' => ['Active Client']]]));
        // Owner
        $owner = $this->staffWith(['crm.view'], 'Olu');
        DB::table('organizations')->where('id', $logistics)->update(['assigned_to' => $owner->id]);
        $this->assertSame(['log.lead@example.test'], $this->emails(['who' => ['leads'], 'filters' => ['owner' => [$owner->id]]]));
        $this->assertArrayHasKey($owner->id, SegmentFilters::options('owner'));
        // Language
        $this->assertSame(['bank.lead@example.test', 'cm.sub@example.test'],
            $this->emails(['who' => ['leads', 'subscribers'], 'filters' => ['language' => ['fr']]]));
        // Unknown filters are dropped from the stored rules
        $this->assertSame(['who' => ['leads'], 'filters' => []], Audience::rules(['who' => ['leads', 'prospects'], 'filters' => ['tags' => ['x'], 'industry' => []]]));
    }

    public function test_each_person_gets_their_language_then_their_organisations_then_their_countrys(): void
    {
        $this->subscriber(['email' => 'own.fr@example.test', 'language' => 'fr', 'country' => 'Nigeria']);
        $this->subscriber(['email' => 'country.fr@example.test', 'country' => 'Côte d’Ivoire']);
        $this->subscriber(['email' => 'country.en@example.test', 'country' => 'Ghana']);
        $this->contact($this->org(['preferred_language' => 'French (FR)', 'country' => 'Nigeria']), ['email' => 'org.fr@example.test']);
        $this->contact($this->org(['preferred_language' => null, 'country' => 'Cameroun']), ['email' => 'cm.fr@example.test']);
        $this->contact($this->org(['preferred_language' => 'EN', 'country' => 'Cameroon']), ['email' => 'chose.en@example.test']);

        $locales = Audience::recipients(['who' => ['leads', 'subscribers']])->pluck('locale', 'email')->all();
        $this->assertSame('fr', $locales['own.fr@example.test']);
        $this->assertSame('fr', $locales['country.fr@example.test']);
        $this->assertSame('en', $locales['country.en@example.test']);
        $this->assertSame('fr', $locales['org.fr@example.test']);
        $this->assertSame('fr', $locales['cm.fr@example.test']);
        $this->assertSame('en', $locales['chose.en@example.test'], 'A stored English preference wins over the country');

        $this->assertSame('fr', MailLocale::fromCountry('SN'));
        $this->assertSame('fr', MailLocale::fromCountry('République démocratique du Congo'));
        $this->assertNull(MailLocale::fromCountry('Nigeria'));
        $this->assertNull(MailLocale::fromCountry(null));
        $this->assertSame('en', MailLocale::forContact(null, null, 'Kenya'));
    }

    public function test_the_lead_status_filter_takes_over_once_leads_pr_1_adds_the_column(): void
    {
        SegmentFilters::assumeLeadStatus(false);
        $this->assertArrayHasKey('lead_stage', SegmentFilters::definitions());
        $this->assertArrayNotHasKey('lead_status', SegmentFilters::definitions());

        SegmentFilters::assumeLeadStatus(true);
        $definitions = SegmentFilters::definitions();
        $this->assertArrayHasKey('lead_status', $definitions);
        $this->assertArrayNotHasKey('lead_stage', $definitions);
        $this->assertSame('o.lead_status', $definitions['lead_status']['column']);
        $this->assertSame(['leads'], $definitions['lead_status']['groups']);
        $this->assertSame('New', SegmentFilters::options('lead_status')['new']);
    }

    public function test_a_segment_reads_as_words(): void
    {
        $this->assertSame('Clients and leads · Industry: Banking · Language: French',
            Audience::describe(['who' => ['clients', 'leads'], 'filters' => ['industry' => ['Banking'], 'language' => ['fr']]]));
        $this->assertSame('Clients, leads and subscribers', Audience::describe(['who' => ['clients', 'leads', 'subscribers']]));
        $this->assertSame('Nobody chosen yet', Audience::describe([]));
    }
}
