<?php

use App\Http\Controllers\Admin\WarrantyController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/warranties')->name('admin.warranties.')->middleware(['auth', 'admin', 'permission:use_pos,manage_orders'])->group(function (): void {
    Route::get('/', [WarrantyController::class, 'index'])->name('index');
    Route::post('/', [WarrantyController::class, 'store'])->name('store');
    Route::post('/{warranty}/messages/{type}', [WarrantyController::class, 'resend'])
        ->whereNumber('warranty')
        ->whereIn('type', ['thank_you', 'details', 'expired'])
        ->name('messages.resend');
});
