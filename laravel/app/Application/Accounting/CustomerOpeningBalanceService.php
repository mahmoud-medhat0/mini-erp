<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ReceivableEntry;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CustomerOpeningBalanceService
{
    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    /**
     * @param  array{customer_id: string, fiscal_year_id: string, financial_period_id: string, entry_date: string, due_date?: string|null, reference?: string|null, description?: string|null, currency: string, amount_minor: int, fx_rate_e6?: int}  $data
     */
    public function create(array $data, int|string|null $actorId = null): CustomerOpeningBalance
    {
        $amountMinor = $this->positiveInteger($data['amount_minor'] ?? null, 'amount_minor');
        $fxRateE6 = $this->positiveInteger($data['fx_rate_e6'] ?? 1000000, 'fx_rate_e6');

        $this->validateData($data, $amountMinor, $fxRateE6);

        $cob = CustomerOpeningBalance::query()->create([
            'customer_id' => $data['customer_id'],
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
            entityType: 'customer_opening_balance',
            entityId: $cob->id,
            before: null,
            after: $cob->fresh()->toArray(),
        );

        return $cob;
    }

    public function cancel(string $id, int|string|null $actorId = null): CustomerOpeningBalance
    {
        /** @var CustomerOpeningBalance $cob */
        $cob = CustomerOpeningBalance::query()->findOrFail($id);

        if ($cob->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft opening balances can be cancelled.'],
            ]);
        }

        $before = $cob->toArray();
        $cob->update([
            'status' => 'cancelled',
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'cancel',
            entityType: 'customer_opening_balance',
            entityId: $id,
            before: $before,
            after: $cob->fresh()->toArray(),
        );

        return $cob;
    }

    public function post(string $id, int $actorId): CustomerOpeningBalance
    {
        $idempotencyKey = "customer_opening_balance:{$id}:post";

        $result = $this->idempotencyStore->run(
            operation: 'customer_opening_balance.post',
            rawKey: $idempotencyKey,
            callback: function () use ($id, $actorId): CustomerOpeningBalance {
                return DB::transaction(function () use ($id, $actorId): CustomerOpeningBalance {
                    // 1. Lock Opening Balance Row
                    /** @var CustomerOpeningBalance $cob */
                    $cob = CustomerOpeningBalance::query()
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($cob->status === 'posted') {
                        return $cob;
                    }

                    if ($cob->status !== 'draft') {
                        throw new InvalidArgumentException("Customer opening balance [{$id}] cannot be posted from status [{$cob->status}].");
                    }

                    // 2. Lock & Guard Financial Period Row
                    $period = $this->periodGuard->assertPeriodOpenForPostingWithLock(
                        (string) $cob->financial_period_id,
                        (string) $cob->entry_date
                    );

                    $this->assertPostingAmountAndFx((int) $cob->amount_minor, (int) $cob->fx_rate_e6);

                    // 3. Resolve GL Mappings
                    $arControl = $this->mappingService->getAccount('ar_control');
                    $offset = $this->mappingService->getAccount('opening_balance_offset');
                    $this->assertMappedAccountsMatchCurrency((string) $cob->currency, [
                        'ar_control' => $arControl,
                        'opening_balance_offset' => $offset,
                    ]);

                    // 4. Create Draft Approved Journal Entry
                    $journalEntry = JournalEntry::query()->create([
                        'source_type' => 'customer_opening_balance',
                        'source_id' => $cob->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $cob->entry_date,
                        'currency' => $cob->currency,
                        'fx_rate_e6' => $cob->fx_rate_e6,
                        'description' => $cob->description ?? "Customer Opening Balance for Customer {$cob->customer_id}",
                        'reference' => $cob->reference,
                        'status' => 'approved',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    // Dr AR Control
                    $drLine = JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $arControl->id,
                        'line_no' => 1,
                        'debit_minor' => $cob->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $cob->amount_minor,
                        'credit_txn_minor' => 0,
                        'currency' => $cob->currency,
                        'fx_rate_e6' => $cob->fx_rate_e6,
                        'memo' => 'AR Control - Opening Balance',
                    ]);

                    // Cr Opening Balance Offset
                    JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $offset->id,
                        'line_no' => 2,
                        'debit_minor' => 0,
                        'credit_minor' => $cob->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $cob->amount_minor,
                        'currency' => $cob->currency,
                        'fx_rate_e6' => $cob->fx_rate_e6,
                        'memo' => 'Opening Balance Offset',
                    ]);

                    // 5. Post Journal via PostingEngine
                    $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

                    // 6. Create Receivable Subledger Entry
                    $receivableEntry = ReceivableEntry::query()->create([
                        'customer_id' => $cob->customer_id,
                        'source_type' => 'customer_opening_balance',
                        'source_id' => $cob->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $drLine->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $cob->entry_date,
                        'due_date' => $cob->due_date,
                        'description' => $cob->description ?? 'Customer Opening Balance',
                        'currency' => $cob->currency,
                        'debit_minor' => $cob->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $cob->amount_minor,
                        'credit_txn_minor' => 0,
                        'fx_rate_e6' => $cob->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    // 7. Update CustomerOpeningBalance status
                    $before = $cob->toArray();
                    $cob->update([
                        'status' => 'posted',
                        'journal_entry_id' => $postedJournal->id,
                        'receivable_entry_id' => $receivableEntry->id,
                        'posted_by' => $actorId,
                        'posted_at' => now(),
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'post',
                        entityType: 'customer_opening_balance',
                        entityId: $cob->id,
                        before: $before,
                        after: $cob->fresh()->toArray(),
                    );

                    return $cob->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return CustomerOpeningBalance::query()->findOrFail($id);
        }

        /** @var CustomerOpeningBalance */
        return $result->value;
    }

    private function validateData(array $data, int $amountMinor, int $fxRateE6): void
    {
        $this->assertRequired($data, [
            'customer_id',
            'fiscal_year_id',
            'financial_period_id',
            'entry_date',
            'currency',
        ]);

        $this->assertPostingAmountAndFx($amountMinor, $fxRateE6);

        if (! Customer::query()->where('id', $data['customer_id'])->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => ["Customer [{$data['customer_id']}] does not exist."],
            ]);
        }

        if (! FiscalYear::query()->where('id', $data['fiscal_year_id'])->exists()) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => ["Fiscal year [{$data['fiscal_year_id']}] does not exist."],
            ]);
        }

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()->find($data['financial_period_id']);
        if (! $period) {
            throw ValidationException::withMessages([
                'financial_period_id' => ["Financial period [{$data['financial_period_id']}] does not exist."],
            ]);
        }

        if ((string) $period->fiscal_year_id !== (string) $data['fiscal_year_id']) {
            throw ValidationException::withMessages([
                'financial_period_id' => ['Financial period must belong to the selected fiscal year.'],
            ]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'financial_period_id' => ['Financial period is closed.'],
            ]);
        }

        JournalDraftService::assertDateInPeriod($period, $data['entry_date']);

        if (! Currency::query()->where('code', $data['currency'])->exists()) {
            throw ValidationException::withMessages([
                'currency' => ["Currency [{$data['currency']}] does not exist."],
            ]);
        }

        if (CustomerOpeningBalance::query()
            ->where('customer_id', $data['customer_id'])
            ->where('fiscal_year_id', $data['fiscal_year_id'])
            ->where('status', '!=', 'cancelled')
            ->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => ['Customer already has an active opening balance for this fiscal year.'],
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
                    $field => ["Field [{$field}] is required."],
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
                $field => ["Field [{$field}] must be a positive integer."],
            ]);
        }

        return $value;
    }

    private function assertPostingAmountAndFx(int $amountMinor, int $fxRateE6): void
    {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => ['Amount must be a positive minor integer.'],
            ]);
        }

        if ($fxRateE6 !== 1000000) {
            throw ValidationException::withMessages([
                'fx_rate_e6' => ['Slice 2 opening balances currently require 1:1 FX rate until exact FX posting is implemented.'],
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
                    'currency' => ["Mapped account [{$key}] currency must match opening balance currency [{$currency}]."],
                ]);
            }
        }
    }
}
