<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Application\CostCenters\CostCenterService;
use App\Application\Projects\ProjectService;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class Phase16Slice2GlDimensionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private FinancialPeriod $openPeriod;

    private Account $cashAccount;

    private Account $revenueAccount;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([
            'accounting.view',
            'accounting.create',
            'accounting.submit',
            'accounting.approve',
            'accounting.post',
            'accounting.reverse',
            'accounting.override_control',
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'costCenters.view',
            'costCenters.create',
            'costCenters.edit',
            'costCenters.delete',
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->openPeriod = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_number' => 8,
            'month' => 8,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);

        $this->currency = Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            ['name' => ['en' => 'Egyptian Pound', 'ar' => 'جنيه مصري'], 'symbol' => 'EGP', 'is_base' => true, 'is_active' => true]
        );

        $group = AccountGroup::query()->create([
            'code' => 'GRP-100',
            'name' => ['en' => 'Assets Group', 'ar' => 'مجموعة الأصول'],
            'type' => 'asset',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->cashAccount = Account::query()->create([
            'code' => '1010',
            'name' => ['en' => 'Cash Account', 'ar' => 'حساب الصندوق'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::query()->create([
            'code' => '4010',
            'name' => ['en' => 'Sales Revenue', 'ar' => 'إيرادات المبيعات'],
            'type' => 'revenue',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);
    }

    public function test_schema_has_gl_dimension_columns_and_no_tenant_assumptions(): void
    {
        $this->assertTrue(Schema::hasColumn('journal_line', 'project_id'), 'journal_line must have project_id column');
        $this->assertTrue(Schema::hasColumn('journal_line', 'cost_center_id'), 'journal_line must have cost_center_id column');
        $this->assertTrue(Schema::hasColumn('ledger_entry', 'project_id'), 'ledger_entry must have project_id column');
        $this->assertTrue(Schema::hasColumn('ledger_entry', 'cost_center_id'), 'ledger_entry must have cost_center_id column');

        $journalLineColumns = Schema::getColumnListing('journal_line');
        $ledgerEntryColumns = Schema::getColumnListing('ledger_entry');

        $bannedTenantColumns = ['tenant_id', 'company_id', 'department_id'];
        foreach ($bannedTenantColumns as $col) {
            $this->assertNotContains($col, $journalLineColumns, "journal_line must not contain [{$col}]");
            $this->assertNotContains($col, $ledgerEntryColumns, "ledger_entry must not contain [{$col}]");
        }
    }

    public function test_manual_journal_creation_stores_project_and_cost_center_on_lines(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-ALPHA',
            'name' => ['en' => 'Project Alpha', 'ar' => 'مشروع ألفا'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-OPS',
            'name' => ['en' => 'Operations Cost Center', 'ar' => 'مركز عمليات'],
            'category' => 'operations',
            'is_active' => true,
        ]);

        $payload = [
            'entry_date' => '2026-08-20',
            'financial_period_id' => $this->openPeriod->id,
            'description' => 'Test project and cost center journal draft',
            'reference' => 'REF-P16S2',
            'currency' => 'EGP',
            'lines' => [
                [
                    'account_id' => $this->cashAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'debit_minor' => 150000,
                    'credit_minor' => 0,
                    'memo' => 'Debit with project and cost center',
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'project_id' => null,
                    'cost_center_id' => null,
                    'debit_minor' => 0,
                    'credit_minor' => 150000,
                    'memo' => 'Credit without dimensions',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->post('/accounting/journal', $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $journal = JournalEntry::query()->where('reference', 'REF-P16S2')->firstOrFail();
        $lines = $journal->lines()->orderBy('line_no')->get();

        $this->assertCount(2, $lines);
        $this->assertSame($project->id, $lines[0]->project_id);
        $this->assertSame($costCenter->id, $lines[0]->cost_center_id);
        $this->assertNull($lines[1]->project_id);
        $this->assertNull($lines[1]->cost_center_id);

        // Test model relationships
        $this->assertNotNull($lines[0]->project);
        $this->assertSame('PRJ-ALPHA', $lines[0]->project->code);
        $this->assertNotNull($lines[0]->costCenter);
        $this->assertSame('CC-OPS', $lines[0]->costCenter->code);
    }

    public function test_manual_journal_posting_copies_dimensions_to_immutable_ledger_entries(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-BETA',
            'name' => ['en' => 'Project Beta', 'ar' => 'مشروع بيتا'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-FIN',
            'name' => ['en' => 'Finance Cost Center', 'ar' => 'مركز المالية'],
            'category' => 'finance',
            'is_active' => true,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-08-22',
                'financial_period_id' => $this->openPeriod->id,
                'description' => 'Posting dimensions test',
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'debit_minor' => 200000,
                    'credit_minor' => 0,
                    'memo' => 'Line 1 tagged',
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => null,
                    'debit_minor' => 0,
                    'credit_minor' => 200000,
                    'memo' => 'Line 2 project only',
                ],
            ],
            $this->adminUser->id
        );

        $postingEngine = app(PostingEngine::class);
        $posted = $postingEngine->post($entry, $this->adminUser->id);

        $this->assertSame('posted', $posted->status);

        $ledgerEntries = LedgerEntry::query()
            ->where('journal_entry_id', $posted->id)
            ->orderBy('debit_minor', 'desc')
            ->get();

        $this->assertCount(2, $ledgerEntries);

        // First ledger entry
        $this->assertSame(200000, (int) $ledgerEntries[0]->debit_minor);
        $this->assertSame($project->id, $ledgerEntries[0]->project_id);
        $this->assertSame($costCenter->id, $ledgerEntries[0]->cost_center_id);
        $this->assertNotNull($ledgerEntries[0]->project);
        $this->assertSame('PRJ-BETA', $ledgerEntries[0]->project->code);
        $this->assertNotNull($ledgerEntries[0]->costCenter);
        $this->assertSame('CC-FIN', $ledgerEntries[0]->costCenter->code);

        // Second ledger entry
        $this->assertSame(200000, (int) $ledgerEntries[1]->credit_minor);
        $this->assertSame($project->id, $ledgerEntries[1]->project_id);
        $this->assertNull($ledgerEntries[1]->cost_center_id);

        // Reverse check through Project / CostCenter model relationships
        $this->assertTrue($project->ledgerEntries()->where('id', $ledgerEntries[0]->id)->exists());
        $this->assertTrue($project->ledgerEntries()->where('id', $ledgerEntries[1]->id)->exists());
        $this->assertTrue($costCenter->ledgerEntries()->where('id', $ledgerEntries[0]->id)->exists());
        $this->assertFalse($costCenter->ledgerEntries()->where('id', $ledgerEntries[1]->id)->exists());
    }

    public function test_journal_reversal_copies_dimensions_to_reversal_lines_and_ledger_entries(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-REV',
            'name' => ['en' => 'Project Reversal', 'ar' => 'مشروع العكس'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-REV',
            'name' => ['en' => 'Cost Center Reversal', 'ar' => 'مركز العكس'],
            'category' => 'sales',
            'is_active' => true,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-08-25',
                'financial_period_id' => $this->openPeriod->id,
                'description' => 'Original entry to be reversed',
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'debit_minor' => 88000,
                    'credit_minor' => 0,
                    'memo' => 'Original cash debit',
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => null,
                    'debit_minor' => 0,
                    'credit_minor' => 88000,
                    'memo' => 'Original revenue credit',
                ],
            ],
            $this->adminUser->id
        );

        $postingEngine = app(PostingEngine::class);
        $postedOriginal = $postingEngine->post($entry, $this->adminUser->id);

        $reversalService = app(ReversalService::class);
        $postedReversal = $reversalService->reverse($postedOriginal, (string) $this->openPeriod->id, $this->adminUser->id);

        $this->assertSame('reversed', $postedOriginal->fresh()->status);
        $this->assertSame('posted', $postedReversal->status);

        // Verify reversal lines preserve dimensions
        $reversalLines = $postedReversal->lines()->orderBy('line_no')->get();
        $this->assertCount(2, $reversalLines);

        $this->assertSame($project->id, $reversalLines[0]->project_id);
        $this->assertSame($costCenter->id, $reversalLines[0]->cost_center_id);
        $this->assertSame(88000, (int) $reversalLines[0]->credit_minor); // Swapped!

        $this->assertSame($project->id, $reversalLines[1]->project_id);
        $this->assertNull($reversalLines[1]->cost_center_id);
        $this->assertSame(88000, (int) $reversalLines[1]->debit_minor); // Swapped!

        // Verify reversal ledger entries preserve dimensions
        $reversalLedgers = LedgerEntry::query()
            ->where('journal_entry_id', $postedReversal->id)
            ->orderBy('credit_minor', 'desc')
            ->get();

        $this->assertCount(2, $reversalLedgers);
        $this->assertSame($project->id, $reversalLedgers[0]->project_id);
        $this->assertSame($costCenter->id, $reversalLedgers[0]->cost_center_id);
        $this->assertSame(88000, (int) $reversalLedgers[0]->credit_minor);

        $this->assertSame($project->id, $reversalLedgers[1]->project_id);
        $this->assertNull($reversalLedgers[1]->cost_center_id);
        $this->assertSame(88000, (int) $reversalLedgers[1]->debit_minor);
    }

    public function test_inactive_project_cannot_be_used_on_draft_creation_update_or_posting(): void
    {
        $inactiveProject = Project::query()->create([
            'code' => 'PRJ-INACTIVE',
            'name' => ['en' => 'Inactive Project'],
            'status' => 'cancelled',
            'is_active' => false,
        ]);

        $draftService = app(JournalDraftService::class);

        // 1. Creation blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selected project is inactive or missing.');

        $draftService->createDraft(
            [
                'entry_date' => '2026-08-15',
                'financial_period_id' => $this->openPeriod->id,
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'project_id' => $inactiveProject->id,
                    'debit_minor' => 50000,
                    'credit_minor' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 50000,
                ],
            ],
            $this->adminUser->id
        );
    }

    public function test_inactive_project_blocks_posting_if_deactivated_after_draft(): void
    {
        $activeProject = Project::query()->create([
            'code' => 'PRJ-TEMP',
            'name' => ['en' => 'Temp Project'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-08-15',
                'financial_period_id' => $this->openPeriod->id,
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'project_id' => $activeProject->id,
                    'debit_minor' => 50000,
                    'credit_minor' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 50000,
                ],
            ],
            $this->adminUser->id
        );

        // Deactivate project before posting
        $activeProject->update(['is_active' => false]);

        $postingEngine = app(PostingEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot post journal line with inactive project [PRJ-TEMP].');

        $postingEngine->post($entry, $this->adminUser->id);
    }

    public function test_inactive_cost_center_cannot_be_used_on_draft_creation_or_posting(): void
    {
        $inactiveCC = CostCenter::query()->create([
            'code' => 'CC-INACTIVE',
            'name' => ['en' => 'Inactive CC'],
            'is_active' => false,
        ]);

        $draftService = app(JournalDraftService::class);

        // 1. Creation blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selected cost center is inactive or missing.');

        $draftService->createDraft(
            [
                'entry_date' => '2026-08-15',
                'financial_period_id' => $this->openPeriod->id,
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'cost_center_id' => $inactiveCC->id,
                    'debit_minor' => 60000,
                    'credit_minor' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 60000,
                ],
            ],
            $this->adminUser->id
        );
    }

    public function test_inactive_project_or_cost_center_cannot_be_used_on_draft_update(): void
    {
        $draftService = app(JournalDraftService::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-08-16',
                'financial_period_id' => $this->openPeriod->id,
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'debit_minor' => 70000,
                    'credit_minor' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 70000,
                ],
            ],
            $this->adminUser->id
        );

        $inactiveProject = Project::query()->create([
            'code' => 'PRJ-UPD-INACTIVE',
            'name' => ['en' => 'Inactive Update Project'],
            'status' => 'cancelled',
            'is_active' => false,
        ]);

        $inactiveCostCenter = CostCenter::query()->create([
            'code' => 'CC-UPD-INACTIVE',
            'name' => ['en' => 'Inactive Update Cost Center'],
            'is_active' => false,
        ]);

        try {
            $draftService->updateDraft(
                $entry,
                ['lock_version' => $entry->lock_version],
                [
                    [
                        'account_id' => $this->cashAccount->id,
                        'project_id' => $inactiveProject->id,
                        'debit_minor' => 70000,
                        'credit_minor' => 0,
                    ],
                    [
                        'account_id' => $this->revenueAccount->id,
                        'debit_minor' => 0,
                        'credit_minor' => 70000,
                    ],
                ],
                $this->adminUser->id
            );

            $this->fail('Expected InvalidArgumentException when updating draft with inactive project.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Selected project is inactive or missing.', $e->getMessage());
        }

        $entry->refresh();

        try {
            $draftService->updateDraft(
                $entry,
                ['lock_version' => $entry->lock_version],
                [
                    [
                        'account_id' => $this->cashAccount->id,
                        'cost_center_id' => $inactiveCostCenter->id,
                        'debit_minor' => 70000,
                        'credit_minor' => 0,
                    ],
                    [
                        'account_id' => $this->revenueAccount->id,
                        'debit_minor' => 0,
                        'credit_minor' => 70000,
                    ],
                ],
                $this->adminUser->id
            );

            $this->fail('Expected InvalidArgumentException when updating draft with inactive cost center.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Selected cost center is inactive or missing.', $e->getMessage());
        }
    }

    public function test_inactive_cost_center_blocks_posting_if_deactivated_after_draft(): void
    {
        $activeCC = CostCenter::query()->create([
            'code' => 'CC-TEMP',
            'name' => ['en' => 'Temp CC'],
            'is_active' => true,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-08-15',
                'financial_period_id' => $this->openPeriod->id,
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'cost_center_id' => $activeCC->id,
                    'debit_minor' => 60000,
                    'credit_minor' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 60000,
                ],
            ],
            $this->adminUser->id
        );

        // Deactivate cost center before posting
        $activeCC->update(['is_active' => false]);

        $postingEngine = app(PostingEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot post journal line with inactive cost center [CC-TEMP].');

        $postingEngine->post($entry, $this->adminUser->id);
    }

    public function test_deleting_project_or_cost_center_referenced_by_accounting_records_is_blocked(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-NODELETE',
            'name' => ['en' => 'No Delete Project'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-NODELETE',
            'name' => ['en' => 'No Delete CC'],
            'is_active' => true,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-08-18',
                'financial_period_id' => $this->openPeriod->id,
                'currency' => 'EGP',
            ],
            [
                [
                    'account_id' => $this->cashAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'debit_minor' => 30000,
                    'credit_minor' => 0,
                ],
                [
                    'account_id' => $this->revenueAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 30000,
                ],
            ],
            $this->adminUser->id
        );

        $projectService = app(ProjectService::class);
        $costCenterService = app(CostCenterService::class);

        // 1. Attempt delete project
        try {
            $projectService->delete((string) $project->id, $this->adminUser->id);
            $this->fail('Expected ValidationException when deleting referenced project.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('project', $e->errors());
            $this->assertTrue(Project::query()->where('id', $project->id)->exists());
        }

        // 2. Attempt delete cost center
        try {
            $costCenterService->delete((string) $costCenter->id, $this->adminUser->id);
            $this->fail('Expected ValidationException when deleting referenced cost center.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cost_center', $e->errors());
            $this->assertTrue(CostCenter::query()->where('id', $costCenter->id)->exists());
        }
    }

    public function test_journal_form_props_include_active_dimensions_and_exclude_inactive(): void
    {
        $activeProject = Project::query()->create([
            'code' => 'PRJ-ACT',
            'name' => ['en' => 'Active Project'],
            'is_active' => true,
        ]);

        $inactiveProject = Project::query()->create([
            'code' => 'PRJ-INACT',
            'name' => ['en' => 'Inactive Project'],
            'is_active' => false,
        ]);

        $activeCC = CostCenter::query()->create([
            'code' => 'CC-ACT',
            'name' => ['en' => 'Active CC'],
            'is_active' => true,
        ]);

        $inactiveCC = CostCenter::query()->create([
            'code' => 'CC-INACT',
            'name' => ['en' => 'Inactive CC'],
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/accounting/journal/create');
        $response->assertOk();

        $response->assertInertia(function (Assert $page) use ($activeProject, $activeCC) {
            $page->component('Accounting/JournalForm')
                ->has('projects', fn (Assert $projects) => $projects
                    ->where('0.id', $activeProject->id)
                    ->where('0.code', 'PRJ-ACT')
                    ->missing('1')
                )
                ->has('costCenters', fn (Assert $costCenters) => $costCenters
                    ->where('0.id', $activeCC->id)
                    ->where('0.code', 'CC-ACT')
                    ->missing('1')
                );
        });
    }

    public function test_ui_source_scan_confirms_no_banned_elements_and_dictionary_backed_labels(): void
    {
        $formFile = resource_path('js/Pages/Accounting/JournalForm.tsx');
        $detailFile = resource_path('js/Pages/Accounting/JournalDetail.tsx');

        $this->assertFileExists($formFile);
        $this->assertFileExists($detailFile);

        $formCode = file_get_contents($formFile);
        $detailCode = file_get_contents($detailFile);

        foreach (['<select', '<option', 'type="date"', "type='date'", 'window.location.href'] as $banned) {
            $this->assertStringNotContainsString(
                $banned,
                $formCode,
                "JournalForm.tsx contains banned token: {$banned}"
            );
            $this->assertStringNotContainsString(
                $banned,
                $detailCode,
                "JournalDetail.tsx contains banned token: {$banned}"
            );
        }
    }

    public function test_scope_scan_confirms_no_tenant_or_company_assumptions_introduced(): void
    {
        $filesToScan = [
            database_path('migrations/2026_08_28_020000_add_phase16_gl_dimensions_to_journal_and_ledger.php'),
            app_path('Domain/Accounting/DraftLine.php'),
            app_path('Application/Accounting/JournalDraftService.php'),
            app_path('Application/Accounting/PostingEngine.php'),
            app_path('Application/Accounting/ReversalService.php'),
            app_path('Application/Accounting/JournalPageData.php'),
            app_path('Http/Controllers/Accounting/JournalController.php'),
        ];

        $bannedTokens = [
            'company_id',
            'tenant_id',
            'currentCompany',
            'currentTenant',
            'currentBranch',
            'Spatie\\Permission\\Traits\\HasRoles', // teams check
        ];

        foreach ($filesToScan as $file) {
            $this->assertFileExists($file);
            $contents = file_get_contents($file);

            foreach ($bannedTokens as $banned) {
                $this->assertStringNotContainsString(
                    $banned,
                    $contents,
                    "File [{$file}] must not contain banned scope assumption: {$banned}"
                );
            }
        }
    }
}
