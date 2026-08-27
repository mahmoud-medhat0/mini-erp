<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierPaymentService
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
     * @param  array{supplier_id: string, fiscal_year_id: string, financial_period_id: string, payment_date: string, reference?: string|null, description?: string|null, cash_account_id?: string|null, bank_account_id?: string|null, currency: string, amount_minor: int, fx_rate_e6?: int}  $data
     */
    public function create(array $data, int|string|null $actorId = null): SupplierPayment
    {
        $amountMinor = $this->positiveInteger($data['amount_minor'] ?? null, 'amount_minor');
        $fxRateE6 = $this->positiveInteger($data['fx_rate_e6'] ?? 1000000, 'fx_rate_e6');

        $this->validateData($data, $amountMinor, $fxRateE6);

        $payment = SupplierPayment::query()->create([
            'supplier_id' => $data['supplier_id'],
            'fiscal_year_id' => $data['fiscal_year_id'],
            'financial_period_id' => $data['financial_period_id'],
            'payment_date' => $data['payment_date'],
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
            entityType: 'supplier_payment',
            entityId: $payment->id,
            before: null,
            after: $payment->fresh()->toArray(),
        );

        return $payment;
    }

    public function cancel(string $id, int|string|null $actorId = null): SupplierPayment
    {
        /** @var SupplierPayment $payment */
        $payment = SupplierPayment::query()->findOrFail($id);

        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft payments can be cancelled.')],
            ]);
        }

        $before = $payment->toArray();
        $payment->update([
            'status' => 'cancelled',
            'updated_by' => $actorId,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'cancel',
            entityType: 'supplier_payment',
            entityId: $id,
            before: $before,
            after: $payment->fresh()->toArray(),
        );

        return $payment;
    }

    public function post(string $id, int $actorId): SupplierPayment
    {
        $idempotencyKey = "supplier_payment:{$id}:post";

        $result = $this->idempotencyStore->run(
            operation: 'supplier_payment.post',
            rawKey: $idempotencyKey,
            callback: function () use ($id, $actorId): SupplierPayment {
                return DB::transaction(function () use ($id, $actorId): SupplierPayment {
                    // 1. Lock Supplier Payment Row
                    /** @var SupplierPayment $payment */
                    $payment = SupplierPayment::query()
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($payment->status === 'posted') {
                        return $payment;
                    }

                    if ($payment->status !== 'draft') {
                        throw new InvalidArgumentException(__('Supplier payment :id cannot be posted from status :status.', [
                            'id' => $id,
                            'status' => $payment->status,
                        ]));
                    }

                    // 2. Lock & Guard Financial Period Row
                    $period = $this->periodGuard->assertPeriodOpenForPostingWithLock(
                        (string) $payment->financial_period_id,
                        (string) $payment->payment_date
                    );
                    $this->assertPostingAmountAndFx((int) $payment->amount_minor, (int) $payment->fx_rate_e6);

                    // 3. Resolve & Lock Cash/Bank Account GL target
                    $cashOrBankGlAccountId = $this->resolveAndLockCashOrBankGlAccount($payment);

                    // 4. Resolve Trusted AP Control Mapping
                    $apControl = $this->mappingService->getAccount('ap_control');

                    if ($apControl->currency !== $payment->currency) {
                        throw ValidationException::withMessages([
                            'currency' => [__('Mapped AP Control account currency :accountCurrency must match payment currency :currency.', [
                                'accountCurrency' => $apControl->currency,
                                'currency' => $payment->currency,
                            ])],
                        ]);
                    }

                    // 5. Allocate Payment Number if missing
                    $number = $payment->number;
                    if (empty($number)) {
                        $seq = $this->sequenceAllocator->nextValue('supplier.payment');
                        $year = Carbon::parse($payment->payment_date)->format('Y');
                        $number = 'PAY-'.$year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
                    }

                    // 6. Create Approved Journal Entry
                    $journalEntry = JournalEntry::query()->create([
                        'source_type' => 'supplier_payment',
                        'source_id' => $payment->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $payment->payment_date,
                        'currency' => $payment->currency,
                        'fx_rate_e6' => $payment->fx_rate_e6,
                        'description' => $payment->description ?? "Supplier Payment {$number}",
                        'reference' => $payment->reference,
                        'status' => 'approved',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);

                    // Dr AP Control Account
                    $drLine = JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $apControl->id,
                        'line_no' => 1,
                        'debit_minor' => $payment->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $payment->amount_minor,
                        'credit_txn_minor' => 0,
                        'currency' => $payment->currency,
                        'fx_rate_e6' => $payment->fx_rate_e6,
                        'memo' => 'AP Control - Supplier Payment',
                    ]);

                    // Cr Cash/Bank GL Account
                    JournalLine::query()->create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $cashOrBankGlAccountId,
                        'line_no' => 2,
                        'debit_minor' => 0,
                        'credit_minor' => $payment->amount_minor,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $payment->amount_minor,
                        'currency' => $payment->currency,
                        'fx_rate_e6' => $payment->fx_rate_e6,
                        'memo' => 'Supplier Payment - Cash/Bank Disbursement',
                    ]);

                    // 7. Post Journal via PostingEngine
                    $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

                    // 8. Create Payable Subledger Entry (Debit)
                    $payableEntry = PayableEntry::query()->create([
                        'supplier_id' => $payment->supplier_id,
                        'source_type' => 'supplier_payment',
                        'source_id' => $payment->id,
                        'journal_entry_id' => $postedJournal->id,
                        'journal_line_id' => $drLine->id,
                        'financial_period_id' => $period->id,
                        'entry_date' => $payment->payment_date,
                        'description' => $payment->description ?? "Supplier Payment {$number}",
                        'currency' => $payment->currency,
                        'debit_minor' => $payment->amount_minor,
                        'credit_minor' => 0,
                        'debit_txn_minor' => $payment->amount_minor,
                        'credit_txn_minor' => 0,
                        'fx_rate_e6' => $payment->fx_rate_e6,
                        'created_by' => $actorId,
                    ]);

                    // 9. Update SupplierPayment
                    $before = $payment->toArray();
                    $payment->update([
                        'number' => $number,
                        'status' => 'posted',
                        'journal_entry_id' => $postedJournal->id,
                        'payable_entry_id' => $payableEntry->id,
                        'allocated_minor' => 0,
                        'unapplied_minor' => $payment->amount_minor,
                        'posted_by' => $actorId,
                        'posted_at' => now(),
                        'updated_by' => $actorId,
                    ]);

                    $this->auditLogger->record(
                        actorId: $actorId,
                        action: 'post',
                        entityType: 'supplier_payment',
                        entityId: $payment->id,
                        before: $before,
                        after: $payment->fresh()->toArray(),
                    );

                    return $payment->fresh();
                });
            }
        );

        if (is_array($result->value)) {
            return SupplierPayment::query()->findOrFail($id);
        }

        /** @var SupplierPayment */
        return $result->value;
    }

    private function resolveAndLockCashOrBankGlAccount(SupplierPayment $payment): string
    {
        if ($payment->cash_account_id) {
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()
                ->where('id', $payment->cash_account_id)
                ->lockForUpdate()
                ->first();

            if (! $cashAccount || ! $cashAccount->is_active) {
                throw ValidationException::withMessages([
                    'cash_account_id' => [__('Selected Cash Account is missing or inactive.')],
                ]);
            }

            if ($cashAccount->currency !== $payment->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Cash account currency :accountCurrency must match payment currency :currency.', [
                        'accountCurrency' => $cashAccount->currency,
                        'currency' => $payment->currency,
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

            if ($glAccount->currency !== $payment->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Linked GL Account currency :accountCurrency must match payment currency :currency.', [
                        'accountCurrency' => $glAccount->currency,
                        'currency' => $payment->currency,
                    ])],
                ]);
            }

            return $glAccount->id;
        }

        if ($payment->bank_account_id) {
            /** @var BankAccount|null $bankAccount */
            $bankAccount = BankAccount::query()
                ->where('id', $payment->bank_account_id)
                ->lockForUpdate()
                ->first();

            if (! $bankAccount || ! $bankAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => [__('Selected Bank Account is missing or inactive.')],
                ]);
            }

            if ($bankAccount->currency !== $payment->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Bank account currency :accountCurrency must match payment currency :currency.', [
                        'accountCurrency' => $bankAccount->currency,
                        'currency' => $payment->currency,
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

            if ($glAccount->currency !== $payment->currency) {
                throw ValidationException::withMessages([
                    'currency' => [__('Linked GL Account currency :accountCurrency must match payment currency :currency.', [
                        'accountCurrency' => $glAccount->currency,
                        'currency' => $payment->currency,
                    ])],
                ]);
            }

            return $glAccount->id;
        }

        throw ValidationException::withMessages([
            'cash_account_id' => [__('Payment requires exactly one of Cash Account or Bank Account.')],
        ]);
    }

    private function validateData(array $data, int $amountMinor, int $fxRateE6): void
    {
        $this->assertRequired($data, [
            'supplier_id',
            'fiscal_year_id',
            'financial_period_id',
            'payment_date',
            'currency',
        ]);

        $this->assertPostingAmountAndFx($amountMinor, $fxRateE6);

        $hasCash = ! empty($data['cash_account_id']);
        $hasBank = ! empty($data['bank_account_id']);

        if (($hasCash && $hasBank) || (! $hasCash && ! $hasBank)) {
            throw ValidationException::withMessages([
                'cash_account_id' => [__('Payment requires exactly one of cash_account_id or bank_account_id.')],
            ]);
        }

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

        JournalDraftService::assertDateInPeriod($period, $data['payment_date']);

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
                    'currency' => [__('Cash account currency :accountCurrency must match payment currency :currency.', [
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
                    'currency' => [__('Bank account currency :accountCurrency must match payment currency :currency.', [
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
                'fx_rate_e6' => [__('Payments currently require 1:1 FX rate until exact integer FX posting is implemented.')],
            ]);
        }
    }
}
