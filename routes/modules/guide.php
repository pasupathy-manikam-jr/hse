<?php

use App\Http\Controllers\GuideController;
use Illuminate\Support\Facades\Route;

Route::get('guide', GuideController::class)->middleware(['auth', 'verified'])->name('guide');
