<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role:id,name');

        return Inertia::render('Admin/Account', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role?->name ?: 'Administrator',
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $email = strtolower(trim((string) $request->input('email')));
        $emailChanged = $email !== strtolower((string) $user->email);

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => $email,
            'phone' => trim((string) $request->input('phone')) ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password'],
        ], [
            'current_password.required' => 'Enter your current password to change the login email.',
            'current_password.current_password' => 'The current password is incorrect.',
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ])->save();

        return back()->with('success', 'Admin account details updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '<>', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully. Other signed-in sessions were closed.');
    }
}
