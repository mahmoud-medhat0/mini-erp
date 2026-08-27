<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Application\Expenses\AccrualScheduleService;
use App\Application\Expenses\PrepaidScheduleService;
use App\Models\Account;
use App\Models\AccrualEntry;
use App\Models\AccrualSchedule;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PrepaidRecognition;
use App\Models\PrepaidSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase12PrepaidAccruedExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $prepaidAssetAccount;

    private Account $expenseAccount;

    private Account $accruedLiabilityAccount;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.submit',
            'expenses.approve',
            'expenses.post',
            'view_financials',
        ]);
        $this->actingAs($this->user);

        $this->prepaidAssetAccount = Account::query()->where('code', '1800')->firstOrFail();
        $this->expenseAccount = Account::query()->where('code', '5100')->firstOrFail();
        $this->accruedLiabilityAccount = Account::query()->where('code', '2500')->firstOrFail();
        $this->category = ExpenseCategory::query()->where('code', 'GENERAL_ADMIN')->firstOrFail();
    }

    public function test_phase12_schema_preserves_single_erp_scope_with_operational_branch_only(): void
    {
        foreach (['prepaid_schedule', 'prepaid_recognition', 'accrual_schedule', 'accrual_entry'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must not contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must not contain tenant_id.");
        }

        $this->assertTrue(Schema::hasColumn('prepaid_schedule', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('accrual_schedule', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('prepaid_recognition', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('accrual_entry', 'branch_id'));
        $this->assertFalse(config('permission.teams'));
    }

    public function test_prepaid_schedule_generates_exact_monthly_recognitions(): void
    {
        $schedule = $this->createPrepaidSchedule(['total_minor' => 10000, 'months' => 3])
            ->fresh(['recognitions']);

        $this->assertCount(3, $schedule->recognitions);
        $this->assertSame([3334, 3333, 3333], $schedule->recognitions->pluck('amount_minor')->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $schedule->recognitions->pluck('recognition_date')->map->format('Y-m-d')->all());
        $this->assertSame(10000, (int) $schedule->recognitions->sum('amount_minor'));
    }

    public function test_prepaid_recognition_posts_balanced_journal_and_ledger(): void
    {
        $service = app(PrepaidScheduleService::class);
        $schedule = $this->approvePrepaidSchedule($this->createPrepaidSchedule(['total_minor' => 12000, 'months' => 2]));
        $recognition = $schedule->recognitions()->orderBy('recognition_date')->firstOrFail();

        $postedSchedule = $service->postRecognition($schedule->id, $recognition->id, $this->user->id);
        $recognition = PrepaidRecognition::query()->whereKey($recognition->id)->firstOrFail();
        $journal = JournalEntry::query()->with('lines.account')->whereKey($recognition->journal_entry_id)->firstOrFail();

        $this->assertSame('active', $postedSchedule->status);
        $this->assertSame(6000, $postedSchedule->recognized_minor);
        $this->assertSame('posted', $recognition->status);
        $this->assertSame('prepaid_recognition', $journal->source_type);
        $this->assertSame($recognition->id, $journal->source_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame(6000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(6000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account_id === $this->expenseAccount->id && $line->debit_minor === 6000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account_id === $this->prepaidAssetAccount->id && $line->credit_minor === 6000));
        $this->assertSame(2, LedgerEntry::query()->where('journal_entry_id', $journal->id)->count());
    }

    public function test_accrual_entry_posts_balanced_journal_and_ledger(): void
    {
        $service = app(AccrualScheduleService::class);
        $schedule = $this->approveAccrualSchedule($this->createAccrualSchedule(['total_minor' => 9000, 'months' => 3]));
        $entry = $schedule->entries()->orderBy('accrual_date')->firstOrFail();

        $postedSchedule = $service->postEntry($schedule->id, $entry->id, $this->user->id);
        $entry = AccrualEntry::query()->whereKey($entry->id)->firstOrFail();
        $journal = JournalEntry::query()->with('lines.account')->whereKey($entry->journal_entry_id)->firstOrFail();

        $this->assertSame('active', $postedSchedule->status);
        $this->assertSame(3000, $postedSchedule->accrued_minor);
        $this->assertSame('posted', $entry->status);
        $this->assertSame('accrual_entry', $journal->source_type);
        $this->assertSame($entry->id, $journal->source_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame(3000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(3000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account_id === $this->expenseAccount->id && $line->debit_minor === 3000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account_id === $this->accruedLiabilityAccount->id && $line->credit_minor === 3000));
        $this->assertSame(2, LedgerEntry::query()->where('journal_entry_id', $journal->id)->count());
    }

    public function test_period_close_readiness_blocks_pending_prepaid_and_accrual_entries(): void
    {
        $prepaid = $this->approvePrepaidSchedule($this->createPrepaidSchedule());
        $accrual = $this->approveAccrualSchedule($this->createAccrualSchedule());
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', '2026-01-31')
            ->where('end_date', '>=', '2026-01-31')
            ->firstOrFail();

        $readiness = app(PeriodService::class)->checkCloseReadiness($period);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue(collect($readiness['blockers'])->contains(fn (array $blocker): bool => $blocker['entity_type'] === 'prepaid_recognition' && $blocker['id'] === $prepaid->recognitions()->firstOrFail()->id && $blocker['reason_code'] === 'pending_prepaid_recognition'));
        $this->assertTrue(collect($readiness['blockers'])->contains(fn (array $blocker): bool => $blocker['entity_type'] === 'accrual_entry' && $blocker['id'] === $accrual->entries()->firstOrFail()->id && $blocker['reason_code'] === 'pending_accrual_entry'));
    }

    public function test_prepaid_and_accrual_inertia_pages_render_expected_props(): void
    {
        $this->withoutVite();

        $this->get('/expenses/prepaids')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Prepaids')
                ->has('schedules')
                ->has('categories')
                ->has('prepaidAssetAccounts')
                ->has('expenseAccounts')
                ->has('branches')
                ->has('currencies'));

        $this->get('/expenses/accruals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Accruals')
                ->has('schedules')
                ->has('categories')
                ->has('expenseAccounts')
                ->has('liabilityAccounts')
                ->has('branches')
                ->has('currencies'));
    }

    private function createPrepaidSchedule(array $overrides = []): PrepaidSchedule
    {
        return app(PrepaidScheduleService::class)->create([
            'schedule_date' => '2026-01-05',
            'start_date' => '2026-01-01',
            'months' => 2,
            'expense_category_id' => $this->category->id,
            'prepaid_asset_account_id' => $this->prepaidAssetAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'total_minor' => 12000,
            'reference' => 'PREP-TEST',
            ...$overrides,
        ], $this->user->id);
    }

    private function approvePrepaidSchedule(PrepaidSchedule $schedule): PrepaidSchedule
    {
        $service = app(PrepaidScheduleService::class);
        $service->submit($schedule->id, $this->user->id);

        return $service->approve($schedule->id, $this->user->id);
    }

    private function createAccrualSchedule(array $overrides = []): AccrualSchedule
    {
        return app(AccrualScheduleService::class)->create([
            'schedule_date' => '2026-01-05',
            'start_date' => '2026-01-01',
            'months' => 2,
            'expense_category_id' => $this->category->id,
            'expense_account_id' => $this->expenseAccount->id,
            'accrued_liability_account_id' => $this->accruedLiabilityAccount->id,
            'currency' => 'EGP',
            'fx_rate_e6' => 1000000,
            'total_minor' => 12000,
            'reference' => 'ACCR-TEST',
            ...$overrides,
        ], $this->user->id);
    }

    private function approveAccrualSchedule(AccrualSchedule $schedule): AccrualSchedule
    {
        $service = app(AccrualScheduleService::class);
        $service->submit($schedule->id, $this->user->id);

        return $service->approve($schedule->id, $this->user->id);
    }
}
