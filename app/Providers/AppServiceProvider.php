<?php

namespace App\Providers;

use App\Models\MailAccount;
use App\Models\MailAttachment;
use App\Models\MailContact;
use App\Models\MailContactGroup;
use App\Models\MailDraft;
use App\Models\MailFolder;
use App\Models\MailMessage;
use App\Models\MailOutbox;
use App\Models\Product;
use App\Models\HeroSlide;
use App\Services\AiSettings;
use App\Services\ImageOptimizationService;
use App\Observers\ProductObserver;
use App\Policies\MailAccountPolicy;
use App\Policies\MailAttachmentPolicy;
use App\Policies\MailContactGroupPolicy;
use App\Policies\MailContactPolicy;
use App\Policies\MailDraftPolicy;
use App\Policies\MailFolderPolicy;
use App\Policies\MailMessagePolicy;
use App\Policies\MailOutboxPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        AiSettings::apply();
        Product::observe(ProductObserver::class);

        View::composer('app', function ($view): void {
            if (request()->getPathInfo() !== '/') {
                return;
            }

            try {
                $slide = HeroSlide::query()
                    ->active()
                    ->where('item_type', 'image')
                    ->whereNotNull('image_path')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                if (! $slide) {
                    return;
                }

                $view->with('heroPreload', [
                    'url' => $slide->image_url,
                    'srcset' => app(ImageOptimizationService::class)
                        ->responsiveWebpSrcset($slide->image_path, [720, 960, 1280, 1600]),
                ]);
            } catch (\Throwable) {
                // Never let a preload optimization block the storefront.
            }
        });

        Gate::policy(MailAccount::class, MailAccountPolicy::class);
        Gate::policy(MailFolder::class, MailFolderPolicy::class);
        Gate::policy(MailMessage::class, MailMessagePolicy::class);
        Gate::policy(MailAttachment::class, MailAttachmentPolicy::class);
        Gate::policy(MailDraft::class, MailDraftPolicy::class);
        Gate::policy(MailOutbox::class, MailOutboxPolicy::class);
        Gate::policy(MailContact::class, MailContactPolicy::class);
        Gate::policy(MailContactGroup::class, MailContactGroupPolicy::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
