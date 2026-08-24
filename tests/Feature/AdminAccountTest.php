<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_update_basic_account_details(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Account')
                ->where('account.name', 'Administrator')
                ->where('account.email', 'admin@example.test')
            );

        $this->actingAs($admin)
            ->patch('/admin/account/profile', [
                'name' => 'Prinx Administrator',
                'email' => 'admin@example.test',
                'phone' => '+255700000000',
                'current_password' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Prinx Administrator',
            'email' => 'admin@example.test',
            'phone' => '+255700000000',
        ]);
    }

    public function test_email_change_requires_the_current_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/admin/account')
            ->patch('/admin/account/profile', [
                'name' => $admin->name,
                'email' => 'new-admin@example.test',
                'phone' => $admin->phone,
                'current_password' => '',
            ])
            ->assertRedirect('/admin/account')
            ->assertSessionHasErrors('current_password');

        $this->assertSame('admin@example.test', $admin->fresh()->email);

        $this->actingAs($admin)
            ->patch('/admin/account/profile', [
                'name' => $admin->name,
                'email' => 'new-admin@example.test',
                'phone' => $admin->phone,
                'current_password' => 'Password123',
            ])
            ->assertRedirect();

        $this->assertSame('new-admin@example.test', $admin->fresh()->email);
    }

    public function test_admin_password_change_requires_current_password_and_updates_hash(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/admin/account')
            ->patch('/admin/account/password', [
                'current_password' => 'WrongPassword1',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ])
            ->assertRedirect('/admin/account')
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Password123', $admin->fresh()->password));

        $this->actingAs($admin)
            ->patch('/admin/account/password', [
                'current_password' => 'Password123',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword456', $admin->fresh()->password));
        $this->assertFalse(Hash::check('Password123', $admin->fresh()->password));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'phone' => null,
            'password' => 'Password123',
            'is_admin' => true,
        ]);
    }
}
