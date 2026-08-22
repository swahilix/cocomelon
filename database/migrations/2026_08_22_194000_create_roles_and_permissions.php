<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->default('General');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('is_admin')->constrained('roles')->nullOnDelete();
        });

        $now = now();
        $permissions = [
            ['View inventory', 'view_inventory', 'Inventory', 'View inventory balances, stock status and movement history.'],
            ['Adjust stock', 'adjust_stock', 'Inventory', 'Post opening balances and manual stock adjustments.'],
            ['Manage purchases', 'manage_purchases', 'Purchasing', 'Create purchases, payments, supplier returns and purchase changes.'],
            ['Receive purchases', 'receive_purchases', 'Purchasing', 'Receive ordered products into inventory.'],
            ['Manage suppliers', 'manage_suppliers', 'Purchasing', 'Create and maintain supplier records.'],
            ['Use POS', 'use_pos', 'Sales', 'Access the point of sale and complete counter sales.'],
            ['Discount POS sale', 'discount_pos', 'Sales', 'Apply overall POS discounts.'],
            ['Override POS price', 'override_pos_price', 'Sales', 'Change a product selling price inside POS.'],
            ['Refund sale', 'refund_sale', 'Sales', 'Post POS returns and refunds.'],
            ['Void sale', 'void_sale', 'Sales', 'Void completed POS sales.'],
            ['Manage expenses', 'manage_expenses', 'Finance', 'Create, categorize and void expenses.'],
            ['View costs', 'view_costs', 'Finance', 'View purchase costs and inventory valuation.'],
            ['View profit reports', 'view_profit_reports', 'Finance', 'View COGS, gross profit and operating result.'],
            ['Manage registers', 'manage_registers', 'Sales', 'Open, close and reconcile cash registers.'],
            ['View reports', 'view_reports', 'Reporting', 'View stock, purchasing and sales reports.'],
            ['Manage staff access', 'manage_staff', 'System', 'Create staff accounts and change roles and permissions.'],
        ];

        foreach ($permissions as [$name, $slug, $group, $description]) {
            DB::table('permissions')->insert([
                'name' => $name,
                'slug' => $slug,
                'group' => $group,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = [
            ['Administrator', 'administrator', 'Full system access.'],
            ['Manager', 'manager', 'Operational management without staff-access administration.'],
            ['Cashier', 'cashier', 'Counter sales, permitted refunds and cash-register operation.'],
            ['Stock Keeper', 'stock-keeper', 'Inventory, suppliers and purchasing operations.'],
            ['Accountant', 'accountant', 'Expenses, costs, profit and business reporting.'],
        ];

        foreach ($roles as [$name, $slug, $description]) {
            DB::table('roles')->insert([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $grants = [
            'administrator' => $permissionIds->keys()->all(),
            'manager' => ['view_inventory','adjust_stock','manage_purchases','receive_purchases','manage_suppliers','use_pos','discount_pos','override_pos_price','refund_sale','void_sale','manage_expenses','view_costs','view_profit_reports','manage_registers','view_reports'],
            'cashier' => ['use_pos','discount_pos','refund_sale','manage_registers'],
            'stock-keeper' => ['view_inventory','adjust_stock','manage_purchases','receive_purchases','manage_suppliers','view_costs','view_reports'],
            'accountant' => ['manage_expenses','view_costs','view_profit_reports','view_reports'],
        ];

        foreach ($grants as $roleSlug => $slugs) {
            foreach ($slugs as $permissionSlug) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleIds[$roleSlug],
                    'permission_id' => $permissionIds[$permissionSlug],
                ]);
            }
        }

        DB::table('users')->where('is_admin', true)->update(['role_id' => $roleIds['administrator']]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
