<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login/verify', [CustomerAuthController::class, 'verifyForm'])->name('customer.login.verify');
    Route::post('/login/verify', [CustomerAuthController::class, 'verify'])->middleware('throttle:12,1')->name('customer.login.verify.submit');
    Route::post('/login/resend', [CustomerAuthController::class, 'resend'])->middleware('throttle:4,5')->name('customer.login.resend');

    Route::get('/admin/login', [AuthController::class, 'staffLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'staffLogin'])->middleware('throttle:8,1')->name('admin.login.submit');
});

Route::prefix('account')->name('customer.account.')->middleware('auth')->group(function (): void {
    Route::get('/', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [CustomerPortalController::class, 'orders'])->name('orders');
    Route::get('/orders/{orderNumber}', [CustomerPortalController::class, 'order'])->name('orders.show');
    Route::get('/payments', [CustomerPortalController::class, 'payments'])->name('payments');
    Route::get('/invoices', [CustomerPortalController::class, 'invoices'])->name('invoices');
    Route::get('/invoices/{source}/{reference}', [CustomerPortalController::class, 'invoice'])->whereIn('source', ['order', 'pos'])->name('invoices.show');
});

Route::prefix('wishlist')->name('wishlist.')->middleware('auth')->group(function (): void {
    Route::get('/', [WishlistController::class, 'index'])->name('index');
    Route::post('/{product}', [WishlistController::class, 'store'])->name('store');
    Route::delete('/{product}', [WishlistController::class, 'destroy'])->name('destroy');
});
