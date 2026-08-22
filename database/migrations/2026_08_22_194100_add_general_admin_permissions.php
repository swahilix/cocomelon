<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['Manage storefront', 'manage_storefront', 'Store', 'Manage products, categories, brands, homepage carousel and product reels.'],
            ['Manage services', 'manage_services', 'Store', 'Manage services and service catalogue content.'],
            ['Manage orders', 'manage_orders', 'Sales', 'Review and update online customer orders.'],
            ['Manage service requests', 'manage_inquiries', 'Sales', 'Review and update customer service requests.'],
            ['Manage Merchant integration', 'manage_merchant', 'System', 'Manage Google Merchant settings and synchronization.'],
            ['View system monitoring', 'view_system_monitor', 'System', 'View scheduler, queue and application monitoring information.'],
        ];

        foreach ($permissions as [$name, $slug, $group, $description]) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'group' => $group, 'description' => $description, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $grants = [
            'administrator' => ['manage_storefront','manage_services','manage_orders','manage_inquiries','manage_merchant','view_system_monitor'],
            'manager' => ['manage_storefront','manage_services','manage_orders','manage_inquiries','manage_merchant'],
        ];

        foreach ($grants as $roleSlug => $slugs) {
            if (! isset($roleIds[$roleSlug])) continue;
            foreach ($slugs as $slug) {
                if (! isset($permissionIds[$slug])) continue;
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleIds[$roleSlug], 'permission_id' => $permissionIds[$slug],
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = ['manage_storefront','manage_services','manage_orders','manage_inquiries','manage_merchant','view_system_monitor'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
