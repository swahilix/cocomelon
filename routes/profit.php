<?php

use App\Http\Controllers\Admin\ProfitController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:view_profit_reports'])->group(function (): void {
    Route::get('profit', ProfitController::class)->name('profit.index');
});
