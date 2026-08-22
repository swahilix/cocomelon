<?php

use App\Http\Controllers\Admin\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::post('expenses/categories', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
    Route::patch('expenses/categories/{category}', [ExpenseController::class, 'updateCategory'])->whereNumber('category')->name('expenses.categories.update');
    Route::post('expenses/{expense}/void', [ExpenseController::class, 'void'])->whereNumber('expense')->name('expenses.void');
    Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->whereNumber('expense')->name('expenses.receipt');
});
