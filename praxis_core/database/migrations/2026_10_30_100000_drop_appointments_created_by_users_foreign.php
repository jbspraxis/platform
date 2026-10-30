<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Website bookings of a free audit store the contact who booked (client_users) in appointments.created_by,
 * and HQ → Free audits reads it from there (LiveConsultation). Since client accounts moved out of users
 * (S2), the old foreign key from created_by to users refused every website booking, so the booking form
 * failed. created_by now holds either a staff id (HQ-created meetings) or a contact id (website bookings),
 * so it has no foreign key. Safe to run on any database: it only drops that key if it is there.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Schema::getForeignKeys('appointments') as $fk) {
            if ($fk['columns'] === ['created_by'] && $fk['foreign_table'] === 'users') {
                Schema::table('appointments', fn ($table) => $table->dropForeign($fk['name']));
            }
        }
    }

    public function down(): void
    {
        // Not restored: the key would refuse the website bookings already stored with a contact id.
    }
};
