<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\IncomingChequeService;
use App\Application\Accounting\OutgoingChequeService;
use App\Application\Accounting\PeriodService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\IncomingCheque;
use App\Models\JournalEntry;
use App\Models\OutgoingCheque;
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

class ChequeConcurrencyStressCommand extends Command
{
    protected $signature = 'accounting:cheque-concurrency-stress {--workers=50}';

    protected $description = 'Run PostgreSQL cheque transition concurrency stress checks.';

    public function handle(
        PeriodService $periodService,
        AccountingAccountMappingService $mappingService,
        IncomingChequeService $incomingService,
        OutgoingChequeService $outgoingService,
    ): int {
        $driver = DB::connection()->getDriverName();
        $workers = max(2, min((int) $this->option('workers'), 250));
        $this->info("Running Cheque Concurrency Stress Test on DB driver: [{$driver}] with [{$workers}] concurrent workers...");

        if ($driver !== 'pgsql') {
            $this->error('Cheque concurrency stress requires PostgreSQL row locking.');

            return SymfonyCommand::FAILURE;
        }

        $mappingKeys = ['ar_control', 'ap_control', 'opening_balance_offset', 'cheques_under_collection', 'cheques_payable'];
        $previousMappings = $this->captureMappings($mappingKeys);

        try {
            $user = User::query()->first() ?? User::factory()->create();
            $suffix = Str::upper(Str::random(8));
            $yearNum = random_int(2400, 8999);

            while (DB::table('fiscal_year')->where('year', $yearNum)->exists()) {
                $yearNum = random_int(2400, 8999);
            }

            $fiscalYear = $periodService->createFiscalYear($yearNum, "{$yearNum}-01-01", "{$yearNum}-12-31");
            $period = $fiscalYear->periods()->firstOrFail();

            $accounts = $this->createAccountsAndMappings($mappingService, $user->id, $suffix);
            $bankAccount = BankAccount::query()->create([
                'code' => "BANK-CHQ-{$suffix}",
                'name' => ['en' => 'Cheque Stress Bank Account'],
                'gl_account_id' => $accounts['bank']->id,
                'currency' => 'EGP',
                'is_active' => true,
            ]);

            $customer = Customer::query()->create([
                'code' => "CUST-CHQ-{$suffix}",
                'name' => ['en' => 'Cheque Stress Customer'],
            ]);
            $supplier = Supplier::query()->create([
                'code' => "SUPP-CHQ-{$suffix}",
                'name' => ['en' => 'Cheque Stress Supplier'],
            ]);

            $idempotentIncoming = $this->createReceivedIncomingCheque(
                $incomingService,
                $customer->id,
                $fiscalYear->id,
                $period->id,
                "{$yearNum}-01-01",
                $user->id,
                "IN-IDEM-{$suffix}",
            );

            $sharedKeyResults = $this->runIncomingClearWorkers(
                $workers,
                $idempotentIncoming->id,
                $fiscalYear->id,
                $period->id,
                "{$yearNum}-01-01",
                $bankAccount->id,
                $user->id,
                static fn (int $i): string => "cheque-shared-clear-{$suffix}",
            );

            $idempotentIncoming = $idempotentIncoming->fresh();
            $idempotentIncomingClearJournals = JournalEntry::query()
                ->where('source_type', 'incoming_cheque')
                ->where('source_id', $idempotentIncoming->id)
                ->where('description', 'like', 'Customer Cheque Cleared%')
                ->count();

            if ($idempotentIncoming->status !== 'cleared' || $idempotentIncomingClearJournals !== 1) {
                $this->error('FAIL: Shared idempotency incoming clear created an invalid result.');

                return SymfonyCommand::FAILURE;
            }

            $raceIncoming = $this->createReceivedIncomingCheque(
                $incomingService,
                $customer->id,
                $fiscalYear->id,
                $period->id,
                "{$yearNum}-01-01",
                $user->id,
                "IN-RACE-{$suffix}",
            );

            $clearVsBounceResults = Concurrency::run([
                static fn (): string => (static function () use ($raceIncoming, $fiscalYear, $period, $bankAccount, $user): string {
                    try {
                        app(IncomingChequeService::class)->clear(
                            $raceIncoming->id,
                            $fiscalYear->id,
                            $period->id,
                            "{$fiscalYear->year}-01-01",
                            $bankAccount->id,
                            $user->id,
                            "cheque-clear-race-{$raceIncoming->id}",
                        );

                        return 'clear-committed';
                    } catch (Throwable) {
                        return 'clear-rejected';
                    }
                })(),
                static fn (): string => (static function () use ($raceIncoming, $fiscalYear, $period, $user): string {
                    try {
                        app(IncomingChequeService::class)->bounceBeforeClear(
                            $raceIncoming->id,
                            $fiscalYear->id,
                            $period->id,
                            "{$fiscalYear->year}-01-01",
                            'Concurrent bounce stress',
                            $user->id,
                            "cheque-bounce-race-{$raceIncoming->id}",
                        );

                        return 'bounce-committed';
                    } catch (Throwable) {
                        return 'bounce-rejected';
                    }
                })(),
            ]);

            $raceIncoming = $raceIncoming->fresh();
            $raceIncomingTerminalEffects = collect([
                $raceIncoming->clear_journal_entry_id,
                $raceIncoming->bounce_journal_entry_id,
            ])->filter()->count();

            if (! in_array($raceIncoming->status, ['cleared', 'bounced'], true) || $raceIncomingTerminalEffects !== 1) {
                $this->error('FAIL: Incoming clear-vs-bounce race produced inconsistent cheque state.');

                return SymfonyCommand::FAILURE;
            }

            $issuedOutgoing = $this->createIssuedOutgoingCheque(
                $outgoingService,
                $supplier->id,
                $bankAccount->id,
                $fiscalYear->id,
                $period->id,
                "{$yearNum}-01-01",
                $user->id,
                "OUT-CLEAR-{$suffix}",
            );

            $outgoingClearResults = $this->runOutgoingClearWorkers(
                $workers,
                $issuedOutgoing->id,
                $fiscalYear->id,
                $period->id,
                "{$yearNum}-01-01",
                $user->id,
                $suffix,
            );

            $issuedOutgoing = $issuedOutgoing->fresh();
            $outgoingClearJournals = JournalEntry::query()
                ->where('source_type', 'outgoing_cheque')
                ->where('source_id', $issuedOutgoing->id)
                ->where('description', 'like', 'Supplier Cheque Cleared%')
                ->count();

            if ($issuedOutgoing->status !== 'cleared' || $outgoingClearJournals !== 1) {
                $this->error('FAIL: Concurrent outgoing clear attempts created an invalid result.');

                return SymfonyCommand::FAILURE;
            }

            $this->info('Shared incoming clear results: '.json_encode(array_count_values($sharedKeyResults), JSON_THROW_ON_ERROR));
            $this->info('Incoming clear-vs-bounce results: '.json_encode($clearVsBounceResults, JSON_THROW_ON_ERROR));
            $this->info('Outgoing clear results: '.json_encode(array_count_values($outgoingClearResults), JSON_THROW_ON_ERROR));
            $this->info('PASS: Incoming cheque clear is idempotent under concurrent replay.');
            $this->info('PASS: Incoming clear vs bounce allows exactly one terminal posting.');
            $this->info('PASS: Outgoing cheque clear creates exactly one clear posting under concurrent workers.');
            $this->info('Cheque Concurrency Stress Test PASSED CLEANLY.');

            return SymfonyCommand::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Cheque stress test failed: {$e->getMessage()}");

            return SymfonyCommand::FAILURE;
        } finally {
            $this->restoreMappings($mappingKeys, $previousMappings);
        }
    }

