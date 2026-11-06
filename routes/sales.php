<?php

use App\Http\Controllers\Admin\PastSaleController;
use App\Http\Controllers\Admin\SalesController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    // Sales that happened earlier, entered with their real date.
    Route::get('sales/past', [PastSaleController::class, 'create'])->middleware('permission:use_pos')->name('sales.past.create');
    Route::post('sales/past', [PastSaleController::class, 'store'])->middleware(['permission:use_pos', 'throttle:60,1'])->name('sales.past.store');
    Route::get('sales', [SalesController::class, 'index'])->middleware('permission:use_pos,view_reports')->name('sales.index');
    Route::get('sales/pos/{sale}', [SalesController::class, 'showPos'])->middleware('permission:use_pos,view_reports')->whereNumber('sale')->name('sales.pos.show');
    Route::post('sales/pos/{sale}/returns', [SalesController::class, 'returnPos'])->middleware('permission:refund_sale')->whereNumber('sale')->name('sales.pos.returns.store');
    Route::post('sales/pos/{sale}/void', [SalesController::class, 'voidPos'])->middleware('permission:void_sale')->whereNumber('sale')->name('sales.pos.void');
});
