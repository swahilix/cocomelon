<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AccessController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Access/Index', [
            'staff' => User::query()
                ->with('role:id,name,slug')
                ->where('is_admin', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'is_admin', 'role_id', 'created_at']),
            'roles' => Role::query()
                ->with(['permissions:id,name,slug,group'])
                ->withCount('users')
                ->orderBy('id')
                ->get(),
            'permissions' => Permission::query()->orderBy('group')->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        User::query()->create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_admin' => true,
            'role_id' => $validated['role_id'],
        ]);

        return back()->with('success', 'Staff account created successfully.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:60'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $payload = [
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'role_id' => $validated['role_id'],
            'is_admin' => true,
        ];
        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return back()->with('success', 'Staff access updated successfully.');
    }

    public function revokeUser(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->is($user)) {
            return back()->withErrors(['staff' => 'You cannot remove your own admin-panel access.']);
        }

        $user->update(['is_admin' => false, 'role_id' => null]);

        return back()->with('success', 'Admin-panel access removed. The user account itself was preserved.');
    }

    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        if ($role->slug === 'administrator') {
            return back()->withErrors(['role' => 'Administrator permissions are fixed to full system access.']);
        }

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);

        try {
            DB::transaction(function () use ($role, $validated): void {
                $ids = Permission::query()->whereIn('slug', $validated['permissions'] ?? [])->pluck('id');
                $role->permissions()->sync($ids);
            });
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to update role permissions.', previous: $e);
        }

        return back()->with('success', $role->name.' permissions updated.');
    }
}
