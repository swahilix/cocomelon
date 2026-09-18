<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_settings')) {
            DB::table('business_settings')->where('id', 1)->update([
                'business_name' => 'Prinx Tech',
                'legal_name' => 'Prinx Tech',
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'meta_title')) {
            DB::table('products')
                ->where('meta_title', 'like', '%Prinx Electronics%')
                ->update([
                    'meta_title' => DB::raw("REPLACE(meta_title, 'Prinx Electronics', 'Prinx Tech')"),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_settings')) {
            DB::table('business_settings')->where('id', 1)->update([
                'business_name' => 'Prinx Electronics',
                'legal_name' => 'Prinx Electronics',
                'updated_at' => now(),
            ]);
        }
    }
};
