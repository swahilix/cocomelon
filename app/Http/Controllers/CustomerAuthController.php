<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\CustomerOtpSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CustomerAuthController extends Controller
{
    public function loginForm(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->to($request->user()->is_admin ? '/admin' : '/account');
        }

        return Inertia::render('Store/CustomerLogin');
    }

    public function sendCode(Request $request, CustomerOtpSmsService $sms): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);
        $rateKey = 'customer-otp-send:'.sha1($phone.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'phone' => "Too many code requests. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($rateKey, 300);
        $code = (string) random_int(100000, 999999);

        Cache::put($this->otpKey($phone), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(5));

        try {
            $sms->send($phone, $code);
        } catch (RuntimeException $exception) {
            Cache::forget($this->otpKey($phone));
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        $request->session()->put('customer_login_phone', $phone);

        return redirect()->route('customer.login.verify')
            ->with('success', 'Verification code sent to '.$this->maskedPhone($phone).'.');
    }

    public function verifyForm(Request $request): Response|RedirectResponse
    {
        $phone = $request->session()->get('customer_login_phone');
        if (! $phone) {
            return redirect()->route('login');
        }

        return Inertia::render('Store/CustomerVerify', [
            'phone' => $this->maskedPhone($phone),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = (string) $request->session()->get('customer_login_phone', '');
        if (! $phone) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $key = $this->otpKey($phone);
        $record = Cache::get($key);

        if (! is_array($record) || empty($record['hash'])) {
            throw ValidationException::withMessages(['code' => 'This verification code has expired. Request a new code.']);
        }

        $attempts = (int) ($record['attempts'] ?? 0);
        if ($attempts >= 5) {
            Cache::forget($key);
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($validated['code'], $record['hash'])) {
            $record['attempts'] = $attempts + 1;
            Cache::put($key, $record, now()->addMinutes(5));
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }

        $user = $this->findOrCreateCustomer($phone);
        $this->claimOrders($user, $phone);

        Cache::forget($key);
        $request->session()->forget('customer_login_phone');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended('/account')->with('success', 'Welcome to your Prinx account.');
    }

    public function resend(Request $request, CustomerOtpSmsService $sms): RedirectResponse
    {
        $phone = (string) $request->session()->get('customer_login_phone', '');
        if (! $phone) {
            return redirect()->route('login');
        }

        $request->merge(['phone' => $phone]);
        return $this->sendCode($request, $sms);
    }

    private function findOrCreateCustomer(string $phone): User
    {
        $user = User::query()
            ->where('is_admin', false)
            ->whereNotNull('phone')
            ->get()
            ->first(fn (User $candidate): bool => $this->phoneMatches($candidate->phone, $phone));

        if ($user) {
            if ($user->phone !== $phone) {
                $user->forceFill(['phone' => $phone])->save();
            }
            return $user;
        }

        $name = $this->customerNameFromHistory($phone) ?: 'Prinx Customer';
        $digits = preg_replace('/\D+/', '', $phone);
        $baseEmail = 'customer.'.$digits.'@phone.prinx.local';
        $email = $baseEmail;
        $counter = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = 'customer.'.$digits.'.'.$counter.'@phone.prinx.local';
            $counter++;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Str::random(48),
            'is_admin' => false,
        ]);
    }

    private function customerNameFromHistory(string $phone): ?string
    {
        $local = substr(preg_replace('/\D+/', '', $phone), -9);

        return Order::query()
            ->whereRaw($this->phoneDigitsSql('phone').' LIKE ?', ['%'.$local])
            ->latest('id')
            ->get(['customer_name', 'phone'])
            ->first(fn (Order $order): bool => $this->phoneMatches($order->phone, $phone))
            ?->customer_name;
    }

    private function claimOrders(User $user, string $phone): void
    {
        $local = substr(preg_replace('/\D+/', '', $phone), -9);

        Order::query()
            ->whereNull('user_id')
            ->whereRaw($this->phoneDigitsSql('phone').' LIKE ?', ['%'.$local])
            ->select(['id', 'phone'])
            ->chunkById(100, function ($orders) use ($user, $phone): void {
                $ids = $orders
                    ->filter(fn (Order $order): bool => $this->phoneMatches($order->phone, $phone))
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    Order::query()->whereIn('id', $ids)->update(['user_id' => $user->id]);
                }
            });
    }

    private function normalizePhone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input);

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        if (strlen($digits) !== 12 || ! str_starts_with($digits, '255')) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Tanzania phone number, for example 0712 345 678.',
            ]);
        }

        return '+'.$digits;
    }

    private function phoneMatches(?string $left, string $right): bool
    {
        if (! $left) {
            return false;
        }

        try {
            return $this->normalizePhone($left) === $this->normalizePhone($right);
        } catch (ValidationException) {
            return false;
        }
    }

    private function phoneDigitsSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
    }

    private function otpKey(string $phone): string
    {
        return 'customer-login-otp:'.sha1($phone);
    }

    private function maskedPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return '+'.substr($digits, 0, 3).' *** *** '.substr($digits, -3);
    }
}
