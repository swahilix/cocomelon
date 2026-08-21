<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::create('merchant_sync_logs', function(Blueprint $t){$t->id();$t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();$t->enum('action',['insert','update','delete','test'])->default('insert');$t->enum('status',['pending','success','failed'])->default('pending')->index();$t->json('request_payload')->nullable();$t->json('response_payload')->nullable();$t->text('error_message')->nullable();$t->timestamp('synced_at')->nullable();$t->timestamps();});}
 public function down(): void {Schema::dropIfExists('merchant_sync_logs');}
};
