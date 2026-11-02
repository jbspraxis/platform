<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\TestCase as AppTestCase;

/** Tests that rebuild the database only do it on SQLite or a throwaway MySQL database (Tests\TestCase::setUpTraits) */
class RebuildGuardTest extends TestCase
{
    public function test_only_a_throwaway_mysql_database_may_be_rebuilt(): void
    {
        foreach (['praxis_test', 'praxis_test_qa', 'praxis_pr2b_fix_hq', 'praxis_pr5'] as $name) {
            $this->assertTrue(AppTestCase::mayRebuild('mysql', $name), $name);
        }
        foreach (['praxis_local', 'praxis_qa_dev', 'smartqaq_praxis', 'praxis_production', '', 'praxis_testing-live'] as $name) {
            $this->assertFalse(AppTestCase::mayRebuild('mysql', $name), $name);
            $this->assertFalse(AppTestCase::mayRebuild('mariadb', $name), $name);
        }
        // phpunit.xml's SQLite in memory, as before
        $this->assertTrue(AppTestCase::mayRebuild('sqlite', ':memory:'));
    }
}
