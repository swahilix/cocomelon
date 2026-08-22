<?php

use App\Http\Controllers\Admin\AccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:manage_staff'])->group(function (): void {
    Route::get('access', [AccessController::class, 'index'])->name('access.index');
    Route::post('access/staff', [AccessController::class, 'storeUser'])->name('access.staff.store');
    Route::patch('access/staff/{user}', [AccessController::class, 'updateUser'])->whereNumber('user')->name('access.staff.update');
    Route::delete('access/staff/{user}', [AccessController::class, 'revokeUser'])->whereNumber('user')->name('access.staff.revoke');
    Route::put('access/roles/{role}/permissions', [AccessController::class, 'updateRolePermissions'])->whereNumber('role')->name('access.roles.permissions');
});
