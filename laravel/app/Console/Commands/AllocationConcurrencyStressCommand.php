<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\CustomerOpeningBalanceService;
use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\PayableAllocationService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\ReceivableAllocationService;
use App\Application\Accounting\SupplierOpeningBalanceService;
use App\Application\Accounting\SupplierPaymentService;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\PayableAllocation;
use App\Models\ReceivableAllocation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Concurrency\DuplicateOperationInProgressException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class AllocationConcurrencyStressCommand extends Command
{
    protected $signature = 'accounting:allocation-concurrency-stress {--workers=50}';

    protected $description = 'Run PostgreSQL concurrent allocation and over-allocation stress checks.';

    public function handle(
        PeriodService $periodService,
        AccountingAccountMappingService $mappingService,
        CustomerOpeningBalanceService $cobService,
        SupplierOpeningBalanceService $sobService,
        CustomerReceiptService $receiptService,
        SupplierPaymentService $paymentService,
    ): int {
        $driver = DB::connection()->getDriverName();
        $workers = max(2, min((int) $this->option('workers'), 250));
        $this->info("Running Allocation Concurrency Stress Test on DB driver: [{$driver}] with [{$workers}] concurrent workers...");

        if ($driver !== 'pgsql') {
            $this->error('Allocation concurrency stress requires PostgreSQL row locking.');

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
                'code' => "1100-AR-ALLOC-{$suffix}",
                'name' => ['en' => 'AR Allocation Stress Control'],
                'type' => 'asset',
                'nature' => 'debit',
                'currency' => 'EGP',
                'is_control' => true,
                'allow_manual_posting' => false,
                'is_active' => true,
            ]);

            $apControl = Account::query()->create([
                'code' => "2100-AP-ALLOC-{$suffix}",
                'name' => ['en' => 'AP Allocation Stress Control'],
                'type' => 'liability',
                'nature' => 'credit',
                'currency' => 'EGP',
                'is_control' => true,
                'allow_manual_posting' => false,
                'is_active' => true,
            ]);

            $offset = Account::query()->create([
                'code' => "3900-OFFSET-ALLOC-{$suffix}",
                'name' => ['en' => 'Allocation Stress Offset'],
                'type' => 'equity',
                'nature' => 'credit',
                'currency' => 'EGP',
                'is_control' => false,
                'allow_manual_posting' => true,
                'is_active' => true,
            ]);

            $cashGl = Account::query()->create([
                'code' => "1010-CASH-ALLOC-{$suffix}",
                'name' => ['en' => 'Allocation Stress Cash'],
                'type' => 'asset',
                'nature' => 'debit',
                'currency' => 'EGP',
                'is_control' => false,
                'allow_manual_posting' => true,
                'is_active' => true,
            ]);

            $mappingService->setMapping('ar_control', $arControl->id, 'AR Allocation Stress', $user->id);
            $mappingService->setMapping('ap_control', $apControl->id, 'AP Allocation Stress', $user->id);
            $mappingService->setMapping('opening_balance_offset', $offset->id, 'Offset Allocation Stress', $user->id);

            $cashAccount = CashAccount::query()->create([
                'code' => "CASH-ALLOC-{$suffix}",
                'name' => ['en' => 'Allocation Stress Cash Account'],
                'gl_account_id' => $cashGl->id,
                'currency' => 'EGP',
                'is_active' => true,
            ]);

            $customer = Customer::query()->create([
                'code' => "CUST-ALLOC-{$suffix}",
                'name' => ['en' => 'Allocation Stress Customer'],
            ]);

            $supplier = Supplier::query()->create([
                'code' => "SUPP-ALLOC-{$suffix}",
                'name' => ['en' => 'Allocation Stress Supplier'],
            ]);

            $customerOpeningBalance = $cobService->create([
                'customer_id' => $customer->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$yearNum}-01-01",
                'currency' => 'EGP',
                'amount_minor' => 300000,
            ], $user->id);
            $postedCustomerOpeningBalance = $cobService->post($customerOpeningBalance->id, $user->id);

            $receipt = $receiptService->create([
                'customer_id' => $customer->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'receipt_date' => "{$yearNum}-01-01",
                'cash_account_id' => $cashAccount->id,
                'currency' => 'EGP',
                'amount_minor' => 300000,
            ], $user->id);
            $postedReceipt = $receiptService->post($receipt->id, $user->id);

            $supplierOpeningBalance = $sobService->create([
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$yearNum}-01-01",
                'currency' => 'EGP',
                'amount_minor' => 300000,
            ], $user->id);
            $postedSupplierOpeningBalance = $sobService->post($supplierOpeningBalance->id, $user->id);

            $payment = $paymentService->create([
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $fiscalYear->id,
                'financial_period_id' => $period->id,
                'payment_date' => "{$yearNum}-01-01",
                'cash_account_id' => $cashAccount->id,
                'currency' => 'EGP',
                'amount_minor' => 300000,
            ], $user->id);
            $postedPayment = $paymentService->post($payment->id, $user->id);

            $receivableResults = $this->runReceivableWorkers(
                $workers,
                $postedReceipt->id,
                $postedCustomerOpeningBalance->receivable_entry_id,
                $user->id,
                $suffix,
            );

            $payableResults = $this->runPayableWorkers(
                $workers,
                $postedPayment->id,
                $postedSupplierOpeningBalance->payable_entry_id,
                $user->id,
                $suffix,
            );

            $activeReceivableAllocated = (int) ReceivableAllocation::query()
                ->where('receivable_entry_id', $postedCustomerOpeningBalance->receivable_entry_id)
                ->where('status', 'active')
                ->sum('amount_minor');

            $activePayableAllocated = (int) PayableAllocation::query()
                ->where('payable_entry_id', $postedSupplierOpeningBalance->payable_entry_id)
                ->where('status', 'active')
                ->sum('amount_minor');

            $freshReceipt = $postedReceipt->fresh();
            $freshPayment = $postedPayment->fresh();

            $this->info('Receivable worker results: '.json_encode(array_count_values($receivableResults), JSON_THROW_ON_ERROR));
            $this->info('Payable worker results: '.json_encode(array_count_values($payableResults), JSON_THROW_ON_ERROR));
            $this->info("Active AR allocated: {$activeReceivableAllocated}; receipt allocated={$freshReceipt->allocated_minor}, unapplied={$freshReceipt->unapplied_minor}");
            $this->info("Active AP allocated: {$activePayableAllocated}; payment allocated={$freshPayment->allocated_minor}, unapplied={$freshPayment->unapplied_minor}");

            if ($activeReceivableAllocated !== 300000 || $freshReceipt->allocated_minor !== 300000 || $freshReceipt->unapplied_minor !== 0) {
                $this->error('FAIL: Receivable allocation concurrency invariant failed.');

                return SymfonyCommand::FAILURE;
            }

            if ($activePayableAllocated !== 300000 || $freshPayment->allocated_minor !== 300000 || $freshPayment->unapplied_minor !== 0) {
                $this->error('FAIL: Payable allocation concurrency invariant failed.');

                return SymfonyCommand::FAILURE;
            }

            $idempotencyResult = $this->runSharedIdempotencyReplay(
                $cobService,
                $receiptService,
                $fiscalYear->id,
                $period->id,
                "{$yearNum}-01-01",
                $cashAccount->id,
                $user->id,
                $suffix,
            );

            if (! $idempotencyResult) {
                $this->error('FAIL: Shared idempotency allocation replay created duplicate rows.');

                return SymfonyCommand::FAILURE;
            }

            $this->info('PASS: Zero AR over-allocation under concurrent workers.');
            $this->info('PASS: Zero AP over-allocation under concurrent workers.');
            $this->info('PASS: Receipt/payment unapplied balance invariants strictly preserved.');
            $this->info('PASS: Shared idempotency key did not duplicate allocation rows.');
            $this->info('Allocation Concurrency Stress Test PASSED CLEANLY.');

            return SymfonyCommand::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Allocation stress test failed: {$e->getMessage()}");

            return SymfonyCommand::FAILURE;
        } finally {
            $this->restoreMappings($mappingKeys, $previousMappings);
        }
    }

    /**
     * @return list<string>
     */
    private function runReceivableWorkers(int $workers, string $receiptId, string $receivableEntryId, int $actorId, string $suffix): array
    {
        $tasks = [];

        for ($i = 1; $i <= $workers; $i++) {
            $key = "allocation-stress-ar-{$suffix}-{$i}";
            $tasks[] = static fn (): string => (static function () use ($receiptId, $receivableEntryId, $actorId, $key): string {
                try {
                    app(ReceivableAllocationService::class)->allocateReceipt(
                        $receiptId,
                        [[
                            'receivable_entry_id' => $receivableEntryId,
                            'amount_minor' => 100000,
                        ]],
                        $actorId,
                        $key,
                    );

                    return 'allocated';
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
    private function runPayableWorkers(int $workers, string $paymentId, string $payableEntryId, int $actorId, string $suffix): array
    {
        $tasks = [];

        for ($i = 1; $i <= $workers; $i++) {
            $key = "allocation-stress-ap-{$suffix}-{$i}";
            $tasks[] = static fn (): string => (static function () use ($paymentId, $payableEntryId, $actorId, $key): string {
                try {
                    app(PayableAllocationService::class)->allocatePayment(
                        $paymentId,
                        [[
                            'payable_entry_id' => $payableEntryId,
                            'amount_minor' => 100000,
                        ]],
                        $actorId,
                        $key,
                    );

                    return 'allocated';
                } catch (ValidationException) {
                    return 'rejected';
                }
            })();
        }

        return Concurrency::run($tasks);
    }

    private function runSharedIdempotencyReplay(
        CustomerOpeningBalanceService $cobService,
        CustomerReceiptService $receiptService,
        string $fiscalYearId,
        string $periodId,
        string $date,
        string $cashAccountId,
        int $actorId,
        string $suffix,
    ): bool {
        $customer = Customer::query()->create([
            'code' => "CUST-ALLOC-IDEM-{$suffix}",
            'name' => ['en' => 'Allocation Idempotency Customer'],
        ]);

        $customerOpeningBalance = $cobService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $fiscalYearId,
            'financial_period_id' => $periodId,
            'entry_date' => $date,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $actorId);
        $postedCustomerOpeningBalance = $cobService->post($customerOpeningBalance->id, $actorId);

        $receipt = $receiptService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $fiscalYearId,
            'financial_period_id' => $periodId,
            'receipt_date' => $date,
            'cash_account_id' => $cashAccountId,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ], $actorId);
        $postedReceipt = $receiptService->post($receipt->id, $actorId);

        $sharedKey = "allocation-stress-shared-key-{$suffix}";
        $receiptId = $postedReceipt->id;
        $receivableEntryId = $postedCustomerOpeningBalance->receivable_entry_id;
        $tasks = [];

        for ($i = 1; $i <= 10; $i++) {
            $tasks[] = static fn (): string => (static function () use ($receiptId, $receivableEntryId, $actorId, $sharedKey): string {
                try {
                    app(ReceivableAllocationService::class)->allocateReceipt(
                        $receiptId,
                        [[
                            'receivable_entry_id' => $receivableEntryId,
                            'amount_minor' => 100000,
                        ]],
                        $actorId,
                        $sharedKey,
                    );

                    return 'completed';
                } catch (DuplicateOperationInProgressException) {
                    return 'pending';
                } catch (ValidationException) {
                    return 'rejected';
                }
            })();
        }

        Concurrency::run($tasks);

        return ReceivableAllocation::query()
            ->where('customer_receipt_id', $receiptId)
            ->where('status', 'active')
            ->count() === 1;
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