    /**
     * @return array<string, Account>
     */
    private function createAccountsAndMappings(AccountingAccountMappingService $mappingService, int $actorId, string $suffix): array
    {
        $arControl = Account::query()->create([
            'code' => "1100-AR-CHQ-{$suffix}",
            'name' => ['en' => 'AR Cheque Control'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $apControl = Account::query()->create([
            'code' => "2100-AP-CHQ-{$suffix}",
            'name' => ['en' => 'AP Cheque Control'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
            'is_active' => true,
        ]);

        $underCollection = Account::query()->create([
            'code' => "1050-CHQ-COLL-{$suffix}",
            'name' => ['en' => 'Cheques Under Collection'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $chequesPayable = Account::query()->create([
            'code' => "2050-CHQ-PAY-{$suffix}",
            'name' => ['en' => 'Cheques Payable'],
            'type' => 'liability',
            'nature' => 'credit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $bank = Account::query()->create([
            'code' => "1020-BANK-CHQ-{$suffix}",
            'name' => ['en' => 'Cheque Stress Bank'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);

        $mappingService->setMapping('ar_control', $arControl->id, 'AR Control Cheque Stress', $actorId);
        $mappingService->setMapping('ap_control', $apControl->id, 'AP Control Cheque Stress', $actorId);
        $mappingService->setMapping('cheques_under_collection', $underCollection->id, 'Under Collection Stress', $actorId);
        $mappingService->setMapping('cheques_payable', $chequesPayable->id, 'Cheques Payable Stress', $actorId);

        return [
            'ar' => $arControl,
            'ap' => $apControl,
            'under_collection' => $underCollection,
            'payable' => $chequesPayable,
            'bank' => $bank,
        ];
    }

    private function createReceivedIncomingCheque(
        IncomingChequeService $incomingService,
        string $customerId,
        string $fiscalYearId,
        string $periodId,
        string $date,
        int $actorId,
        string $chequeNumber,
    ): IncomingCheque {
        $draft = $incomingService->createDraft([
            'customer_id' => $customerId,
            'cheque_number' => $chequeNumber,
            'currency' => 'EGP',
            'amount_minor' => 150000,
        ], $actorId);

        return $incomingService->receive($draft->id, $fiscalYearId, $periodId, $date, $actorId);
    }

    private function createIssuedOutgoingCheque(
        OutgoingChequeService $outgoingService,
        string $supplierId,
        string $bankAccountId,
        string $fiscalYearId,
        string $periodId,
        string $date,
        int $actorId,
        string $chequeNumber,
    ): OutgoingCheque {
        $draft = $outgoingService->createDraft([
            'supplier_id' => $supplierId,
            'bank_account_id' => $bankAccountId,
            'cheque_number' => $chequeNumber,
            'currency' => 'EGP',
            'amount_minor' => 250000,
        ], $actorId);

        return $outgoingService->issue($draft->id, $fiscalYearId, $periodId, $date, $actorId);
    }

    /**
     * @return list<string>
     */
    private function runIncomingClearWorkers(
        int $workers,
        string $chequeId,
        string $fiscalYearId,
        string $periodId,
        string $date,
        string $bankAccountId,
        int $actorId,
        callable $keyFactory,
    ): array {
        $tasks = [];

        for ($i = 1; $i <= $workers; $i++) {
            $key = $keyFactory($i);
            $tasks[] = static fn (): string => (static function () use ($chequeId, $fiscalYearId, $periodId, $date, $bankAccountId, $actorId, $key): string {
                try {
                    app(IncomingChequeService::class)->clear($chequeId, $fiscalYearId, $periodId, $date, $bankAccountId, $actorId, $key);

                    return 'completed';
                } catch (DuplicateOperationInProgressException) {
                    return 'pending';
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
    private function runOutgoingClearWorkers(
        int $workers,
        string $chequeId,
        string $fiscalYearId,
        string $periodId,
        string $date,
        int $actorId,
        string $suffix,
    ): array {
        $tasks = [];

        for ($i = 1; $i <= $workers; $i++) {
            $key = "cheque-outgoing-clear-{$suffix}-{$i}";
            $tasks[] = static fn (): string => (static function () use ($chequeId, $fiscalYearId, $periodId, $date, $actorId, $key): string {
                try {
                    app(OutgoingChequeService::class)->clear($chequeId, $fiscalYearId, $periodId, $date, $actorId, $key);

                    return 'completed';
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
