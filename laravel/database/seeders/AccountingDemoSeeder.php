<?php

namespace Database\Seeders;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Application\Support\CurrencyInput;
use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AccountingDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure core structure and users exist
        $this->call([
            CurrencySeeder::class,
            RbacSeeder::class,
            PermissionSeeder::class,
            BootstrapUserSeeder::class,
            FirstUserSuperAdminSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
            AccountingCoreSeeder::class,
        ]);

        $adminUser = User::query()->first();
        if (! $adminUser) {
            $adminUser = User::factory()->create([
                'name' => 'Demo Administrator',
                'email' => 'admin@demo.local',
            ]);
        }

        // 2. Ensure an open FiscalYear and FinancialPeriod exist
        $currentYear = (int) date('Y');
        $fiscalYear = FiscalYear::query()->where('year', $currentYear)->first();

        if (! $fiscalYear) {
            $periodService = app(PeriodService::class);
            $fiscalYear = $periodService->createFiscalYear(
                $currentYear,
                "{$currentYear}-01-01",
                "{$currentYear}-12-31"
            );
        }

        $openPeriod = FinancialPeriod::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('status', ['open', 'reopened'])
            ->orderBy('month')
            ->first();

        if (! $openPeriod) {
            $openPeriod = FinancialPeriod::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->orderBy('month')
                ->first();

            if ($openPeriod) {
                $openPeriod->update(['status' => 'open']);
            }
        }

        // 3. Idempotency Check for Demo Journal Entry
        $demoReference = 'DEMO-POSTED-SALE-001';
        $existingEntry = JournalEntry::query()
            ->where('reference', $demoReference)
            ->first();

        if ($existingEntry && $existingEntry->status === 'posted') {
            return;
        }

        // 4. Resolve Account Models
        $cashAccount = Account::query()->where('code', '1100')->firstOrFail();
        $salesAccount = Account::query()->where('code', '4100')->firstOrFail();
        $currency = CurrencyInput::related($cashAccount->currency, 'currency', 'Demo cash account');

        if ($currency !== CurrencyInput::related($salesAccount->currency, 'currency', 'Demo sales account')) {
            throw new \InvalidArgumentException('Demo sale accounts must use the same currency.');
        }

        // 5. Create Draft, Submit, Approve, and Post via Business Services
        $draftService = app(JournalDraftService::class);
        $postingEngine = app(PostingEngine::class);

        $entryDate = Carbon::parse($openPeriod->start_date)->toDateString();

        if (! $existingEntry) {
            $journalData = [
                'entry_date' => $entryDate,
                'financial_period_id' => $openPeriod->id,
                'source_type' => 'manual_journal',
                'source_id' => 'demo-sale-receipt-001',
                'description' => 'Demo posted sale receipt',
                'reference' => $demoReference,
                'currency' => $currency,
            ];

            $linesData = [
                [
                    'account_id' => $cashAccount->id,
                    'debit_minor' => 100000,
                    'credit_minor' => 0,
                    'memo' => 'Debit Cash Clearing',
                ],
                [
                    'account_id' => $salesAccount->id,
                    'debit_minor' => 0,
                    'credit_minor' => 100000,
                    'memo' => 'Credit Sales Revenue',
                ],
            ];

            $existingEntry = $draftService->createDraft($journalData, $linesData, $adminUser->id);
        }

        if ($existingEntry->status === 'draft') {
            $draftService->submit($existingEntry, $adminUser->id);
            $existingEntry->refresh();
        }

        if ($existingEntry->status === 'submitted') {
            $draftService->approve($existingEntry, $adminUser->id);
            $existingEntry->refresh();
        }

        if ($existingEntry->status === 'approved') {
            $postingEngine->post($existingEntry, $adminUser->id, allowControlAccounts: false);
        }
    }
}
