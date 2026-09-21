<?php

namespace Tests\Feature;

use App\Application\Partners\PartnerLoanService;
use App\Application\Partners\PartnerService;
use App\Application\Partners\PartnerTransactionService;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\PartnerLoanRepayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 30 - Partners & Equity (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §9).
 * Partner transactions and loans both post real journal entries against the
 * shared partner_capital_account / partner_loan_payable control accounts,
 * following the same control-account + subledger pattern already validated
 * by AR, AP, and employee loans.
 */
class Phase30PartnersEquityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Partner $partner;

    private CashAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'partners.view', 'partners.create', 'partners.edit', 'partners.delete', 'partners.post',
            'view_financials', 'reports.view',
        ]);
        $this->actingAs($this->user);

        $this->partner = app(PartnerService::class)->create([
            'code' => 'PTR-001',
            'name' => ['en' => 'Test Partner', 'ar' => 'شريك تجريبي'],
            'share_bps' => 5000,
            'status' => 'active',
        ], $this->user->id);

        $cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'PARTNER-CASH-01',
            'name' => ['en' => 'Partner Test Cash', 'ar' => 'خزينة اختبار الشركاء'],
            'gl_account_id' => $cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_contribution_posts_a_balanced_journal_entry_debiting_cash_and_crediting_partner_capital(): void
    {
        $transactionService = app(PartnerTransactionService::class);

        $transaction = $transactionService->create([
            'partner_id' => $this->partner->id,
            'transaction_type' => 'contribution',
            'transaction_date' => '2026-01-05',
            'currency' => 'EGP',
            'amount_minor' => 500_000,
        ], $this->user->id);

        $this->assertSame('draft', $transaction->status);

        $posted = $transactionService->post($transaction->id, [
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertNotNull($posted->journal_entry_id);

        $journal = JournalEntry::query()->with('lines.account')->findOrFail($posted->journal_entry_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame(500_000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(500_000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '1100' && (int) $line->debit_minor === 500_000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '3300' && (int) $line->credit_minor === 500_000));
    }

    public function test_drawing_debits_partner_capital_and_credits_cash(): void
    {
        $transactionService = app(PartnerTransactionService::class);

        $transaction = $transactionService->create([
            'partner_id' => $this->partner->id,
            'transaction_type' => 'drawing',
            'transaction_date' => '2026-01-06',
            'currency' => 'EGP',
            'amount_minor' => 100_000,
        ], $this->user->id);

        $posted = $transactionService->post($transaction->id, [
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $journal = JournalEntry::query()->with('lines.account')->findOrFail($posted->journal_entry_id);
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '3300' && (int) $line->debit_minor === 100_000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '1100' && (int) $line->credit_minor === 100_000));
    }

    public function test_disbursing_a_partner_loan_posts_a_balanced_journal_entry(): void
    {
        $loanService = app(PartnerLoanService::class);

        $loan = $loanService->disburse([
            'partner_id' => $this->partner->id,
            'currency' => 'EGP',
            'principal_minor' => 300_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->assertSame('active', $loan->status);
        $this->assertSame(300_000, $loan->remaining_balance_minor);
        $this->assertNotNull($loan->journal_entry_id);

        $journal = JournalEntry::query()->with('lines.account')->findOrFail($loan->journal_entry_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame('partner_loan', $journal->source_type);
        $this->assertSame(300_000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(300_000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '1100' && (int) $line->debit_minor === 300_000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '2640' && (int) $line->credit_minor === 300_000));
    }

    public function test_repaying_a_partner_loan_decrements_balance_and_completes_at_zero(): void
    {
        $loanService = app(PartnerLoanService::class);
        $loan = $loanService->disburse([
            'partner_id' => $this->partner->id,
            'currency' => 'EGP',
            'principal_minor' => 200_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $loan = $loanService->repay($loan->id, [
            'repayment_date' => '2026-01-10',
            'amount_minor' => 120_000,
            'repayment_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->assertSame(80_000, $loan->remaining_balance_minor);
        $this->assertSame('active', $loan->status);

        $repayment = PartnerLoanRepayment::query()->where('partner_loan_id', $loan->id)->sole();
        $this->assertSame(120_000, $repayment->amount_minor);
        $journal = JournalEntry::query()->with('lines.account')->findOrFail($repayment->journal_entry_id);
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '2640' && (int) $line->debit_minor === 120_000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '1100' && (int) $line->credit_minor === 120_000));

        $loan = $loanService->repay($loan->id, [
            'repayment_date' => '2026-01-15',
            'amount_minor' => 80_000,
            'repayment_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->assertSame(0, $loan->remaining_balance_minor);
        $this->assertSame('completed', $loan->status);
    }

    public function test_repayment_amount_cannot_exceed_remaining_balance(): void
    {
        $loanService = app(PartnerLoanService::class);
        $loan = $loanService->disburse([
            'partner_id' => $this->partner->id,
            'currency' => 'EGP',
            'principal_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $loanService->repay($loan->id, [
            'repayment_date' => '2026-01-10',
            'amount_minor' => 150_000,
            'repayment_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);
    }

    public function test_a_loan_with_repayments_already_applied_cannot_be_cancelled(): void
    {
        $loanService = app(PartnerLoanService::class);
        $loan = $loanService->disburse([
            'partner_id' => $this->partner->id,
            'currency' => 'EGP',
            'principal_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $loanService->repay($loan->id, [
            'repayment_date' => '2026-01-10',
            'amount_minor' => 20_000,
            'repayment_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $loanService->cancel($loan->id, $this->user->id);
    }

    public function test_a_loan_with_no_repayments_can_be_cancelled(): void
    {
        $loanService = app(PartnerLoanService::class);
        $loan = $loanService->disburse([
            'partner_id' => $this->partner->id,
            'currency' => 'EGP',
            'principal_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $cancelled = $loanService->cancel($loan->id, $this->user->id);
        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_partner_routes_require_partners_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('dashboard.view');
        $this->actingAs($viewer)->get('/partners')->assertForbidden();
        $this->actingAs($viewer)->get('/partners/transactions')->assertForbidden();
        $this->actingAs($viewer)->get('/partners/loans')->assertForbidden();

        $this->actingAs($this->user)->get('/partners')->assertOk();
        $this->actingAs($this->user)->get('/partners/transactions')->assertOk();
        $this->actingAs($this->user)->get('/partners/loans')->assertOk();

        $this->actingAs($this->user)->post('/partners', [
            'code' => 'PTR-ROUTE-01',
            'name' => ['en' => 'Route Partner', 'ar' => 'شريك المسار'],
            'share_bps' => 2500,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partner', ['code' => 'PTR-ROUTE-01']);
    }

    public function test_posting_a_partner_loan_requires_view_financials_permission(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo(['partners.view', 'partners.create']);
        $this->actingAs($limited)->post('/partners/loans', [
            'partner_id' => $this->partner->id,
            'currency' => 'EGP',
            'principal_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ])->assertForbidden();
    }
}
