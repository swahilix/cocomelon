<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string { return parent::version($request); }

    public function rootView(Request $request): string
    {
        if ($request->is('admin','admin/*','login','login/*','register','cart','checkout','order-success/*','track-order','account','account/*','wishlist','wishlist/*')) config(['inertia.ssr.enabled' => false]);
        return parent::rootView($request);
    }

    public function share(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        $navigationCategories = [];
        $navigationServiceCategories = [];

        if (! $request->is('admin','admin/*') && Schema::hasTable('categories')) {
            $navigationCategories = Category::productType()->active()->withCount(['products' => fn($q) => $q->active()])->orderBy('sort_order')->orderBy('name')->take(8)->get(['id','name','slug'])->values();
            $navigationServiceCategories = Category::serviceType()->active()->withCount(['services' => fn($q) => $q->active()])->orderBy('sort_order')->orderBy('name')->take(6)->get(['id','name','slug'])->values();
        }

        $user = $request->user();
        $permissions = [];
        $role = null;
        if ($user?->is_admin && Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasColumn('users', 'role_id')) {
            $user->loadMissing('role.permissions');
            $permissions = $user->permissionSlugs();
            $role = $user->role ? ['id' => $user->role->id, 'name' => $user->role->name, 'slug' => $user->role->slug] : null;
        }

        $authUser = $user?->only('id','name','email','phone','is_admin');
        if ($authUser && Str::endsWith((string) ($authUser['email'] ?? ''), '@phone.prinx.local')) {
            $authUser['email'] = null;
        }
        if ($authUser && ! ($authUser['is_admin'] ?? false) && filled($authUser['phone'] ?? null)) {
            $digits = preg_replace('/\D+/', '', (string) $authUser['phone']);
            if (strlen($digits) === 12 && str_starts_with($digits, '255')) {
                $authUser['phone'] = substr($digits, 3);
            } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                $authUser['phone'] = substr($digits, 1);
            }
        }

        $wishlistProducts = collect();
        if ($user && Schema::hasTable('wishlists') && Schema::hasTable('products')) {
            $wishlistProducts = DB::table('wishlists')
                ->join('products', 'products.id', '=', 'wishlists.product_id')
                ->where('wishlists.user_id', $user->id)
                ->where('products.status', 'active')
                ->whereNull('products.deleted_at')
                ->orderByDesc('wishlists.id')
                ->get(['wishlists.product_id', 'products.slug']);
        }

        $business = Schema::hasTable('business_settings')
            ? DB::table('business_settings')->where('id', 1)->first()
            : null;
        $value = static fn (string $key, mixed $fallback = null): mixed => filled($business?->{$key}) ? $business->{$key} : $fallback;
        $social = [
            'facebook' => $value('facebook_url'),
            'instagram' => $value('instagram_url'),
            'tiktok' => $value('tiktok_url'),
            'youtube' => $value('youtube_url'),
            'x' => $value('x_url'),
        ];
        $databaseProfiles = array_values(array_filter($social));
        $paymentSettingsReady = Schema::hasTable('business_settings')
            && Schema::hasColumn('business_settings', 'payment_mobile_money_enabled');

        $paymentMethods = collect([
            [
                'value' => 'mobile_money',
                'title' => 'Mobile Money',
                'description' => $paymentSettingsReady
                    ? $value('payment_mobile_money_description', 'Pay using the mobile money instructions provided for your order.')
                    : 'Pay using the mobile money instructions provided for your order.',
                'icon' => 'phone',
                'enabled' => $paymentSettingsReady ? (bool) ($business?->payment_mobile_money_enabled ?? true) : true,
            ],
            [
                'value' => 'bank_transfer',
                'title' => 'Bank Transfer',
                'description' => $paymentSettingsReady
                    ? $value('payment_bank_transfer_description', 'Pay by bank transfer using the confirmed order instructions.')
                    : 'Pay by bank transfer using the confirmed order instructions.',
                'icon' => 'bank',
                'enabled' => $paymentSettingsReady ? (bool) ($business?->payment_bank_transfer_enabled ?? true) : true,
            ],
            [
                'value' => 'cash_on_delivery',
                'title' => 'Cash Payment',
                'description' => $paymentSettingsReady
                    ? $value('payment_cash_on_delivery_description', 'Pay in cash when your order is delivered or collected.')
                    : 'Pay in cash when your order is delivered or collected.',
                'icon' => 'cash',
                'enabled' => $paymentSettingsReady ? (bool) ($business?->payment_cash_on_delivery_enabled ?? true) : true,
            ],
        ])->filter(fn (array $method) => $method['enabled'])
            ->map(function (array $method): array {
                unset($method['enabled']);
                return $method;
            })
            ->values()
            ->all();

        $paymentSafetyNote = $paymentSettingsReady
            ? $value('payment_safety_note', 'Prinx Tech will never ask for your mobile-money PIN, password or OTP.')
            : 'Prinx Tech will never ask for your mobile-money PIN, password or OTP.';

        $paymentStrip = Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'payment_strip')
            ? $value('payment_strip')
            : null;
        $paymentStripVisible = ! Schema::hasColumn('business_settings', 'payment_strip_visible')
            || (bool) ($business?->payment_strip_visible ?? false);
        $paymentStripUrl = null;

        if ($paymentStrip && $paymentStripVisible) {
            $paymentStripUrl = Str::startsWith($paymentStrip, ['http://', 'https://', '/'])
                ? $paymentStrip
                : Storage::disk(config('media.disk', 'public'))->url($paymentStrip);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser,
                'role' => $role,
                'permissions' => $permissions,
            ],
            'cartCount' => collect($cart)->sum(),
            'wishlistCount' => $wishlistProducts->count(),
            'wishlistProductIds' => $wishlistProducts->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all(),
            'wishlistProductSlugs' => $wishlistProducts->pluck('slug')->filter()->values()->all(),
            'navigationCategories' => $navigationCategories,
            'navigationServiceCategories' => $navigationServiceCategories,
            'store' => [
                'name' => $value('business_name', config('app.name')),
                'url' => rtrim((string) config('app.url'), '/'),
                'logo' => asset('assets/prinx-logo.svg'),
                'description' => $value('description', config('store.description')),
                'currency' => config('store.currency'),
                'country' => $value('country', config('store.country')),
                'phone' => $value('phone', config('store.phone')),
                'secondary_phone' => $value('secondary_phone'),
                'whatsapp' => $value('whatsapp', config('store.phone')),
                'email' => $value('email', config('store.email')),
                'address' => $value('address', config('store.address')),
                'city' => $value('city'),
                'region' => $value('region'),
                'working_hours' => $value('working_hours'),
                'maps_url' => $value('maps_url'),
                'delivery_enabled' => Schema::hasColumn('business_settings', 'delivery_enabled')
                    ? (bool) ($business?->delivery_enabled ?? true)
                    : true,
                'delivery_area' => Schema::hasColumn('business_settings', 'delivery_area')
                    ? $value('delivery_area', $value('city', $value('region')))
                    : $value('city', $value('region')),
                'delivery_eta' => Schema::hasColumn('business_settings', 'delivery_eta')
                    ? $value('delivery_eta')
                    : null,
                'pickup_enabled' => Schema::hasColumn('business_settings', 'pickup_enabled')
                    ? (bool) ($business?->pickup_enabled ?? false)
                    : false,
                'pickup_location' => Schema::hasColumn('business_settings', 'pickup_location')
                    ? $value('pickup_location', $value('address'))
                    : $value('address'),
                'pickup_instructions' => Schema::hasColumn('business_settings', 'pickup_instructions')
                    ? $value('pickup_instructions')
                    : null,
                'legal_name' => $value('legal_name', config('store.legal_name')),
                'payment_methods' => $paymentMethods,
                'payment_safety_note' => $paymentSafetyNote,
                'payment_strip_url' => $paymentStripUrl,
                'social' => $social,
                'same_as' => $databaseProfiles !== [] ? $databaseProfiles : config('store.social_profiles'),
                'image_credit' => config('store.image_credit'),
                'image_rights_url' => config('store.image_rights_url'),
                'google_site_verification' => config('store.google_site_verification'),
                'bing_site_verification' => config('store.bing_site_verification'),
                'year' => now()->year,
            ],
            'flash' => ['success' => fn() => $request->session()->get('success'), 'error' => fn() => $request->session()->get('error')],
        ];
    }
}
