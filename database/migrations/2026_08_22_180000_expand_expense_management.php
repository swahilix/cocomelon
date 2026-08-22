<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $defaults = [
            'Rent', 'Salaries', 'Electricity', 'Internet', 'Transport', 'Marketing',
            'Repairs', 'Office supplies', 'Taxes/fees', 'Miscellaneous', 'Operations', 'Utilities', 'Other',
        ];
        foreach ($defaults as $index => $name) {
            DB::table('expense_categories')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('expense_category_id')->nullable()->after('category')->constrained('expense_categories')->nullOnDelete();
            $table->string('title')->nullable()->after('expense_category_id');
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
            $table->string('receipt_path')->nullable()->after('payment_reference');
            $table->string('receipt_original_name')->nullable()->after('receipt_path');
            $table->boolean('is_recurring')->default(false)->after('receipt_original_name')->index();
            $table->string('recurring_interval', 30)->nullable()->after('is_recurring');
            $table->date('recurring_until')->nullable()->after('recurring_interval');
            $table->string('status', 20)->default('posted')->after('recurring_until')->index();
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->index(['expense_category_id', 'expense_date']);
        });

        $categories = DB::table('expense_categories')->pluck('id', 'name');
        DB::table('expenses')->orderBy('id')->chunkById(200, function ($expenses) use ($categories): void {
            foreach ($expenses as $expense) {
                $categoryId = $categories->get($expense->category)
                    ?? $categories->get('Miscellaneous')
                    ?? null;
                DB::table('expenses')->where('id', $expense->id)->update([
                    'expense_category_id' => $categoryId,
                    'title' => $expense->description,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['voided_by']);
            $table->dropIndex(['expense_category_id', 'expense_date']);
            $table->dropColumn([
                'expense_category_id', 'title', 'payment_reference', 'receipt_path', 'receipt_original_name',
                'is_recurring', 'recurring_interval', 'recurring_until', 'status', 'voided_at', 'voided_by', 'void_reason',
            ]);
        });

        Schema::dropIfExists('expense_categories');
    }
};
