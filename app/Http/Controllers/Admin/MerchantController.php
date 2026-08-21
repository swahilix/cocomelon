<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProductToGoogleMerchant;
use App\Models\MerchantSyncLog;
use App\Models\Product;
use App\Services\GoogleMerchantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use Throwable;

class MerchantController extends Controller
{
    public function index(GoogleMerchantService $merchant): Response
    {
        return Inertia::render('Admin/Merchant', [
            'configured' => $merchant->configured(),
            'credentialsInstalled' => is_file($merchant->credentialsPath()),
            'config' => [
                'enabled' => (bool) config('merchant.enabled'),
                'account_id' => config('merchant.account_id'),
                'data_source_id' => config('merchant.data_source_id'),
                'feed_label' => config('merchant.feed_label'),
                'content_language' => config('merchant.content_language'),
                'currency' => config('merchant.currency'),
            ],
            'counts' => [
                'eligible' => Product::merchantEligible()->count(),
                'synced' => Product::where('merchant_sync_status', 'synced')->count(),
                'failed' => Product::where('merchant_sync_status', 'failed')->count(),
                'pending' => Product::where('merchant_sync_status', 'pending')->count(),
            ],
            'logs' => MerchantSyncLog::with('product:id,name,slug')->latest()->paginate(20),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'account_id' => ['required', 'regex:/^\d+$/', 'max:40'],
            'data_source_id' => ['required', 'regex:/^\d+$/', 'max:40'],
            'content_language' => ['required', 'regex:/^[a-zA-Z]{2,5}$/', 'max:5'],
            'feed_label' => ['required', 'string', 'max:50'],
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        $this->writeEnv([
            'GOOGLE_MERCHANT_ENABLED' => $validated['enabled'] ? 'true' : 'false',
            'GOOGLE_MERCHANT_ACCOUNT_ID' => $validated['account_id'],
            'GOOGLE_MERCHANT_DATA_SOURCE_ID' => $validated['data_source_id'],
            'GOOGLE_MERCHANT_CONTENT_LANGUAGE' => strtolower($validated['content_language']),
            'GOOGLE_MERCHANT_FEED_LABEL' => strtoupper($validated['feed_label']),
            'GOOGLE_MERCHANT_CURRENCY' => strtoupper($validated['currency']),
        ]);

        Cache::forget('google-merchant-access-token');
        Artisan::call('config:clear');

        return back()->with('success', 'Google Merchant settings saved. The integration status has been refreshed.');
    }

    public function uploadCredentials(Request $request, GoogleMerchantService $merchant): RedirectResponse
    {
        $request->validate([
            'credentials' => ['required', 'file', 'mimes:json,txt', 'max:1024'],
        ]);

        try {
            $contents = $request->file('credentials')->get();
            $credentials = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return back()->withErrors(['credentials' => 'The selected file is not valid JSON.']);
        }

        if (! is_array($credentials)
            || ($credentials['type'] ?? null) !== 'service_account'
            || blank($credentials['client_email'] ?? null)
            || blank($credentials['private_key'] ?? null)
            || blank($credentials['project_id'] ?? null)) {
            return back()->withErrors([
                'credentials' => 'Upload a valid Google service-account JSON file containing type, project_id, client_email and private_key.',
            ]);
        }

        $path = $merchant->credentialsPath();
        File::ensureDirectoryExists(dirname($path), 0700, true);
        File::put($path, $contents, true);

        @chmod($path, 0600);
        Cache::forget('google-merchant-access-token');

        return back()->with('success', 'Google Merchant service-account credentials uploaded securely.');
    }

    public function registerGcp(Request $request, GoogleMerchantService $merchant): RedirectResponse
    {
        $validated = $request->validate([
            'developer_email' => ['required', 'email:rfc', 'max:255', 'not_regex:/\.gserviceaccount\.com$/i'],
        ], [
            'developer_email.not_regex' => 'Use a real Google Account email for the developer contact, not a service-account email.',
        ]);

        try {
            $response = $merchant->registerGcp($validated['developer_email']);
            $projectIds = collect($response['gcpIds'] ?? [])->filter()->implode(', ');
            $message = 'Google Cloud project registered with Merchant Center successfully.';

            if ($projectIds !== '') {
                $message .= ' Registered project: '.$projectIds.'.';
            }

            $message .= ' Google may take up to about 5 minutes before normal Merchant API calls start working.';

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'developer_email' => $exception->getMessage(),
            ]);
        }
    }

    public function syncAll(): RedirectResponse
    {
        Product::merchantEligible()->select('id')->chunkById(
            100,
            fn ($products) => $products->each(fn ($product) => SyncProductToGoogleMerchant::dispatch($product->id))
        );

        return back()->with('success', 'Eligible products queued for Google Merchant sync.');
    }

    public function sync(Product $product): RedirectResponse
    {
        $issues = $product->merchantReadinessIssues();

        if ($issues !== []) {
            return back()->with('error', 'This product is not Merchant-ready. Missing: '.implode('; ', $issues).'.');
        }

        SyncProductToGoogleMerchant::dispatch($product->id);

        return back()->with('success', 'Product queued for Google Merchant sync.');
    }

    public function test(GoogleMerchantService $merchant): RedirectResponse
    {
        try {
            $merchant->testConnection();

            return back()->with('success', 'Google Merchant connection is working.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function writeEnv(array $values): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            throw new \RuntimeException('The server .env file could not be found.');
        }

        $contents = File::get($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->envValue((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $line, $contents, 1);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        File::put($path, $contents, true);
    }

    private function envValue(string $value): string
    {
        if (in_array($value, ['true', 'false'], true) || preg_match('/^\d+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
