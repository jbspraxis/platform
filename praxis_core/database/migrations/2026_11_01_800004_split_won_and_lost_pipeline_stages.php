<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HQ › Leads: the last pipeline stage was one value, 'Won / Lost'. It becomes two, 'Won' and 'Lost', so the
 * register can show which is which. An organisation that is (or was) a client won; the others were lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizations')->where('pipeline_stage', 'Won / Lost')->whereIn('status', ['Active Client', 'Past Client', 'Active'])
            ->update(['pipeline_stage' => 'Won']);
        DB::table('organizations')->where('pipeline_stage', 'Won / Lost')->update(['pipeline_stage' => 'Lost']);
    }

    public function down(): void
    {
        DB::table('organizations')->whereIn('pipeline_stage', ['Won', 'Lost'])->update(['pipeline_stage' => 'Won / Lost']);
    }
};
