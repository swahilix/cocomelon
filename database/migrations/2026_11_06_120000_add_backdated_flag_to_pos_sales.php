<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks sales entered later with a past date ("Record past sale"). They reduce
 * stock and count in reports for their date, but are not part of any cash
 * register session.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_sales') && ! Schema::hasColumn('pos_sales', 'is_backdated')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->boolean('is_backdated')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_sales') && Schema::hasColumn('pos_sales', 'is_backdated')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->dropIndex(['is_backdated']);
                $table->dropColumn('is_backdated');
            });
        }
    }
};
