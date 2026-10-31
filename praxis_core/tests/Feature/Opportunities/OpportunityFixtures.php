<?php

namespace Tests\Feature\Opportunities;

use App\Models\Opportunity;
use Illuminate\Support\Str;

/** Records in today's shapes for the opportunity tests (needs UsesThrowawayDatabase) */
trait OpportunityFixtures
{
    protected function organisation(array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('organizations', $values + ['id' => $id, 'name' => 'Acme ' . Str::random(4), 'status' => 'Lead', 'preferred_language' => 'EN']);

        return $values['id'] ?? $id;
    }

    protected function opportunityFor(string $organisationId, array $values = []): Opportunity
    {
        return Opportunity::create($values + [
            'organization_id' => $organisationId,
            'title' => 'Finance system',
            'stage' => 'discovery',
            'language' => 'en',
            'source' => 'manual',
        ]);
    }

    protected function clientContact(string $organisationId, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('client_users', $values + [
            'id' => $id, 'organization_id' => $organisationId, 'first_name' => 'Ada', 'last_name' => 'Client',
            'email' => 'ada.' . Str::random(6) . '@client.test', 'is_primary_client' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $values['id'] ?? $id;
    }
}
