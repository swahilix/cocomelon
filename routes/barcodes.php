<?php

use App\Http\Controllers\Admin\BarcodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'permission:view_inventory,manage_storefront'])->prefix('admin/barcodes')->name('admin.barcodes.')->group(function (): void {
    Route::get('/', [BarcodeController::class, 'index'])->name('index');
    Route::post('/printed', [BarcodeController::class, 'printed'])->name('printed');
});
