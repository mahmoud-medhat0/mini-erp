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
use App\Http\Controllers\AccountingAccountMappingController;
use App\Http\Controllers\AccrualScheduleController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\Budgeting\BudgetController;
use App\Http\Controllers\Budgeting\BudgetVarianceController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\Catalog\ProductCategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\UnitOfMeasureController;
use App\Http\Controllers\CostCenters\CostCenterController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerCreditNoteController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\CustomerInvoiceRevisionController;
use App\Http\Controllers\CustomerOpeningBalanceController;
use App\Http\Controllers\CustomerReceiptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialStatementMappingController;
use App\Http\Controllers\FixedAssets\FixedAssetCapitalizationController;
use App\Http\Controllers\FixedAssets\FixedAssetCategoryController;
use App\Http\Controllers\FixedAssets\FixedAssetController;
use App\Http\Controllers\FixedAssets\FixedAssetDepreciationRunController;
use App\Http\Controllers\FixedAssets\FixedAssetDepreciationScheduleController;
use App\Http\Controllers\FixedAssets\FixedAssetDisposalController;
use App\Http\Controllers\FixedAssets\FixedAssetLocationController;
use App\Http\Controllers\FixedAssets\FixedAssetMovementController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\IncomingChequeController;
use App\Http\Controllers\LandedCostAllocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutgoingChequeController;
use App\Http\Controllers\PayableAllocationController;
use App\Http\Controllers\PayableEntrySettlementController;
use App\Http\Controllers\PayrollComponentController;
use App\Http\Controllers\PayrollEmployeeController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PrepaidScheduleController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReceivableAllocationController;
use App\Http\Controllers\ReceivableEntrySettlementController;
use App\Http\Controllers\RentableItemController;
use App\Http\Controllers\RentalContractController;
use App\Http\Controllers\RentalHandoverController;
use App\Http\Controllers\RentalInvoiceController;
use App\Http\Controllers\RentalReturnController;
use App\Http\Controllers\Reports\AgingReportDataTableController;
use App\Http\Controllers\Reports\ApAgingController;
use App\Http\Controllers\Reports\ApToGlReconciliationController;
use App\Http\Controllers\Reports\ArAgingController;
use App\Http\Controllers\Reports\ArApReconciliationDataTableController;
use App\Http\Controllers\Reports\ArToGlReconciliationController;
use App\Http\Controllers\Reports\BalanceSheetReportController;
use App\Http\Controllers\Reports\BankBookController;
use App\Http\Controllers\Reports\BankReconciliationReportController;
use App\Http\Controllers\Reports\BranchOperationalReportController;
use App\Http\Controllers\Reports\BranchProfitabilityReportController;
use App\Http\Controllers\Reports\CashBankBookDataTableController;
use App\Http\Controllers\Reports\CashBookController;
use App\Http\Controllers\Reports\CashFlowReportController;
use App\Http\Controllers\Reports\ChequeRegisterDataTableController;
use App\Http\Controllers\Reports\ChequeRegisterReportController;
use App\Http\Controllers\Reports\CostCenterActualsReportController;
use App\Http\Controllers\Reports\CustomerInvoiceReportController;
use App\Http\Controllers\Reports\CustomerStatementController;
use App\Http\Controllers\Reports\DeliveryNoteReportController;
use App\Http\Controllers\Reports\FixedAssetReportController;
use App\Http\Controllers\Reports\GoodsReceiptReportController;
use App\Http\Controllers\Reports\IncomeStatementReportController;
use App\Http\Controllers\Reports\OperationalReportDataTableController;
use App\Http\Controllers\Reports\PartnerStatementDataTableController;
use App\Http\Controllers\Reports\ProjectProfitabilityReportController;
use App\Http\Controllers\Reports\PurchaseOrderReportController;
use App\Http\Controllers\Reports\RentalOperationsDataTableController;
use App\Http\Controllers\Reports\RentalOperationsReportController;
use App\Http\Controllers\Reports\ReportsHubController;
use App\Http\Controllers\Reports\SalesOrderReportController;
use App\Http\Controllers\Reports\StockMovementReportController;
use App\Http\Controllers\Reports\SupplierBillReportController;
use App\Http\Controllers\Reports\SupplierStatementController;
use App\Http\Controllers\Reports\VatRegisterDataTableController;
use App\Http\Controllers\Reports\VatReportController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\Settings\BranchApprovalRuleController;
use App\Http\Controllers\Settings\BranchSettingsController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\NumberingSettingsController;
use App\Http\Controllers\Settings\RoleSettingsController;
use App\Http\Controllers\Settings\SettingsHomeController;
use App\Http\Controllers\Settings\UserRoleAssignmentController;
use App\Http\Controllers\Settings\UserSettingsController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockBalanceController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockLocationController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierAdjustmentNoteController;
use App\Http\Controllers\SupplierBillController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierOpeningBalanceController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\Taxes\TaxCodeController;
use App\Http\Controllers\Taxes\TaxPeriodController;
use App\Http\Controllers\Taxes\TaxRateController;
use App\Http\Controllers\TreasuryTransferController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/{locale}/{path?}', function (Request $request, string $locale, ?string $path = null) {
    $request->session()->put('locale', $locale);
    app()->setLocale($locale);

    if ($request->user()) {
        $request->user()->update(['locale' => $locale]);
    }

    $target = '/'.ltrim($path ?: 'dashboard', '/');
    $query = $request->getQueryString();

    return redirect()->to($target.($query ? '?'.$query : ''));
})
    ->where(['locale' => 'en|ar', 'path' => '.*'])
    ->name('locale.prefixed_redirect');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard')->name('foundation');
    Route::get('/foundation', FoundationController::class)->middleware('permission.any:settings.configure,audit.view')->name('foundation.diagnostics');
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/settings', SettingsHomeController::class)->middleware('permission.any:settings.view,settings.configure,settings.company,settings.branches,settings.numbering,users.configure,approvals.configure,audit.view')->name('settings');
    Route::get('/settings/company', [CompanySettingsController::class, 'index'])->middleware('permission.any:settings.company,settings.configure')->name('settings.company');
    Route::post('/settings/company', [CompanySettingsController::class, 'store'])->middleware('permission.any:settings.company,settings.configure')->name('settings.company.store');
    Route::patch('/settings/company/{companyId?}', [CompanySettingsController::class, 'update'])->middleware('permission.any:settings.company,settings.configure')->name('settings.company.update');
    Route::get('/settings/branches', [BranchSettingsController::class, 'index'])->middleware('permission.any:settings.branches,settings.configure')->name('settings.branches');
    Route::post('/settings/branches', [BranchSettingsController::class, 'store'])->middleware('permission.any:settings.branches,settings.configure')->name('settings.branches.store');
    Route::patch('/settings/branches/{branchId}', [BranchSettingsController::class, 'update'])->middleware('permission.any:settings.branches,settings.configure')->name('settings.branches.update');
    Route::delete('/settings/branches/{branchId}', [BranchSettingsController::class, 'destroy'])->middleware('permission.any:settings.branches,settings.configure')->name('settings.branches.delete');
    Route::get('/settings/numbering', [NumberingSettingsController::class, 'index'])->middleware('permission.any:settings.numbering,settings.configure')->name('settings.numbering');
    Route::post('/settings/numbering', [NumberingSettingsController::class, 'store'])->middleware('permission.any:settings.numbering,settings.configure')->name('settings.numbering.store');
    Route::patch('/settings/numbering/{sequenceId}', [NumberingSettingsController::class, 'update'])->middleware('permission.any:settings.numbering,settings.configure')->name('settings.numbering.update');
    Route::get('/settings/users', [UserSettingsController::class, 'index'])->middleware('permission.any:users.configure,settings.configure')->name('settings.users');
    Route::post('/settings/users', [UserSettingsController::class, 'store'])->middleware('permission.any:users.configure,settings.configure')->name('settings.users.store');
    Route::post('/settings/users/roles', [UserRoleAssignmentController::class, 'assign'])->middleware('permission.any:users.configure,settings.configure')->name('settings.users.roles.assign');
    Route::delete('/settings/users/roles', [UserRoleAssignmentController::class, 'revoke'])->middleware('permission.any:users.configure,settings.configure')->name('settings.users.roles.revoke');
    Route::patch('/settings/users/{userId}', [UserSettingsController::class, 'update'])->middleware('permission.any:users.configure,settings.configure')->name('settings.users.update');
    Route::delete('/settings/users/{userId}', [UserSettingsController::class, 'destroy'])->middleware('permission.any:users.configure,settings.configure')->name('settings.users.delete');
    Route::post('/settings/roles', [RoleSettingsController::class, 'store'])->middleware('permission.any:users.configure,settings.configure')->name('settings.roles.store');
    Route::patch('/settings/roles/{roleId}', [RoleSettingsController::class, 'update'])->middleware('permission.any:users.configure,settings.configure')->name('settings.roles.update');
    Route::delete('/settings/roles/{roleId}', [RoleSettingsController::class, 'destroy'])->middleware('permission.any:users.configure,settings.configure')->name('settings.roles.delete');
    Route::get('/settings/branch-approval-rules', [BranchApprovalRuleController::class, 'index'])->middleware('permission.any:approvals.configure,settings.configure')->name('settings.branch_approval_rules');
    Route::post('/settings/branch-approval-rules', [BranchApprovalRuleController::class, 'store'])->middleware('permission.any:approvals.configure,settings.configure')->name('settings.branch_approval_rules.store');
    Route::patch('/settings/branch-approval-rules/{id}', [BranchApprovalRuleController::class, 'update'])->middleware('permission.any:approvals.configure,settings.configure')->name('settings.branch_approval_rules.update');
    Route::delete('/settings/branch-approval-rules/{id}', [BranchApprovalRuleController::class, 'destroy'])->middleware('permission.any:approvals.configure,settings.configure')->name('settings.branch_approval_rules.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{id}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit.index');

    // Phase 2 Accounting Core Routes
    Route::prefix('accounting')->group(function (): void {
        Route::get('/', AccountingOverviewController::class)->middleware('permission.any:accounting.view,settings.configure')->name('accounting.index');
        Route::get('/coa', [ChartOfAccountsController::class, 'index'])->middleware('permission.any:accounting.view,settings.configure')->name('accounting.coa');
        Route::get('/coa/data', [ChartOfAccountsController::class, 'datatable'])->middleware('permission.any:accounting.view,settings.configure')->name('accounting.coa.datatable');
        Route::post('/coa/groups', [ChartOfAccountsController::class, 'storeGroup'])->middleware('permission.any:accounting.create,settings.configure')->name('accounting.coa.groups.store');
        Route::post('/coa/accounts', [ChartOfAccountsController::class, 'storeAccount'])->middleware('permission.any:accounting.create,settings.configure')->name('accounting.coa.accounts.store');
        Route::get('/journal', [JournalController::class, 'index'])->middleware('permission.any:accounting.view,settings.configure')->name('accounting.journal');
        Route::get('/journal/data', [JournalController::class, 'datatable'])->middleware('permission.any:accounting.view,settings.configure')->name('accounting.journal.datatable');
        Route::get('/journal/create', [JournalController::class, 'create'])->middleware('permission.any:accounting.create,settings.configure')->name('accounting.journal.create');
        Route::post('/journal', [JournalController::class, 'store'])->middleware('permission.any:accounting.create,settings.configure')->name('accounting.journal.store');
        Route::get('/journal/{journalEntry}', [JournalController::class, 'show'])->middleware('permission.any:accounting.view,settings.configure')->name('accounting.journal.show');
        Route::post('/journal/{journalEntry}/submit', [JournalController::class, 'submit'])->middleware('permission.any:accounting.submit,settings.configure')->name('accounting.journal.submit');
        Route::post('/journal/{journalEntry}/approve', [JournalController::class, 'approve'])->middleware('permission.any:accounting.approve,settings.configure')->name('accounting.journal.approve');
        Route::post('/journal/{journalEntry}/post', [JournalController::class, 'post'])->middleware(['permission.all:accounting.post,view_financials', 'sensitive.confirm'])->name('accounting.journal.post');
        Route::post('/journal/{journalEntry}/reverse', [JournalController::class, 'reverse'])->middleware(['permission.any:accounting.reverse,settings.configure', 'sensitive.confirm'])->name('accounting.journal.reverse');
        Route::get('/ledger', GeneralLedgerController::class)->middleware('permission.any:accounting.view,settings.configure')->name('accounting.ledger');
        Route::get('/trial-balance', TrialBalanceController::class)->middleware('permission.any:accounting.view,settings.configure')->name('accounting.trial_balance');
        Route::get('/periods', [FinancialPeriodController::class, 'index'])->middleware('permission.any:accounting.periods,settings.configure')->name('accounting.periods');
        Route::get('/periods/{period}/close-readiness', [FinancialPeriodController::class, 'closeReadiness'])->middleware('permission.any:accounting.periods,settings.configure')->name('accounting.periods.close_readiness');
        Route::post('/periods/fiscal-years', [FinancialPeriodController::class, 'storeFiscalYear'])->middleware('permission.any:accounting.periods,settings.configure')->name('accounting.periods.fiscal_years.store');
        Route::post('/periods/{period}/close', [FinancialPeriodController::class, 'close'])->middleware(['can:close_period', 'sensitive.confirm'])->name('accounting.periods.close');
        Route::post('/periods/{period}/reopen', [FinancialPeriodController::class, 'reopen'])->middleware(['can:reopen_period', 'sensitive.confirm'])->name('accounting.periods.reopen');
        Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])->middleware('permission.any:accounting.opening_balances,settings.configure')->name('accounting.opening_balances');
        Route::post('/opening-balances', [OpeningBalanceController::class, 'save'])->middleware('permission.any:accounting.opening_balances,settings.configure')->name('accounting.opening_balances.save');
        Route::post('/opening-balances/post', [OpeningBalanceController::class, 'post'])->middleware(['permission.all:accounting.opening_balances,view_financials', 'sensitive.confirm'])->name('accounting.opening_balances.post');
        Route::get('/fx-rates', [ExchangeRateController::class, 'index'])->middleware('permission.any:accounting.view,accounting.fx_rates,manage_fx_rates,settings.configure')->name('accounting.fx_rates');
        Route::post('/fx-rates', [ExchangeRateController::class, 'store'])->middleware('permission.any:accounting.create,manage_fx_rates,settings.configure')->name('accounting.fx_rates.store');
        Route::get('/currencies', [CurrencyController::class, 'index'])->middleware('permission.any:accounting.view,accounting.currencies,manage_currencies,settings.configure')->name('accounting.currencies');
        Route::post('/currencies', [CurrencyController::class, 'store'])->middleware('permission.any:accounting.create,manage_currencies,settings.configure')->name('accounting.currencies.store');
        Route::patch('/currencies/{currency}', [CurrencyController::class, 'update'])->middleware('permission.any:accounting.edit,manage_currencies,settings.configure')->name('accounting.currencies.update');
        Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])->middleware('permission.any:accounting.delete,manage_currencies,settings.configure')->name('accounting.currencies.destroy');
        Route::get('/account-types', [AccountTypeController::class, 'index'])->middleware('permission.any:accounting.account_types,manage_account_types,settings.configure')->name('accounting.account_types');
        Route::post('/account-types', [AccountTypeController::class, 'store'])->middleware('permission.any:accounting.account_types,manage_account_types,settings.configure')->name('accounting.account_types.store');
        Route::patch('/account-types/{accountType}', [AccountTypeController::class, 'update'])->middleware('permission.any:accounting.account_types,manage_account_types,settings.configure')->name('accounting.account_types.update');
        Route::delete('/account-types/{accountType}', [AccountTypeController::class, 'destroy'])->middleware('permission.any:accounting.delete,manage_account_types,settings.configure')->name('accounting.account_types.destroy');
        Route::get('/account-categories', [AccountCategoryController::class, 'index'])->middleware('permission.any:accounting.account_categories,manage_account_categories,settings.configure')->name('accounting.account_categories');
        Route::post('/account-categories', [AccountCategoryController::class, 'store'])->middleware('permission.any:accounting.account_categories,manage_account_categories,settings.configure')->name('accounting.account_categories.store');
        Route::patch('/account-categories/{accountCategory}', [AccountCategoryController::class, 'update'])->middleware('permission.any:accounting.account_categories,manage_account_categories,settings.configure')->name('accounting.account_categories.update');
        Route::delete('/account-categories/{accountCategory}', [AccountCategoryController::class, 'destroy'])->middleware('permission.any:accounting.delete,manage_account_categories,settings.configure')->name('accounting.account_categories.destroy');

        // Phase 5 Slice 1 Financial Statement Mappings Routes
        Route::get('/statement-mappings', [FinancialStatementMappingController::class, 'index'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.index');
        Route::get('/statement-mappings/data', [FinancialStatementMappingController::class, 'datatable'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.datatable');
        Route::post('/statement-mappings/lines', [FinancialStatementMappingController::class, 'storeLine'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.lines.store');
        Route::put('/statement-mappings/lines/{id}', [FinancialStatementMappingController::class, 'updateLine'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.lines.update');
        Route::delete('/statement-mappings/lines/{id}', [FinancialStatementMappingController::class, 'destroyLine'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.lines.destroy');
        Route::post('/statement-mappings/assign', [FinancialStatementMappingController::class, 'assign'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.assign');
        Route::post('/statement-mappings/bulk-assign', [FinancialStatementMappingController::class, 'bulkAssign'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.bulk_assign');
        Route::post('/statement-mappings/account-cash-flow', [FinancialStatementMappingController::class, 'updateAccountCashFlow'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.statement_mappings.account_cash_flow');
        Route::get('/account-mappings', [AccountingAccountMappingController::class, 'index'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.account_mappings.index');
        Route::get('/account-mappings/data', [AccountingAccountMappingController::class, 'datatable'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.account_mappings.datatable');
        Route::post('/account-mappings', [AccountingAccountMappingController::class, 'store'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.account_mappings.store');
        Route::delete('/account-mappings/{id}', [AccountingAccountMappingController::class, 'destroy'])->middleware('permission.any:accounting.mappings,settings.configure')->name('accounting.account_mappings.destroy');
    });

    // Phase 3 Operational Master Data & Accounting Routes
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
    Route::get('/customers/data', [CustomerController::class, 'datatable'])->middleware('can:customers.view')->name('customers.datatable');
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

    Route::get('/treasury-transfers', [TreasuryTransferController::class, 'index'])->middleware('permission.any:cash.view,banks.view')->name('treasury-transfers.index');
    Route::post('/treasury-transfers', [TreasuryTransferController::class, 'store'])->middleware('permission.any:cash.create,banks.create')->name('treasury-transfers.store');
    Route::patch('/treasury-transfers/{id}', [TreasuryTransferController::class, 'update'])->middleware('permission.any:cash.edit,banks.edit')->name('treasury-transfers.update');
    Route::post('/treasury-transfers/{id}/post', [TreasuryTransferController::class, 'post'])->middleware(['permission.any:cash.post,banks.post', 'can:view_financials', 'sensitive.confirm'])->name('treasury-transfers.post');
    Route::post('/treasury-transfers/{id}/cancel', [TreasuryTransferController::class, 'cancel'])->middleware('permission.any:cash.edit,banks.edit')->name('treasury-transfers.cancel');

    Route::get('/customer-opening-balances', [CustomerOpeningBalanceController::class, 'index'])->middleware('can:customers.view')->name('customer-opening-balances.index');
    Route::get('/customer-opening-balances/data', [CustomerOpeningBalanceController::class, 'datatable'])->middleware('can:customers.view')->name('customer-opening-balances.datatable');
    Route::post('/customer-opening-balances', [CustomerOpeningBalanceController::class, 'store'])->middleware('can:customers.opening_balances')->name('customer-opening-balances.store');
    Route::post('/customer-opening-balances/{id}/post', [CustomerOpeningBalanceController::class, 'post'])->middleware(['permission.all:customers.opening_balances,view_financials', 'sensitive.confirm'])->name('customer-opening-balances.post');

    Route::get('/supplier-opening-balances', [SupplierOpeningBalanceController::class, 'index'])->middleware('can:suppliers.view')->name('supplier-opening-balances.index');
    Route::post('/supplier-opening-balances', [SupplierOpeningBalanceController::class, 'store'])->middleware('can:suppliers.opening_balances')->name('supplier-opening-balances.store');
    Route::post('/supplier-opening-balances/{id}/post', [SupplierOpeningBalanceController::class, 'post'])->middleware(['permission.all:suppliers.opening_balances,view_financials', 'sensitive.confirm'])->name('supplier-opening-balances.post');

    Route::get('/customer-receipts', [CustomerReceiptController::class, 'index'])->middleware('can:customers.view')->name('customer-receipts.index');
    Route::get('/customer-receipts/data', [CustomerReceiptController::class, 'datatable'])->middleware('can:customers.view')->name('customer-receipts.datatable');
    Route::post('/customer-receipts', [CustomerReceiptController::class, 'store'])->middleware('can:customers.receipts')->name('customer-receipts.store');
    Route::post('/customer-receipts/{id}/post', [CustomerReceiptController::class, 'post'])->middleware(['permission.all:customers.receipts,view_financials', 'sensitive.confirm'])->name('customer-receipts.post');

    Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->middleware('can:suppliers.view')->name('supplier-payments.index');
    Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->middleware('can:suppliers.payments')->name('supplier-payments.store');
    Route::post('/supplier-payments/{id}/post', [SupplierPaymentController::class, 'post'])->middleware(['permission.all:suppliers.payments,view_financials', 'sensitive.confirm'])->name('supplier-payments.post');

    Route::get('/receivable-allocations', [ReceivableAllocationController::class, 'index'])->middleware('can:customers.view')->name('receivable-allocations.index');
    Route::get('/receivable-allocations/data', [ReceivableAllocationController::class, 'datatable'])->middleware('can:customers.view')->name('receivable-allocations.datatable');
    Route::post('/receivable-allocations', [ReceivableAllocationController::class, 'store'])->middleware('can:customers.allocations')->name('receivable-allocations.store');
    Route::post('/receivable-allocations/{id}/reverse', [ReceivableAllocationController::class, 'reverse'])->middleware(['can:customers.allocations', 'sensitive.confirm'])->name('receivable-allocations.reverse');

    Route::get('/payable-allocations', [PayableAllocationController::class, 'index'])->middleware('can:suppliers.view')->name('payable-allocations.index');
    Route::post('/payable-allocations', [PayableAllocationController::class, 'store'])->middleware('can:suppliers.allocations')->name('payable-allocations.store');
    Route::post('/payable-allocations/{id}/reverse', [PayableAllocationController::class, 'reverse'])->middleware(['can:suppliers.allocations', 'sensitive.confirm'])->name('payable-allocations.reverse');

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
    Route::post('/bank-reconciliations/{id}/finalize', [BankReconciliationController::class, 'finalize'])->middleware(['can:banks.reconcile', 'sensitive.confirm'])->name('bank-reconciliations.finalize');

    // Phase 3 Slice 8 Reports & Subledgers Routes
    Route::prefix('reports')->middleware('permission.all:reports.view,view_financials')->group(function (): void {
        Route::get('/', [ReportsHubController::class, 'index'])->name('reports.index');
        Route::get('/customer-statement', [CustomerStatementController::class, 'index'])->name('reports.customer-statement');
        Route::get('/customer-statement/data', [PartnerStatementDataTableController::class, 'customer'])->name('reports.customer-statement.data');
        Route::get('/customer-statement/export', [CustomerStatementController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.customer-statement.export');
        Route::get('/supplier-statement', [SupplierStatementController::class, 'index'])->name('reports.supplier-statement');
        Route::get('/supplier-statement/data', [PartnerStatementDataTableController::class, 'supplier'])->name('reports.supplier-statement.data');
        Route::get('/supplier-statement/export', [SupplierStatementController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.supplier-statement.export');
        Route::get('/ar-aging', [ArAgingController::class, 'index'])->name('reports.ar-aging');
        Route::get('/ar-aging/data', [AgingReportDataTableController::class, 'accountsReceivable'])->name('reports.ar-aging.data');
        Route::get('/ar-aging/export', [ArAgingController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.ar-aging.export');
        Route::get('/ap-aging', [ApAgingController::class, 'index'])->name('reports.ap-aging');
        Route::get('/ap-aging/data', [AgingReportDataTableController::class, 'accountsPayable'])->name('reports.ap-aging.data');
        Route::get('/ap-aging/export', [ApAgingController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.ap-aging.export');
        Route::get('/cash-book', [CashBookController::class, 'index'])->name('reports.cash-book');
        Route::get('/cash-book/data', [CashBankBookDataTableController::class, 'cashBook'])->name('reports.cash-book.data');
        Route::get('/cash-book/export', [CashBookController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.cash-book.export');
        Route::get('/bank-book', [BankBookController::class, 'index'])->name('reports.bank-book');
        Route::get('/bank-book/data', [CashBankBookDataTableController::class, 'bankBook'])->name('reports.bank-book.data');
        Route::get('/bank-book/export', [BankBookController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.bank-book.export');
        Route::get('/cheque-register', [ChequeRegisterReportController::class, 'index'])->name('reports.cheque-register');
        Route::get('/cheque-register/data', ChequeRegisterDataTableController::class)->name('reports.cheque-register.data');
        Route::get('/cheque-register/export', [ChequeRegisterReportController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.cheque-register.export');
        Route::get('/bank-reconciliations', [BankReconciliationReportController::class, 'index'])->name('reports.bank-reconciliations');
        Route::get('/bank-reconciliations/{id}', [BankReconciliationReportController::class, 'show'])->name('reports.bank-reconciliations.show');
        Route::get('/ar-gl-reconciliation', [ArToGlReconciliationController::class, 'index'])->name('reports.ar-gl-reconciliation');
        Route::get('/ar-gl-reconciliation/data', [ArApReconciliationDataTableController::class, 'accountsReceivable'])->name('reports.ar-gl-reconciliation.data');
        Route::get('/ar-gl-reconciliation/export', [ArToGlReconciliationController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.ar-gl-reconciliation.export');
        Route::get('/ap-gl-reconciliation', [ApToGlReconciliationController::class, 'index'])->name('reports.ap-gl-reconciliation');
        Route::get('/ap-gl-reconciliation/data', [ArApReconciliationDataTableController::class, 'accountsPayable'])->name('reports.ap-gl-reconciliation.data');
        Route::get('/ap-gl-reconciliation/export', [ApToGlReconciliationController::class, 'exportCsv'])->middleware('can:reports.export')->name('reports.ap-gl-reconciliation.export');

        // Phase 4 Slice 9 Operational Reports
        Route::get('/sales-orders', [SalesOrderReportController::class, 'index'])->name('reports.sales-orders');
        Route::get('/sales-orders/data', [OperationalReportDataTableController::class, 'salesOrders'])->name('reports.sales-orders.data');
        Route::get('/purchase-orders', [PurchaseOrderReportController::class, 'index'])->name('reports.purchase-orders');
        Route::get('/purchase-orders/data', [OperationalReportDataTableController::class, 'purchaseOrders'])->name('reports.purchase-orders.data');
        Route::get('/delivery-notes', [DeliveryNoteReportController::class, 'index'])->name('reports.delivery-notes');
        Route::get('/delivery-notes/data', [OperationalReportDataTableController::class, 'deliveryNotes'])->name('reports.delivery-notes.data');
        Route::get('/goods-receipts', [GoodsReceiptReportController::class, 'index'])->name('reports.goods-receipts');
        Route::get('/goods-receipts/data', [OperationalReportDataTableController::class, 'goodsReceipts'])->name('reports.goods-receipts.data');
        Route::get('/customer-invoices', [CustomerInvoiceReportController::class, 'index'])->name('reports.customer-invoices');
        Route::get('/customer-invoices/data', [OperationalReportDataTableController::class, 'customerInvoices'])->name('reports.customer-invoices.data');
        Route::get('/supplier-bills', [SupplierBillReportController::class, 'index'])->name('reports.supplier-bills');
        Route::get('/supplier-bills/data', [OperationalReportDataTableController::class, 'supplierBills'])->name('reports.supplier-bills.data');
        Route::get('/stock-movements', [StockMovementReportController::class, 'index'])->name('reports.stock-movements');
        Route::get('/stock-movements/data', [OperationalReportDataTableController::class, 'stockMovements'])->name('reports.stock-movements.data');
        Route::get('/branch-operations', [BranchOperationalReportController::class, 'index'])->middleware(['can:reports.view', 'can:view_financials'])->name('reports.branch-operations');
        Route::get('/branch-profitability', [BranchProfitabilityReportController::class, 'index'])->middleware(['can:reports.view', 'can:view_financials'])->name('reports.branch-profitability');
        Route::get('/branch-profitability/export', [BranchProfitabilityReportController::class, 'exportCsv'])->middleware(['can:reports.view', 'permission.all:reports.export,view_financials'])->name('reports.branch-profitability.export');
        Route::get('/project-profitability', [ProjectProfitabilityReportController::class, 'index'])->middleware(['can:reports.view', 'can:view_financials'])->name('reports.project-profitability');
        Route::get('/project-profitability/export', [ProjectProfitabilityReportController::class, 'exportCsv'])->middleware(['can:reports.view', 'permission.all:reports.export,view_financials'])->name('reports.project-profitability.export');
        Route::get('/cost-center-actuals', [CostCenterActualsReportController::class, 'index'])->middleware(['can:reports.view', 'can:view_financials'])->name('reports.cost-center-actuals');
        Route::get('/cost-center-actuals/export', [CostCenterActualsReportController::class, 'exportCsv'])->middleware(['can:reports.view', 'permission.all:reports.export,view_financials'])->name('reports.cost-center-actuals.export');
        Route::get('/rentals', [RentalOperationsReportController::class, 'index'])->middleware('can:view_financials')->name('reports.rentals');
        Route::get('/rentals/data', RentalOperationsDataTableController::class)->name('reports.rentals.data');
        Route::get('/rentals/export', [RentalOperationsReportController::class, 'exportCsv'])->middleware('permission.all:reports.export,view_financials')->name('reports.rentals.export');

        // Phase 5 Slice 2 & 3 Financial Statements Reports
        Route::get('/balance-sheet', [BalanceSheetReportController::class, 'index'])->name('reports.balance_sheet');

        // Phase 6 Slice 7 Fixed Asset Reports
        Route::get('/fixed-asset-register', [FixedAssetReportController::class, 'register'])->middleware('can:view_financials')->name('reports.fixed-asset-register');
        Route::get('/fixed-asset-register/export', [FixedAssetReportController::class, 'exportRegister'])->middleware('permission.any:reports.export,fixedAssets.export')->name('reports.fixed-asset-register.export');
        Route::get('/fixed-asset-net-book-values', [FixedAssetReportController::class, 'netBookValues'])->middleware('can:view_financials')->name('reports.fixed-asset-net-book-values');
        Route::get('/fixed-asset-net-book-values/export', [FixedAssetReportController::class, 'exportNetBookValues'])->middleware('permission.any:reports.export,fixedAssets.export')->name('reports.fixed-asset-net-book-values.export');
        Route::get('/fixed-asset-depreciation', [FixedAssetReportController::class, 'depreciation'])->middleware('can:view_financials')->name('reports.fixed-asset-depreciation');
        Route::get('/fixed-asset-depreciation/export', [FixedAssetReportController::class, 'exportDepreciation'])->middleware('permission.any:reports.export,fixedAssets.export')->name('reports.fixed-asset-depreciation.export');
        Route::get('/fixed-asset-depreciation-runs', [FixedAssetReportController::class, 'depreciationRuns'])->middleware('can:view_financials')->name('reports.fixed-asset-depreciation-runs');
        Route::get('/fixed-asset-depreciation-runs/export', [FixedAssetReportController::class, 'exportDepreciationRuns'])->middleware('permission.any:reports.export,fixedAssets.export')->name('reports.fixed-asset-depreciation-runs.export');
        Route::get('/fixed-asset-disposals', [FixedAssetReportController::class, 'disposals'])->middleware('can:view_financials')->name('reports.fixed-asset-disposals');
        Route::get('/fixed-asset-disposals/export', [FixedAssetReportController::class, 'exportDisposals'])->middleware('permission.any:reports.export,fixedAssets.export')->name('reports.fixed-asset-disposals.export');
        Route::get('/balance-sheet/export', [BalanceSheetReportController::class, 'exportCsv'])->middleware('permission.all:reports.export,view_financials')->name('reports.balance_sheet.export');
        Route::get('/income-statement', [IncomeStatementReportController::class, 'index'])->name('reports.income_statement');
        Route::get('/income-statement/export', [IncomeStatementReportController::class, 'exportCsv'])->middleware('permission.all:reports.export,view_financials')->name('reports.income_statement.export');
        Route::get('/cash-flow', [CashFlowReportController::class, 'index'])->name('reports.cash_flow');
        Route::get('/cash-flow/export', [CashFlowReportController::class, 'exportCsv'])->middleware('permission.all:reports.export,view_financials')->name('reports.cash_flow.export');

        // Phase 7 Slice 5 VAT Reports
        Route::get('/vat-register', [VatReportController::class, 'register'])->middleware('can:view_financials')->name('reports.vat-register');
        Route::get('/vat-register/data', VatRegisterDataTableController::class)->name('reports.vat-register.data');
        Route::get('/vat-register/export', [VatReportController::class, 'exportRegister'])->middleware('permission.any:reports.export,taxes.view')->name('reports.vat-register.export');
        Route::get('/vat-summary', [VatReportController::class, 'summary'])->middleware('can:view_financials')->name('reports.vat-summary');
        Route::get('/vat-summary/export', [VatReportController::class, 'exportSummary'])->middleware('permission.any:reports.export,taxes.view')->name('reports.vat-summary.export');
        Route::get('/vat-gl-reconciliation', [VatReportController::class, 'reconciliation'])->middleware('can:view_financials')->name('reports.vat-gl-reconciliation');
        Route::get('/vat-gl-reconciliation/export', [VatReportController::class, 'exportReconciliation'])->middleware('permission.any:reports.export,taxes.view')->name('reports.vat-gl-reconciliation.export');
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

    Route::get('/purchasing/landed-costs', [LandedCostAllocationController::class, 'index'])->middleware('can:purchasing.landed_costs')->name('landed-costs.index');
    Route::post('/purchasing/landed-costs', [LandedCostAllocationController::class, 'store'])->middleware('can:purchasing.landed_costs')->name('landed-costs.store');
    Route::put('/purchasing/landed-costs/{id}', [LandedCostAllocationController::class, 'update'])->middleware('can:purchasing.landed_costs')->name('landed-costs.update');
    Route::post('/purchasing/landed-costs/{id}/submit', [LandedCostAllocationController::class, 'submit'])->middleware('can:purchasing.landed_costs')->name('landed-costs.submit');
    Route::post('/purchasing/landed-costs/{id}/approve', [LandedCostAllocationController::class, 'approve'])->middleware('permission.all:purchasing.landed_costs,purchasing.approve')->name('landed-costs.approve');
    Route::post('/purchasing/landed-costs/{id}/post', [LandedCostAllocationController::class, 'post'])->middleware(['permission.all:purchasing.landed_costs,purchasing.post,view_financials', 'sensitive.confirm'])->name('landed-costs.post');
    Route::post('/purchasing/landed-costs/{id}/cancel', [LandedCostAllocationController::class, 'cancel'])->middleware('can:purchasing.landed_costs')->name('landed-costs.cancel');

    // Phase 11 Expense Management Routes
    Route::get('/expenses/categories', [ExpenseCategoryController::class, 'index'])->middleware('can:expenses.view')->name('expense-categories.index');
    Route::post('/expenses/categories', [ExpenseCategoryController::class, 'store'])->middleware('can:expenses.create')->name('expense-categories.store');
    Route::put('/expenses/categories/{id}', [ExpenseCategoryController::class, 'update'])->middleware('can:expenses.edit')->name('expense-categories.update');
    Route::delete('/expenses/categories/{id}', [ExpenseCategoryController::class, 'destroy'])->middleware('can:expenses.delete')->name('expense-categories.destroy');
    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('can:expenses.view')->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('can:expenses.create')->name('expenses.store');
    Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->middleware('can:expenses.edit')->name('expenses.update');
    Route::post('/expenses/{id}/submit', [ExpenseController::class, 'submit'])->middleware('can:expenses.submit')->name('expenses.submit');
    Route::post('/expenses/{id}/approve', [ExpenseController::class, 'approve'])->middleware('can:expenses.approve')->name('expenses.approve');
    Route::post('/expenses/{id}/post', [ExpenseController::class, 'post'])->middleware('permission.all:expenses.post,view_financials')->name('expenses.post');
    Route::post('/expenses/{id}/cancel', [ExpenseController::class, 'cancel'])->middleware('can:expenses.edit')->name('expenses.cancel');

    // Phase 12 Prepaid and Accrued Expense Routes
    Route::get('/expenses/prepaids', [PrepaidScheduleController::class, 'index'])->middleware('can:expenses.view')->name('prepaid-schedules.index');
    Route::post('/expenses/prepaids', [PrepaidScheduleController::class, 'store'])->middleware('can:expenses.create')->name('prepaid-schedules.store');
    Route::put('/expenses/prepaids/{id}', [PrepaidScheduleController::class, 'update'])->middleware('can:expenses.edit')->name('prepaid-schedules.update');
    Route::post('/expenses/prepaids/{id}/submit', [PrepaidScheduleController::class, 'submit'])->middleware('can:expenses.submit')->name('prepaid-schedules.submit');
    Route::post('/expenses/prepaids/{id}/approve', [PrepaidScheduleController::class, 'approve'])->middleware('can:expenses.approve')->name('prepaid-schedules.approve');
    Route::post('/expenses/prepaids/{id}/recognitions/{recognitionId}/post', [PrepaidScheduleController::class, 'postRecognition'])->middleware('permission.all:expenses.post,view_financials')->name('prepaid-schedules.recognitions.post');
    Route::post('/expenses/prepaids/{id}/cancel', [PrepaidScheduleController::class, 'cancel'])->middleware('can:expenses.edit')->name('prepaid-schedules.cancel');

    Route::get('/expenses/accruals', [AccrualScheduleController::class, 'index'])->middleware('can:expenses.view')->name('accrual-schedules.index');
    Route::post('/expenses/accruals', [AccrualScheduleController::class, 'store'])->middleware('can:expenses.create')->name('accrual-schedules.store');
    Route::put('/expenses/accruals/{id}', [AccrualScheduleController::class, 'update'])->middleware('can:expenses.edit')->name('accrual-schedules.update');
    Route::post('/expenses/accruals/{id}/submit', [AccrualScheduleController::class, 'submit'])->middleware('can:expenses.submit')->name('accrual-schedules.submit');
    Route::post('/expenses/accruals/{id}/approve', [AccrualScheduleController::class, 'approve'])->middleware('can:expenses.approve')->name('accrual-schedules.approve');
    Route::post('/expenses/accruals/{id}/entries/{entryId}/post', [AccrualScheduleController::class, 'postEntry'])->middleware('permission.all:expenses.post,view_financials')->name('accrual-schedules.entries.post');
    Route::post('/expenses/accruals/{id}/cancel', [AccrualScheduleController::class, 'cancel'])->middleware('can:expenses.edit')->name('accrual-schedules.cancel');

    // Phase 13 Payroll Foundation Routes
    Route::prefix('payroll')->group(function (): void {
        Route::get('/employees', [PayrollEmployeeController::class, 'index'])->middleware('permission.all:payroll.view,view_payroll')->name('payroll.employees.index');
        Route::post('/employees', [PayrollEmployeeController::class, 'store'])->middleware('permission.all:payroll.create,view_payroll')->name('payroll.employees.store');
        Route::put('/employees/{id}', [PayrollEmployeeController::class, 'update'])->middleware('permission.all:payroll.edit,view_payroll')->name('payroll.employees.update');
        Route::post('/employees/{id}/components', [PayrollEmployeeController::class, 'storeComponent'])->middleware('permission.all:payroll.edit,view_payroll')->name('payroll.employees.components.store');
        Route::delete('/employees/{id}/components/{assignmentId}', [PayrollEmployeeController::class, 'destroyComponent'])->middleware('permission.all:payroll.edit,view_payroll')->name('payroll.employees.components.destroy');

        Route::get('/components', [PayrollComponentController::class, 'index'])->middleware('permission.all:payroll.view,view_payroll')->name('payroll.components.index');
        Route::post('/components', [PayrollComponentController::class, 'store'])->middleware('permission.all:payroll.create,view_payroll')->name('payroll.components.store');
        Route::put('/components/{id}', [PayrollComponentController::class, 'update'])->middleware('permission.all:payroll.edit,view_payroll')->name('payroll.components.update');
        Route::delete('/components/{id}', [PayrollComponentController::class, 'destroy'])->middleware('permission.all:payroll.delete,view_payroll')->name('payroll.components.destroy');

        Route::get('/runs', [PayrollRunController::class, 'index'])->middleware('permission.all:payroll.view,view_payroll')->name('payroll.runs.index');
        Route::post('/runs', [PayrollRunController::class, 'store'])->middleware('permission.all:payroll.create,view_payroll')->name('payroll.runs.store');
        Route::post('/runs/{id}/regenerate', [PayrollRunController::class, 'regenerate'])->middleware('permission.all:payroll.edit,view_payroll')->name('payroll.runs.regenerate');
        Route::post('/runs/{id}/submit', [PayrollRunController::class, 'submit'])->middleware('permission.all:payroll.submit,view_payroll')->name('payroll.runs.submit');
        Route::post('/runs/{id}/approve', [PayrollRunController::class, 'approve'])->middleware('permission.all:payroll.approve,view_payroll')->name('payroll.runs.approve');
        Route::post('/runs/{id}/post', [PayrollRunController::class, 'post'])->middleware(['permission.all:payroll.post,view_payroll,view_financials', 'sensitive.confirm'])->name('payroll.runs.post');
        Route::post('/runs/{id}/cancel', [PayrollRunController::class, 'cancel'])->middleware('permission.all:payroll.edit,view_payroll')->name('payroll.runs.cancel');
    });

    // Phase 14 Rentals Foundation Routes
    Route::prefix('rentals')->group(function (): void {
        Route::get('/items', [RentableItemController::class, 'index'])->middleware('can:rentals.view')->name('rentals.items.index');
        Route::post('/items', [RentableItemController::class, 'store'])->middleware('can:rentals.create')->name('rentals.items.store');
        Route::put('/items/{id}', [RentableItemController::class, 'update'])->middleware('can:rentals.edit')->name('rentals.items.update');
        Route::delete('/items/{id}', [RentableItemController::class, 'destroy'])->middleware('can:rentals.delete')->name('rentals.items.destroy');

        Route::get('/contracts', [RentalContractController::class, 'index'])->middleware('can:rentals.view')->name('rentals.contracts.index');
        Route::post('/contracts', [RentalContractController::class, 'store'])->middleware('can:rentals.create')->name('rentals.contracts.store');
        Route::put('/contracts/{id}', [RentalContractController::class, 'update'])->middleware('can:rentals.edit')->name('rentals.contracts.update');
        Route::post('/contracts/{id}/submit', [RentalContractController::class, 'submit'])->middleware('can:rentals.submit')->name('rentals.contracts.submit');
        Route::post('/contracts/{id}/approve', [RentalContractController::class, 'approve'])->middleware('can:rentals.approve')->name('rentals.contracts.approve');
        Route::post('/contracts/{id}/activate', [RentalContractController::class, 'activate'])->middleware('can:rentals.deliver')->name('rentals.contracts.activate');
        Route::post('/contracts/{id}/cancel', [RentalContractController::class, 'cancel'])->middleware('can:rentals.cancel')->name('rentals.contracts.cancel');

        Route::get('/invoices', [RentalInvoiceController::class, 'index'])->middleware('can:rentals.view')->name('rentals.invoices.index');
        Route::post('/invoices', [RentalInvoiceController::class, 'store'])->middleware('can:rentals.invoice')->name('rentals.invoices.store');
        Route::put('/invoices/{id}', [RentalInvoiceController::class, 'update'])->middleware('can:rentals.invoice')->name('rentals.invoices.update');
        Route::post('/invoices/{id}/submit', [RentalInvoiceController::class, 'submit'])->middleware('can:rentals.submit')->name('rentals.invoices.submit');
        Route::post('/invoices/{id}/approve', [RentalInvoiceController::class, 'approve'])->middleware('can:rentals.approve')->name('rentals.invoices.approve');
        Route::post('/invoices/{id}/post', [RentalInvoiceController::class, 'post'])->middleware(['permission.all:rentals.post,view_financials', 'sensitive.confirm'])->name('rentals.invoices.post');
        Route::post('/invoices/{id}/cancel', [RentalInvoiceController::class, 'cancel'])->middleware('can:rentals.cancel')->name('rentals.invoices.cancel');

        Route::get('/handovers', [RentalHandoverController::class, 'index'])->middleware('can:rentals.view')->name('rentals.handovers.index');
        Route::post('/handovers', [RentalHandoverController::class, 'store'])->middleware('can:rentals.deliver')->name('rentals.handovers.store');
        Route::post('/handovers/{id}/confirm', [RentalHandoverController::class, 'confirm'])->middleware('can:rentals.deliver')->name('rentals.handovers.confirm');
        Route::post('/handovers/{id}/cancel', [RentalHandoverController::class, 'cancel'])->middleware('can:rentals.cancel')->name('rentals.handovers.cancel');

        Route::get('/returns', [RentalReturnController::class, 'index'])->middleware('can:rentals.view')->name('rentals.returns.index');
        Route::post('/returns', [RentalReturnController::class, 'store'])->middleware('can:rentals.return')->name('rentals.returns.store');
        Route::post('/returns/{id}/submit', [RentalReturnController::class, 'submit'])->middleware('can:rentals.return')->name('rentals.returns.submit');
        Route::post('/returns/{id}/complete', [RentalReturnController::class, 'complete'])->middleware('can:rentals.inspect')->name('rentals.returns.complete');
        Route::post('/returns/{id}/cancel', [RentalReturnController::class, 'cancel'])->middleware('can:rentals.cancel')->name('rentals.returns.cancel');
    });

    // Phase 4 Slice 5 Customer Invoice Routes
    Route::get('/sales/invoices', [CustomerInvoiceController::class, 'index'])->middleware('can:sales.view')->name('customer-invoices.index');
    Route::post('/sales/invoices', [CustomerInvoiceController::class, 'store'])->middleware('can:sales.create')->name('customer-invoices.store');
    Route::put('/sales/invoices/{customerInvoice}', [CustomerInvoiceController::class, 'update'])->middleware('can:sales.edit')->name('customer-invoices.update');
    Route::post('/sales/invoices/{customerInvoice}/submit', [CustomerInvoiceController::class, 'submit'])->middleware('can:sales.submit')->name('customer-invoices.submit');
    Route::post('/sales/invoices/{customerInvoice}/approve', [CustomerInvoiceController::class, 'approve'])->middleware('can:sales.approve')->name('customer-invoices.approve');
    Route::post('/sales/invoices/{customerInvoice}/post', [CustomerInvoiceController::class, 'post'])->middleware(['permission.all:sales.post,view_financials', 'sensitive.confirm'])->name('customer-invoices.post');
    Route::post('/sales/invoices/{customerInvoice}/cancel', [CustomerInvoiceController::class, 'cancel'])->middleware('can:sales.cancel')->name('customer-invoices.cancel');

    // Phase 4 Slice 8 Inventory Costing Routes
    Route::get('/inventory/stock-balances', [StockBalanceController::class, 'index'])->middleware('can:inventory.view')->name('stock-balances.index');
    Route::get('/inventory/warehouses', [WarehouseController::class, 'index'])->middleware('can:inventory.view')->name('warehouses.index');
    Route::post('/inventory/warehouses', [WarehouseController::class, 'store'])->middleware('can:inventory.create')->name('warehouses.store');
    Route::put('/inventory/warehouses/{id}', [WarehouseController::class, 'update'])->middleware('can:inventory.edit')->name('warehouses.update');
    Route::delete('/inventory/warehouses/{id}', [WarehouseController::class, 'destroy'])->middleware('can:inventory.delete')->name('warehouses.destroy');
    Route::post('/inventory/locations', [StockLocationController::class, 'store'])->middleware('can:inventory.create')->name('stock-locations.store');
    Route::put('/inventory/locations/{id}', [StockLocationController::class, 'update'])->middleware('can:inventory.edit')->name('stock-locations.update');
    Route::get('/inventory/transfers', [StockTransferController::class, 'index'])->middleware('can:inventory.view')->name('stock-transfers.index');
    Route::post('/inventory/transfers', [StockTransferController::class, 'store'])->middleware('can:inventory.transfer')->name('stock-transfers.store');
    Route::put('/inventory/transfers/{id}', [StockTransferController::class, 'update'])->middleware('can:inventory.transfer')->name('stock-transfers.update');
    Route::post('/inventory/transfers/{id}/submit', [StockTransferController::class, 'submit'])->middleware('can:inventory.transfer')->name('stock-transfers.submit');
    Route::post('/inventory/transfers/{id}/approve', [StockTransferController::class, 'approve'])->middleware('can:inventory.approve')->name('stock-transfers.approve');
    Route::post('/inventory/transfers/{id}/issue', [StockTransferController::class, 'issue'])->middleware(['can:inventory.post', 'sensitive.confirm'])->name('stock-transfers.issue');
    Route::post('/inventory/transfers/{id}/receive', [StockTransferController::class, 'receive'])->middleware(['can:inventory.receive', 'sensitive.confirm'])->name('stock-transfers.receive');
    Route::post('/inventory/transfers/{id}/cancel', [StockTransferController::class, 'cancel'])->middleware('can:inventory.transfer')->name('stock-transfers.cancel');
    Route::get('/inventory/stock-counts', [StockCountController::class, 'index'])->middleware('can:inventory.view')->name('stock-counts.index');
    Route::post('/inventory/stock-counts', [StockCountController::class, 'store'])->middleware('can:inventory.count')->name('stock-counts.store');
    Route::put('/inventory/stock-counts/{id}', [StockCountController::class, 'update'])->middleware('can:inventory.count')->name('stock-counts.update');
    Route::post('/inventory/stock-counts/{id}/submit', [StockCountController::class, 'submit'])->middleware('can:inventory.count')->name('stock-counts.submit');
    Route::post('/inventory/stock-counts/{id}/approve', [StockCountController::class, 'approve'])->middleware('can:inventory.approve')->name('stock-counts.approve');
    Route::post('/inventory/stock-counts/{id}/post', [StockCountController::class, 'post'])->middleware(['permission.all:inventory.post,view_financials', 'sensitive.confirm'])->name('stock-counts.post');
    Route::post('/inventory/stock-counts/{id}/cancel', [StockCountController::class, 'cancel'])->middleware('can:inventory.count')->name('stock-counts.cancel');
    Route::get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])->middleware('can:inventory.view')->name('stock-adjustments.index');
    Route::post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])->middleware('can:inventory.adjust')->name('stock-adjustments.store');
    Route::put('/inventory/adjustments/{id}', [StockAdjustmentController::class, 'update'])->middleware('can:inventory.adjust')->name('stock-adjustments.update');
    Route::post('/inventory/adjustments/{id}/submit', [StockAdjustmentController::class, 'submit'])->middleware('can:inventory.adjust')->name('stock-adjustments.submit');
    Route::post('/inventory/adjustments/{id}/approve', [StockAdjustmentController::class, 'approve'])->middleware('can:inventory.approve')->name('stock-adjustments.approve');
    Route::post('/inventory/adjustments/{id}/post', [StockAdjustmentController::class, 'post'])->middleware(['permission.all:inventory.post,view_financials', 'sensitive.confirm'])->name('stock-adjustments.post');
    Route::post('/inventory/adjustments/{id}/cancel', [StockAdjustmentController::class, 'cancel'])->middleware('can:inventory.adjust')->name('stock-adjustments.cancel');

    // Phase 4 Slice 6 Supplier Bill Routes
    Route::get('/purchasing/bills', [SupplierBillController::class, 'index'])->middleware('can:purchasing.view')->name('supplier-bills.index');
    Route::post('/purchasing/bills', [SupplierBillController::class, 'store'])->middleware('can:purchasing.create')->name('supplier-bills.store');
    Route::put('/purchasing/bills/{supplierBill}', [SupplierBillController::class, 'update'])->middleware('can:purchasing.edit')->name('supplier-bills.update');
    Route::post('/purchasing/bills/{supplierBill}/submit', [SupplierBillController::class, 'submit'])->middleware('can:purchasing.submit')->name('supplier-bills.submit');
    Route::post('/purchasing/bills/{supplierBill}/approve', [SupplierBillController::class, 'approve'])->middleware('can:purchasing.approve')->name('supplier-bills.approve');
    Route::post('/purchasing/bills/{supplierBill}/post', [SupplierBillController::class, 'post'])->middleware(['permission.all:purchasing.post,view_financials', 'sensitive.confirm'])->name('supplier-bills.post');
    Route::post('/purchasing/bills/{supplierBill}/cancel', [SupplierBillController::class, 'cancel'])->middleware('can:purchasing.cancel')->name('supplier-bills.cancel');

    // Phase 4 Slice 10 Returns & Adjustment Notes Routes
    Route::get('/sales/returns/returnable-lines/{invoiceId}', [SalesReturnController::class, 'returnableInvoiceLines'])->middleware('can:sales.returns')->name('sales-returns.returnable-lines');
    Route::get('/sales/returns', [SalesReturnController::class, 'index'])->middleware('can:sales.returns')->name('sales-returns.index');
    Route::post('/sales/returns', [SalesReturnController::class, 'store'])->middleware('can:sales.returns')->name('sales-returns.store');
    Route::put('/sales/returns/{id}', [SalesReturnController::class, 'update'])->middleware('can:sales.returns')->name('sales-returns.update');
    Route::post('/sales/returns/{id}/submit', [SalesReturnController::class, 'submit'])->middleware('can:sales.returns')->name('sales-returns.submit');
    Route::post('/sales/returns/{id}/approve', [SalesReturnController::class, 'approve'])->middleware('can:sales.returns')->name('sales-returns.approve');
    Route::post('/sales/returns/{id}/post', [SalesReturnController::class, 'post'])->middleware(['permission.all:sales.returns,view_financials', 'sensitive.confirm'])->name('sales-returns.post');
    Route::post('/sales/returns/{id}/cancel', [SalesReturnController::class, 'cancel'])->middleware('can:sales.returns')->name('sales-returns.cancel');

    Route::get('/sales/credit-notes', [CustomerCreditNoteController::class, 'index'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.index');
    Route::post('/sales/credit-notes', [CustomerCreditNoteController::class, 'store'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.store');
    Route::put('/sales/credit-notes/{id}', [CustomerCreditNoteController::class, 'update'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.update');
    Route::post('/sales/credit-notes/{id}/submit', [CustomerCreditNoteController::class, 'submit'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.submit');
    Route::post('/sales/credit-notes/{id}/approve', [CustomerCreditNoteController::class, 'approve'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.approve');
    Route::post('/sales/credit-notes/{id}/post', [CustomerCreditNoteController::class, 'post'])->middleware(['permission.all:sales.credit_notes,view_financials', 'sensitive.confirm'])->name('customer-credit-notes.post');
    Route::post('/sales/credit-notes/{id}/cancel', [CustomerCreditNoteController::class, 'cancel'])->middleware('can:sales.credit_notes')->name('customer-credit-notes.cancel');

    Route::get('/sales/invoice-revisions', [CustomerInvoiceRevisionController::class, 'index'])->middleware('can:sales.invoice_revisions')->name('invoice-revisions.index');
    Route::get('/sales/invoice-revisions/{id}', [CustomerInvoiceRevisionController::class, 'show'])->middleware('can:sales.invoice_revisions')->name('invoice-revisions.show');

    Route::get('/purchasing/returns', [PurchaseReturnController::class, 'index'])->middleware('can:purchasing.returns')->name('purchase-returns.index');
    Route::post('/purchasing/returns', [PurchaseReturnController::class, 'store'])->middleware('can:purchasing.returns')->name('purchase-returns.store');
    Route::put('/purchasing/returns/{id}', [PurchaseReturnController::class, 'update'])->middleware('can:purchasing.returns')->name('purchase-returns.update');
    Route::post('/purchasing/returns/{id}/submit', [PurchaseReturnController::class, 'submit'])->middleware('can:purchasing.returns')->name('purchase-returns.submit');
    Route::post('/purchasing/returns/{id}/approve', [PurchaseReturnController::class, 'approve'])->middleware('can:purchasing.returns')->name('purchase-returns.approve');
    Route::post('/purchasing/returns/{id}/post', [PurchaseReturnController::class, 'post'])->middleware(['permission.all:purchasing.returns,view_financials', 'sensitive.confirm'])->name('purchase-returns.post');
    Route::post('/purchasing/returns/{id}/cancel', [PurchaseReturnController::class, 'cancel'])->middleware('can:purchasing.returns')->name('purchase-returns.cancel');

    Route::get('/purchasing/adjustment-notes', [SupplierAdjustmentNoteController::class, 'index'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.index');
    Route::post('/purchasing/adjustment-notes', [SupplierAdjustmentNoteController::class, 'store'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.store');
    Route::put('/purchasing/adjustment-notes/{id}', [SupplierAdjustmentNoteController::class, 'update'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.update');
    Route::post('/purchasing/adjustment-notes/{id}/submit', [SupplierAdjustmentNoteController::class, 'submit'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.submit');
    Route::post('/purchasing/adjustment-notes/{id}/approve', [SupplierAdjustmentNoteController::class, 'approve'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.approve');
    Route::post('/purchasing/adjustment-notes/{id}/post', [SupplierAdjustmentNoteController::class, 'post'])->middleware(['permission.all:purchasing.adjustment_notes,view_financials', 'sensitive.confirm'])->name('supplier-adjustment-notes.post');
    Route::post('/purchasing/adjustment-notes/{id}/cancel', [SupplierAdjustmentNoteController::class, 'cancel'])->middleware('can:purchasing.adjustment_notes')->name('supplier-adjustment-notes.cancel');

    Route::get('/sales/receivable-settlements', [ReceivableEntrySettlementController::class, 'index'])->middleware('can:sales.credit_notes')->name('receivable-settlements.index');
    Route::post('/sales/receivable-settlements', [ReceivableEntrySettlementController::class, 'store'])->middleware('can:sales.credit_notes')->name('receivable-settlements.store');
    Route::post('/sales/receivable-settlements/{id}/reverse', [ReceivableEntrySettlementController::class, 'reverse'])->middleware(['can:sales.credit_notes', 'sensitive.confirm'])->name('receivable-settlements.reverse');

    Route::get('/purchasing/payable-settlements', [PayableEntrySettlementController::class, 'index'])->middleware('can:purchasing.adjustment_notes')->name('payable-settlements.index');
    Route::post('/purchasing/payable-settlements', [PayableEntrySettlementController::class, 'store'])->middleware('can:purchasing.adjustment_notes')->name('payable-settlements.store');
    Route::post('/purchasing/payable-settlements/{id}/reverse', [PayableEntrySettlementController::class, 'reverse'])->middleware(['can:purchasing.adjustment_notes', 'sensitive.confirm'])->name('payable-settlements.reverse');

    // Phase 6 Slice 2 Fixed Assets Routes
    Route::get('/fixed-asset-categories', [FixedAssetCategoryController::class, 'index'])->middleware('can:fixedAssets.view')->name('fixed-asset-categories.index');
    Route::post('/fixed-asset-categories', [FixedAssetCategoryController::class, 'store'])->middleware('can:fixedAssets.create')->name('fixed-asset-categories.store');
    Route::put('/fixed-asset-categories/{id}', [FixedAssetCategoryController::class, 'update'])->middleware('can:fixedAssets.edit')->name('fixed-asset-categories.update');
    Route::delete('/fixed-asset-categories/{id}', [FixedAssetCategoryController::class, 'destroy'])->middleware('can:fixedAssets.delete')->name('fixed-asset-categories.destroy');

    Route::get('/fixed-asset-locations', [FixedAssetLocationController::class, 'index'])->middleware('can:fixedAssets.view')->name('fixed-asset-locations.index');
    Route::post('/fixed-asset-locations', [FixedAssetLocationController::class, 'store'])->middleware('can:fixedAssets.create')->name('fixed-asset-locations.store');
    Route::put('/fixed-asset-locations/{id}', [FixedAssetLocationController::class, 'update'])->middleware('can:fixedAssets.edit')->name('fixed-asset-locations.update');
    Route::delete('/fixed-asset-locations/{id}', [FixedAssetLocationController::class, 'destroy'])->middleware('can:fixedAssets.delete')->name('fixed-asset-locations.destroy');

    Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->middleware('can:fixedAssets.view')->name('fixed-assets.index');
    Route::get('/fixed-assets/create', [FixedAssetController::class, 'create'])->middleware('can:fixedAssets.create')->name('fixed-assets.create');
    Route::post('/fixed-assets', [FixedAssetController::class, 'store'])->middleware('can:fixedAssets.create')->name('fixed-assets.store');
    Route::get('/fixed-assets/{id}', [FixedAssetController::class, 'show'])->middleware('can:fixedAssets.view')->name('fixed-assets.show');
    Route::get('/fixed-assets/{id}/edit', [FixedAssetController::class, 'edit'])->middleware('can:fixedAssets.edit')->name('fixed-assets.edit');
    Route::put('/fixed-assets/{id}', [FixedAssetController::class, 'update'])->middleware('can:fixedAssets.edit')->name('fixed-assets.update');
    Route::delete('/fixed-assets/{id}', [FixedAssetController::class, 'destroy'])->middleware('can:fixedAssets.delete')->name('fixed-assets.destroy');
    Route::post('/fixed-assets/{id}/capitalize', [FixedAssetCapitalizationController::class, 'store'])->middleware(['permission.all:fixedAssets.post,view_financials', 'sensitive.confirm'])->name('fixed-assets.capitalize');
    Route::post('/fixed-assets/{id}/reverse-capitalization', [FixedAssetCapitalizationController::class, 'reverse'])->middleware(['permission.all:fixedAssets.reverse,view_financials', 'sensitive.confirm'])->name('fixed-assets.reverse_capitalization');
    Route::post('/fixed-assets/{id}/generate-schedule', [FixedAssetDepreciationScheduleController::class, 'store'])->middleware('permission.all:fixedAssets.edit,view_financials')->name('fixed-assets.generate_schedule');
    Route::post('/fixed-assets/{id}/movements', [FixedAssetMovementController::class, 'store'])->middleware('can:fixedAssets.transfer')->name('fixed-assets.movements.store');

    Route::get('/fixed-assets-depreciation-runs', [FixedAssetDepreciationRunController::class, 'index'])->middleware('permission.all:fixedAssets.view,view_financials')->name('fixed-assets.depreciation-runs.index');
    Route::post('/fixed-assets-depreciation-runs', [FixedAssetDepreciationRunController::class, 'store'])->middleware(['permission.all:fixedAssets.post,view_financials', 'sensitive.confirm'])->name('fixed-assets.depreciation-runs.store');
    Route::get('/fixed-assets-depreciation-runs/preview/{financialPeriodId}', [FixedAssetDepreciationRunController::class, 'preview'])->middleware('permission.all:fixedAssets.view,view_financials')->name('fixed-assets.depreciation-runs.preview');
    Route::get('/fixed-assets-depreciation-runs/{id}', [FixedAssetDepreciationRunController::class, 'show'])->middleware('permission.all:fixedAssets.view,view_financials')->name('fixed-assets.depreciation-runs.show');
    Route::post('/fixed-assets-depreciation-runs/{id}/reverse', [FixedAssetDepreciationRunController::class, 'reverse'])->middleware(['permission.all:fixedAssets.reverse,view_financials', 'sensitive.confirm'])->name('fixed-assets.depreciation-runs.reverse');

    // Phase 6 Slice 6 Fixed Asset Disposal Routes
    Route::get('/fixed-assets-disposals', [FixedAssetDisposalController::class, 'index'])->middleware('permission.all:fixedAssets.view,view_financials')->name('fixed-assets-disposals.index');
    Route::get('/fixed-assets-disposals/{id}', [FixedAssetDisposalController::class, 'show'])->middleware('permission.all:fixedAssets.view,view_financials')->name('fixed-assets-disposals.show');
    Route::post('/fixed-assets/{assetId}/disposals/preview', [FixedAssetDisposalController::class, 'preview'])->middleware('permission.all:fixedAssets.view,view_financials')->name('fixed-assets.disposals.preview');
    Route::post('/fixed-assets/{assetId}/disposals', [FixedAssetDisposalController::class, 'store'])->middleware(['permission.all:fixedAssets.post,view_financials', 'sensitive.confirm'])->name('fixed-assets.disposals.store');
    Route::post('/fixed-assets-disposals/{id}/reverse', [FixedAssetDisposalController::class, 'reverse'])->middleware(['permission.all:fixedAssets.reverse,view_financials', 'sensitive.confirm'])->name('fixed-assets-disposals.reverse');

    // Phase 7 Slice 2 Tax Code and Tax Rate Routes
    Route::get('/taxes/codes', [TaxCodeController::class, 'index'])->middleware('can:taxes.view')->name('taxes.codes.index');
    Route::get('/taxes/codes/create', [TaxCodeController::class, 'create'])->middleware('can:taxes.edit')->name('taxes.codes.create');
    Route::post('/taxes/codes', [TaxCodeController::class, 'store'])->middleware('can:taxes.edit')->name('taxes.codes.store');
    Route::get('/taxes/codes/{id}/edit', [TaxCodeController::class, 'edit'])->middleware('can:taxes.edit')->name('taxes.codes.edit');
    Route::put('/taxes/codes/{id}', [TaxCodeController::class, 'update'])->middleware('can:taxes.edit')->name('taxes.codes.update');
    Route::delete('/taxes/codes/{id}', [TaxCodeController::class, 'destroy'])->middleware('can:taxes.edit')->name('taxes.codes.destroy');

    Route::get('/taxes/rates', [TaxRateController::class, 'index'])->middleware('can:taxes.view')->name('taxes.rates.index');
    Route::post('/taxes/rates', [TaxRateController::class, 'store'])->middleware('can:taxes.edit')->name('taxes.rates.store');
    Route::put('/taxes/rates/{id}', [TaxRateController::class, 'update'])->middleware('can:taxes.edit')->name('taxes.rates.update');
    Route::delete('/taxes/rates/{id}', [TaxRateController::class, 'destroy'])->middleware('can:taxes.edit')->name('taxes.rates.destroy');

    // Phase 7 Slice 6 Tax Period & Filing Routes
    Route::get('/taxes/periods', [TaxPeriodController::class, 'index'])->middleware('can:taxes.view')->name('taxes.periods.index');
    Route::post('/taxes/periods', [TaxPeriodController::class, 'store'])->middleware('can:taxes.edit')->name('taxes.periods.store');
    Route::get('/taxes/periods/{id}', [TaxPeriodController::class, 'show'])->middleware('can:taxes.view')->name('taxes.periods.show');
    Route::post('/taxes/periods/{id}/draft', [TaxPeriodController::class, 'generateDraft'])->middleware('can:taxes.edit')->name('taxes.periods.draft');
    Route::post('/taxes/returns/{id}/file', [TaxPeriodController::class, 'fileReturn'])->middleware(['can:taxes.file', 'sensitive.confirm'])->name('taxes.returns.file');

    // Phase 16 Slice 1 Project & Cost Center Master Data Routes
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('can:projects.view')->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('can:projects.create')->name('projects.store');
    Route::patch('/projects/{id}', [ProjectController::class, 'update'])->middleware('can:projects.edit')->name('projects.update');
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->middleware('can:projects.delete')->name('projects.destroy');

    Route::get('/cost-centers', [CostCenterController::class, 'index'])->middleware('can:costCenters.view')->name('cost-centers.index');
    Route::post('/cost-centers', [CostCenterController::class, 'store'])->middleware('can:costCenters.create')->name('cost-centers.store');
    Route::patch('/cost-centers/{id}', [CostCenterController::class, 'update'])->middleware('can:costCenters.edit')->name('cost-centers.update');
    Route::delete('/cost-centers/{id}', [CostCenterController::class, 'destroy'])->middleware('can:costCenters.delete')->name('cost-centers.destroy');

    // Phase 16 Slice 5 Budgeting Routes
    Route::prefix('budgeting')->group(function (): void {
        Route::get('/budgets', [BudgetController::class, 'index'])->middleware('permission.all:budgeting.view,view_financials')->name('budgeting.budgets.index');
        Route::post('/budgets', [BudgetController::class, 'store'])->middleware('permission.all:budgeting.create,view_financials')->name('budgeting.budgets.store');
        Route::patch('/budgets/{budget}', [BudgetController::class, 'update'])->middleware('permission.all:budgeting.edit,view_financials')->name('budgeting.budgets.update');
        Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy'])->middleware('permission.all:budgeting.delete,view_financials')->name('budgeting.budgets.destroy');
        Route::post('/budgets/{budget}/submit', [BudgetController::class, 'submit'])->middleware('permission.all:budgeting.edit,view_financials')->name('budgeting.budgets.submit');
        Route::post('/budgets/{budget}/approve', [BudgetController::class, 'approve'])->middleware('permission.all:budgeting.approve,view_financials')->name('budgeting.budgets.approve');
        Route::post('/budgets/{budget}/activate', [BudgetController::class, 'activate'])->middleware(['permission.all:budgeting.approve,view_financials', 'sensitive.confirm'])->name('budgeting.budgets.activate');
        Route::post('/budgets/{budget}/archive', [BudgetController::class, 'archive'])->middleware(['permission.all:budgeting.approve,view_financials', 'sensitive.confirm'])->name('budgeting.budgets.archive');
        Route::post('/budgets/{budget}/cancel', [BudgetController::class, 'cancel'])->middleware(['permission.all:budgeting.edit,view_financials', 'sensitive.confirm'])->name('budgeting.budgets.cancel');

        // Phase 16 Slice 6 Budget vs Actual Variance Routes
        Route::get('/variance', [BudgetVarianceController::class, 'index'])->middleware('permission.all:budgeting.view,reports.view,view_financials')->name('budgeting.variance.index');
        Route::get('/variance/export', [BudgetVarianceController::class, 'exportCsv'])->middleware('permission.all:budgeting.export,reports.export,view_financials')->name('budgeting.variance.export');
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
