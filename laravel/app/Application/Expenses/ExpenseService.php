<?php

namespace App\Application\Expenses;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Support\CurrencyInput;
use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxPeriodGuard;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    private const QUANTITY_SCALE = 1000000;

    public const ALLOWED_STATUSES = ['draft', 'submitted', 'approved', 'posted', 'cancelled'];

    public const SETTLEMENT_METHODS = ['payable', 'cash', 'bank'];

    public function __construct(
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly AuditLogger $auditLogger,
        private readonly PeriodGuard $periodGuard,
        private readonly TaxCalculationService $taxCalculationService,
        private readonly TaxPeriodGuard $taxPeriodGuard,
    ) {}

    public function create(array $data, ?int $actorId = null): Expense
    {
        return DB::transaction(function () use ($data, $actorId): Expense {
            $expenseDate = (string) ($data['expense_date'] ?? '');
            if ($expenseDate === '') {
                throw ValidationException::withMessages(['expense_date' => [__('Expense date is required.')]]);
            }

            $period = $this->resolveOpenPeriodForDate($expenseDate);
            $currency = $this->assertCurrency(CurrencyInput::required($data['currency'] ?? null));
            $fxRateE6 = (int) ($data['fx_rate_e6'] ?? 1000000);
            if ($fxRateE6 !== 1000000) {
                throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
            }

            $branchId = $this->normalizeBranchId($data['branch_id'] ?? null);
            $this->assertBranch($branchId);
            $settlement = $this->validateSettlement($data, $currency, $branchId);
            $lines = $this->validateAndCalculateLines($data['lines'] ?? [], $expenseDate, $currency);

            /** @var Expense $expense */
            $expense = Expense::query()->create([
                'expense_date' => $expenseDate,
                'due_date' => $data['due_date'] ?? null,
                'branch_id' => $branchId,
                'supplier_id' => $settlement['supplier_id'],
                'payee_name' => $settlement['payee_name'],
                'settlement_method' => $settlement['settlement_method'],
                'cash_account_id' => $settlement['cash_account_id'],
                'bank_account_id' => $settlement['bank_account_id'],
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'currency' => $currency,
                'fx_rate_e6' => $fxRateE6,
                'subtotal_minor' => array_sum(array_column($lines, 'line_total_minor')),
                'tax_amount_minor' => array_sum(array_column($lines, 'tax_amount_minor')),
                'total_minor' => array_sum(array_column($lines, 'gross_amount_minor')),
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->syncLines($expense, $lines);
            $expense->load($this->defaultRelations());

            $this->auditLogger->record($actorId, 'expense.create', 'expense', $expense->id, after: $expense->toArray());

            return $expense;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): Expense
    {
        return DB::transaction(function () use ($id, $data, $actorId): Expense {
            /** @var Expense $expense */
            $expense = Expense::query()->with('lines')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($expense->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft expenses can be updated.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $expense->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The record has been modified by another user. Please refresh and try again.')]]);
            }

            $expenseDate = (string) ($data['expense_date'] ?? $expense->expense_date?->format('Y-m-d'));
            $period = $this->resolveOpenPeriodForDate($expenseDate);
            $currency = $this->assertCurrency($data['currency'] ?? $expense->currency);
            $fxRateE6 = (int) ($data['fx_rate_e6'] ?? $expense->fx_rate_e6);
            if ($fxRateE6 !== 1000000) {
                throw ValidationException::withMessages(['fx_rate_e6' => [__('FX rate must be 1.000000 (1000000) in this slice.')]]);
            }

            $branchId = array_key_exists('branch_id', $data)
                ? $this->normalizeBranchId($data['branch_id'])
                : ($expense->branch_id ? (string) $expense->branch_id : null);
            $this->assertBranch($branchId);

            $settlementData = [
                'settlement_method' => $data['settlement_method'] ?? $expense->settlement_method,
                'supplier_id' => $data['supplier_id'] ?? $expense->supplier_id,
                'cash_account_id' => $data['cash_account_id'] ?? $expense->cash_account_id,
                'bank_account_id' => $data['bank_account_id'] ?? $expense->bank_account_id,
                'payee_name' => $data['payee_name'] ?? $expense->payee_name,
            ];
            $settlement = $this->validateSettlement($settlementData, $currency, $branchId);
            $lines = $this->validateAndCalculateLines($data['lines'] ?? [], $expenseDate, $currency);
            $before = $expense->toArray();

            $expense->update([
                'expense_date' => $expenseDate,
                'due_date' => $data['due_date'] ?? $expense->due_date,
                'branch_id' => $branchId,
                'supplier_id' => $settlement['supplier_id'],
                'payee_name' => $settlement['payee_name'],
                'settlement_method' => $settlement['settlement_method'],
                'cash_account_id' => $settlement['cash_account_id'],
                'bank_account_id' => $settlement['bank_account_id'],
                'fiscal_year_id' => $period->fiscal_year_id,
                'financial_period_id' => $period->id,
                'currency' => $currency,
                'fx_rate_e6' => $fxRateE6,
                'subtotal_minor' => array_sum(array_column($lines, 'line_total_minor')),
                'tax_amount_minor' => array_sum(array_column($lines, 'tax_amount_minor')),
                'total_minor' => array_sum(array_column($lines, 'gross_amount_minor')),
                'reference' => $data['reference'] ?? $expense->reference,
                'description' => $data['description'] ?? $expense->description,
                'updated_by' => $actorId,
                'lock_version' => $expense->lock_version + 1,
            ]);

            $expense->lines()->delete();
            $this->syncLines($expense, $lines);

            $expense->load($this->defaultRelations());
            $this->auditLogger->record($actorId, 'expense.update', 'expense', $expense->id, before: $before, after: $expense->toArray());

            return $expense;
        });
    }

    public function submit(string $id, ?int $actorId = null): Expense
    {
        return $this->transition($id, 'draft', 'submitted', 'submitted_by', 'submitted_at', 'expense.submit', $actorId);
    }

    public function approve(string $id, ?int $actorId = null): Expense
    {
        return $this->transition($id, 'submitted', 'approved', 'approved_by', 'approved_at', 'expense.approve', $actorId);
    }

    public function post(string $id, ?int $actorId = null): Expense
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Posting an expense requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($id, $actorId): Expense {
            /** @var Expense $expense */
            $expense = Expense::query()
                ->with(['lines.category', 'lines.expenseAccount', 'supplier', 'cashAccount.glAccount', 'bankAccount.glAccount'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expense->status === 'posted') {
                return $expense->fresh($this->defaultRelations());
            }

            if ($expense->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved expenses can be posted.')]]);
            }

            if ($expense->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Cannot post an expense without lines.')]]);
            }

            if ((int) $expense->tax_amount_minor > 0) {
                $this->taxPeriodGuard->ensureDateNotFiled((string) $expense->expense_date->format('Y-m-d'));
            }

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock((string) $expense->financial_period_id, (string) $expense->expense_date->format('Y-m-d'));
            if ($period->fiscal_year_id !== $expense->fiscal_year_id) {
                throw ValidationException::withMessages(['financial_period_id' => [__('Financial period does not belong to the expense fiscal year.')]]);
            }

            $this->assertRequiredAttachments($expense);

            $number = $expense->number;
            if (! $number) {
                $year = Carbon::parse($expense->expense_date)->format('Y');
                $sequence = $this->numberAllocator->nextValue('expenses.expense');
                $number = 'EXP-'.$year.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            }

            $creditAccount = $this->resolveSettlementCreditAccount($expense);
            $this->assertAccountCurrency($creditAccount, $expense->currency, 'Settlement account');

            $inputTaxAccount = null;
            if ((int) $expense->tax_amount_minor > 0) {
                $inputTaxAccount = $this->mappingService->getAccount('input_tax_receivable', $expense->branch_id ? (string) $expense->branch_id : null);
                $this->assertAccountCurrency($inputTaxAccount, $expense->currency, 'Input Tax Receivable account');
            }

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $expense->expense_date,
                'financial_period_id' => $expense->financial_period_id,
                'branch_id' => $expense->branch_id,
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'description' => "Expense {$number}",
                'currency' => $expense->currency,
                'fx_rate_e6' => $expense->fx_rate_e6,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $lineNo = 1;
            foreach ($this->expenseLineTotalsByAccount($expense) as $accountId => $amountMinor) {
                /** @var Account $account */
                $account = Account::query()->findOrFail($accountId);
                $this->assertAccountCurrency($account, $expense->currency, 'Expense line account');

                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $account->id,
                    'branch_id' => $expense->branch_id,
                    'memo' => "Expense {$number}",
                    'debit_minor' => $amountMinor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $amountMinor,
                    'credit_txn_minor' => 0,
                    'currency' => $expense->currency,
                    'fx_rate_e6' => $expense->fx_rate_e6,
                ]);
            }

            if ($inputTaxAccount !== null) {
                $journalEntry->lines()->create([
                    'line_no' => $lineNo++,
                    'account_id' => $inputTaxAccount->id,
                    'branch_id' => $expense->branch_id,
                    'memo' => "Input Tax - Expense {$number}",
                    'debit_minor' => $expense->tax_amount_minor,
                    'credit_minor' => 0,
                    'debit_txn_minor' => $expense->tax_amount_minor,
                    'credit_txn_minor' => 0,
                    'currency' => $expense->currency,
                    'fx_rate_e6' => $expense->fx_rate_e6,
                ]);
            }

            $creditLine = $journalEntry->lines()->create([
                'line_no' => $lineNo++,
                'account_id' => $creditAccount->id,
                'branch_id' => $expense->branch_id,
                'memo' => "Settlement - Expense {$number}",
                'debit_minor' => 0,
                'credit_minor' => $expense->total_minor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $expense->total_minor,
                'currency' => $expense->currency,
                'fx_rate_e6' => $expense->fx_rate_e6,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);
            $payableEntry = null;

            if ($expense->settlement_method === 'payable') {
                /** @var PayableEntry $payableEntry */
                $payableEntry = PayableEntry::query()->create([
                    'supplier_id' => $expense->supplier_id,
                    'source_type' => 'expense',
                    'source_id' => $expense->id,
                    'journal_entry_id' => $postedJournal->id,
                    'journal_line_id' => $creditLine->id,
                    'financial_period_id' => $expense->financial_period_id,
                    'entry_date' => $expense->expense_date,
                    'due_date' => $expense->due_date ?? $expense->expense_date,
                    'description' => "Expense {$number}",
                    'currency' => $expense->currency,
                    'debit_minor' => 0,
                    'credit_minor' => $expense->total_minor,
                    'debit_txn_minor' => 0,
                    'credit_txn_minor' => $expense->total_minor,
                    'fx_rate_e6' => $expense->fx_rate_e6,
                    'created_by' => $actorId,
                ]);
            }

            $before = $expense->toArray();
            $expense->update([
                'number' => $number,
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'payable_entry_id' => $payableEntry?->id,
                'posted_by' => $actorId,
                'posted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $expense->lock_version + 1,
            ]);

            $expense->load($this->defaultRelations());
            $this->auditLogger->record($actorId, 'expense.post', 'expense', $expense->id, before: $before, after: $expense->toArray());

            return $expense;
        });
    }

    public function cancel(string $id, ?int $actorId = null): Expense
    {
        return DB::transaction(function () use ($id, $actorId): Expense {
            /** @var Expense $expense */
            $expense = Expense::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if (! in_array($expense->status, ['draft', 'submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only unposted expenses can be cancelled.')]]);
            }

            $before = $expense->toArray();
            $expense->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $expense->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'expense.cancel', 'expense', $expense->id, before: $before, after: $expense->fresh()->toArray());

            return $expense->fresh($this->defaultRelations());
        });
    }

    private function transition(
        string $id,
        string $from,
        string $to,
        string $actorColumn,
        string $timestampColumn,
        string $auditAction,
        ?int $actorId,
    ): Expense {
        return DB::transaction(function () use ($id, $from, $to, $actorColumn, $timestampColumn, $auditAction, $actorId): Expense {
            /** @var Expense $expense */
            $expense = Expense::query()->with('lines')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($expense->status === $to) {
                return $expense->fresh($this->defaultRelations());
            }

            if ($expense->status !== $from) {
                throw ValidationException::withMessages(['status' => [__('Expense must be :from before it can move to :to.', [
                    'from' => $from,
                    'to' => $to,
                ])]]);
            }

            if ($expense->lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => [__('Expense requires at least one line.')]]);
            }

            $before = $expense->toArray();
            $expense->update([
                'status' => $to,
                $actorColumn => $actorId,
                $timestampColumn => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $expense->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, $auditAction, 'expense', $expense->id, before: $before, after: $expense->fresh()->toArray());

            return $expense->fresh($this->defaultRelations());
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validateAndCalculateLines(array $lines, string $expenseDate, string $currency): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => [__('At least one expense line is required.')]]);
        }

        $validated = [];

        foreach ($lines as $index => $line) {
            $lineIndex = $index + 1;
            $categoryId = $line['expense_category_id'] ?? null;
            if (! $categoryId) {
                throw ValidationException::withMessages(["lines.{$index}.expense_category_id" => [__('Line :line category is required.', ['line' => $lineIndex])]]);
            }

            /** @var ExpenseCategory|null $category */
            $category = ExpenseCategory::query()->with(['defaultExpenseAccount', 'defaultTaxCode'])->where('id', $categoryId)->first();
            if (! $category || ! $category->is_active) {
                throw ValidationException::withMessages(["lines.{$index}.expense_category_id" => [__('Line :line category must be active.', ['line' => $lineIndex])]]);
            }

            $accountId = $line['expense_account_id'] ?? $category->default_expense_account_id;
            if (! $accountId) {
                throw ValidationException::withMessages(["lines.{$index}.expense_account_id" => [__('Line :line expense account is required.', ['line' => $lineIndex])]]);
            }

            /** @var Account|null $account */
            $account = Account::query()->where('id', $accountId)->first();
            if (! $account || ! $account->is_active || $account->type !== 'expense' || $account->nature !== 'debit' || $account->is_control) {
                throw ValidationException::withMessages(["lines.{$index}.expense_account_id" => [__('Line :line account must be an active debit expense account and not a control account.', ['line' => $lineIndex])]]);
            }
            $this->assertAccountCurrency($account, $currency, "Line {$lineIndex} expense account");

            $quantityE6 = (int) ($line['quantity_e6'] ?? self::QUANTITY_SCALE);
            if ($quantityE6 <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity_e6" => [__('Line :line quantity must be greater than zero.', ['line' => $lineIndex])]]);
            }

            $unitAmountMinor = (int) ($line['unit_amount_minor'] ?? 0);
            if ($unitAmountMinor <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_amount_minor" => [__('Line :line unit amount must be greater than zero.', ['line' => $lineIndex])]]);
            }

            $lineTotalMinor = $this->calculateLineTotalMinor($quantityE6, $unitAmountMinor, $lineIndex);
            $taxCodeId = $line['tax_code_id'] ?? $category->default_tax_code_id;
            $taxRateBps = 0;
            $taxAmountMinor = 0;
            $grossAmountMinor = $lineTotalMinor;

            if ($taxCodeId) {
                $taxResult = $this->taxCalculationService->calculateTax((string) $taxCodeId, $lineTotalMinor, $expenseDate);
                $taxRateBps = (int) $taxResult['rate_bps'];
                $taxAmountMinor = (int) $taxResult['tax_minor'];
                $grossAmountMinor = (int) $taxResult['gross_minor'];
            }

            $validated[] = [
                'expense_category_id' => $category->id,
                'expense_account_id' => $account->id,
                'description' => $line['description'] ?? null,
                'quantity_e6' => $quantityE6,
                'unit_amount_minor' => $unitAmountMinor,
                'line_total_minor' => $lineTotalMinor,
                'tax_code_id' => $taxCodeId,
                'tax_rate_bps' => $taxRateBps,
                'tax_amount_minor' => $taxAmountMinor,
                'gross_amount_minor' => $grossAmountMinor,
            ];
        }

        return $validated;
    }

    private function validateSettlement(array $data, string $currency, ?string $branchId): array
    {
        $method = (string) ($data['settlement_method'] ?? '');
        if (! in_array($method, self::SETTLEMENT_METHODS, true)) {
            throw ValidationException::withMessages(['settlement_method' => [__('Settlement method must be payable, cash, or bank.')]]);
        }

        $result = [
            'settlement_method' => $method,
            'supplier_id' => null,
            'cash_account_id' => null,
            'bank_account_id' => null,
            'payee_name' => $data['payee_name'] ?? null,
        ];

        if ($method === 'payable') {
            /** @var Supplier|null $supplier */
            $supplier = Supplier::query()->where('id', $data['supplier_id'] ?? null)->first();
            if (! $supplier || $supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => [__('Payable expenses require an active supplier.')]]);
            }

            $result['supplier_id'] = $supplier->id;
            $result['payee_name'] = null;

            return $result;
        }

        if ($method === 'cash') {
            /** @var CashAccount|null $cashAccount */
            $cashAccount = CashAccount::query()->with('glAccount')->where('id', $data['cash_account_id'] ?? null)->first();
            if (! $cashAccount || ! $cashAccount->is_active || ! $cashAccount->glAccount) {
                throw ValidationException::withMessages(['cash_account_id' => [__('Cash settlement requires an active cash account.')]]);
            }

            $this->assertPaymentAccountContext($cashAccount->currency, $cashAccount->branch_id ? (string) $cashAccount->branch_id : null, $currency, $branchId, 'cash_account_id');
            $result['cash_account_id'] = $cashAccount->id;

            return $result;
        }

        /** @var BankAccount|null $bankAccount */
        $bankAccount = BankAccount::query()->with('glAccount')->where('id', $data['bank_account_id'] ?? null)->first();
        if (! $bankAccount || ! $bankAccount->is_active || ! $bankAccount->glAccount) {
            throw ValidationException::withMessages(['bank_account_id' => [__('Bank settlement requires an active bank account.')]]);
        }

        $this->assertPaymentAccountContext($bankAccount->currency, $bankAccount->branch_id ? (string) $bankAccount->branch_id : null, $currency, $branchId, 'bank_account_id');
        $result['bank_account_id'] = $bankAccount->id;

        return $result;
    }

    private function assertPaymentAccountContext(string $accountCurrency, ?string $accountBranchId, string $expenseCurrency, ?string $expenseBranchId, string $field): void
    {
        if ($accountCurrency !== $expenseCurrency) {
            throw ValidationException::withMessages([$field => [__('Settlement account currency must match expense currency.')]]);
        }

        if ($accountBranchId !== null && $expenseBranchId === null) {
            throw ValidationException::withMessages(['branch_id' => [__('Select the same operational branch as the settlement account, or use an unassigned settlement account.')]]);
        }

        if ($accountBranchId !== null && $expenseBranchId !== $accountBranchId) {
            throw ValidationException::withMessages(['branch_id' => [__('Expense branch must match the selected settlement account branch.')]]);
        }
    }

    private function resolveSettlementCreditAccount(Expense $expense): Account
    {
        if ($expense->settlement_method === 'payable') {
            return $this->mappingService->getAccount('ap_control', $expense->branch_id ? (string) $expense->branch_id : null);
        }

        if ($expense->settlement_method === 'cash') {
            /** @var CashAccount $cashAccount */
            $cashAccount = CashAccount::query()->with('glAccount')->where('id', $expense->cash_account_id)->firstOrFail();

            return $cashAccount->glAccount;
        }

        /** @var BankAccount $bankAccount */
        $bankAccount = BankAccount::query()->with('glAccount')->where('id', $expense->bank_account_id)->firstOrFail();

        return $bankAccount->glAccount;
    }

    /**
     * @return array<string, int>
     */
    private function expenseLineTotalsByAccount(Expense $expense): array
    {
        $totals = [];

        foreach ($expense->lines as $line) {
            $accountId = (string) $line->expense_account_id;
            $totals[$accountId] = ($totals[$accountId] ?? 0) + (int) $line->line_total_minor;
        }

        return array_filter($totals, fn (int $amount): bool => $amount > 0);
    }

    private function assertRequiredAttachments(Expense $expense): void
    {
        $requiresAttachment = $expense->lines->contains(fn ($line): bool => (bool) $line->category?->requires_attachment);

        if (! $requiresAttachment) {
            return;
        }

        $hasAttachment = DB::table('attachment')
            ->where('entity_type', 'expense')
            ->where('entity_id', $expense->id)
            ->exists();

        if (! $hasAttachment) {
            throw ValidationException::withMessages(['attachments' => [__('At least one attachment is required before posting this expense.')]]);
        }
    }

    private function resolveOpenPeriodForDate(string $date): FinancialPeriod
    {
        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereIn('status', ['open', 'reopened'])
            ->first();

        if (! $period) {
            throw ValidationException::withMessages(['expense_date' => [__('No open financial period covers date :date.', ['date' => $date])]]);
        }

        return $period;
    }

    private function calculateLineTotalMinor(int $quantityE6, int $unitAmountMinor, int $lineIndex): int
    {
        if ($unitAmountMinor > 0 && $quantityE6 > intdiv(PHP_INT_MAX, $unitAmountMinor)) {
            throw ValidationException::withMessages(["lines.{$lineIndex}.line_total" => [__('Line :line amount exceeds maximum allowable integer limit.', ['line' => $lineIndex])]]);
        }

        $product = $quantityE6 * $unitAmountMinor;
        if ($product % self::QUANTITY_SCALE !== 0) {
            throw ValidationException::withMessages(["lines.{$lineIndex}.quantity_e6" => [__('Line :line quantity and unit amount result in fractional minor units.', ['line' => $lineIndex])]]);
        }

        return intdiv($product, self::QUANTITY_SCALE);
    }

    private function assertCurrency(?string $currency): string
    {
        $code = CurrencyInput::required($currency);

        if (! DB::table('currency')->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Currency [:code] does not exist.', ['code' => $code])]]);
        }

        return $code;
    }

    private function assertBranch(?string $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        if (! Branch::query()->where('id', $branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch does not exist or is inactive.')]]);
        }
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency must match expense currency.', ['label' => $label])]]);
        }
    }

    private function normalizeBranchId(?string $branchId): ?string
    {
        return $branchId === '' ? null : $branchId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(Expense $expense, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $expense->lines()->create([
                'line_no' => $index + 1,
                ...$line,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return [
            'branch',
            'supplier',
            'cashAccount',
            'bankAccount',
            'period.fiscalYear',
            'lines.category',
            'lines.expenseAccount',
            'lines.taxCode',
            'journalEntry',
            'payableEntry',
        ];
    }
}
