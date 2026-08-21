<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AppPageController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
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
    Route::patch('/settings/company/{companyId?}', [SettingsActionController::class, 'updateCompany'])->name('settings.company.update');
    Route::get('/settings/branches', [AppPageController::class, 'branches'])->name('settings.branches');
    Route::post('/settings/branches', [SettingsActionController::class, 'storeBranch'])->name('settings.branches.store');
    Route::patch('/settings/branches/{branchId}', [SettingsActionController::class, 'updateBranch'])->name('settings.branches.update');
    Route::delete('/settings/branches/{branchId}', [SettingsActionController::class, 'deleteBranch'])->name('settings.branches.delete');
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
    Route::get('/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{id}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');

    // Phase 2 Accounting Core Routes
    Route::prefix('accounting')->group(function (): void {
        Route::get('/', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/coa', [AccountingController::class, 'coa'])->name('accounting.coa');
        Route::post('/coa/groups', [AccountingController::class, 'storeGroup'])->name('accounting.coa.groups.store');
        Route::post('/coa/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.coa.accounts.store');
        Route::get('/journal', [AccountingController::class, 'journal'])->name('accounting.journal');
        Route::get('/journal/create', [AccountingController::class, 'createJournal'])->name('accounting.journal.create');
        Route::post('/journal', [AccountingController::class, 'storeJournal'])->name('accounting.journal.store');
        Route::get('/journal/{journalEntry}', [AccountingController::class, 'showJournal'])->name('accounting.journal.show');
        Route::post('/journal/{journalEntry}/submit', [AccountingController::class, 'submitJournal'])->name('accounting.journal.submit');
        Route::post('/journal/{journalEntry}/approve', [AccountingController::class, 'approveJournal'])->name('accounting.journal.approve');
        Route::post('/journal/{journalEntry}/post', [AccountingController::class, 'postJournal'])->name('accounting.journal.post');
        Route::post('/journal/{journalEntry}/reverse', [AccountingController::class, 'reverseJournal'])->name('accounting.journal.reverse');
        Route::get('/ledger', [AccountingController::class, 'ledger'])->name('accounting.ledger');
        Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trial_balance');
        Route::get('/periods', [AccountingController::class, 'periods'])->name('accounting.periods');
        Route::post('/periods/fiscal-years', [AccountingController::class, 'storeFiscalYear'])->name('accounting.periods.fiscal_years.store');
        Route::post('/periods/{period}/close', [AccountingController::class, 'closePeriod'])->name('accounting.periods.close');
        Route::post('/periods/{period}/reopen', [AccountingController::class, 'reopenPeriod'])->name('accounting.periods.reopen');
        Route::get('/opening-balances', [AccountingController::class, 'openingBalances'])->name('accounting.opening_balances');
        Route::post('/opening-balances', [AccountingController::class, 'saveOpeningBalances'])->name('accounting.opening_balances.save');
        Route::post('/opening-balances/post', [AccountingController::class, 'postOpeningBalances'])->name('accounting.opening_balances.post');
        Route::get('/fx-rates', [AccountingController::class, 'fxRates'])->name('accounting.fx_rates');
        Route::post('/fx-rates', [AccountingController::class, 'storeFxRate'])->name('accounting.fx_rates.store');
        Route::get('/currencies', [AccountingController::class, 'currencies'])->name('accounting.currencies');
        Route::post('/currencies', [AccountingController::class, 'storeCurrency'])->name('accounting.currencies.store');
        Route::patch('/currencies/{currency}', [AccountingController::class, 'updateCurrency'])->name('accounting.currencies.update');
        Route::delete('/currencies/{currency}', [AccountingController::class, 'destroyCurrency'])->name('accounting.currencies.destroy');
        Route::get('/account-types', [AccountingController::class, 'accountTypes'])->name('accounting.account_types');
        Route::post('/account-types', [AccountingController::class, 'storeAccountType'])->name('accounting.account_types.store');
        Route::patch('/account-types/{accountType}', [AccountingController::class, 'updateAccountType'])->name('accounting.account_types.update');
        Route::delete('/account-types/{accountType}', [AccountingController::class, 'destroyAccountType'])->name('accounting.account_types.destroy');
        Route::get('/account-categories', [AccountingController::class, 'accountCategories'])->name('accounting.account_categories');
        Route::post('/account-categories', [AccountingController::class, 'storeAccountCategory'])->name('accounting.account_categories.store');
        Route::patch('/account-categories/{accountCategory}', [AccountingController::class, 'updateAccountCategory'])->name('accounting.account_categories.update');
        Route::delete('/account-categories/{accountCategory}', [AccountingController::class, 'destroyAccountCategory'])->name('accounting.account_categories.destroy');
    });
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
