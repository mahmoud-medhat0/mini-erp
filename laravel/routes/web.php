<?php

use App\Http\Controllers\AppPageController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\SettingsActionController;
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
    Route::post('/settings/company', [SettingsActionController::class, 'storeCompany'])->name('settings.company.store');
    Route::patch('/settings/company/{companyId}', [SettingsActionController::class, 'updateCompany'])->name('settings.company.update');
    Route::get('/settings/branches', [AppPageController::class, 'branches'])->name('settings.branches');
    Route::post('/settings/branches', [SettingsActionController::class, 'storeBranch'])->name('settings.branches.store');
    Route::patch('/settings/branches/{branchId}', [SettingsActionController::class, 'updateBranch'])->name('settings.branches.update');
    Route::get('/settings/numbering', [AppPageController::class, 'numbering'])->name('settings.numbering');
    Route::post('/settings/numbering', [SettingsActionController::class, 'storeNumbering'])->name('settings.numbering.store');
    Route::patch('/settings/numbering/{sequenceId}', [SettingsActionController::class, 'updateNumbering'])->name('settings.numbering.update');
    Route::get('/settings/users', [AppPageController::class, 'users'])->name('settings.users');
    Route::post('/settings/users', [SettingsActionController::class, 'storeUser'])->name('settings.users.store');
    Route::post('/settings/users/roles', [SettingsActionController::class, 'assignRole'])->name('settings.users.roles.assign');
    Route::delete('/settings/users/roles', [SettingsActionController::class, 'revokeRole'])->name('settings.users.roles.revoke');
    Route::patch('/settings/users/{userId}', [SettingsActionController::class, 'updateUser'])->name('settings.users.update');
    Route::delete('/settings/users/{userId}', [SettingsActionController::class, 'deleteUser'])->name('settings.users.delete');
    Route::post('/settings/roles', [SettingsActionController::class, 'storeRole'])->name('settings.roles.store');
    Route::patch('/settings/roles/{roleId}', [SettingsActionController::class, 'updateRole'])->name('settings.roles.update');
    Route::delete('/settings/roles/{roleId}', [SettingsActionController::class, 'deleteRole'])->name('settings.roles.delete');
    Route::get('/notifications', [AppPageController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [AppPageController::class, 'markAllNotificationsRead'])->name('notifications.read_all');
    Route::post('/notifications/{id}/read', [AppPageController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{id}', [AttachmentController::class, 'show'])->name('attachments.show');
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
