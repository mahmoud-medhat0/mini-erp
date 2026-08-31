<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ReceivableEntry;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CustomerReceiptService
{
    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly NumberSequenceAllocator $sequenceAllocator,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
    ) {}

    /**
     * @param  array{customer_id: string, fiscal_year_id: string, financial_period_id: string, receipt_date: string, reference?: string|null, description?: string|null, cash_account_id?: string|null, bank_account_id?: string|null, currency: string, amount_minor: int, fx_rate_e6?: int}  $data
     */
    public function create(array $data, int|string|null $actorId = null): CustomerReceipt
    {
        $amountMinor = $this->positiveInteger($data['amount_minor'] ?? null, 'amount_minor');
        $fxRateE6 = $this->positiveInteger($data['fx_rate_e6'] ?? 1000000, 'fx_rate_e6');

        $this->validateData($data, $amountMinor, $fxRateE6);

        $receipt = CustomerReceipt::query()->create([
            'customer_id' => $data['customer_id'],
            'fiscal_year_id' => $data['fiscal_year_id'],
            'financial_period_id' => $data['financial_period_id'],
            'receipt_date' => $data['receipt_date'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'currency' => $data['currency'],
            'amount_minor' => $amountMinor,
            'allocated_minor' => 0,
            'unapplied_minor' => $amountMinor,
            'fx_rate_e6' => $fxRateE6,
            'status' => 'draft',
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 0,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'customer_receipt',
            entityId: $receipt->id,
            before: null,
            after: $receipt->fresh()->toArray(),
        );

        return $receipt;
    }

    public function cancel(string $id, int|string|null $actorId = null): CustomerReceipt
    {
        /** @var CustomerReceipt $receipt */
        $receipt = CustomerReceipt::query()->findOrFail($id);

        if ($receipt->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft receipts can be cancelled.')],
            ]);
        }

        $before = $receipt->toArray();
        $receipt->update([
            'status' => 'cancelled',
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'cancel',
            entityType: 'customer_receipt',
            entityId: $id,
            before: $before,
            after: $receipt->fresh()->toArray(),
        );

        return $receipt;
    }

    public function post(string $id, int $actorId): CustomerReceipt
    {
        $idempotencyKey = "customer_receipt:{$id}:post";

        $result = $this->idempotencyStore->run(
            operation: 'customer_receipt.post',
            rawKey: $idempotencyKey,
            callback: function () use ($id, $actorId): CustomerReceipt {
                return DB::transaction(function () use ($id, $actorId): CustomerReceipt {
                    // 1. Lock Customer Receipt Row
                    /** @var CustomerReceipt $receipt */
                    $receipt = CustomerReceipt::query()
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($receipt->status === 'posted') {
                        return $receipt;
                    }

                    if ($receipt->status !== 'draft') {
                        throw new InvalidArgumentException(__('Customer receipt :id cannot be posted from status :status.', [
                            'id' => $id,
                            'status' => $receipt->status,
                        ]));
                    }

                    // 2. Lock & Guard Financial Period Row
                    $period = $this->periodGuard->assertPeriodOpenForPostingWithLock(
                        (string) $receipt->financial_period_id,
                        (string) $receipt->receipt_date
                    );
                    $this->assertPostingAmountAndFx((int) $receipt->amount_minor, (int) $receipt->fx_rate_e6);

                    // 3. Resolve & Lock Cash/Bank Account GL target
                    $cashOrBankGlAccountId = $this->resolveAndLockCashOrBankGlAccount($receipt);

                    // 4. Resolve Trusted AR Control Mapping
                    $arControl = $this->mappingService->getAccount('ar_control');

                    if ($arControl->currency !== $receipt->currency) {
                        throw ValidationException::withMessages([
                            'currency' => [__('Mapped AR Control account currency :accountCurrency must match receipt currency :currency.', [
                                'accountCurrency' => $arControl->currency,
                                'currency' => $receipt->currency,
                            ])],
                        ]);
                    }

                    // 5. Allocate Receipt Number if missing
                    $number = $receipt->number;
                    if (empty($number)) {
                        $number = $this->sequenceAllocator->nextNumber('customer.receipt', 'REC', $receipt->receipt_date);
                    }

                    // 6. Create Approved Journal Entry
                    $journalEntry = JournalEntry::query()->create([
                        'source_type' => 'customer_receipt',
                        'source_id' => $receipt->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $receipt->receipt_date,
                        'currency' => $receipt->currency,
                        'fx_rate_e6' => $receipt->fx_rate_e6,
                        'description' => $receipt->description ?? "Customer Receipt {$number}",
                        'reference' => $receipt->reference,
                        'status' => 'approved',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    // Dr Cash/Bank GL Account
                    JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $cashOrBankGlAccountId,
                        'line_no' => 1,
                        'debit_minor' => $receipt->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $receipt->amount_minor,
                        'credit_txn_minor' => 0,
                        'currency' => $receipt->currency,
                        'fx_rate_e6' => $receipt->fx_rate_e6,
                        'memo' => 'Customer Receipt - Cash/Bank Deposit',
                    ]);

                    // Cr AR Control Account
                    $crLine = JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $arControl->id,
                        'line_no' => 2,
                        'debit_minor' => 0,
                        'credit_minor' => $receipt->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $receipt->amount_minor,
                        'currency' => $receipt->currency,
                        'fx_rate_e6' => $receipt->fx_rate_e6,
                        'memo' => 'AR Control - Customer Receipt',
                    ]);

                    // 7. Post Journal via PostingEngine
                    $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

                    // 8. Create Receivable Subledger Entry (Credit)
                    $receivableEntry = ReceivableEntry::query()->create([
                        'customer_id' => $receipt->customer_id,
                        'source_type' => 'customer_receipt',
                        'source_id' => $receipt->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $crLine->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $receipt->receipt_date,
                        'description' => $receipt->description ?? "Customer Receipt {$number}",
                        'currency' => $receipt->currency,
                        'debit_minor' => 0,
                        'credit_minor' => $receipt->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $receipt->amount_minor,
                        'fx_rate_e6' => $receipt->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    // 9. Update CustomerReceipt
                    $before = $receipt->toArray();
                    $receipt->update([
                        'number' => $number,
                        'status' => 'posted',
                        'journal_entry_id' => $postedJournal->id,
                        'receivable_entry_id' => $receivableEntry->id,
                        'allocated_minor' => 0,
                        'unapplied_minor' => $receipt->amount_minor,
                        'posted_by' => $actorId,
                        'posted_at' => now(),
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'post',
                        entityType: 'customer_receipt',
                        entityId: $receipt->id,
                        before: $before,
                        after: $receipt->fresh()->toArray(),
                    );

                    return $receipt->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return CustomerReceipt::query()->findOrFail($id);
        }

        /** @var CustomerReceipt */
        return $result->value;
    }

    private function resolveAndLockCashOrBankGlAccount(CustomerReceipt $receipt): string
    {
        if ($receipt->cash_account_id) {
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()
                ->where('id', $receipt->cash_account_id)
                ->lockForUpdate()
                ->first();

            if (! $cashAccount || ! $cashAccount->is_active) {
                throw ValidationException::withMessages([
                    'cash_account_id' => [__('Selected Cash Account is missing or inactive.')],
                ]);
            }

            if ($cashAccount->currency !== $receipt->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Cash account currency :accountCurrency must match receipt currency :currency.', [
                        'accountCurrency' => $cashAccount->currency,
                        'currency' => $receipt->currency,
                    ])],
                ]);
            }

            /** @var Account|null $glAccount */
            $glAccount = Account::query()->where('id', $cashAccount->gl_account_id)->lockForUpdate()->first();
            if (! $glAccount || ! $glAccount->is_active) {
                throw ValidationException::withMessages([
                    'cash_account_id' => [__('Linked GL Account for Cash Account is missing or inactive.')],
                ]);
            }

            if ($glAccount->currency !== $receipt->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Linked GL Account currency :accountCurrency must match receipt currency :currency.', [
                        'accountCurrency' => $glAccount->currency,
                        'currency' => $receipt->currency,
                    ])],
                ]);
            }

            return $glAccount->id;
        }

        if ($receipt->bank_account_id) {
            /** @var BankAccount|null $bankAccount */
            $bankAccount = BankAccount::query()
                ->where('id', $receipt->bank_account_id)
                ->lockForUpdate()
                ->first();

            if (! $bankAccount || ! $bankAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => [__('Selected Bank Account is missing or inactive.')],
                ]);
            }

            if ($bankAccount->currency !== $receipt->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Bank account currency :accountCurrency must match receipt currency :currency.', [
                        'accountCurrency' => $bankAccount->currency,
                        'currency' => $receipt->currency,
                    ])],
                ]);
            }

            /** @var Account|null $glAccount */
            $glAccount = Account::query()->where('id', $bankAccount->gl_account_id)->lockForUpdate()->first();
            if (! $glAccount || ! $glAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => [__('Linked GL Account for Bank Account is missing or inactive.')],
                ]);
            }

            if ($glAccount->currency !== $receipt->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Linked GL Account currency :accountCurrency must match receipt currency :currency.', [
                        'accountCurrency' => $glAccount->currency,
                        'currency' => $receipt->currency,
                    ])],
                ]);
            }

            return $glAccount->id;
        }

        throw ValidationException::withMessages([
            'cash_account_id' => [__('Receipt requires exactly one of Cash Account or Bank Account.')],
        ]);
    }

    private function validateData(array $data, int $amountMinor, int $fxRateE6): void
    {
        $this->assertRequired($data, [
            'customer_id',
            'fiscal_year_id',
            'financial_period_id',
            'receipt_date',
            'currency',
        ]);

        $this->assertPostingAmountAndFx($amountMinor, $fxRateE6);

        $hasCash = ! empty($data['cash_account_id']);
        $hasBank = ! empty($data['bank_account_id']);

        if (($hasCash && $hasBank) || (! $hasCash && ! $hasBank)) {
            throw ValidationException::withMessages([
                'cash_account_id' => [__('Receipt requires exactly one of cash_account_id or bank_account_id.')],
            ]);
        }

        if (! Customer::query()->where('id', $data['customer_id'])->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => [__('Customer :customer does not exist.', ['customer' => $data['customer_id']])],
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

        JournalDraftService::assertDateInPeriod($period, $data['receipt_date']);

        if (! Currency::query()->where('code', $data['currency'])->exists()) {
            throw ValidationException::withMessages([
                'currency' => [__('Currency :currency does not exist.', ['currency' => $data['currency']])],
            ]);
        }

        if ($hasCash) {
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()->find($data['cash_account_id']);
            if (! $cashAccount || ! $cashAccount->is_active) {
                throw ValidationException::withMessages([
                    'cash_account_id' => [__('Selected Cash Account does not exist or is inactive.')],
                ]);
            }
            if ($cashAccount->currency !== $data['currency']) {
                throw ValidationException::withMessages([
                    'currency' => [__('Cash account currency :accountCurrency must match receipt currency :currency.', [
                        'accountCurrency' => $cashAccount->currency,
                        'currency' => $data['currency'],
                    ])],
                ]);
            }
        }

        if ($hasBank) {
            /** @var BankAccount|null $bankAccount */
            $bankAccount = BankAccount::query()->find($data['bank_account_id']);
            if (! $bankAccount || ! $bankAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => [__('Selected Bank Account does not exist or is inactive.')],
                ]);
            }
            if ($bankAccount->currency !== $data['currency']) {
                throw ValidationException::withMessages([
                    'currency' => [__('Bank account currency :accountCurrency must match receipt currency :currency.', [
                        'accountCurrency' => $bankAccount->currency,
                        'currency' => $data['currency'],
                    ])],
                ]);
            }
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
                'fx_rate_e6' => [__('Receipts currently require 1:1 FX rate until exact integer FX posting is implemented.')],
            ]);
        }
    }
}
