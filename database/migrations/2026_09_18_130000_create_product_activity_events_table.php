<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_activity_events')) {
            return;
        }

        Schema::create('product_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_type', 30)->index();
            $table->string('session_key', 64)->index();
            $table->date('event_day')->index();
            $table->timestamps();

            $table->unique(['product_id', 'event_type', 'session_key', 'event_day'], 'product_activity_daily_unique');
            $table->index(['event_type', 'event_day', 'product_id'], 'product_activity_trending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_activity_events');
    }
};
