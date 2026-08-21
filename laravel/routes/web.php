<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AppPageController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\Catalog\ProductCategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\UnitOfMeasureController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOpeningBalanceController;
use App\Http\Controllers\CustomerReceiptController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\IncomingChequeController;
use App\Http\Controllers\OutgoingChequeController;
use App\Http\Controllers\PayableAllocationController;
use App\Http\Controllers\ReceivableAllocationController;
use App\Http\Controllers\Reports\ApAgingController;
use App\Http\Controllers\Reports\ApToGlReconciliationController;
use App\Http\Controllers\Reports\ArAgingController;
use App\Http\Controllers\Reports\ArToGlReconciliationController;
use App\Http\Controllers\Reports\BankBookController;
use App\Http\Controllers\Reports\BankReconciliationReportController;
use App\Http\Controllers\Reports\CashBookController;
use App\Http\Controllers\Reports\ChequeRegisterReportController;
use App\Http\Controllers\Reports\CustomerStatementController;
use App\Http\Controllers\Reports\ReportsHubController;
use App\Http\Controllers\Reports\SupplierStatementController;
use App\Http\Controllers\SettingsActionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierOpeningBalanceController;
use App\Http\Controllers\SupplierPaymentController;
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

    // Phase 3 Operational Master Data & Accounting Routes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('/suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update');

    Route::get('/cash-accounts', [CashAccountController::class, 'index'])->name('cash-accounts.index');
    Route::post('/cash-accounts', [CashAccountController::class, 'store'])->name('cash-accounts.store');
    Route::patch('/cash-accounts/{id}', [CashAccountController::class, 'update'])->name('cash-accounts.update');

    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::patch('/bank-accounts/{id}', [BankAccountController::class, 'update'])->name('bank-accounts.update');

    Route::get('/customer-opening-balances', [CustomerOpeningBalanceController::class, 'index'])->name('customer-opening-balances.index');
    Route::post('/customer-opening-balances', [CustomerOpeningBalanceController::class, 'store'])->name('customer-opening-balances.store');
    Route::post('/customer-opening-balances/{id}/post', [CustomerOpeningBalanceController::class, 'post'])->name('customer-opening-balances.post');

    Route::get('/supplier-opening-balances', [SupplierOpeningBalanceController::class, 'index'])->name('supplier-opening-balances.index');
    Route::post('/supplier-opening-balances', [SupplierOpeningBalanceController::class, 'store'])->name('supplier-opening-balances.store');
    Route::post('/supplier-opening-balances/{id}/post', [SupplierOpeningBalanceController::class, 'post'])->name('supplier-opening-balances.post');

    Route::get('/customer-receipts', [CustomerReceiptController::class, 'index'])->name('customer-receipts.index');
    Route::post('/customer-receipts', [CustomerReceiptController::class, 'store'])->name('customer-receipts.store');
    Route::post('/customer-receipts/{id}/post', [CustomerReceiptController::class, 'post'])->name('customer-receipts.post');

    Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
    Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
    Route::post('/supplier-payments/{id}/post', [SupplierPaymentController::class, 'post'])->name('supplier-payments.post');

    Route::get('/receivable-allocations', [ReceivableAllocationController::class, 'index'])->name('receivable-allocations.index');
    Route::post('/receivable-allocations', [ReceivableAllocationController::class, 'store'])->name('receivable-allocations.store');
    Route::post('/receivable-allocations/{id}/reverse', [ReceivableAllocationController::class, 'reverse'])->name('receivable-allocations.reverse');

    Route::get('/payable-allocations', [PayableAllocationController::class, 'index'])->name('payable-allocations.index');
    Route::post('/payable-allocations', [PayableAllocationController::class, 'store'])->name('payable-allocations.store');
    Route::post('/payable-allocations/{id}/reverse', [PayableAllocationController::class, 'reverse'])->name('payable-allocations.reverse');

    Route::get('/incoming-cheques', [IncomingChequeController::class, 'index'])->name('incoming-cheques.index');
    Route::post('/incoming-cheques', [IncomingChequeController::class, 'store'])->name('incoming-cheques.store');
    Route::post('/incoming-cheques/{id}/receive', [IncomingChequeController::class, 'receive'])->name('incoming-cheques.receive');
    Route::post('/incoming-cheques/{id}/deposit', [IncomingChequeController::class, 'deposit'])->name('incoming-cheques.deposit');
    Route::post('/incoming-cheques/{id}/clear', [IncomingChequeController::class, 'clear'])->name('incoming-cheques.clear');
    Route::post('/incoming-cheques/{id}/bounce', [IncomingChequeController::class, 'bounce'])->name('incoming-cheques.bounce');
    Route::post('/incoming-cheques/{id}/return', [IncomingChequeController::class, 'return'])->name('incoming-cheques.return');

    Route::get('/outgoing-cheques', [OutgoingChequeController::class, 'index'])->name('outgoing-cheques.index');
    Route::post('/outgoing-cheques', [OutgoingChequeController::class, 'store'])->name('outgoing-cheques.store');
    Route::post('/outgoing-cheques/{id}/issue', [OutgoingChequeController::class, 'issue'])->name('outgoing-cheques.issue');
    Route::post('/outgoing-cheques/{id}/clear', [OutgoingChequeController::class, 'clear'])->name('outgoing-cheques.clear');
    Route::post('/outgoing-cheques/{id}/return', [OutgoingChequeController::class, 'return'])->name('outgoing-cheques.return');
    Route::post('/outgoing-cheques/{id}/cancel', [OutgoingChequeController::class, 'cancel'])->name('outgoing-cheques.cancel');

    Route::get('/bank-reconciliations', [BankReconciliationController::class, 'index'])->name('bank-reconciliations.index');
    Route::post('/bank-reconciliations', [BankReconciliationController::class, 'store'])->name('bank-reconciliations.store');
    Route::get('/bank-reconciliations/{id}', [BankReconciliationController::class, 'show'])->name('bank-reconciliations.show');
    Route::post('/bank-reconciliations/{id}/lines', [BankReconciliationController::class, 'addLine'])->name('bank-reconciliations.lines.store');
    Route::patch('/bank-reconciliations/{id}/lines/{lineId}', [BankReconciliationController::class, 'updateLine'])->name('bank-reconciliations.lines.update');
    Route::delete('/bank-reconciliations/{id}/lines/{lineId}', [BankReconciliationController::class, 'deleteLine'])->name('bank-reconciliations.lines.delete');
    Route::post('/bank-reconciliations/{id}/lines/{lineId}/match', [BankReconciliationController::class, 'matchLine'])->name('bank-reconciliations.lines.match');
    Route::post('/bank-reconciliations/{id}/lines/{lineId}/unmatch', [BankReconciliationController::class, 'unmatchLine'])->name('bank-reconciliations.lines.unmatch');
    Route::post('/bank-reconciliations/{id}/finalize', [BankReconciliationController::class, 'finalize'])->name('bank-reconciliations.finalize');

    // Phase 3 Slice 8 Reports & Subledgers Routes
    Route::prefix('reports')->middleware('can:reports.view')->group(function (): void {
        Route::get('/', [ReportsHubController::class, 'index'])->name('reports.index');
        Route::get('/customer-statement', [CustomerStatementController::class, 'index'])->name('reports.customer-statement');
        Route::get('/customer-statement/export', [CustomerStatementController::class, 'exportCsv'])->name('reports.customer-statement.export');
        Route::get('/supplier-statement', [SupplierStatementController::class, 'index'])->name('reports.supplier-statement');
        Route::get('/supplier-statement/export', [SupplierStatementController::class, 'exportCsv'])->name('reports.supplier-statement.export');
        Route::get('/ar-aging', [ArAgingController::class, 'index'])->name('reports.ar-aging');
        Route::get('/ar-aging/export', [ArAgingController::class, 'exportCsv'])->name('reports.ar-aging.export');
        Route::get('/ap-aging', [ApAgingController::class, 'index'])->name('reports.ap-aging');
        Route::get('/ap-aging/export', [ApAgingController::class, 'exportCsv'])->name('reports.ap-aging.export');
        Route::get('/cash-book', [CashBookController::class, 'index'])->name('reports.cash-book');
        Route::get('/cash-book/export', [CashBookController::class, 'exportCsv'])->name('reports.cash-book.export');
        Route::get('/bank-book', [BankBookController::class, 'index'])->name('reports.bank-book');
        Route::get('/bank-book/export', [BankBookController::class, 'exportCsv'])->name('reports.bank-book.export');
        Route::get('/cheque-register', [ChequeRegisterReportController::class, 'index'])->name('reports.cheque-register');
        Route::get('/cheque-register/export', [ChequeRegisterReportController::class, 'exportCsv'])->name('reports.cheque-register.export');
        Route::get('/bank-reconciliations', [BankReconciliationReportController::class, 'index'])->name('reports.bank-reconciliations');
        Route::get('/bank-reconciliations/{id}', [BankReconciliationReportController::class, 'show'])->name('reports.bank-reconciliations.show');
        Route::get('/ar-gl-reconciliation', [ArToGlReconciliationController::class, 'index'])->name('reports.ar-gl-reconciliation');
        Route::get('/ar-gl-reconciliation/export', [ArToGlReconciliationController::class, 'exportCsv'])->name('reports.ar-gl-reconciliation.export');
        Route::get('/ap-gl-reconciliation', [ApToGlReconciliationController::class, 'index'])->name('reports.ap-gl-reconciliation');
        Route::get('/ap-gl-reconciliation/export', [ApToGlReconciliationController::class, 'exportCsv'])->name('reports.ap-gl-reconciliation.export');
    });

    // Phase 4 Slice 1 Catalog Routes
    Route::get('/catalog/uoms', [UnitOfMeasureController::class, 'index'])->name('uoms.index');
    Route::post('/catalog/uoms', [UnitOfMeasureController::class, 'store'])->name('uoms.store');
    Route::put('/catalog/uoms/{uom}', [UnitOfMeasureController::class, 'update'])->name('uoms.update');
    Route::delete('/catalog/uoms/{uom}', [UnitOfMeasureController::class, 'destroy'])->name('uoms.destroy');

    Route::get('/catalog/categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
    Route::post('/catalog/categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::put('/catalog/categories/{category}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
    Route::delete('/catalog/categories/{category}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

    Route::get('/catalog/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/catalog/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/catalog/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/catalog/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
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
