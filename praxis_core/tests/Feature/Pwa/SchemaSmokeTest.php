<?php

namespace Tests\Feature\Pwa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_tables_exist(): void
    {
        foreach (['push_subscriptions', 'push_preferences', 'push_deliveries', 'pwa_settings'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }
}
