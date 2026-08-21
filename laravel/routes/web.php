<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $database = 'unknown';

    try {
        DB::connection()->getPdo();
        $database = 'ok';
    } catch (Throwable) {
        $database = 'unavailable';
    }

    return Inertia::render('Foundation', [
        'status' => 'M2 foundation',
        'database' => $database,
    ]);
});

Route::get('/health', HealthCheckController::class)->name('health');
