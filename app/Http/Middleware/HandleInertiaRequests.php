<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string { return parent::version($request); }

    public function rootView(Request $request): string
    {
        if ($request->is('admin','admin/*','login','register','cart','checkout','order-success/*','track-order','account/*')) config(['inertia.ssr.enabled' => false]);
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only('id','name','email','phone','is_admin'),
                'role' => $role,
                'permissions' => $permissions,
            ],
            'cartCount' => collect($cart)->sum(),
            'navigationCategories' => $navigationCategories,
            'navigationServiceCategories' => $navigationServiceCategories,
            'store' => [
                'name' => config('app.name'), 'url' => rtrim((string) config('app.url'), '/'), 'logo' => asset('assets/prinx-logo.svg'),
                'description' => config('store.description'), 'currency' => config('store.currency'), 'country' => config('store.country'),
                'phone' => config('store.phone'), 'email' => config('store.email'), 'address' => config('store.address'), 'legal_name' => config('store.legal_name'),
                'same_as' => config('store.social_profiles'), 'image_credit' => config('store.image_credit'), 'image_rights_url' => config('store.image_rights_url'),
                'google_site_verification' => config('store.google_site_verification'), 'bing_site_verification' => config('store.bing_site_verification'), 'year' => now()->year,
            ],
            'flash' => ['success' => fn() => $request->session()->get('success'), 'error' => fn() => $request->session()->get('error')],
        ];
    }
}
