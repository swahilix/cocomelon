<?php

use App\Http\Controllers\Admin\HistoricalDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/historical-data')->name('admin.historical.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', [HistoricalDataController::class, 'index'])
        ->middleware('permission:manage_purchases,use_pos,view_reports')
        ->name('index');

    Route::post('purchases', [HistoricalDataController::class, 'storePurchase'])
        ->middleware('permission:manage_purchases')
        ->name('purchases.store');
    Route::delete('purchases/{purchase}', [HistoricalDataController::class, 'destroyPurchase'])
        ->middleware('permission:manage_purchases')
        ->whereNumber('purchase')
        ->name('purchases.destroy');
    Route::post('confirm-purchases', [HistoricalDataController::class, 'confirmPurchases'])
        ->middleware('permission:manage_purchases')
        ->name('purchases.confirm');
    Route::post('reopen-purchases', [HistoricalDataController::class, 'reopenPurchases'])
        ->middleware('permission:manage_purchases')
        ->name('purchases.reopen');

    Route::post('sales', [HistoricalDataController::class, 'storeSale'])
        ->middleware('permission:use_pos')
        ->name('sales.store');
    Route::delete('sales/{sale}', [HistoricalDataController::class, 'destroySale'])
        ->middleware('permission:use_pos')
        ->whereNumber('sale')
        ->name('sales.destroy');

    Route::post('complete', [HistoricalDataController::class, 'complete'])
        ->middleware('permission:manage_purchases,use_pos')
        ->name('complete');
});
