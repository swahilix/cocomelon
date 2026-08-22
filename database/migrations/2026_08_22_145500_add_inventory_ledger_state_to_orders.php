<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_posted_at')->nullable()->after('status')->index();
            $table->timestamp('inventory_restored_at')->nullable()->after('inventory_posted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['inventory_posted_at']);
            $table->dropIndex(['inventory_restored_at']);
            $table->dropColumn(['inventory_posted_at', 'inventory_restored_at']);
        });
    }
};
