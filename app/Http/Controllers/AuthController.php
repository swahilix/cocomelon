<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function loginForm(): Response { return Inertia::render('Auth/Login'); }
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string'],'remember'=>['nullable','boolean']]);
        if (!Auth::attempt(['email'=>$credentials['email'],'password'=>$credentials['password']], (bool)($credentials['remember'] ?? false))) return back()->withErrors(['email'=>'The provided credentials do not match our records.'])->onlyInput('email');
        $request->session()->regenerate();
        return redirect()->intended($request->user()->is_admin ? route('admin.dashboard') : route('home'));
    }
    public function registerForm(): Response { return Inertia::render('Auth/Register'); }
    public function register(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:190','unique:users'],'phone'=>['nullable','string','max:30'],'password'=>['required','confirmed',Password::min(8)->letters()->numbers()]]);
        $user=User::create($data); Auth::login($user); $request->session()->regenerate(); return redirect()->route('home')->with('success','Account created successfully.');
    }
    public function logout(Request $request): RedirectResponse { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('home'); }
}
