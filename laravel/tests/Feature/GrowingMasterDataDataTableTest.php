<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\FixedAssetLocation;
use App\Models\PayrollComponent;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowingMasterDataDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Account $assetAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'banks.view',
            'cash.view',
            'expenses.view',
            'fixedAssets.view',
            'payroll.view',
            'view_payroll',
        ]);

        $this->branch = Branch::query()->create([
            'code' => 'GROW-BR',
            'name' => ['en' => 'Growing Registers Branch', 'ar' => 'فرع السجلات المتنامية'],
            'is_active' => true,
        ]);

        $this->assetAccount = $this->createAccount('GROW-1100', 'Growing Asset', 'asset', 'debit');
        $this->expenseAccount = $this->createAccount('GROW-5100', 'Growing Expense', 'expense', 'debit');

        BankAccount::query()->create([
            'code' => 'GROW-BANK',
            'name' => ['en' => 'Operating Account', 'ar' => 'حساب تشغيلي'],
            'bank_name' => ['en' => 'Growing Bank', 'ar' => 'بنك متنامي'],
            'branch_id' => $this->branch->id,
            'account_number' => 'GROW-123456',
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        CashAccount::query()->create([
            'code' => 'GROW-CASH',
            'name' => ['en' => 'Growing Safe', 'ar' => 'خزينة متنامية'],
            'branch_id' => $this->branch->id,
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        FixedAssetLocation::query()->create([
            'code' => 'GROW-LOC',
            'name' => ['en' => 'Growing Warehouse', 'ar' => 'مخزن متنامي'],
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        ExpenseCategory::query()->create([
            'code' => 'GROW-EXP',
            'name' => ['en' => 'Growing Operations', 'ar' => 'عمليات متنامية'],
            'default_expense_account_id' => $this->expenseAccount->id,
            'requires_attachment' => true,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        PayrollComponent::query()->create([
            'code' => 'GROW-PAY',
            'name' => ['en' => 'Growing Allowance', 'ar' => 'بدل متنامي'],
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_amount_minor' => 5000,
            'expense_account_id' => $this->expenseAccount->id,
            'sort_order' => 10,
            'is_system' => false,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_index_payloads_are_slim_while_form_lookups_are_retained(): void
    {
        $this->actingAs($this->user)
            ->get('/bank-accounts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BankAccounts/Index')
                ->where('bankAccounts', [])
                ->has('glAccounts', 1)
                ->has('currencies')
                ->has('branches', 1));

        $this->actingAs($this->user)
            ->get('/cash-accounts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CashAccounts/Index')
                ->where('cashAccounts', [])
                ->has('glAccounts', 1)
                ->has('currencies')
                ->has('branches', 1));

        $this->actingAs($this->user)
            ->get('/fixed-asset-locations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FixedAssets/Locations')
                ->where('locations', [])
                ->has('branches', 1)
                ->has('can'));

        $this->actingAs($this->user)
            ->get('/expenses/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Categories')
                ->where('categories', [])
                ->has('expenseAccounts', 1)
                ->has('taxCodes'));

        $this->actingAs($this->user)
            ->get('/payroll/components')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Components')
                ->where('components', [])
                ->has('expenseAccounts', 1)
                ->has('types')
                ->has('calculationTypes'));
    }

    public function test_feeds_search_filter_and_return_relations_and_counts(): void
    {
        $cases = [
            [
                '/bank-accounts/data',
                $this->bankColumns(),
                'Growing Bank',
                ['status' => 'active', 'branch_id' => $this->branch->id],
                'data.0.code',
                'GROW-BANK',
                'data.0.gl_account.code',
                'GROW-1100',
            ],
            [
                '/cash-accounts/data',
                $this->cashColumns(),
                'Growing Safe',
                ['status' => 'active', 'branch_id' => $this->branch->id],
                'data.0.code',
                'GROW-CASH',
                'data.0.branch.code',
                'GROW-BR',
            ],
            [
                '/fixed-asset-locations/data',
                $this->locationColumns(),
                'Growing Warehouse',
                ['status' => 'active', 'branch_id' => $this->branch->id],
                'data.0.code',
                'GROW-LOC',
                'data.0.assets_count',
                0,
            ],
            [
                '/expenses/categories/data',
                $this->expenseCategoryColumns(),
                'Growing Operations',
                [],
                'data.0.code',
                'GROW-EXP',
                'data.0.default_expense_account.code',
                'GROW-5100',
            ],
            [
                '/payroll/components/data',
                $this->payrollComponentColumns(),
                'Growing Allowance',
                ['type' => 'earning'],
                'data.0.code',
                'GROW-PAY',
                'data.0.employee_assignments_count',
                0,
            ],
        ];

        foreach ($cases as [$uri, $columns, $search, $filters, $rowPath, $rowValue, $detailPath, $detailValue]) {
            $query = array_merge($this->gridQuery($columns, $search), $filters);
            $response = $this->actingAs($this->user)->getJson($uri.'?'.http_build_query($query));

            $response->assertOk();
            $this->assertSame(1, $response->json('recordsFiltered'), "{$uri} did not return its matching row: ".$response->getContent());
            $response->assertJsonPath($rowPath, $rowValue);
            $response->assertJsonPath($detailPath, $detailValue);
        }

        foreach ([
            ['/bank-accounts/data', $this->bankColumns(), ['status' => 'inactive', 'branch_id' => $this->branch->id]],
            ['/cash-accounts/data', $this->cashColumns(), ['status' => 'inactive', 'branch_id' => $this->branch->id]],
            ['/fixed-asset-locations/data', $this->locationColumns(), ['status' => 'inactive', 'branch_id' => $this->branch->id]],
            ['/payroll/components/data', $this->payrollComponentColumns(), ['type' => 'deduction']],
        ] as [$uri, $columns, $filters]) {
            $query = array_merge($this->gridQuery($columns, ''), $filters);
            $response = $this->actingAs($this->user)->getJson($uri.'?'.http_build_query($query));

            $response->assertOk();
            $this->assertSame(0, $response->json('recordsFiltered'), "{$uri} ignored its register filter: ".$response->getContent());
        }
    }

    public function test_feed_ordering_is_database_backed(): void
    {
        CashAccount::query()->create([
            'code' => 'ZZ-GROW-CASH',
            'name' => ['en' => 'Last Safe', 'ar' => 'الخزينة الأخيرة'],
            'branch_id' => $this->branch->id,
            'currency' => 'EGP',
            'gl_account_id' => $this->assetAccount->id,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $query = array_merge($this->gridQuery($this->cashColumns(), '', 0, 'desc'), [
            'status' => 'active',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/cash-accounts/data?'.http_build_query($query));

        $response->assertOk()->assertJsonPath('data.0.code', 'ZZ-GROW-CASH');
        $this->assertSame(2, $response->json('recordsFiltered'));
    }

    public function test_feed_routes_preserve_view_permissions(): void
    {
        $stranger = User::factory()->create();

        foreach ([
            ['/bank-accounts/data', $this->bankColumns()],
            ['/cash-accounts/data', $this->cashColumns()],
            ['/fixed-asset-locations/data', $this->locationColumns()],
            ['/expenses/categories/data', $this->expenseCategoryColumns()],
            ['/payroll/components/data', $this->payrollComponentColumns()],
        ] as [$uri, $columns]) {
            $this->actingAs($stranger)
                ->getJson($uri.'?'.http_build_query($this->gridQuery($columns, '')))
                ->assertForbidden();
        }
    }

    public function test_register_pages_use_server_tables_and_reload_after_mutations(): void
    {
        foreach ([
            'BankAccounts/Index.tsx' => '/bank-accounts/data',
            'CashAccounts/Index.tsx' => '/cash-accounts/data',
            'FixedAssets/Locations.tsx' => '/fixed-asset-locations/data',
            'Expenses/Categories.tsx' => '/expenses/categories/data',
            'Payroll/Components.tsx' => '/payroll/components/data',
        ] as $file => $feed) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$file}"));

            $this->assertStringContainsString('ServerDataTable', $source);
            $this->assertStringContainsString("ajaxUrl=\"{$feed}\"", $source);
            $this->assertStringContainsString('reloadToken={reloadToken}', $source);
            $this->assertStringNotContainsString('<table', $source);
        }
    }

    private function createAccount(string $code, string $name, string $type, string $nature): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => $type,
            'nature' => $nature,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);
    }

    /** @return array<int, array{string, bool, bool}> */
    private function bankColumns(): array
    {
        return [
            ['code', true, true],
            ['bank_label', true, false],
            ['account_number', true, true],
            ['branch_label', false, false],
            ['currency', true, true],
            ['gl_account_label', false, false],
            ['is_active', false, true],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function cashColumns(): array
    {
        return [
            ['code', true, true],
            ['name_text', true, false],
            ['branch_label', false, false],
            ['currency', true, true],
            ['gl_account_label', false, false],
            ['is_active', false, true],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function locationColumns(): array
    {
        return [
            ['code', true, true],
            ['name_text', true, false],
            ['branch_label', false, false],
            ['assets_count', false, true],
            ['is_active', false, true],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function expenseCategoryColumns(): array
    {
        return [
            ['code', true, true],
            ['name_text', true, false],
            ['default_expense_account_text', false, false],
            ['default_tax_code_text', false, false],
            ['requires_attachment', false, true],
            ['expense_lines_count', false, true],
            ['is_active', false, true],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function payrollComponentColumns(): array
    {
        return [
            ['sort_order', false, true],
            ['code', true, true],
            ['name_text', true, false],
            ['type', true, true],
            ['calculation_type', true, true],
            ['default_amount_minor', false, true],
            ['employee_assignments_count', false, true],
            ['is_active', false, true],
            ['actions', false, false],
        ];
    }

    /**
     * @param  array<int, array{string, bool, bool}>  $columns
     * @return array<string, mixed>
     */
    private function gridQuery(array $columns, string $search, int $orderColumn = 0, string $direction = 'asc'): array
    {
        return [
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => array_map(fn (array $column): array => [
                'data' => $column[0],
                'name' => $column[0],
                'searchable' => $column[1] ? 'true' : 'false',
                'orderable' => $column[2] ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => (string) $orderColumn, 'dir' => $direction]],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
