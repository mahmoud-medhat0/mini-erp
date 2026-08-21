<?php

use App\Http\Controllers\AppPageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard')->name('foundation');
    Route::get('/foundation', [AppPageController::class, 'foundation'])->name('foundation.diagnostics');
    Route::get('/dashboard', [AppPageController::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [AppPageController::class, 'settings'])->name('settings');
    Route::get('/settings/company', [AppPageController::class, 'companies'])->name('settings.company');
    Route::get('/settings/branches', [AppPageController::class, 'branches'])->name('settings.branches');
    Route::get('/settings/numbering', [AppPageController::class, 'numbering'])->name('settings.numbering');
    Route::get('/settings/users', [AppPageController::class, 'users'])->name('settings.users');
    Route::get('/notifications', [AppPageController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/{id}/read', [AppPageController::class, 'markNotificationRead'])->name('notifications.read');
});

Route::get('/health', HealthCheckController::class)->name('health');

Route::post('/locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:en,ar'],
    ]);

    $locale = $validated['locale'];
    $request->session()->put('locale', $locale);
    app()->setLocale($locale);

    if ($request->user()) {
        $request->user()->update(['locale' => $locale]);
    }

    return back();
})->name('locale.update');
