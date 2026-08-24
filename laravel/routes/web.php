<?php

use App\Http\Controllers\Accounting\AccountCategoryController;
use App\Http\Controllers\Accounting\AccountingOverviewController;
use App\Http\Controllers\Accounting\AccountTypeController;
use App\Http\Controllers\Accounting\ChartOfAccountsController;
use App\Http\Controllers\Accounting\CurrencyController;
use App\Http\Controllers\Accounting\ExchangeRateController;
use App\Http\Controllers\Accounting\FinancialPeriodController;
use App\Http\Controllers\Accounting\GeneralLedgerController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\OpeningBalanceController;
use App\Http\Controllers\Accounting\TrialBalanceController;
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
use App\Http\Controllers\CustomerCreditNoteController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\CustomerInvoiceRevisionController;
use App\Http\Controllers\CustomerOpeningBalanceController;
use App\Http\Controllers\CustomerReceiptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\FinancialStatementMappingController;
use App\Http\Controllers\FixedAssets\FixedAssetCapitalizationController;
use App\Http\Controllers\FixedAssets\FixedAssetCategoryController;
use App\Http\Controllers\FixedAssets\FixedAssetController;
use App\Http\Controllers\FixedAssets\FixedAssetDepreciationRunController;
use App\Http\Controllers\FixedAssets\FixedAssetDepreciationScheduleController;
use App\Http\Controllers\FixedAssets\FixedAssetDisposalController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\IncomingChequeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutgoingChequeController;
use App\Http\Controllers\PayableAllocationController;
use App\Http\Controllers\PayableEntrySettlementController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReceivableAllocationController;
use App\Http\Controllers\ReceivableEntrySettlementController;
use App\Http\Controllers\Reports\ApAgingController;
use App\Http\Controllers\Reports\ApToGlReconciliationController;
use App\Http\Controllers\Reports\ArAgingController;
use App\Http\Controllers\Reports\ArToGlReconciliationController;
use App\Http\Controllers\Reports\BalanceSheetReportController;
use App\Http\Controllers\Reports\BankBookController;
use App\Http\Controllers\Reports\BankReconciliationReportController;
use App\Http\Controllers\Reports\CashBookController;
use App\Http\Controllers\Reports\CashFlowReportController;
use App\Http\Controllers\Reports\ChequeRegisterReportController;
use App\Http\Controllers\Reports\CustomerInvoiceReportController;
use App\Http\Controllers\Reports\CustomerStatementController;
use App\Http\Controllers\Reports\DeliveryNoteReportController;
use App\Http\Controllers\Reports\FixedAssetReportController;
use App\Http\Controllers\Reports\GoodsReceiptReportController;
use App\Http\Controllers\Reports\IncomeStatementReportController;
use App\Http\Controllers\Reports\PurchaseOrderReportController;
use App\Http\Controllers\Reports\ReportsHubController;
use App\Http\Controllers\Reports\SalesOrderReportController;
use App\Http\Controllers\Reports\StockMovementReportController;
use App\Http\Controllers\Reports\SupplierBillReportController;
use App\Http\Controllers\Reports\SupplierStatementController;
use App\Http\Controllers\Reports\VatReportController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\Settings\BranchSettingsController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\NumberingSettingsController;
use App\Http\Controllers\Settings\RoleSettingsController;
use App\Http\Controllers\Settings\SettingsHomeController;
use App\Http\Controllers\Settings\UserRoleAssignmentController;
use App\Http\Controllers\Settings\UserSettingsController;
use App\Http\Controllers\StockBalanceController;
use App\Http\Controllers\SupplierAdjustmentNoteController;
use App\Http\Controllers\SupplierBillController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierOpeningBalanceController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\Taxes\TaxCodeController;
use App\Http\Controllers\Taxes\TaxPeriodController;
use App\Http\Controllers\Taxes\TaxRateController;
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
    Route::get('/foundation', FoundationController::class)->name('foundation.diagnostics');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/settings', SettingsHomeController::class)->name('settings');
    Route::get('/settings/company', [CompanySettingsController::class, 'index'])->name('settings.company');
    Route::post('/settings/company', [CompanySettingsController::class, 'store'])->name('settings.company.store');
    Route::patch('/settings/company/{companyId?}', [CompanySettingsController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/branches', [BranchSettingsController::class, 'index'])->name('settings.branches');
    Route::post('/settings/branches', [BranchSettingsController::class, 'store'])->name('settings.branches.store');
    Route::patch('/settings/branches/{branchId}', [BranchSettingsController::class, 'update'])->name('settings.branches.update');
    Route::delete('/settings/branches/{branchId}', [BranchSettingsController::class, 'destroy'])->name('settings.branches.delete');
    Route::get('/settings/numbering', [NumberingSettingsController::class, 'index'])->name('settings.numbering');
    Route::post('/settings/numbering', [NumberingSettingsController::class, 'store'])->name('settings.numbering.store');
    Route::patch('/settings/numbering/{sequenceId}', [NumberingSettingsController::class, 'update'])->name('settings.numbering.update');
    Route::get('/settings/users', [UserSettingsController::class, 'index'])->name('settings.users');
    Route::post('/settings/users', [UserSettingsController::class, 'store'])->name('settings.users.store');
    Route::post('/settings/users/roles', [UserRoleAssignmentController::class, 'assign'])->name('settings.users.roles.assign');
    Route::delete('/settings/users/roles', [UserRoleAssignmentController::class, 'revoke'])->name('settings.users.roles.revoke');
    Route::patch('/settings/users/{userId}', [UserSettingsController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{userId}', [UserSettingsController::class, 'destroy'])->name('settings.users.delete');
    Route::post('/settings/roles', [RoleSettingsController::class, 'store'])->name('settings.roles.store');
    Route::patch('/settings/roles/{roleId}', [RoleSettingsController::class, 'update'])->name('settings.roles.update');
    Route::delete('/settings/roles/{roleId}', [RoleSettingsController::class, 'destroy'])->name('settings.roles.delete');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{id}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');

    // Phase 2 Accounting Core Routes
    Route::prefix('accounting')->group(function (): void {
        Route::get('/', AccountingOverviewController::class)->name('accounting.index');
        Route::get('/coa', [ChartOfAccountsController::class, 'index'])->name('accounting.coa');
        Route::post('/coa/groups', [ChartOfAccountsController::class, 'storeGroup'])->name('accounting.coa.groups.store');
        Route::post('/coa/accounts', [ChartOfAccountsController::class, 'storeAccount'])->name('accounting.coa.accounts.store');
        Route::get('/journal', [JournalController::class, 'index'])->name('accounting.journal');
        Route::get('/journal/create', [JournalController::class, 'create'])->name('accounting.journal.create');
        Route::post('/journal', [JournalController::class, 'store'])->name('accounting.journal.store');
        Route::get('/journal/{journalEntry}', [JournalController::class, 'show'])->name('accounting.journal.show');
        Route::post('/journal/{journalEntry}/submit', [JournalController::class, 'submit'])->name('accounting.journal.submit');
        Route::post('/journal/{journalEntry}/approve', [JournalController::class, 'approve'])->name('accounting.journal.approve');
        Route::post('/journal/{journalEntry}/post', [JournalController::class, 'post'])->name('accounting.journal.post');
        Route::post('/journal/{journalEntry}/reverse', [JournalController::class, 'reverse'])->name('accounting.journal.reverse');
        Route::get('/ledger', GeneralLedgerController::class)->name('accounting.ledger');
        Route::get('/trial-balance', TrialBalanceController::class)->name('accounting.trial_balance');
        Route::get('/periods', [FinancialPeriodController::class, 'index'])->name('accounting.periods');
        Route::get('/periods/{period}/close-readiness', [FinancialPeriodController::class, 'closeReadiness'])->name('accounting.periods.close_readiness');
        Route::post('/periods/fiscal-years', [FinancialPeriodController::class, 'storeFiscalYear'])->name('accounting.periods.fiscal_years.store');
        Route::post('/periods/{period}/close', [FinancialPeriodController::class, 'close'])->name('accounting.periods.close');
        Route::post('/periods/{period}/reopen', [FinancialPeriodController::class, 'reopen'])->name('accounting.periods.reopen');
        Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])->name('accounting.opening_balances');
        Route::post('/opening-balances', [OpeningBalanceController::class, 'save'])->name('accounting.opening_balances.save');
        Route::post('/opening-balances/post', [OpeningBalanceController::class, 'post'])->name('accounting.opening_balances.post');
        Route::get('/fx-rates', [ExchangeRateController::class, 'index'])->name('accounting.fx_rates');
        Route::post('/fx-rates', [ExchangeRateController::class, 'store'])->name('accounting.fx_rates.store');
        Route::get('/currencies', [CurrencyController::class, 'index'])->name('accounting.currencies');
        Route::post('/currencies', [CurrencyController::class, 'store'])->name('accounting.currencies.store');
        Route::patch('/currencies/{currency}', [CurrencyController::class, 'update'])->name('accounting.currencies.update');
        Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('accounting.currencies.destroy');
        Route::get('/account-types', [AccountTypeController::class, 'index'])->name('accounting.account_types');
        Route::post('/account-types', [AccountTypeController::class, 'store'])->name('accounting.account_types.store');
        Route::patch('/account-types/{accountType}', [AccountTypeController::class, 'update'])->name('accounting.account_types.update');
        Route::delete('/account-types/{accountType}', [AccountTypeController::class, 'destroy'])->name('accounting.account_types.destroy');
        Route::get('/account-categories', [AccountCategoryController::class, 'index'])->name('accounting.account_categories');
        Route::post('/account-categories', [AccountCategoryController::class, 'store'])->name('accounting.account_categories.store');
        Route::patch('/account-categories/{accountCategory}', [AccountCategoryController::class, 'update'])->name('accounting.account_categories.update');
        Route::delete('/account-categories/{accountCategory}', [AccountCategoryController::class, 'destroy'])->name('accounting.account_categories.destroy');

        // Phase 5 Slice 1 Financial Statement Mappings Routes
        Route::get('/statement-mappings', [FinancialStatementMappingController::class, 'index'])->name('accounting.statement_mappings.index');
        Route::post('/statement-mappings/lines', [FinancialStatementMappingController::class, 'storeLine'])->name('accounting.statement_mappings.lines.store');
        Route::put('/statement-mappings/lines/{id}', [FinancialStatementMappingController::class, 'updateLine'])->name('accounting.statement_mappings.lines.update');
        Route::delete('/statement-mappings/lines/{id}', [FinancialStatementMappingController::class, 'destroyLine'])->name('accounting.statement_mappings.lines.destroy');
        Route::post('/statement-mappings/assign', [FinancialStatementMappingController::class, 'assign'])->name('accounting.statement_mappings.assign');
        Route::post('/statement-mappings/bulk-assign', [FinancialStatementMappingController::class, 'bulkAssign'])->name('accounting.statement_mappings.bulk_assign');
        Route::post('/statement-mappings/account-cash-flow', [FinancialStatementMappingController::class, 'updateAccountCashFlow'])->name('accounting.statement_mappings.account_cash_flow');
    });

    // Phase 3 Operational Master Data & Accounting Routes
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('can:customers.create')->name('customers.store');
    Route::patch('/customers/{id}', [CustomerController::class, 'update'])->middleware('can:customers.edit')->name('customers.update');

    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.view')->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('can:suppliers.create')->name('suppliers.store');
    Route::patch('/suppliers/{id}', [SupplierController::class, 'update'])->middleware('can:suppliers.edit')->name('suppliers.update');

    Route::get('/cash-accounts', [CashAccountController::class, 'index'])->middleware('can:cash.view')->name('cash-accounts.index');
    Route::post('/cash-accounts', [CashAccountController::class, 'store'])->middleware('can:cash.create')->name('cash-accounts.store');
    Route::patch('/cash-accounts/{id}', [CashAccountController::class, 'update'])->middleware('can:cash.edit')->name('cash-accounts.update');

    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->middleware('can:banks.view')->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->middleware('can:banks.create')->name('bank-accounts.store');
    Route::patch('/bank-accounts/{id}', [BankAccountController::class, 'update'])->middleware('can:banks.edit')->name('bank-accounts.update');

    Route::get('/customer-opening-balances', [CustomerOpeningBalanceController::class, 'index'])->middleware('can:customers.view')->name('customer-opening-balances.index');
    Route::post('/customer-opening-balances', [CustomerOpeningBalanceController::class, 'store'])->middleware('can:customers.opening_balances')->name('customer-opening-balances.store');
    Route::post('/customer-opening-balances/{id}/post', [CustomerOpeningBalanceController::class, 'post'])->middleware('can:customers.opening_balances')->name('customer-opening-balances.post');

    Route::get('/supplier-opening-balances', [SupplierOpeningBalanceController::class, 'index'])->middleware('can:suppliers.view')->name('supplier-opening-balances.index');
    Route::post('/supplier-opening-balances', [SupplierOpeningBalanceController::class, 'store'])->middleware('can:suppliers.opening_balances')->name('supplier-opening-balances.store');
    Route::post('/supplier-opening-balances/{id}/post', [SupplierOpeningBalanceController::class, 'post'])->middleware('can:suppliers.opening_balances')->name('supplier-opening-balances.post');

    Route::get('/customer-receipts', [CustomerReceiptController::class, 'index'])->middleware('can:customers.view')->name('customer-receipts.index');
    Route::post('/customer-receipts', [CustomerReceiptController::class, 'store'])->middleware('can:customers.receipts')->name('customer-receipts.store');
    Route::post('/customer-receipts/{id}/post', [CustomerReceiptController::class, 'post'])->middleware('can:customers.receipts')->name('customer-receipts.post');

    Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->middleware('can:suppliers.view')->name('supplier-payments.index');
    Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->middleware('can:suppliers.payments')->name('supplier-payments.store');
    Route::post('/supplier-payments/{id}/post', [SupplierPaymentController::class, 'post'])->middleware('can:suppliers.payments')->name('supplier-payments.post');

    Route::get('/receivable-allocations', [ReceivableAllocationController::class, 'index'])->middleware('can:customers.view')->name('receivable-allocations.index');
    Route::post('/receivable-allocations', [ReceivableAllocationController::class, 'store'])->middleware('can:customers.allocations')->name('receivable-allocations.store');
    Route::post('/receivable-allocations/{id}/reverse', [ReceivableAllocationController::class, 'reverse'])->middleware('can:customers.allocations')->name('receivable-allocations.reverse');

    Route::get('/payable-allocations', [PayableAllocationController::class, 'index'])->middleware('can:suppliers.view')->name('payable-allocations.index');
    Route::post('/payable-allocations', [PayableAllocationController::class, 'store'])->middleware('can:suppliers.allocations')->name('payable-allocations.store');
    Route::post('/payable-allocations/{id}/reverse', [PayableAllocationController::class, 'reverse'])->middleware('can:suppliers.allocations')->name('payable-allocations.reverse');

    Route::get('/incoming-cheques', [IncomingChequeController::class, 'index'])->middleware('can:cheques.view')->name('incoming-cheques.index');
    Route::post('/incoming-cheques', [IncomingChequeController::class, 'store'])->middleware('can:cheques.create')->name('incoming-cheques.store');
    Route::post('/incoming-cheques/{id}/receive', [IncomingChequeController::class, 'receive'])->middleware('can:cheques.receive')->name('incoming-cheques.receive');
    Route::post('/incoming-cheques/{id}/deposit', [IncomingChequeController::class, 'deposit'])->middleware('can:cheques.deposit')->name('incoming-cheques.deposit');
    Route::post('/incoming-cheques/{id}/clear', [IncomingChequeController::class, 'clear'])->middleware('can:cheques.clear')->name('incoming-cheques.clear');
    Route::post('/incoming-cheques/{id}/bounce', [IncomingChequeController::class, 'bounce'])->middleware('can:cheques.bounce')->name('incoming-cheques.bounce');
    Route::post('/incoming-cheques/{id}/return', [IncomingChequeController::class, 'return'])->middleware('can:cheques.return')->name('incoming-cheques.return');

    Route::get('/outgoing-cheques', [OutgoingChequeController::class, 'index'])->middleware('can:cheques.view')->name('outgoing-cheques.index');
    Route::post('/outgoing-cheques', [OutgoingChequeController::class, 'store'])->middleware('can:cheques.create')->name('outgoing-cheques.store');
    Route::post('/outgoing-cheques/{id}/issue', [OutgoingChequeController::class, 'issue'])->middleware('can:cheques.issue')->name('outgoing-cheques.issue');
    Route::post('/outgoing-cheques/{id}/clear', [OutgoingChequeController::class, 'clear'])->middleware('can:cheques.clear')->name('outgoing-cheques.clear');
    Route::post('/outgoing-cheques/{id}/return', [OutgoingChequeController::class, 'return'])->middleware('can:cheques.return')->name('outgoing-cheques.return');
    Route::post('/outgoing-cheques/{id}/cancel', [OutgoingChequeController::class, 'cancel'])->middleware('can:cheques.cancel')->name('outgoing-cheques.cancel');

    Route::get('/bank-reconciliations', [BankReconciliationController::class, 'index'])->middleware('can:banks.view')->name('bank-reconciliations.index');
    Route::post('/bank-reconciliations', [BankReconciliationController::class, 'store'])->middleware('can:banks.reconcile')->name('bank-reconciliations.store');
    Route::get('/bank-reconciliations/{id}', [BankReconciliationController::class, 'show'])->middleware('can:banks.view')->name('bank-reconciliations.show');
    Route::post('/bank-reconciliations/{id}/lines', [BankReconciliationController::class, 'addLine'])->middleware('can:banks.reconcile')->name('bank-reconciliations.lines.store');
    Route::patch('/bank-reconciliations/{id}/lines/{lineId}', [BankReconciliationController::class, 'updateLine'])->middleware('can:banks.reconcile')->name('bank-reconciliations.lines.update');
    Route::delete('/bank-reconciliations/{id}/lines/{lineId}', [BankReconciliationController::class, 'deleteLine'])->middleware('can:banks.reconcile')->name('bank-reconciliations.lines.delete');
    Route::post('/bank-reconciliations/{id}/lines/{lineId}/match', [BankReconciliationController::class, 'matchLine'])->middleware('can:banks.reconcile')->name('bank-reconciliations.lines.match');
    Route::post('/bank-reconciliations/{id}/lines/{lineId}/unmatch', [BankReconciliationController::class, 'unmatchLine'])->middleware('can:banks.reconcile')->name('bank-reconciliations.lines.unmatch');
    Route::post('/bank-reconciliations/{id}/finalize', [BankReconciliationController::class, 'finalize'])->middleware('can:banks.reconcile')->name('bank-reconciliations.finalize');

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

        // Phase 4 Slice 9 Operational Reports
        Route::get('/sales-orders', [SalesOrderReportController::class, 'index'])->name('reports.sales-orders');
        Route::get('/purchase-orders', [PurchaseOrderReportController::class, 'index'])->name('reports.purchase-orders');
        Route::get('/delivery-notes', [DeliveryNoteReportController::class, 'index'])->name('reports.delivery-notes');
        Route::get('/goods-receipts', [GoodsReceiptReportController::class, 'index'])->name('reports.goods-receipts');
        Route::get('/customer-invoices', [CustomerInvoiceReportController::class, 'index'])->name('reports.customer-invoices');
        Route::get('/supplier-bills', [SupplierBillReportController::class, 'index'])->name('reports.supplier-bills');
        Route::get('/stock-movements', [StockMovementReportController::class, 'index'])->name('reports.stock-movements');

        // Phase 5 Slice 2 & 3 Financial Statements Reports
        Route::get('/balance-sheet', [BalanceSheetReportController::class, 'index'])->name('reports.balance_sheet');

        // Phase 6 Slice 7 Fixed Asset Reports
        Route::get('/fixed-asset-register', [FixedAssetReportController::class, 'register'])->name('reports.fixed-asset-register');
        Route::get('/fixed-asset-register/export', [FixedAssetReportController::class, 'exportRegister'])->name('reports.fixed-asset-register.export');
        Route::get('/fixed-asset-net-book-values', [FixedAssetReportController::class, 'netBookValues'])->name('reports.fixed-asset-net-book-values');
        Route::get('/fixed-asset-net-book-values/export', [FixedAssetReportController::class, 'exportNetBookValues'])->name('reports.fixed-asset-net-book-values.export');
        Route::get('/fixed-asset-depreciation', [FixedAssetReportController::class, 'depreciation'])->name('reports.fixed-asset-depreciation');
        Route::get('/fixed-asset-depreciation/export', [FixedAssetReportController::class, 'exportDepreciation'])->name('reports.fixed-asset-depreciation.export');
        Route::get('/fixed-asset-depreciation-runs', [FixedAssetReportController::class, 'depreciationRuns'])->name('reports.fixed-asset-depreciation-runs');
        Route::get('/fixed-asset-depreciation-runs/export', [FixedAssetReportController::class, 'exportDepreciationRuns'])->name('reports.fixed-asset-depreciation-runs.export');
        Route::get('/fixed-asset-disposals', [FixedAssetReportController::class, 'disposals'])->name('reports.fixed-asset-disposals');
        Route::get('/fixed-asset-disposals/export', [FixedAssetReportController::class, 'exportDisposals'])->name('reports.fixed-asset-disposals.export');
        Route::get('/balance-sheet/export', [BalanceSheetReportController::class, 'exportCsv'])->name('reports.balance_sheet.export');
        Route::get('/income-statement', [IncomeStatementReportController::class, 'index'])->name('reports.income_statement');
        Route::get('/income-statement/export', [IncomeStatementReportController::class, 'exportCsv'])->name('reports.income_statement.export');
        Route::get('/cash-flow', [CashFlowReportController::class, 'index'])->name('reports.cash_flow');
        Route::get('/cash-flow/export', [CashFlowReportController::class, 'exportCsv'])->name('reports.cash_flow.export');

        // Phase 7 Slice 5 VAT Reports
        Route::get('/vat-register', [VatReportController::class, 'register'])->name('reports.vat-register');
        Route::get('/vat-register/export', [VatReportController::class, 'exportRegister'])->name('reports.vat-register.export');
        Route::get('/vat-summary', [VatReportController::class, 'summary'])->name('reports.vat-summary');
        Route::get('/vat-summary/export', [VatReportController::class, 'exportSummary'])->name('reports.vat-summary.export');
        Route::get('/vat-gl-reconciliation', [VatReportController::class, 'reconciliation'])->name('reports.vat-gl-reconciliation');
        Route::get('/vat-gl-reconciliation/export', [VatReportController::class, 'exportReconciliation'])->name('reports.vat-gl-reconciliation.export');
    });

    // Phase 4 Slice 1 Catalog Routes
    Route::get('/catalog/uoms', [UnitOfMeasureController::class, 'index'])->middleware('can:uom.view')->name('uoms.index');
    Route::post('/catalog/uoms', [UnitOfMeasureController::class, 'store'])->middleware('can:uom.create')->name('uoms.store');
    Route::put('/catalog/uoms/{uom}', [UnitOfMeasureController::class, 'update'])->middleware('can:uom.edit')->name('uoms.update');
    Route::delete('/catalog/uoms/{uom}', [UnitOfMeasureController::class, 'destroy'])->middleware('can:uom.delete')->name('uoms.destroy');

    Route::get('/catalog/categories', [ProductCategoryController::class, 'index'])->middleware('can:products.view')->name('product-categories.index');
    Route::post('/catalog/categories', [ProductCategoryController::class, 'store'])->middleware('can:products.create')->name('product-categories.store');
    Route::put('/catalog/categories/{category}', [ProductCategoryController::class, 'update'])->middleware('can:products.edit')->name('product-categories.update');
    Route::delete('/catalog/categories/{category}', [ProductCategoryController::class, 'destroy'])->middleware('can:products.delete')->name('product-categories.destroy');

    Route::get('/catalog/products', [ProductController::class, 'index'])->middleware('can:products.view')->name('products.index');
    Route::post('/catalog/products', [ProductController::class, 'store'])->middleware('can:products.create')->name('products.store');
    Route::put('/catalog/products/{product}', [ProductController::class, 'update'])->middleware('can:products.edit')->name('products.update');
    Route::delete('/catalog/products/{product}', [ProductController::class, 'destroy'])->middleware('can:products.delete')->name('products.destroy');

    // Phase 4 Slice 2 Sales Order Routes
    Route::get('/sales/orders', [SalesOrderController::class, 'index'])->middleware('can:sales.view')->name('sales-orders.index');
    Route::post('/sales/orders', [SalesOrderController::class, 'store'])->middleware('can:sales.create')->name('sales-orders.store');
    Route::put('/sales/orders/{salesOrder}', [SalesOrderController::class, 'update'])->middleware('can:sales.edit')->name('sales-orders.update');
    Route::post('/sales/orders/{salesOrder}/submit', [SalesOrderController::class, 'submit'])->middleware('can:sales.submit')->name('sales-orders.submit');
    Route::post('/sales/orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->middleware('can:sales.approve')->name('sales-orders.confirm');
    Route::post('/sales/orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('can:sales.cancel')->name('sales-orders.cancel');

    // Phase 4 Slice 3 Purchase Order Routes
    Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])->middleware('can:purchasing.view')->name('purchase-orders.index');
    Route::post('/purchasing/orders', [PurchaseOrderController::class, 'store'])->middleware('can:purchasing.create')->name('purchase-orders.store');
    Route::put('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('can:purchasing.edit')->name('purchase-orders.update');
    Route::post('/purchasing/orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->middleware('can:purchasing.submit')->name('purchase-orders.submit');
    Route::post('/purchasing/orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->middleware('can:purchasing.approve')->name('purchase-orders.confirm');
    Route::post('/purchasing/orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('can:purchasing.cancel')->name('purchase-orders.cancel');

    // Phase 4 Slice 4 Fulfillment Routes (Delivery Notes & Goods Receipts)
    Route::get('/sales/delivery-notes', [DeliveryNoteController::class, 'index'])->middleware('can:sales.view')->name('delivery-notes.index');
    Route::post('/sales/delivery-notes', [DeliveryNoteController::class, 'store'])->middleware('can:sales.create')->name('delivery-notes.store');
    Route::put('/sales/delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'update'])->middleware('can:sales.edit')->name('delivery-notes.update');
    Route::post('/sales/delivery-notes/{deliveryNote}/confirm', [DeliveryNoteController::class, 'confirm'])->middleware('can:sales.approve')->name('delivery-notes.confirm');
    Route::post('/sales/delivery-notes/{deliveryNote}/cancel', [DeliveryNoteController::class, 'cancel'])->middleware('can:sales.cancel')->name('delivery-notes.cancel');

    Route::get('/purchasing/goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('can:purchasing.view')->name('goods-receipts.index');
    Route::post('/purchasing/goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('can:purchasing.create')->name('goods-receipts.store');
    Route::put('/purchasing/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'update'])->middleware('can:purchasing.edit')->name('goods-receipts.update');
    Route::post('/purchasing/goods-receipts/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm'])->middleware('can:purchasing.approve')->name('goods-receipts.confirm');
    Route::post('/purchasing/goods-receipts/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel'])->middleware('can:purchasing.cancel')->name('goods-receipts.cancel');

    // Phase 4 Slice 5 Customer Invoice Routes
    Route::get('/sales/invoices', [CustomerInvoiceController::class, 'index'])->middleware('can:sales.view')->name('customer-invoices.index');
    Route::post('/sales/invoices', [CustomerInvoiceController::class, 'store'])->middleware('can:sales.create')->name('customer-invoices.store');
    Route::put('/sales/invoices/{customerInvoice}', [CustomerInvoiceController::class, 'update'])->middleware('can:sales.edit')->name('customer-invoices.update');
    Route::post('/sales/invoices/{customerInvoice}/submit', [CustomerInvoiceController::class, 'submit'])->middleware('can:sales.submit')->name('customer-invoices.submit');
    Route::post('/sales/invoices/{customerInvoice}/approve', [CustomerInvoiceController::class, 'approve'])->middleware('can:sales.approve')->name('customer-invoices.approve');
    Route::post('/sales/invoices/{customerInvoice}/post', [CustomerInvoiceController::class, 'post'])->middleware('can:sales.post')->name('customer-invoices.post');
    Route::post('/sales/invoices/{customerInvoice}/cancel', [CustomerInvoiceController::class, 'cancel'])->middleware('can:sales.cancel')->name('customer-invoices.cancel');

    // Phase 4 Slice 8 Inventory Costing Routes
    Route::get('/inventory/stock-balances', [StockBalanceController::class, 'index'])->middleware('can:inventory.view')->name('stock-balances.index');

    // Phase 4 Slice 6 Supplier Bill Routes
    Route::get('/purchasing/bills', [SupplierBillController::class, 'index'])->middleware('can:purchasing.view')->name('supplier-bills.index');
    Route::post('/purchasing/bills', [SupplierBillController::class, 'store'])->middleware('can:purchasing.create')->name('supplier-bills.store');
    Route::put('/purchasing/bills/{supplierBill}', [SupplierBillController::class, 'update'])->middleware('can:purchasing.edit')->name('supplier-bills.update');
    Route::post('/purchasing/bills/{supplierBill}/submit', [SupplierBillController::class, 'submit'])->middleware('can:purchasing.submit')->name('supplier-bills.submit');
    Route::post('/purchasing/bills/{supplierBill}/approve', [SupplierBillController::class, 'approve'])->middleware('can:purchasing.approve')->name('supplier-bills.approve');
    Route::post('/purchasing/bills/{supplierBill}/post', [SupplierBillController::class, 'post'])->middleware('can:purchasing.post')->name('supplier-bills.post');
    Route::post('/purchasing/bills/{supplierBill}/cancel', [SupplierBillController::class, 'cancel'])->middleware('can:purchasing.cancel')->name('supplier-bills.cancel');

    // Phase 4 Slice 10 Returns & Adjustment Notes Routes
    Route::get('/sales/returns/returnable-lines/{invoiceId}', [SalesReturnController::class, 'returnableInvoiceLines'])->middleware('can:sales.returns')->name('sales-returns.returnable-lines');
    Route::get('/sales/returns', [SalesReturnController::class, 'index'])->middleware('can:sales.returns')->name('sales-returns.index');
    Route::post('/sales/returns', [SalesReturnController::class, 'store'])->middleware('can:sales.returns')->name('sales-returns.store');
    Route::put('/sales/returns/{id}', [SalesReturnController::class, 'update'])->middleware('can:sales.returns')->name('sales-returns.update');
    Route::post('/sales/returns/{id}/submit', [SalesReturnController::class, 'submit'])->middleware('can:sales.returns')->name('sales-returns.submit');
    Route::post('/sales/returns/{id}/approve', [SalesReturnController::class, 'approve'])->middleware('can:sales.returns')->name('sales-returns.approve');
    Route::post('/sales/returns/{id}/post', [SalesReturnController::class, 'post'])->middleware('can:sales.returns')->name('sales-returns.post');
    Route::post('/sales/returns/{id}/cancel', [SalesReturnController::class, 'cancel'])->middleware('can:sales.returns')->name('sales-returns.cancel');

    Route::get('/sales/credit-notes', [CustomerCreditNoteController::class, 'index'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.index');
    Route::post('/sales/credit-notes', [CustomerCreditNoteController::class, 'store'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.store');
    Route::put('/sales/credit-notes/{id}', [CustomerCreditNoteController::class, 'update'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.update');
    Route::post('/sales/credit-notes/{id}/submit', [CustomerCreditNoteController::class, 'submit'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.submit');
    Route::post('/sales/credit-notes/{id}/approve', [CustomerCreditNoteController::class, 'approve'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.approve');
    Route::post('/sales/credit-notes/{id}/post', [CustomerCreditNoteController::class, 'post'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.post');
    Route::post('/sales/credit-notes/{id}/cancel', [CustomerCreditNoteController::class, 'cancel'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.cancel');

    Route::get('/sales/invoice-revisions', [CustomerInvoiceRevisionController::class, 'index'])->middleware('can:sales.invoice_revisions')->name('invoice-revisions.index');
    Route::get('/sales/invoice-revisions/{id}', [CustomerInvoiceRevisionController::class, 'show'])->middleware('can:sales.invoice_revisions')->name('invoice-revisions.show');

    Route::get('/purchasing/returns', [PurchaseReturnController::class, 'index'])->middleware('can:purchasing.returns')->name('purchase-returns.index');
    Route::post('/purchasing/returns', [PurchaseReturnController::class, 'store'])->middleware('can:purchasing.returns')->name('purchase-returns.store');
    Route::put('/purchasing/returns/{id}', [PurchaseReturnController::class, 'update'])->middleware('can:purchasing.returns')->name('purchase-returns.update');
    Route::post('/purchasing/returns/{id}/submit', [PurchaseReturnController::class, 'submit'])->middleware('can:purchasing.returns')->name('purchase-returns.submit');
    Route::post('/purchasing/returns/{id}/approve', [PurchaseReturnController::class, 'approve'])->middleware('can:purchasing.returns')->name('purchase-returns.approve');
    Route::post('/purchasing/returns/{id}/post', [PurchaseReturnController::class, 'post'])->middleware('can:purchasing.returns')->name('purchase-returns.post');
    Route::post('/purchasing/returns/{id}/cancel', [PurchaseReturnController::class, 'cancel'])->middleware('can:purchasing.returns')->name('purchase-returns.cancel');

    Route::get('/purchasing/adjustment-notes', [SupplierAdjustmentNoteController::class, 'index'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.index');
    Route::post('/purchasing/adjustment-notes', [SupplierAdjustmentNoteController::class, 'store'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.store');
    Route::put('/purchasing/adjustment-notes/{id}', [SupplierAdjustmentNoteController::class, 'update'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.update');
    Route::post('/purchasing/adjustment-notes/{id}/submit', [SupplierAdjustmentNoteController::class, 'submit'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.submit');
    Route::post('/purchasing/adjustment-notes/{id}/approve', [SupplierAdjustmentNoteController::class, 'approve'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.approve');
    Route::post('/purchasing/adjustment-notes/{id}/post', [SupplierAdjustmentNoteController::class, 'post'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.post');
    Route::post('/purchasing/adjustment-notes/{id}/cancel', [SupplierAdjustmentNoteController::class, 'cancel'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.cancel');

    Route::get('/sales/receivable-settlements', [ReceivableEntrySettlementController::class, 'index'])->middleware('can:sales.credit_notes')->name('receivable-settlements.index');
    Route::post('/sales/receivable-settlements', [ReceivableEntrySettlementController::class, 'store'])->middleware('can:sales.credit_notes')->name('receivable-settlements.store');
    Route::post('/sales/receivable-settlements/{id}/reverse', [ReceivableEntrySettlementController::class, 'reverse'])->middleware('can:sales.credit_notes')->name('receivable-settlements.reverse');

    Route::get('/purchasing/payable-settlements', [PayableEntrySettlementController::class, 'index'])->middleware('can:purchasing.adjustment_notes')->name('payable-settlements.index');
    Route::post('/purchasing/payable-settlements', [PayableEntrySettlementController::class, 'store'])->middleware('can:purchasing.adjustment_notes')->name('payable-settlements.store');
    Route::post('/purchasing/payable-settlements/{id}/reverse', [PayableEntrySettlementController::class, 'reverse'])->middleware('can:purchasing.adjustment_notes')->name('payable-settlements.reverse');

    // Phase 6 Slice 2 Fixed Assets Routes
    Route::get('/fixed-asset-categories', [FixedAssetCategoryController::class, 'index'])->name('fixed-asset-categories.index');
    Route::post('/fixed-asset-categories', [FixedAssetCategoryController::class, 'store'])->name('fixed-asset-categories.store');
    Route::put('/fixed-asset-categories/{id}', [FixedAssetCategoryController::class, 'update'])->name('fixed-asset-categories.update');
    Route::delete('/fixed-asset-categories/{id}', [FixedAssetCategoryController::class, 'destroy'])->name('fixed-asset-categories.destroy');

    Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::get('/fixed-assets/create', [FixedAssetController::class, 'create'])->name('fixed-assets.create');
    Route::post('/fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store');
    Route::get('/fixed-assets/{id}', [FixedAssetController::class, 'show'])->name('fixed-assets.show');
    Route::get('/fixed-assets/{id}/edit', [FixedAssetController::class, 'edit'])->name('fixed-assets.edit');
    Route::put('/fixed-assets/{id}', [FixedAssetController::class, 'update'])->name('fixed-assets.update');
    Route::delete('/fixed-assets/{id}', [FixedAssetController::class, 'destroy'])->name('fixed-assets.destroy');
    Route::post('/fixed-assets/{id}/capitalize', [FixedAssetCapitalizationController::class, 'store'])->name('fixed-assets.capitalize');
    Route::post('/fixed-assets/{id}/reverse-capitalization', [FixedAssetCapitalizationController::class, 'reverse'])->name('fixed-assets.reverse_capitalization');
    Route::post('/fixed-assets/{id}/generate-schedule', [FixedAssetDepreciationScheduleController::class, 'store'])->name('fixed-assets.generate_schedule');

    Route::get('/fixed-assets-depreciation-runs', [FixedAssetDepreciationRunController::class, 'index'])->name('fixed-assets.depreciation-runs.index');
    Route::post('/fixed-assets-depreciation-runs', [FixedAssetDepreciationRunController::class, 'store'])->name('fixed-assets.depreciation-runs.store');
    Route::get('/fixed-assets-depreciation-runs/preview/{financialPeriodId}', [FixedAssetDepreciationRunController::class, 'preview'])->name('fixed-assets.depreciation-runs.preview');
    Route::get('/fixed-assets-depreciation-runs/{id}', [FixedAssetDepreciationRunController::class, 'show'])->name('fixed-assets.depreciation-runs.show');
    Route::post('/fixed-assets-depreciation-runs/{id}/reverse', [FixedAssetDepreciationRunController::class, 'reverse'])->name('fixed-assets.depreciation-runs.reverse');

    // Phase 6 Slice 6 Fixed Asset Disposal Routes
    Route::get('/fixed-assets-disposals', [FixedAssetDisposalController::class, 'index'])->name('fixed-assets-disposals.index');
    Route::get('/fixed-assets-disposals/{id}', [FixedAssetDisposalController::class, 'show'])->name('fixed-assets-disposals.show');
    Route::post('/fixed-assets/{assetId}/disposals/preview', [FixedAssetDisposalController::class, 'preview'])->name('fixed-assets.disposals.preview');
    Route::post('/fixed-assets/{assetId}/disposals', [FixedAssetDisposalController::class, 'store'])->name('fixed-assets.disposals.store');
    Route::post('/fixed-assets-disposals/{id}/reverse', [FixedAssetDisposalController::class, 'reverse'])->name('fixed-assets-disposals.reverse');

    // Phase 7 Slice 2 Tax Code and Tax Rate Routes
    Route::get('/taxes/codes', [TaxCodeController::class, 'index'])->name('taxes.codes.index');
    Route::get('/taxes/codes/create', [TaxCodeController::class, 'create'])->name('taxes.codes.create');
    Route::post('/taxes/codes', [TaxCodeController::class, 'store'])->name('taxes.codes.store');
    Route::get('/taxes/codes/{id}/edit', [TaxCodeController::class, 'edit'])->name('taxes.codes.edit');
    Route::put('/taxes/codes/{id}', [TaxCodeController::class, 'update'])->name('taxes.codes.update');
    Route::delete('/taxes/codes/{id}', [TaxCodeController::class, 'destroy'])->name('taxes.codes.destroy');

    Route::get('/taxes/rates', [TaxRateController::class, 'index'])->name('taxes.rates.index');
    Route::post('/taxes/rates', [TaxRateController::class, 'store'])->name('taxes.rates.store');
    Route::put('/taxes/rates/{id}', [TaxRateController::class, 'update'])->name('taxes.rates.update');
    Route::delete('/taxes/rates/{id}', [TaxRateController::class, 'destroy'])->name('taxes.rates.destroy');

    // Phase 7 Slice 6 Tax Period & Filing Routes
    Route::get('/taxes/periods', [TaxPeriodController::class, 'index'])->name('taxes.periods.index');
    Route::post('/taxes/periods', [TaxPeriodController::class, 'store'])->name('taxes.periods.store');
    Route::get('/taxes/periods/{id}', [TaxPeriodController::class, 'show'])->name('taxes.periods.show');
    Route::post('/taxes/periods/{id}/draft', [TaxPeriodController::class, 'generateDraft'])->name('taxes.periods.draft');
    Route::post('/taxes/returns/{id}/file', [TaxPeriodController::class, 'fileReturn'])->name('taxes.returns.file');
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
