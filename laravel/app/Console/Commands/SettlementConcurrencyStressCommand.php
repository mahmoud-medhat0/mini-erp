<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\CustomerOpeningBalanceService;
use App\Application\Accounting\PayableEntrySettlementService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\ReceivableEntrySettlementService;
use App\Application\Accounting\SupplierOpeningBalanceService;
use App\Models\Account;
use App\Models\Customer;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class SettlementConcurrencyStressCommand extends Command
{
    protected $signature = 'accounting:settlement-concurrency-stress {--workers=50}';

    protected $description = 'Run PostgreSQL concurrent AR/AP note settlement and over-settlement stress checks.';

    public function handle(
        PeriodService $periodService,
        AccountingAccountMappingService $mappingService,
        CustomerOpeningBalanceService $cobService,
        SupplierOpeningBalanceService $sobService,
    ): int {
        $driver = DB::connection()->getDriverName();
        $workers = max(2, min((int) $this->option('workers'), 250));
        $this->info("Running Settlement Concurrency Stress Test on DB driver: [{$driver}] with [{$workers}] concurrent workers...");

        if ($driver !== 'pgsql') {
            $this->error('Settlement concurrency stress requires PostgreSQL row locking.');

            return SymfonyCommand::FAILURE;
        }

        $mappingKeys = ['ar_control', 'ap_control', 'opening_balance_offset'];
        $previousMappings = $this->captureMappings($mappingKeys);

        try {
            $user = User::query()->first() ?? User::factory()->create();
            $suffix = Str::upper(Str::random(8));
            $yearNum = random_int(2300, 8999);

            while (DB::table('fiscal_year')->where('year', $yearNum)->exists()) {
                $yearNum = random_int(2300, 8999);
            }

            $fiscalYear = $periodService->createFiscalYear($yearNum, "{$yearNum}-01-01", "{$yearNum}-12-31");
            $period = $fiscalYear->periods()->firstOrFail();

            $arControl = Account::query()->create([
                'code' => "1100-AR-SETTLE-{$suffix}",
                'name' => ['en' => 'AR Settlement Stress Control'],
                'type' => 'asset',
                'nature' => 'debit',
                'currency' => 'EGP',
                'is_control' => true,
                'allow_manual_posting' => false,
                'is_active' => true,
            ]);

            $apControl = Account::query()->create([
                'code' => "2100-AP-SETTLE-{$suffix}",
                'name' => ['en' => 'AP Settlement Stress Control'],
                'type' => 'liability',
                'nature' => 'credit',
                'currency' => 'EGP',
                'is_control' => true,
                'allow_manual_posting' => false,
                'is_active' => true,
            ]);

            $offset = Account::query()->create([
                'code' => "3900-OFFSET-SETTLE-{$suffix}",
                'name' => ['en' => 'Settlement Stress Offset'],
                'type' => 'equity',
                'nature' => 'credit',
                'currency' => 'EGP',
                'is_control' => false,
                'allow_manual_posting' => true,
                'is_active' => true,
            ]);

            $mappingService->setMapping('ar_control', $arControl->id, 'AR Settlement Stress', $user->id);
            $mappingService->setMapping('ap_control', $apControl->id, 'AP Settlement Stress', $user->id);
            $mappingService->setMapping('opening_balance_offset', $offset->id, 'Offset Settlement Stress', $user->id);

            $customer = Customer::query()->create([
                'code' => "CUST-SETTLE-{$suffix}",
                'name' => ['en' => 'Settlement Stress Customer'],
            ]);

            $supplier = Supplier::query()->create([
                'code' => "SUPP-SETTLE-{$suffix}",
                'name' => ['en' => 'Settlement Stress Supplier'],
            ]);

            // Create target debit entry for Customer (Debit = 300,000 minor)
            $customerDebitCob = $cobService->create([
                'customer_id' => $customer->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$yearNum}-01-01",
                'currency' => 'EGP',
                'amount_minor' => 300000,
            ], $user->id);
            $postedCustomerDebit = $cobService->post($customerDebitCob->id, $user->id);

            // Manually create credit receivable entry for Customer (Credit = 300,000 minor) representing a posted credit note
            $sourceCreditEntry = ReceivableEntry::query()->create([
                'customer_id' => $customer->id,
                'source_type' => 'customer_credit_note',
                'source_id' => (string) Str::uuid(),
                'journal_entry_id' => $postedCustomerDebit->journal_entry_id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$yearNum}-01-01",
                'currency' => 'EGP',
                'debit_minor' => 0,
                'credit_minor' => 300000,
                'created_by' => $user->id,
            ]);

            // Create target credit entry for Supplier (Credit = 300,000 minor)
            $supplierCreditSob = $sobService->create([
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$yearNum}-01-01",
                'currency' => 'EGP',
                'amount_minor' => 300000,
            ], $user->id);
            $postedSupplierCredit = $sobService->post($supplierCreditSob->id, $user->id);

            // Manually create debit payable entry for Supplier (Debit = 300,000 minor) representing a posted SAN
            $sourceDebitPayableEntry = PayableEntry::query()->create([
                'supplier_id' => $supplier->id,
                'source_type' => 'supplier_adjustment_note',
                'source_id' => (string) Str::uuid(),
                'journal_entry_id' => $postedSupplierCredit->journal_entry_id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$yearNum}-01-01",
                'currency' => 'EGP',
                'debit_minor' => 300000,
                'credit_minor' => 0,
                'created_by' => $user->id,
            ]);

            $receivableResults = $this->runReceivableWorkers(
                $workers,
                $sourceCreditEntry->id,
                $postedCustomerDebit->receivable_entry_id,
                $user->id,
                $suffix,
            );

            $payableResults = $this->runPayableWorkers(
                $workers,
                $sourceDebitPayableEntry->id,
                $postedSupplierCredit->payable_entry_id,
                $user->id,
                $suffix,
            );

            $activeReceivableSettled = (int) ReceivableEntrySettlement::query()
                ->where('source_receivable_entry_id', $sourceCreditEntry->id)
                ->where('status', 'active')
                ->sum('amount_minor');

            $activePayableSettled = (int) PayableEntrySettlement::query()
                ->where('source_payable_entry_id', $sourceDebitPayableEntry->id)
                ->where('status', 'active')
                ->sum('amount_minor');

            $this->info('Receivable settlement worker results: '.json_encode(array_count_values($receivableResults), JSON_THROW_ON_ERROR));
            $this->info('Payable settlement worker results: '.json_encode(array_count_values($payableResults), JSON_THROW_ON_ERROR));
            $this->info("Active AR settled: {$activeReceivableSettled}");
            $this->info("Active AP settled: {$activePayableSettled}");

            if ($activeReceivableSettled !== 300000) {
                $this->error('FAIL: Receivable settlement concurrency invariant failed.');

                return SymfonyCommand::FAILURE;
            }

            if ($activePayableSettled !== 300000) {
                $this->error('FAIL: Payable settlement concurrency invariant failed.');

                return SymfonyCommand::FAILURE;
            }

            $this->info('PASS: Zero AR over-settlement under concurrent workers.');
            $this->info('PASS: Zero AP over-settlement under concurrent workers.');
            $this->info('Settlement Concurrency Stress Test PASSED CLEANLY.');

            return SymfonyCommand::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Settlement stress test failed: {$e->getMessage()}");

            return SymfonyCommand::FAILURE;
        } finally {
            $this->restoreMappings($mappingKeys, $previousMappings);
        }
    }

    /**
     * @return list<string>
     */
    private function runReceivableWorkers(int $workers, string $sourceCreditEntryId, string $targetDebitEntryId, int $actorId, string $suffix): array
    {
        $tasks = [];

        for ($i = 1; $i <= $workers; $i++) {
            $key = "settlement-stress-ar-{$suffix}-{$i}";
            $tasks[] = static fn (): string => (static function () use ($sourceCreditEntryId, $targetDebitEntryId, $actorId, $key): string {
                try {
                    app(ReceivableEntrySettlementService::class)->settleCredit(
                        $sourceCreditEntryId,
                        [[
                            'target_receivable_entry_id' => $targetDebitEntryId,
                            'amount_minor' => 100000,
                        ]],
                        $actorId,
                        $key,
                    );

                    return 'settled';
                } catch (ValidationException) {
                    return 'rejected';
                }
            })();
        }

        return Concurrency::run($tasks);
    }

    /**
     * @return list<string>
     */
    private function runPayableWorkers(int $workers, string $sourceDebitEntryId, string $targetCreditEntryId, int $actorId, string $suffix): array
    {
        $tasks = [];

        for ($i = 1; $i <= $workers; $i++) {
            $key = "settlement-stress-ap-{$suffix}-{$i}";
            $tasks[] = static fn (): string => (static function () use ($sourceDebitEntryId, $targetCreditEntryId, $actorId, $key): string {
                try {
                    app(PayableEntrySettlementService::class)->settleDebit(
                        $sourceDebitEntryId,
                        [[
                            'target_payable_entry_id' => $targetCreditEntryId,
                            'amount_minor' => 100000,
                        ]],
                        $actorId,
                        $key,
                    );

                    return 'settled';
                } catch (ValidationException) {
                    return 'rejected';
                }
            })();
        }

        return Concurrency::run($tasks);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    private function captureMappings(array $keys): array
    {
        return DB::table('accounting_account_mapping')
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->key => (array) $row])
            ->all();
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, array<string, mixed>>  $previousMappings
     */
    private function restoreMappings(array $keys, array $previousMappings): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $previousMappings)) {
                DB::table('accounting_account_mapping')
                    ->where('key', $key)
                    ->update($previousMappings[$key]);

                continue;
            }

            DB::table('accounting_account_mapping')
                ->where('key', $key)
                ->delete();
        }
    }
}
