<?php

use App\Http\Controllers\Admin\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'permission:manage_registers'])->prefix('admin/registers')->name('admin.registers.')->group(function (): void {
    Route::get('/', [RegisterController::class, 'index'])->name('index');
    Route::post('/open', [RegisterController::class, 'open'])->name('open');
    Route::post('/movement', [RegisterController::class, 'movement'])->name('movement');
    Route::post('/close', [RegisterController::class, 'close'])->name('close');
    Route::get('/{session}', [RegisterController::class, 'show'])->name('show');
});
