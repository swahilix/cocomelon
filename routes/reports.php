<?php

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:view_reports'])->group(function (): void {
    Route::get('reports', ReportController::class)->name('reports.index');
});
