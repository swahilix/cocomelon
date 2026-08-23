<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('service_id');
            $table->string('image_alt', 191)->nullable()->after('image_path');
            $table->string('link_url', 2048)->nullable()->after('image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'image_alt', 'link_url']);
        });
    }
};
