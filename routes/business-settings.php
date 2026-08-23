<?php

use App\Http\Controllers\Admin\BusinessSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:manage_storefront'])->group(function (): void {
    Route::get('business-settings', [BusinessSettingsController::class, 'edit'])->name('business-settings.edit');
    Route::put('business-settings', [BusinessSettingsController::class, 'update'])->name('business-settings.update');
});
