<?php

use App\Http\Controllers\Admin\AccessController;
use App\Http\Controllers\Admin\AuditController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:manage_staff'])->group(function (): void {
    Route::get('access', [AccessController::class, 'index'])->name('access.index');
    Route::post('access/staff', [AccessController::class, 'storeUser'])->middleware('sensitive:staff.created,user,users,input:_none')->name('access.staff.store');
    Route::patch('access/staff/{user}', [AccessController::class, 'updateUser'])->middleware('sensitive:staff.updated,user,users,route:user')->whereNumber('user')->name('access.staff.update');
    Route::delete('access/staff/{user}', [AccessController::class, 'revokeUser'])->middleware('sensitive:staff.access_revoked,user,users,route:user')->whereNumber('user')->name('access.staff.revoke');
    Route::put('access/roles/{role}/permissions', [AccessController::class, 'updateRolePermissions'])->middleware('sensitive:role.permissions_updated,role,roles,route:role')->whereNumber('role')->name('access.roles.permissions');
    Route::get('audit', AuditController::class)->name('audit.index');
});
