<?php

use App\Http\Controllers\Admin\LegacyPosRedirectController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PosDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:use_pos'])->group(function (): void {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');

    Route::get('pos/documents', [PosDocumentController::class, 'index'])->name('pos.documents.index');
    Route::post('pos/documents/quotation', [PosDocumentController::class, 'quotation'])->name('pos.documents.quotation');
    Route::post('pos/documents/invoice', [PosDocumentController::class, 'invoice'])->name('pos.documents.invoice');
    Route::get('pos/documents/{document}', [PosDocumentController::class, 'show'])->whereNumber('document')->name('pos.documents.show');

    Route::get('pos/unit/{barcode}', [PosController::class, 'lookupUnit'])->where('barcode', '[A-Za-z0-9\-]+')->name('pos.unit.lookup');
    Route::post('pos', [PosController::class, 'store'])->middleware('pos.pricing')->name('pos.store');
    Route::post('pos/hold', [PosController::class, 'hold'])->name('pos.hold');
    Route::delete('pos/hold/{hold}', [PosController::class, 'destroyHold'])->whereNumber('hold')->name('pos.hold.destroy');
    Route::get('pos/receipt/{sale}', [PosController::class, 'receipt'])->whereNumber('sale')->name('pos.receipt');

    // routes/pos.php is loaded after routes/web.php, so this intentionally
    // replaces the old embedded stock POS POST URI without using a closure.
    Route::post('stock/pos', LegacyPosRedirectController::class)->name('pos.legacy-stock-redirect');
});
