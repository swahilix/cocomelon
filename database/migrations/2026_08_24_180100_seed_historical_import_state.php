<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('historical_import_states')) {
            return;
        }

        DB::table('historical_import_states')->insertOrIgnore([
            'id' => 1,
            'purchases_confirmed_at' => null,
            'purchases_confirmed_by' => null,
            'completed_at' => null,
            'completed_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The row contains user workflow state after launch. Never delete it on rollback.
    }
};
