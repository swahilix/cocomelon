<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_the_admin_panel(): void
    {
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'password' => 'Password123',
            'is_admin' => false,
        ]);
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
