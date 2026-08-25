<?php

use App\Http\Controllers\Admin\CustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/customers')->name('admin.customers.')->middleware(['auth', 'admin', 'permission:use_pos,manage_orders'])->group(function (): void {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('/options', [CustomerController::class, 'options'])->name('options');
    Route::post('/', [CustomerController::class, 'store'])->name('store');
    Route::patch('/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('update');
});
