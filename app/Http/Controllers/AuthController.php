<?php

namespace App\Http\Controllers;

use App\Services\CustomerOtpSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function loginForm(Request $request, CustomerAuthController $customer): Response|RedirectResponse
    {
        return $customer->loginForm($request);
    }

    public function login(Request $request, CustomerAuthController $customer, CustomerOtpSmsService $sms): RedirectResponse
    {
        return $customer->sendCode($request, $sms);
    }

    public function staffLoginForm(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->to($request->user()->is_admin ? '/admin' : '/account');
        }

        return Inertia::render('Auth/Login');
    }

    public function staffLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], (bool) ($credentials['remember'] ?? false))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        if (! $request->user()?->is_admin) {
            Auth::logout();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'This sign-in page is for Prinx staff only.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }

    public function registerForm(): RedirectResponse
    {
        return redirect()->route('login');
    }

    public function register(): RedirectResponse
    {
        return redirect()->route('login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
