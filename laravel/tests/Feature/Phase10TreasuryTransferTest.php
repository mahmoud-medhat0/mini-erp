<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Application\Accounting\TreasuryTransferService;
use App\Application\MasterData\BankAccountService;
use App\Application\MasterData\CashAccountService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class Phase10TreasuryTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $northBranch;

    private Branch $southBranch;

    private Account $sourceGl;

    private Account $destinationGl;

    private CashAccount $cashAccount;

    private BankAccount $bankAccount;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RbacSeeder::class, AccountingCoreSeeder::class]);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'cash.view',
            'cash.create',
            'cash.edit',
            'cash.post',
            'banks.view',
            'banks.create',
            'banks.edit',
            'banks.post',
        ]);
        $this->actingAs($this->user);

        $this->northBranch = Branch::query()->create([
            'code' => 'NORTH-TREASURY',
            'name' => ['en' => 'North Treasury Branch', 'ar' => 'فرع خزينة الشمال'],
            'is_active' => true,
        ]);

        $this->southBranch = Branch::query()->create([
            'code' => 'SOUTH-TREASURY',
            'name' => ['en' => 'South Treasury Branch', 'ar' => 'فرع خزينة الجنوب'],
            'is_active' => true,
        ]);

        $this->sourceGl = $this->createAssetAccount('19910', 'North Cash GL');
        $this->destinationGl = $this->createAssetAccount('19920', 'South Bank GL');

        $this->cashAccount = app(CashAccountService::class)->create([
            'code' => 'NORTH-CASH',
            'name' => ['en' => 'North Cash', 'ar' => 'خزينة الشمال'],
            'branch_id' => $this->northBranch->id,
            'gl_account_id' => $this->sourceGl->id,
            'currency' => 'EGP',
        ], $this->user->id);

        $this->bankAccount = app(BankAccountService::class)->create([
            'code' => 'SOUTH-BANK',
            'name' => ['en' => 'South Bank', 'ar' => 'بنك الجنوب'],
            'bank_name' => ['en' => 'Operating Bank', 'ar' => 'بنك التشغيل'],
            'branch_id' => $this->southBranch->id,
            'account_number' => '100200300',
            'swift' => 'TESTEGCX',
            'gl_account_id' => $this->destinationGl->id,
            'currency' => 'EGP',
        ], $this->user->id);

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }

    public function test_cash_and_bank_accounts_have_optional_operational_branch_without_tenant_scope(): void
    {
        foreach (['cash_account', 'bank_account'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'branch_id'));
            $this->assertFalse(Schema::hasColumn($table, 'company_id'));
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'));
        }

        $this->assertSame($this->northBranch->id, $this->cashAccount->fresh('branch')->branch?->id);
        $this->assertSame($this->southBranch->id, $this->bankAccount->fresh('branch')->branch?->id);
    }

    public function test_treasury_transfer_posts_internal_cash_bank_movement_between_branch_linked_accounts(): void
    {
        $transfer = app(TreasuryTransferService::class)->create([
            'transfer_date' => '2026-01-15',
            'source_type' => 'cash',
            'source_cash_account_id' => $this->cashAccount->id,
            'destination_type' => 'bank',
            'destination_bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 125000,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'reference' => 'BRANCH-CASH-BANK',
            'description' => 'Move funds from north cash to south bank',
        ], $this->user->id);

        $posted = app(TreasuryTransferService::class)->post($transfer->id, $this->user->id);

        $this->assertSame('posted', $posted->status);
        $this->assertStringStartsWith('TRF-2026-', (string) $posted->number);
        $this->assertSame($this->northBranch->id, $posted->source_branch_id);
        $this->assertSame($this->southBranch->id, $posted->destination_branch_id);
        $this->assertNotNull($posted->journal_entry_id);

        $journal = JournalEntry::query()->with('lines')->findOrFail($posted->journal_entry_id);
        $this->assertSame('treasury_transfer', $journal->source_type);
        $this->assertSame('posted', $journal->status);
        $this->assertCount(2, $journal->lines);

        $this->assertTrue(LedgerEntry::query()
            ->where('journal_entry_id', $journal->id)
            ->where('account_id', $this->destinationGl->id)
            ->where('branch_id', $this->southBranch->id)
            ->where('debit_minor', 125000)
            ->where('credit_minor', 0)
            ->exists());

        $this->assertTrue(LedgerEntry::query()
            ->where('journal_entry_id', $journal->id)
            ->where('account_id', $this->sourceGl->id)
            ->where('branch_id', $this->northBranch->id)
            ->where('debit_minor', 0)
            ->where('credit_minor', 125000)
            ->exists());

        $again = app(TreasuryTransferService::class)->post($transfer->id, $this->user->id);
        $this->assertSame($posted->journal_entry_id, $again->journal_entry_id);
        $this->assertSame(2, LedgerEntry::query()->where('journal_entry_id', $journal->id)->count());
    }

    public function test_draft_treasury_transfer_blocks_period_close(): void
    {
        $transfer = app(TreasuryTransferService::class)->create([
            'transfer_date' => '2026-01-18',
            'source_type' => 'cash',
            'source_cash_account_id' => $this->cashAccount->id,
            'destination_type' => 'bank',
            'destination_bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 25000,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'reference' => 'CLOSE-BLOCKER-TRF',
        ], $this->user->id);

        $periodService = app(PeriodService::class);
        $readiness = $periodService->checkCloseReadiness($this->period);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue(collect($readiness['blockers'])->contains(
            fn (array $blocker): bool => $blocker['entity_type'] === 'treasury_transfer'
                && $blocker['id'] === $transfer->id
                && $blocker['reason_code'] === 'unposted_treasury_transfer'
        ));

        try {
            $periodService->closePeriod($this->period, $this->user->id);
            $this->fail('A period with a draft treasury transfer must not close.');
        } catch (InvalidArgumentException) {
            $this->assertSame('open', $this->period->fresh()->status);
        }
    }

    public function test_treasury_transfer_rejects_same_source_and_destination_account(): void
    {
        $this->expectException(ValidationException::class);

        app(TreasuryTransferService::class)->create([
            'transfer_date' => '2026-01-15',
            'source_type' => 'cash',
            'source_cash_account_id' => $this->cashAccount->id,
            'destination_type' => 'cash',
            'destination_cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 1000,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
        ], $this->user->id);
    }

    public function test_treasury_transfer_page_renders_with_branch_aware_options(): void
    {
        $this->get('/treasury-transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TreasuryTransfers/Index')
                ->has('transfers.data')
                ->has('cashAccounts')
                ->has('bankAccounts')
                ->has('fiscalYears')
                ->has('financialPeriods')
            );
    }

    private function createAssetAccount(string $code, string $name): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);
    }
}
