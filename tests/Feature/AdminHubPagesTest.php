<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminHubPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_tools_and_settings_pages(): void
    {
        $admin = User::create([
            'name' => 'Hub Admin',
            'email' => 'hub-admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/tools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tools/Index'));

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Settings/Index'));
    }

    public function test_customers_cannot_open_tools_or_settings(): void
    {
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'hub-customer@example.test',
            'password' => 'Password123',
            'is_admin' => false,
        ]);

        $this->actingAs($customer)->get('/admin/tools')->assertForbidden();
        $this->actingAs($customer)->get('/admin/settings')->assertForbidden();
    }
}
