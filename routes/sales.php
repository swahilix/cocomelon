<?php

use App\Http\Controllers\Admin\SalesController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('sales', [SalesController::class, 'index'])->middleware('permission:use_pos,view_reports')->name('sales.index');
    Route::get('sales/pos/{sale}', [SalesController::class, 'showPos'])->middleware('permission:use_pos,view_reports')->whereNumber('sale')->name('sales.pos.show');
    Route::post('sales/pos/{sale}/returns', [SalesController::class, 'returnPos'])->middleware('permission:refund_sale')->whereNumber('sale')->name('sales.pos.returns.store');
    Route::post('sales/pos/{sale}/void', [SalesController::class, 'voidPos'])->middleware('permission:void_sale')->whereNumber('sale')->name('sales.pos.void');
});
