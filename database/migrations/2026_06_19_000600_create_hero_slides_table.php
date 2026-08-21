<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing deployments may already contain this table even when the
        // migration row was not recorded. Preserve the data and allow Laravel
        // to mark this migration as completed instead of attempting to recreate it.
        if (Schema::hasTable('hero_slides')) {
            return;
        }

        Schema::create('hero_slides', function (Blueprint $table): void {
            $table->id();
            $table->string('item_type', 20)->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('eyebrow', 100)->nullable();
            $table->string('badge', 120)->nullable();
            $table->string('headline', 191)->nullable();
            $table->text('description')->nullable();
            $table->string('primary_label', 60)->nullable();
            $table->string('secondary_label', 60)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
