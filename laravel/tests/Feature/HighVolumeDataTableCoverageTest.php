<?php

namespace Tests\Feature;

use Tests\TestCase;

class HighVolumeDataTableCoverageTest extends TestCase
{
    public function test_high_volume_pages_keep_a_server_backed_data_table(): void
    {
        $pages = [
            'Accounting/ExchangeRates.tsx',
            'Accounting/TrialBalance.tsx',
            'AuditLog/Index.tsx',
            'BankAccounts/Index.tsx',
            'BankReconciliations/Index.tsx',
            'BankReconciliations/Show.tsx',
            'Budgeting/Budgets.tsx',
            'Budgeting/Variance.tsx',
            'CashAccounts/Index.tsx',
            'CostCenters/Index.tsx',
            'Expenses/Accruals.tsx',
            'Expenses/Categories.tsx',
            'Expenses/Index.tsx',
            'Expenses/Prepaids.tsx',
            'FixedAssets/DepreciationRuns/Index.tsx',
            'FixedAssets/DepreciationRuns/Preview.tsx',
            'FixedAssets/DepreciationRuns/Show.tsx',
            'FixedAssets/Locations.tsx',
            'IncomingCheques/Index.tsx',
            'Inventory/StockAdjustments.tsx',
            'Inventory/StockCounts.tsx',
            'Notifications.tsx',
            'OutgoingCheques/Index.tsx',
            'Payroll/Components.tsx',
            'Payroll/Employees.tsx',
            'Payroll/Runs.tsx',
            'Projects/Index.tsx',
            'Purchasing/GoodsReceipts.tsx',
            'Purchasing/LandedCosts.tsx',
            'Purchasing/PayableSettlements.tsx',
            'Purchasing/PurchaseOrders.tsx',
            'Purchasing/PurchaseReturns.tsx',
            'Purchasing/SupplierAdjustmentNotes.tsx',
            'Purchasing/SupplierBills.tsx',
            'Rentals/Contracts.tsx',
            'Rentals/Handovers.tsx',
            'Rentals/Invoices.tsx',
            'Rentals/RentableItems.tsx',
            'Rentals/Returns.tsx',
            'Reports/BankReconciliation.tsx',
            'Reports/BankReconciliationDetail.tsx',
            'Reports/CostCenterActuals.tsx',
            'Reports/FixedAssetDepreciationReport.tsx',
            'Reports/FixedAssetDepreciationRunReport.tsx',
            'Reports/FixedAssetDisposalReport.tsx',
            'Reports/FixedAssetNetBookValueReport.tsx',
            'Reports/FixedAssetRegisterReport.tsx',
            'Reports/ProjectProfitability.tsx',
            'Sales/CustomerCreditNotes.tsx',
            'Sales/CustomerInvoices.tsx',
            'Sales/DeliveryNotes.tsx',
            'Sales/InvoiceRevisions.tsx',
            'Sales/ReceivableSettlements.tsx',
            'Sales/SalesOrders.tsx',
            'Sales/SalesReturns.tsx',
            'Settings/Users.tsx',
            'TreasuryTransfers/Index.tsx',
        ];

        foreach ($pages as $page) {
            $source = (string) file_get_contents(resource_path('js/Pages/'.$page));

            $this->assertTrue(
                str_contains($source, 'ServerDataTable') || str_contains($source, 'ScheduleDataTable'),
                "{$page} must keep its high-volume grid server-backed.",
            );
        }
    }

    public function test_shared_data_table_cannot_request_an_unbounded_page(): void
    {
        $component = (string) file_get_contents(resource_path('js/Components/ServerDataTable.tsx'));
        $middleware = (string) file_get_contents(app_path('Http/Middleware/BoundDataTableRequests.php'));

        $this->assertStringContainsString('serverSide: true', $component);
        $this->assertStringContainsString('const defaults = [10, 25, 50, 100]', $component);
        $this->assertStringContainsString('isCreatable={false}', $component);
        $this->assertStringContainsString("'length' => ['required', 'integer', 'min:1', 'max:100']", $middleware);
    }
}
