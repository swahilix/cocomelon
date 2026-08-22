<?php

use App\Http\Controllers\Admin\LegacyPosRedirectController;
use App\Http\Controllers\Admin\PosController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:use_pos'])->group(function (): void {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosController::class, 'store'])->middleware('pos.pricing')->name('pos.store');
    Route::post('pos/hold', [PosController::class, 'hold'])->name('pos.hold');
    Route::delete('pos/hold/{hold}', [PosController::class, 'destroyHold'])->whereNumber('hold')->name('pos.hold.destroy');
    Route::get('pos/receipt/{sale}', [PosController::class, 'receipt'])->whereNumber('sale')->name('pos.receipt');

    // routes/pos.php is loaded after routes/web.php, so this intentionally
    // replaces the old embedded stock POS POST route without using a closure.
    Route::post('stock/pos', LegacyPosRedirectController::class)->name('stock.pos.store');
});
