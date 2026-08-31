<?php

use App\Http\Controllers\Admin\MailSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/mail-settings')
    ->name('admin.mail-settings.')
    ->middleware(['auth', 'admin', 'permission:mail.manage_mailboxes,mail.manage_settings'])
    ->group(function (): void {
        Route::get('/', [MailSettingsController::class, 'index'])->name('index');
        Route::post('/', [MailSettingsController::class, 'store'])->name('store');
        Route::post('/test-imap', [MailSettingsController::class, 'testImap'])->name('test-imap');
        Route::post('/test-smtp', [MailSettingsController::class, 'testSmtp'])->name('test-smtp');
        Route::put('/{mailAccount}', [MailSettingsController::class, 'update'])->whereNumber('mailAccount')->name('update');
        Route::put('/{mailAccount}/staff', [MailSettingsController::class, 'assignUsers'])->whereNumber('mailAccount')->name('staff.update');
        Route::delete('/{mailAccount}', [MailSettingsController::class, 'destroy'])->whereNumber('mailAccount')->name('destroy');
    });
