<?php

use App\Jobs\SystemQueueHeartbeat;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Services\ImageOptimizationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    Cache::put('system-monitor.scheduler-heartbeat', now()->toIso8601String(), now()->addHours(2));
    SystemQueueHeartbeat::dispatch();
})->everyMinute()->name('system-monitor-heartbeat')->withoutOverlapping();

Schedule::command('merchant:sync --stale')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();


Artisan::command('media:optimize-storefront-images', function (): void {
    $images = app(ImageOptimizationService::class);
    $generated = 0;
    $failed = 0;

    $optimize = function (?string $path, array $widths) use ($images, &$generated, &$failed): void {
        if (! $path) {
            return;
        }

        try {
            $generated += count($images->ensureResponsiveWebpVariants($path, $widths));
        } catch (\Throwable $exception) {
            $failed++;
            $this->warn('Skipped '.$path.': '.$exception->getMessage());
        }
    };

    HeroSlide::query()
        ->where('item_type', 'image')
        ->whereNotNull('image_path')
        ->each(fn (HeroSlide $slide) => $optimize($slide->image_path, [720, 960, 1280, 1600]));

    Category::query()
        ->where('type', 'product')
        ->whereNotNull('image')
        ->each(fn (Category $category) => $optimize($category->image, [160, 240]));

    Product::query()
        ->active()
        ->where('featured', true)
        ->whereNotNull('primary_image')
        ->orderBy('sort_order')
        ->take(30)
        ->get()
        ->each(fn (Product $product) => $optimize($product->primary_image, [240, 360, 480]));

    Brand::query()
        ->where('is_active', true)
        ->whereNotNull('logo')
        ->each(fn (Brand $brand) => $optimize($brand->logo, [160, 240]));

    $logoSource = public_path('assets/prinx-logo.png');
    if (is_file($logoSource)) {
        foreach ([180, 360] as $width) {
            try {
                $destination = public_path("assets/prinx-logo-{$width}.webp");
                $sourceModified = filemtime($logoSource) ?: time();

                if (is_file($destination) && (filemtime($destination) ?: 0) >= $sourceModified) {
                    continue;
                }

                if (extension_loaded('imagick')) {
                    $logo = new \Imagick($logoSource);
                    $logo->setImageFormat('webp');
                    $logo->setImageCompressionQuality(82);
                    $logo->stripImage();
                    $logo->thumbnailImage($width, 0);
                    $logo->writeImage($destination);
                    $logo->clear();
                    $generated++;
                    continue;
                }

                if (extension_loaded('gd') && function_exists('imagewebp')) {
                    $source = @imagecreatefromstring((string) file_get_contents($logoSource));
                    if ($source === false) {
                        throw new \RuntimeException('Unable to decode logo.');
                    }

                    $sourceWidth = imagesx($source);
                    $sourceHeight = imagesy($source);
                    $height = max(1, (int) round($sourceHeight * ($width / max(1, $sourceWidth))));
                    $target = imagecreatetruecolor($width, $height);
                    imagealphablending($target, false);
                    imagesavealpha($target, true);
                    imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

                    if (! imagewebp($target, $destination, 82)) {
                        throw new \RuntimeException('Unable to encode optimized logo.');
                    }

                    imagedestroy($source);
                    imagedestroy($target);
                    $generated++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn('Skipped Prinx logo optimization: '.$exception->getMessage());
            }
        }
    }

    $this->info("Responsive storefront images ready ({$generated} variants, {$failed} skipped).");
})->purpose('Generate cached responsive WebP images for the public storefront.');
