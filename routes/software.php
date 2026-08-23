<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/software', fn () => Inertia::render('Store/Software'))->name('software');
