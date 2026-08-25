<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->string('signed_contract_path')->nullable()->after('notes');
            $table->string('signed_contract_original_name')->nullable()->after('signed_contract_path');
            $table->string('signed_contract_mime', 120)->nullable()->after('signed_contract_original_name');
            $table->timestamp('signed_contract_uploaded_at')->nullable()->after('signed_contract_mime');
            $table->unsignedBigInteger('signed_contract_uploaded_by')->nullable()->index()->after('signed_contract_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->dropIndex(['signed_contract_uploaded_by']);
            $table->dropColumn([
                'signed_contract_path',
                'signed_contract_original_name',
                'signed_contract_mime',
                'signed_contract_uploaded_at',
                'signed_contract_uploaded_by',
            ]);
        });
    }
};
