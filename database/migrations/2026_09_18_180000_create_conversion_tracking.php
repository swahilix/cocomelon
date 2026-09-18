<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversion_events')) {
            Schema::create('conversion_events', function (Blueprint $table): void {
                $table->id();
                $table->string('event_type', 40)->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('session_key', 64)->nullable()->index();
                $table->string('source', 30)->default('direct')->index();
                $table->string('source_detail', 190)->nullable();
                $table->string('landing_path', 500)->nullable();
                $table->string('utm_source', 190)->nullable();
                $table->string('utm_medium', 190)->nullable();
                $table->string('utm_campaign', 190)->nullable()->index();
                $table->decimal('value', 15, 2)->nullable();
                $table->unsignedInteger('quantity')->nullable();
                $table->date('event_day')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['event_type', 'event_day', 'source'], 'conversion_event_source_index');
                $table->index(['source', 'event_day'], 'conversion_source_day_index');
                $table->index(['product_id', 'event_type', 'event_day'], 'conversion_product_event_index');
            });
        }

        if (Schema::hasTable('orders')) {
            $missing = [
                'acquisition_source' => ! Schema::hasColumn('orders', 'acquisition_source'),
                'acquisition_detail' => ! Schema::hasColumn('orders', 'acquisition_detail'),
                'utm_source' => ! Schema::hasColumn('orders', 'utm_source'),
                'utm_medium' => ! Schema::hasColumn('orders', 'utm_medium'),
                'utm_campaign' => ! Schema::hasColumn('orders', 'utm_campaign'),
                'attribution_session_key' => ! Schema::hasColumn('orders', 'attribution_session_key'),
            ];

            if (in_array(true, $missing, true)) {
                Schema::table('orders', function (Blueprint $table) use ($missing): void {
                    if ($missing['acquisition_source']) {
                        $table->string('acquisition_source', 30)->nullable()->after('user_id')->index();
                    }
                    if ($missing['acquisition_detail']) {
                        $table->string('acquisition_detail', 190)->nullable()->after('acquisition_source');
                    }
                    if ($missing['utm_source']) {
                        $table->string('utm_source', 190)->nullable()->after('acquisition_detail');
                    }
                    if ($missing['utm_medium']) {
                        $table->string('utm_medium', 190)->nullable()->after('utm_source');
                    }
                    if ($missing['utm_campaign']) {
                        $table->string('utm_campaign', 190)->nullable()->after('utm_medium')->index();
                    }
                    if ($missing['attribution_session_key']) {
                        $table->string('attribution_session_key', 64)->nullable()->after('utm_campaign')->index();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            $columns = collect([
                'acquisition_source',
                'acquisition_detail',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'attribution_session_key',
            ])->filter(fn (string $column): bool => Schema::hasColumn('orders', $column))->all();

            if ($columns !== []) {
                Schema::table('orders', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        Schema::dropIfExists('conversion_events');
    }
};
