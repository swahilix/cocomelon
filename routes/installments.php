<?php

use App\Http\Controllers\Admin\InstallmentContractController;
use App\Http\Controllers\Admin\InstallmentController;
use App\Http\Controllers\Admin\InstallmentStoreController;
use App\Http\Controllers\CustomerInstallmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/installments')->name('admin.installments.')->middleware(['auth', 'admin', 'permission:use_pos,manage_orders'])->group(function (): void {
    Route::get('/', [InstallmentController::class, 'index'])->name('index');
    Route::post('/', InstallmentStoreController::class)->name('store');
    Route::get('/{reference}/contract', [InstallmentContractController::class, 'show'])->where('reference', '[A-Za-z0-9-]+')->name('contract');
    Route::post('/{reference}/contract/signed', [InstallmentContractController::class, 'upload'])->where('reference', '[A-Za-z0-9-]+')->name('contract.signed.upload');
    Route::get('/{reference}/contract/signed', [InstallmentContractController::class, 'signed'])->where('reference', '[A-Za-z0-9-]+')->name('contract.signed');
    Route::get('/{reference}', [InstallmentController::class, 'show'])->where('reference', '[A-Za-z0-9-]+')->name('show');
    Route::post('/{reference}/payments', [InstallmentController::class, 'payment'])->where('reference', '[A-Za-z0-9-]+')->name('payments.store');
    Route::post('/{reference}/payments/{payment}/sms', [InstallmentController::class, 'resendSms'])->where('reference', '[A-Za-z0-9-]+')->whereNumber('payment')->name('payments.sms');
    Route::post('/{reference}/release', [InstallmentController::class, 'release'])->where('reference', '[A-Za-z0-9-]+')->name('release');
    Route::delete('/{reference}', [InstallmentController::class, 'cancel'])->where('reference', '[A-Za-z0-9-]+')->name('cancel');
});

Route::prefix('account/installments')->name('customer.installments.')->middleware('auth')->group(function (): void {
    Route::get('/', [CustomerInstallmentController::class, 'index'])->name('index');
    Route::get('/{reference}', [CustomerInstallmentController::class, 'show'])->where('reference', '[A-Za-z0-9-]+')->name('show');
});
