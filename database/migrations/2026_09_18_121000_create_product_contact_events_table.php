<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_contact_events')) {
            return;
        }

        Schema::create('product_contact_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('channel', 30)->default('whatsapp')->index();
            $table->string('placement', 40)->nullable()->index();
            $table->string('session_key', 120)->nullable()->index();
            $table->string('referrer_path', 500)->nullable();
            $table->string('user_agent_family', 80)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_contact_events');
    }
};
