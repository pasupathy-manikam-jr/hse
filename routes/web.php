<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

require __DIR__.'/settings.php';

foreach (glob(__DIR__.'/modules/*.php') ?: [] as $module) {
    require $module;
}
