<?php

namespace Tests\Feature;

use App\Application\Reports\CsvReportResponse;
use App\Application\Reports\PartnerStatementCsvExporter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportInputHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_query_filters_fail_validation_before_reaching_database_queries(): void
    {
        $user = $this->reportUser();

        $cases = [
            ['/reports/ap-aging', 'supplier_id', 'not-a-uuid'],
            ['/reports/ap-gl-reconciliation', 'as_of_date', 'not-a-date'],
            ['/reports/ar-aging', 'customer_id', 'not-a-uuid'],
            ['/reports/ar-gl-reconciliation', 'as_of_date', '2026-02-31'],
            ['/reports/balance-sheet', 'as_of_date', 'not-a-date'],
            ['/reports/bank-book', 'bank_account_id', 'not-a-uuid'],
            ['/reports/bank-reconciliations', 'status', 'not-a-status'],
            ['/reports/cash-book', 'cash_account_id', 'not-a-uuid'],
            ['/reports/cash-flow', 'period_id', 'not-a-uuid'],
            ['/reports/cheque-register', 'direction', 'sideways'],
            ['/reports/customer-invoices', 'product_id', 'not-a-uuid'],
            ['/reports/customer-statement', 'date_to', 'not-a-date'],
            ['/reports/delivery-notes', 'warehouse_id', 'not-a-uuid'],
            ['/reports/goods-receipts', 'supplier_id', 'not-a-uuid'],
            ['/reports/income-statement', 'from_date', 'not-a-date'],
            ['/reports/purchase-orders', 'status', 'not-a-status'],
            ['/reports/sales-orders', 'currency', 'TOO-LONG'],
            ['/reports/stock-movements', 'movement_type', 'not-a-movement'],
            ['/reports/supplier-bills', 'date_from', 'not-a-date'],
            ['/reports/supplier-statement', 'supplier_id', 'not-a-uuid'],
            ['/reports/fixed-asset-register', 'category_id', 'not-a-uuid'],
            ['/reports/vat-register', 'type', 'not-a-vat-type'],
        ];

        foreach ($cases as [$path, $field, $value]) {
            $response = $this->actingAs($user)->getJson($path.'?'.http_build_query([$field => $value]));

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_report_export_filters_use_the_same_validation_boundary(): void
    {
        $user = $this->reportUser();

        $cases = [
            ['/reports/ar-aging/export', 'customer_id', 'not-a-uuid'],
            ['/reports/balance-sheet/export', 'as_of_date', 'not-a-date'],
            ['/reports/cheque-register/export', 'direction', 'sideways'],
            ['/reports/customer-statement/export', 'customer_id', 'not-a-uuid'],
            ['/reports/supplier-statement/export', 'supplier_id', 'not-a-uuid'],
            ['/reports/fixed-asset-register/export', 'status', 'not-a-status'],
            ['/reports/vat-register/export', 'tax_code_id', 'not-a-uuid'],
        ];

        foreach ($cases as [$path, $field, $value]) {
            $response = $this->actingAs($user)->getJson($path.'?'.http_build_query([$field => $value]));

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_csv_response_neutralizes_formula_cells_and_sanitizes_filename(): void
    {
        $response = app(CsvReportResponse::class)->fromRows(
            "../unsafe\"\r\nX-Injected: yes",
            ['Value'],
            [[
                '=1+1',
                '+cmd',
                '-cmd',
                '@SUM(A1:A2)',
                '  =HIDDEN()',
                '-123.45',
            ]],
            fn (array $row): array => $row,
        );

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertMatchesRegularExpression('/^attachment; filename="[A-Za-z0-9._-]+\.csv"$/', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringContainsString("'=1+1", $content);
        $this->assertStringContainsString("'+cmd", $content);
        $this->assertStringContainsString("'-cmd", $content);
        $this->assertStringContainsString("'@SUM(A1:A2)", $content);
        $this->assertStringContainsString("'  =HIDDEN()", $content);
        $this->assertStringContainsString('-123.45', $content);
        $this->assertStringNotContainsString("'-123.45", $content);
    }

    public function test_partner_statement_export_uses_safe_filename_and_neutralized_user_content(): void
    {
        $report = [
            'customer' => [
                'code' => "=BAD\r\nX-Header: injected",
                'name' => '@Customer',
            ],
            'filters' => [
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'currency' => 'EGP',
            ],
            'opening_balance_minor' => 0,
            'total_debit_minor' => 100,
            'total_credit_minor' => 0,
            'closing_balance_minor' => 100,
            'lines' => [[
                'date' => '2026-01-01',
                'type' => 'invoice',
                'reference' => '+DANGEROUS()',
                'description' => '-COMMAND()',
                'debit_minor' => 100,
                'credit_minor' => 0,
                'running_balance_minor' => 100,
            ]],
        ];

        $response = app(PartnerStatementCsvExporter::class)->customer($report);

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertMatchesRegularExpression('/^attachment; filename="customer_statement_[A-Za-z0-9._-]+\.csv"$/', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringContainsString("'=BAD", $content);
        $this->assertStringContainsString("'+DANGEROUS()", $content);
        $this->assertStringContainsString("'-COMMAND()", $content);
    }

    public function test_datatable_endpoints_require_financial_report_permissions(): void
    {
        $outsider = User::factory()->create();

        foreach ($this->dataTableEndpoints() as [$path, $query]) {
            $this->actingAs($outsider)
                ->getJson($path.'?'.http_build_query($query))
                ->assertForbidden();
        }
    }

    public function test_datatable_endpoints_reject_unsupported_page_lengths(): void
    {
        $user = $this->reportUser();

        foreach ($this->dataTableEndpoints() as [$path, $query]) {
            $this->actingAs($user)
                ->getJson($path.'?'.http_build_query([...$query, 'length' => 999]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('length');
        }
    }

    /** @return list<array{0: string, 1: array<string, string>}> */
    private function dataTableEndpoints(): array
    {
        return [
            ['/reports/ar-aging/data', ['as_of_date' => '2026-08-31']],
            ['/reports/ap-aging/data', ['as_of_date' => '2026-08-31']],
            ['/reports/cheque-register/data', ['direction' => 'all']],
            ['/reports/vat-register/data', ['from_date' => '2026-08-01', 'to_date' => '2026-08-31']],
            ['/reports/rentals/data', ['as_of_date' => '2026-08-31']],
        ];
    }

    private function reportUser(): User
    {
        $user = User::factory()->create();

        foreach (['reports.view', 'view_financials', 'reports.export'] as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'module' => Str::before($name, '.'),
                    'action' => Str::after($name, '.'),
                ],
            );
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
