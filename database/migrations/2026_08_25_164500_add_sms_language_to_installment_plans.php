<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->string('sms_language', 2)->default('en')->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->dropColumn('sms_language');
        });
    }
};
