<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Http\Controllers\Admin\GoogleMapsSettingsController;
use App\Http\Controllers\Admin\StorefrontAppearanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:manage_storefront'])->group(function (): void {
    Route::get('business-settings', [BusinessSettingsController::class, 'edit'])->name('business-settings.edit');
    Route::put('business-settings', [BusinessSettingsController::class, 'update'])->name('business-settings.update');
    Route::get('business-settings/maps', [GoogleMapsSettingsController::class, 'edit'])->name('business-settings.maps.edit');
    Route::put('business-settings/maps', [GoogleMapsSettingsController::class, 'update'])->name('business-settings.maps.update');
    Route::get('business-settings/ai', [AiSettingsController::class, 'edit'])->name('business-settings.ai.edit');
    Route::put('business-settings/ai', [AiSettingsController::class, 'update'])->name('business-settings.ai.update');
    Route::get('business-settings/storefront', [StorefrontAppearanceController::class, 'edit'])->name('business-settings.storefront.edit');
    Route::put('business-settings/storefront', [StorefrontAppearanceController::class, 'update'])->name('business-settings.storefront.update');
    Route::post('business-settings/ai/test', [AiSettingsController::class, 'test'])->middleware('throttle:10,1')->name('business-settings.ai.test');
});
