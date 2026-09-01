<?php

namespace Tests\Feature;

use App\Application\Accounting\ExchangeRateService;
use App\Application\Accounting\GeneralLedgerService;
use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Domain\Errors\UnbalancedEntryError;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountingCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    private Account $cashAccount;

    private Account $revenueAccount;

    private Account $controlAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $perm = Permission::firstOrCreate(
            ['name' => 'settings.configure', 'guard_name' => 'web'],
            ['module' => 'settings', 'action' => 'configure']
        );
        $this->user->givePermissionTo($perm);

        $periodService = app(PeriodService::class);
        $this->fiscalYear = $periodService->createFiscalYear(2026, '2026-01-01', '2026-12-31');
        $this->period = $this->fiscalYear->periods()->first();

        // Seed currencies
        $this->seed(CurrencySeeder::class);

        $group = AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => '1000',
            'name' => ['en' => 'Current Assets', 'ar' => 'الأصول المتداولة'],
            'type' => 'asset',
        ]);

        $this->cashAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1100',
            'name' => ['en' => 'Cash', 'ar' => 'الصندوق'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'currency' => 'EGP',
        ]);

        $this->revenueAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '4100',
            'name' => ['en' => 'Sales Revenue', 'ar' => 'إيراد المبيعات'],
            'type' => 'revenue',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'currency' => 'EGP',
        ]);

        $this->controlAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1200',
            'name' => ['en' => 'AR Control', 'ar' => 'مراقبة العملاء'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => true,
            'allow_manual_posting' => false,
            'currency' => 'EGP',
        ]);
    }

    public function test_can_create_and_post_balanced_journal_entry(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
                'description' => 'Test Sales Journal',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0, 'memo' => 'Cash in'],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000, 'memo' => 'Revenue'],
            ],
            $this->user->id
        );

        $this->assertEquals('draft', $entry->status);
        $this->assertCount(2, $entry->lines);

        $posted = $postingEngine->post($entry, $this->user->id);

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertDatabaseHas('ledger_entry', [
            'journal_entry_id' => $posted->id,
            'account_id' => $this->cashAccount->id,
            'debit_minor' => 10000,
            'debit_txn_minor' => 10000,
        ]);
    }

    public function test_stale_journal_actions_cannot_downgrade_or_edit_a_posted_entry(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
                'description' => 'Concurrency guard journal',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
            ],
            $this->user->id
        );
        $staleEntry = $entry->fresh();

        $postingEngine->post($entry, $this->user->id);

        foreach ([
            fn () => $draftService->submit($staleEntry, $this->user->id),
            fn () => $draftService->approve($staleEntry, $this->user->id),
            fn () => $draftService->updateDraft(
                $staleEntry,
                [
                    'entry_date' => '2026-01-15',
                    'financial_period_id' => $this->period->id,
                    'currency' => 'EGP',
                    'description' => 'Stale overwrite',
                ],
                [
                    ['account_id' => $this->cashAccount->id, 'debit_minor' => 5000, 'credit_minor' => 0],
                    ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 5000],
                ],
                $this->user->id
            ),
        ] as $staleAction) {
            try {
                $staleAction();
                $this->fail('A stale journal action unexpectedly changed a posted entry.');
            } catch (InvalidArgumentException) {
                $this->assertSame('posted', $entry->fresh()->status);
            }
        }

        $this->assertSame('Concurrency guard journal', $entry->fresh()->description);
        $this->assertDatabaseCount('ledger_entry', 2);
    }

    public function test_journal_submit_and_approve_use_the_authoritative_locked_state(): void
    {
        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
            ],
            $this->user->id
        );
        $staleDraft = $entry->fresh();

        $submitted = $draftService->submit($entry, $this->user->id);
        $approved = $draftService->approve($staleDraft, $this->user->id);

        $this->assertSame('submitted', $submitted->status);
        $this->assertSame('approved', $approved->status);
        $this->assertSame('approved', $entry->fresh()->status);
    }

    public function test_posted_ledger_entries_preserve_transaction_currency_amounts(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);
        $reversalService = app(ReversalService::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'USD',
                'fx_rate_e6' => 50000000,
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 500000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 500000],
            ],
            $this->user->id
        );

        $posted = $postingEngine->post($entry, $this->user->id);

        $cashLedger = LedgerEntry::where('journal_entry_id', $posted->id)
            ->where('account_id', $this->cashAccount->id)
            ->firstOrFail();

        $this->assertEquals(500000, $cashLedger->debit_minor);
        $this->assertEquals(500000, $cashLedger->debit_txn_minor);
        $this->assertEquals(0, $cashLedger->credit_txn_minor);

        $reversal = $reversalService->reverse($posted, $this->period->id, $this->user->id);
        $cashReversalLedger = LedgerEntry::where('journal_entry_id', $reversal->id)
            ->where('account_id', $this->cashAccount->id)
            ->firstOrFail();

        $this->assertEquals(500000, $cashReversalLedger->credit_minor);
        $this->assertEquals(500000, $cashReversalLedger->credit_txn_minor);
        $this->assertEquals(0, $cashReversalLedger->debit_txn_minor);
    }

    public function test_cannot_create_unbalanced_journal_entry(): void
    {
        $draftService = app(JournalDraftService::class);

        $this->expectException(UnbalancedEntryError::class);

        $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 5000],
            ],
            $this->user->id
        );
    }

    public function test_direct_manual_posting_to_control_account_is_blocked(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->controlAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
            ],
            $this->user->id
        );

        $this->expectException(InvalidArgumentException::class);
        $postingEngine->post($entry, $this->user->id, allowControlAccounts: false);
    }

    public function test_cannot_post_to_closed_financial_period(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);
        $periodService = app(PeriodService::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
            ],
            $this->user->id
        );

        $this->period->update(['status' => 'closed']);

        $this->expectException(InvalidArgumentException::class);
        $postingEngine->post($entry, $this->user->id);
    }

    public function test_can_reverse_posted_journal_entry(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);
        $reversalService = app(ReversalService::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 10000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 10000],
            ],
            $this->user->id
        );

        $posted = $postingEngine->post($entry, $this->user->id);

        $reversal = $reversalService->reverse($posted, $this->period->id, $this->user->id);

        $this->assertEquals('reversed', $posted->fresh()->status);
        $this->assertEquals('posted', $reversal->status);
        $this->assertEquals($posted->id, $reversal->reverses_entry_id);
    }

    public function test_trial_balance_is_balanced(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);
        $glService = app(GeneralLedgerService::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 25000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 25000],
            ],
            $this->user->id
        );

        $postingEngine->post($entry, $this->user->id);

        $tb = $glService->getTrialBalance(['period_id' => $this->period->id]);

        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(25000, $tb['total_debit']);
        $this->assertEquals(25000, $tb['total_credit']);
    }

    public function test_exchange_rate_service_exact_integer_parsing(): void
    {
        $this->assertEquals(1000000, ExchangeRateService::parseRateToE6('1'));
        $this->assertEquals(1250000, ExchangeRateService::parseRateToE6('1.25'));
        $this->assertEquals(50123456, ExchangeRateService::parseRateToE6('50.123456'));
        $this->assertEquals(50000000, ExchangeRateService::parseRateToE6(50));
    }

    public function test_exchange_rate_service_rejection_rules(): void
    {
        // Reject >6 decimals
        try {
            ExchangeRateService::parseRateToE6('1.1234567');
            $this->fail('Expected InvalidArgumentException for >6 decimals.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // Reject zero
        try {
            ExchangeRateService::parseRateToE6('0');
            $this->fail('Expected InvalidArgumentException for zero rate.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // Reject negative
        try {
            ExchangeRateService::parseRateToE6('-5.25');
            $this->fail('Expected InvalidArgumentException for negative rate.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // Reject invalid string
        try {
            ExchangeRateService::parseRateToE6('abc');
            $this->fail('Expected InvalidArgumentException for invalid string.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_can_save_fx_rate_with_valid_currency(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/fx-rates', [
            'currency' => 'USD',
            'date' => '2026-01-15',
            'rate' => '50.25',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exchange_rate', [
            'currency' => 'USD',
            'date' => '2026-01-15',
            'rate_e6' => 50250000,
        ]);
    }

    public function test_invalid_currency_code_cannot_be_saved_in_fx_rates(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/fx-rates', [
            'currency' => 'ZZZ',
            'date' => '2026-01-15',
            'rate' => 50,
        ]);

        $response->assertSessionHasErrors(['currency']);
    }

    public function test_invalid_currency_code_cannot_be_used_for_account_creation(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/coa/accounts', [
            'code' => '9999',
            'name_en' => 'Bad Account',
            'name_ar' => 'حساب سيء',
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'ZZZ',
        ]);

        $response->assertSessionHasErrors(['currency']);
    }

    public function test_invalid_currency_code_cannot_be_used_for_journal_creation(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/journal', [
            'entry_date' => '2026-01-15',
            'financial_period_id' => $this->period->id,
            'currency' => 'ZZZ',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 1000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 1000],
            ],
        ]);

        $response->assertSessionHasErrors(['currency']);
    }

    public function test_exchange_rate_loads_related_currency_ref(): void
    {
        $rate = ExchangeRate::create([
            'id' => (string) Str::uuid(),
            'currency' => 'USD',
            'date' => '2026-01-15',
            'rate_e6' => 50000000,
            'created_at' => now(),
        ]);

        $loaded = ExchangeRate::with('currencyRef')->find($rate->id);
        $this->assertNotNull($loaded->currencyRef);
        $this->assertEquals('USD', $loaded->currencyRef->code);
    }

    public function test_exchange_rate_search_runs_on_the_server_and_keeps_paginator_totals(): void
    {
        ExchangeRate::create([
            'currency' => 'USD',
            'date' => '2026-01-15',
            'rate_e6' => 50000000,
        ]);
        ExchangeRate::create([
            'currency' => 'EUR',
            'date' => '2026-02-20',
            'rate_e6' => 54000000,
        ]);

        $this->actingAs($this->user)
            ->get('/accounting/fx-rates?search=USD')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/ExchangeRates')
                ->where('filters.search', 'USD')
                ->where('rates.total', 1)
                ->has('rates.data', 1)
                ->where('rates.data.0.currency', 'USD')
                ->where('activeCurrencyCount', 1)
            );

        $this->actingAs($this->user)
            ->get('/accounting/fx-rates?search=2026-02-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rates.total', 1)
                ->where('rates.data.0.currency', 'EUR')
            );
    }

    public function test_accounting_pages_receive_relationship_backed_currency_options(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/fx-rates')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/ExchangeRates')
                ->has('currencies')
            );

        $this->actingAs($this->user)
            ->get('/accounting/coa')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/ChartOfAccounts')
                ->has('currencies')
            );

        $this->actingAs($this->user)
            ->get('/accounting/journal/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/JournalForm')
                ->has('currencies')
            );
    }

    public function test_journal_entry_date_must_be_within_financial_period(): void
    {
        $draftService = app(JournalDraftService::class);

        $this->expectException(InvalidArgumentException::class);
        $draftService->createDraft(
            [
                'entry_date' => '2027-06-15', // Period is 2026-01-01 to 2026-01-31
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 1000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 1000],
            ],
            $this->user->id
        );
    }

    public function test_database_enforced_ledger_entry_immutability(): void
    {
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);

        $entry = $draftService->createDraft(
            [
                'entry_date' => '2026-01-15',
                'financial_period_id' => $this->period->id,
                'currency' => 'EGP',
            ],
            [
                ['account_id' => $this->cashAccount->id, 'debit_minor' => 5000, 'credit_minor' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit_minor' => 0, 'credit_minor' => 5000],
            ],
            $this->user->id
        );

        $posted = $postingEngine->post($entry, $this->user->id);
        $ledgerId = DB::table('ledger_entry')->where('journal_entry_id', $posted->id)->value('id');
        $this->assertNotNull($ledgerId, 'Ledger entry was not created.');

        $updateFailed = false;
        try {
            DB::table('ledger_entry')->where('id', $ledgerId)->update(['debit_minor' => 99999]);
        } catch (\Throwable $e) {
            $updateFailed = true;
        }

        $deleteFailed = false;
        try {
            DB::table('ledger_entry')->where('id', $ledgerId)->delete();
        } catch (\Throwable $e) {
            $deleteFailed = true;
        }

        $this->assertTrue($updateFailed, 'Expected update on ledger_entry to be blocked by database immutability trigger.');
        $this->assertTrue($deleteFailed, 'Expected delete on ledger_entry to be blocked by database immutability trigger.');
    }

    public function test_can_view_currencies_page_and_create_currency(): void
    {
        $this->actingAs($this->user)
            ->get('/accounting/currencies')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/Currencies')
                ->has('currencies')
            );

        $response = $this->actingAs($this->user)->post('/accounting/currencies', [
            'code' => 'JPY',
            'name_en' => 'Japanese Yen',
            'name_ar' => 'الين الياباني',
            'symbol' => '¥',
            'exponent' => 0,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('currency', [
            'code' => 'JPY',
            'symbol' => '¥',
            'exponent' => 0,
        ]);
    }

    public function test_can_delete_unlinked_currency_and_blocks_linked_currency_deletion(): void
    {
        Currency::create([
            'code' => 'CAD',
            'name' => ['en' => 'Canadian Dollar', 'ar' => 'الدولار الكندي'],
            'symbol' => 'C$',
            'exponent' => 2,
        ]);

        $response = $this->actingAs($this->user)->delete('/accounting/currencies/CAD');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('currency', ['code' => 'CAD']);

        $this->actingAs($this->user)->delete('/accounting/currencies/EGP');
        $this->assertDatabaseHas('currency', ['code' => 'EGP']);
    }
}
