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
        if (Schema::hasTable('product_reels')) {
            return;
        }

        Schema::create('product_reels', function (Blueprint $table): void {
            $table->id();
            $table->string('item_type', 20)->default('product')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160)->nullable();
            $table->text('caption')->nullable();
            $table->string('media_type', 20)->default('image')->index();
            $table->string('media_path', 255)->nullable();
            $table->text('external_url')->nullable();
            $table->string('cover_image', 255)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->unsignedTinyInteger('display_seconds')->default(6);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reels');
    }
};
