<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'meta_title')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('meta_title', 190)->nullable();
                $table->string('meta_description', 180)->nullable();
            });

            $city = Schema::hasTable('business_settings')
                ? (DB::table('business_settings')->where('id', 1)->value('city') ?: 'Dodoma')
                : 'Dodoma';

            DB::table('categories')
                ->where('type', 'product')
                ->orderBy('id')
                ->get(['id', 'name', 'description'])
                ->each(function ($category) use ($city): void {
                    DB::table('categories')->where('id', $category->id)->update([
                        'meta_title' => $category->name.' in '.$city.' | Prinx Tech',
                        'meta_description' => substr(
                            $category->description ?: 'Shop '.$category->name.' in '.$city.' from Prinx Tech. Browse current products, prices and availability.',
                            0,
                            180,
                        ),
                    ]);
                });
        }

        if (! Schema::hasTable('buying_guides')) {
            Schema::create('buying_guides', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->string('title', 180);
                $table->string('slug', 200)->unique();
                $table->string('excerpt', 500)->nullable();
                $table->longText('body');
                $table->string('cover_image')->nullable();
                $table->json('related_product_ids')->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->boolean('featured')->default(false)->index();
                $table->string('meta_title', 190)->nullable();
                $table->string('meta_description', 180)->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'published_at', 'category_id'], 'buying_guides_public_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buying_guides');

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'meta_title')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn(['meta_title', 'meta_description']);
            });
        }
    }
};
