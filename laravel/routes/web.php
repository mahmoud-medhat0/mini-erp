<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Foundation', [
        'status' => 'M2 foundation',
        'database' => 'not_checked',
    ]);
});

Route::get('/health', HealthCheckController::class)->name('health');
