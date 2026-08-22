<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Some production installs use a legacy physical type for orders.id/users.id.
        // Keep application-level relationships, but use indexed IDs instead of
        // database foreign keys so this migration is compatible with both schemas.
        // A failed MySQL FK ALTER can also leave the table itself behind, so make
        // this migration safe to retry.
        if (! Schema::hasTable('order_status_histories')) {
            Schema::create('order_status_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('status', 40)->index();
                $table->string('payment_status', 40)->index();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
