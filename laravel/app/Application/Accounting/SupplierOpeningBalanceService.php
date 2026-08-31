<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierOpeningBalance;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierOpeningBalanceService
{
    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    /**
     * @param  array{supplier_id: string, fiscal_year_id: string, financial_period_id: string, entry_date: string, due_date?: string|null, reference?: string|null, description?: string|null, currency: string, amount_minor: int, fx_rate_e6?: int}  $data
     */
    public function create(array $data, int|string|null $actorId = null): SupplierOpeningBalance
    {
        $amountMinor = $this->positiveInteger($data['amount_minor'] ?? null, 'amount_minor');
        $fxRateE6 = $this->positiveInteger($data['fx_rate_e6'] ?? 1000000, 'fx_rate_e6');

        $this->validateData($data, $amountMinor, $fxRateE6);

        $sob = SupplierOpeningBalance::query()->create([
            'supplier_id' => $data['supplier_id'],
            'fiscal_year_id' => $data['fiscal_year_id'],
            'financial_period_id' => $data['financial_period_id'],
            'entry_date' => $data['entry_date'],
            'due_date' => $data['due_date'] ?? null,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'currency' => $data['currency'],
            'amount_minor' => $amountMinor,
            'fx_rate_e6' => $fxRateE6,
            'status' => 'draft',
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 0,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'supplier_opening_balance',
            entityId: $sob->id,
            before: null,
            after: $sob->fresh()->toArray(),
        );

        return $sob;
    }

    public function cancel(string $id, int|string|null $actorId = null): SupplierOpeningBalance
    {
        /** @var SupplierOpeningBalance $sob */
        $sob = SupplierOpeningBalance::query()->findOrFail($id);

        if ($sob->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft opening balances can be cancelled.')],
            ]);
        }

        $before = $sob->toArray();
        $sob->update([
            'status' => 'cancelled',
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'cancel',
            entityType: 'supplier_opening_balance',
            entityId: $id,
            before: $before,
            after: $sob->fresh()->toArray(),
        );

        return $sob;
    }

    public function post(string $id, int $actorId): SupplierOpeningBalance
    {
        $idempotencyKey = "supplier_opening_balance:{$id}:post";

        $result = $this->idempotencyStore->run(
            operation: 'supplier_opening_balance.post',
            rawKey: $idempotencyKey,
            callback: function () use ($id, $actorId): SupplierOpeningBalance {
                return DB::transaction(function () use ($id, $actorId): SupplierOpeningBalance {
                    // 1. Lock Opening Balance Row
                    /** @var SupplierOpeningBalance $sob */
                    $sob = SupplierOpeningBalance::query()
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($sob->status === 'posted') {
                        return $sob;
                    }

                    if ($sob->status !== 'draft') {
                        throw new InvalidArgumentException(__('Supplier opening balance :id cannot be posted from status :status.', [
                            'id' => $id,
                            'status' => $sob->status,
                        ]));
                    }

                    // 2. Lock & Guard Financial Period Row
                    $period = $this->periodGuard->assertPeriodOpenForPostingWithLock(
                        (string) $sob->financial_period_id,
                        (string) $sob->entry_date
                    );

                    $this->assertPostingAmountAndFx((int) $sob->amount_minor, (int) $sob->fx_rate_e6);

                    // 3. Resolve GL Mappings
                    $apControl = $this->mappingService->getAccount('ap_control');
                    $offset = $this->mappingService->getAccount('opening_balance_offset');
                    $this->assertMappedAccountsMatchCurrency((string) $sob->currency, [
                        'ap_control' => $apControl,
                        'opening_balance_offset' => $offset,
                    ]);

                    // 4. Create Draft Approved Journal Entry
                    $journalEntry = JournalEntry::query()->create([
                        'source_type' => 'supplier_opening_balance',
                        'source_id' => $sob->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $sob->entry_date,
                        'currency' => $sob->currency,
                        'fx_rate_e6' => $sob->fx_rate_e6,
                        'description' => $sob->description ?? "Supplier Opening Balance for Supplier {$sob->supplier_id}",
                        'reference' => $sob->reference,
                        'status' => 'approved',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    // Dr Opening Balance Offset
                    JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $offset->id,
                        'line_no' => 1,
                        'debit_minor' => $sob->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $sob->amount_minor,
                        'credit_txn_minor' => 0,
                        'currency' => $sob->currency,
                        'fx_rate_e6' => $sob->fx_rate_e6,
                        'memo' => 'Opening Balance Offset',
                    ]);

                    // Cr AP Control
                    $crLine = JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $apControl->id,
                        'line_no' => 2,
                        'debit_minor' => 0,
                        'credit_minor' => $sob->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $sob->amount_minor,
                        'currency' => $sob->currency,
                        'fx_rate_e6' => $sob->fx_rate_e6,
                        'memo' => 'AP Control - Opening Balance',
                    ]);

                    // 5. Post Journal via PostingEngine
                    $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

                    // 6. Create Payable Subledger Entry
                    $payableEntry = PayableEntry::query()->create([
                        'supplier_id' => $sob->supplier_id,
                        'source_type' => 'supplier_opening_balance',
                        'source_id' => $sob->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $crLine->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $sob->entry_date,
                        'due_date' => $sob->due_date,
                        'description' => $sob->description ?? 'Supplier Opening Balance',
                        'currency' => $sob->currency,
                        'debit_minor' => 0,
                        'credit_minor' => $sob->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $sob->amount_minor,
                        'fx_rate_e6' => $sob->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    // 7. Update SupplierOpeningBalance status
                    $before = $sob->toArray();
                    $sob->update([
                        'status' => 'posted',
                        'journal_entry_id' => $postedJournal->id,
                        'payable_entry_id' => $payableEntry->id,
                        'posted_by' => $actorId,
                        'posted_at' => now(),
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'post',
                        entityType: 'supplier_opening_balance',
                        entityId: $sob->id,
                        before: $before,
                        after: $sob->fresh()->toArray(),
                    );

                    return $sob->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return SupplierOpeningBalance::query()->findOrFail($id);
        }

        /** @var SupplierOpeningBalance */
        return $result->value;
    }

    private function validateData(array $data, int $amountMinor, int $fxRateE6): void
    {
        $this->assertRequired($data, [
            'supplier_id',
            'fiscal_year_id',
            'financial_period_id',
            'entry_date',
            'currency',
        ]);

        $this->assertPostingAmountAndFx($amountMinor, $fxRateE6);

        if (! Supplier::query()->where('id', $data['supplier_id'])->exists()) {
            throw ValidationException::withMessages([
                'supplier_id' => [__('Supplier :supplier does not exist.', ['supplier' => $data['supplier_id']])],
            ]);
        }

        if (! FiscalYear::query()->where('id', $data['fiscal_year_id'])->exists()) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => [__('Fiscal year :year does not exist.', ['year' => $data['fiscal_year_id']])],
            ]);
        }

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()->find($data['financial_period_id']);
        if (! $period) {
            throw ValidationException::withMessages([
                'financial_period_id' => [__('Financial period :period does not exist.', ['period' => $data['financial_period_id']])],
            ]);
        }

        if ((string) $period->fiscal_year_id !== (string) $data['fiscal_year_id']) {
            throw ValidationException::withMessages([
                'financial_period_id' => [__('Financial period must belong to the selected fiscal year.')],
            ]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'financial_period_id' => [__('Financial period is closed.')],
            ]);
        }

        JournalDraftService::assertDateInPeriod($period, $data['entry_date']);

        if (! Currency::query()->where('code', $data['currency'])->exists()) {
            throw ValidationException::withMessages([
                'currency' => [__('Currency :currency does not exist.', ['currency' => $data['currency']])],
            ]);
        }

        if (SupplierOpeningBalance::query()
            ->where('supplier_id', $data['supplier_id'])
            ->where('fiscal_year_id', $data['fiscal_year_id'])
            ->where('status', '!=', 'cancelled')
            ->exists()) {
            throw ValidationException::withMessages([
                'supplier_id' => [__('Supplier already has an active opening balance for this fiscal year.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     */
    private function assertRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                throw ValidationException::withMessages([
                    $field => [__('Field :field is required.', ['field' => $field])],
                ]);
            }
        }
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value <= 0) {
            throw ValidationException::withMessages([
                $field => [__('Field :field must be a positive integer.', ['field' => $field])],
            ]);
        }

        return $value;
    }

    private function assertPostingAmountAndFx(int $amountMinor, int $fxRateE6): void
    {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => [__('Amount must be a positive minor integer.')],
            ]);
        }

        if ($fxRateE6 !== 1000000) {
            throw ValidationException::withMessages([
                'fx_rate_e6' => [__('Opening balances currently require 1:1 FX rate until exact FX posting is implemented.')],
            ]);
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     */
    private function assertMappedAccountsMatchCurrency(string $currency, array $accounts): void
    {
        foreach ($accounts as $key => $account) {
            if ($account->currency !== $currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Mapped account :key currency must match opening balance currency :currency.', [
                        'key' => $key,
                        'currency' => $currency,
                    ])],
                ]);
            }
        }
    }
}
