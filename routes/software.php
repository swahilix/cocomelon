<?php

use App\Http\Controllers\Admin\SoftwareSolutionController;
use App\Models\SoftwareSolution;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/software', function () {
    return Inertia::render('Store/SoftwareManaged', [
        'softwareSolutions' => SoftwareSolution::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(),
    ]);
})->name('software');

Route::get('/software/{softwareSolution}', function (SoftwareSolution $softwareSolution) {
    abort_unless($softwareSolution->status === 'active', 404);

    return Inertia::render('Store/SoftwareDetail', [
        'software' => $softwareSolution,
    ]);
})->name('software.show');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'permission:manage_storefront'])->group(function (): void {
    Route::resource('software', SoftwareSolutionController::class)->except('show');
    Route::post('software/{software}/restore', [SoftwareSolutionController::class, 'restore'])
        ->whereNumber('software')
        ->name('software.restore');
});
