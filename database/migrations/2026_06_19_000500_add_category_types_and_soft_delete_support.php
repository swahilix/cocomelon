<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'type')) {
                $table->string('type', 20)->default('product')->index()->after('slug');
            }

            if (! Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('brands', function (Blueprint $table): void {
            if (! Schema::hasColumn('brands', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });

        Schema::table('brands', function (Blueprint $table): void {
            if (Schema::hasColumn('brands', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('categories', function (Blueprint $table): void {
            if (Schema::hasColumn('categories', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('categories', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
