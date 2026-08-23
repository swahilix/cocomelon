<?php

use App\Http\Controllers\Admin\BarcodeController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/barcodes', BarcodeController::class)
    ->middleware(['auth', 'admin', 'permission:view_inventory,manage_storefront'])
    ->name('admin.barcodes.index');
