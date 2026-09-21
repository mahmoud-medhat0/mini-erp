<?php

namespace App\Application\Taxes;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\WithholdingTaxEntry;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 29 - Withholding Tax (WHT) technical framework
 * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §8). Entry is deliberately manual:
 * an accountant who knows a specific supplier payment is subject to
 * withholding records it here against a real tax_code/tax_rate they (or the
 * owner) configured through /taxes/codes and /taxes/rates. This class does
 * not decide *when* withholding legally applies, nor what the rate is - both
 * are unresolved policy questions flagged in the decision pack that require
 * a real Egyptian tax advisor, not a default in code.
 *
 * Posting affects the accounts-payable control account and a new
 * withholding_tax_payable liability account at the control-account level
 * only. It does not touch the per-bill AR/AP subledger (ReceivableEntry /
 * PayableEntry), so per-supplier aging will not automatically reflect a WHT
 * entry in this slice - that subledger integration is a disclosed follow-on,
 * not part of this bounded framework.
 */
class WithholdingTaxService
{
    public function __construct(
        private readonly TaxCalculationService $taxCalculationService,
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly PeriodGuard $periodGuard,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, ?int $actorId = null): WithholdingTaxEntry
    {
        return DB::transaction(function () use ($data, $actorId): WithholdingTaxEntry {
            $payload = $this->validatePayload($data);

            /** @var WithholdingTaxEntry $entry */
            $entry = WithholdingTaxEntry::query()->create([
                'tax_code_id' => $payload['tax_code']->id,
                'supplier_id' => $payload['supplier']->id,
                'reference' => $payload['reference'],
                'entry_date' => $payload['entry_date'],
                'financial_period_id' => $payload['period']->id,
                'currency' => $payload['currency'],
                'base_amount_minor' => $payload['base_amount_minor'],
                'rate_bps' => $payload['rate_bps'],
                'withheld_amount_minor' => $payload['withheld_amount_minor'],
                'status' => 'draft',
                'notes' => $payload['notes'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'withholding_tax_entry.create', 'withholding_tax_entry', $entry->id, after: $entry->fresh($this->relations())->toArray());

            return $entry->fresh($this->relations());
        });
    }

    public function post(string $id, ?int $actorId = null): WithholdingTaxEntry
    {
        if ($actorId === null) {
            throw ValidationException::withMessages(['actor' => [__('Posting a withholding tax entry requires an authenticated actor.')]]);
        }

        return DB::transaction(function () use ($id, $actorId): WithholdingTaxEntry {
            /** @var WithholdingTaxEntry $entry */
            $entry = WithholdingTaxEntry::query()->with(['supplier'])->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($entry->status === 'posted') {
                return $entry->fresh($this->relations());
            }
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft withholding tax entries can be posted.')]]);
            }

            $period = $this->periodGuard->assertPeriodOpenForPostingWithLock($entry->financial_period_id, $entry->entry_date->format('Y-m-d'));

            $apAccount = $this->mappingService->getAccount('ap_control', null);
            $whtPayableAccount = $this->mappingService->getAccount('withholding_tax_payable', null);
            $this->assertAccountCurrency($apAccount, $entry->currency, 'Accounts payable control account');
            $this->assertAccountCurrency($whtPayableAccount, $entry->currency, 'Withholding tax payable account');

            $number = $entry->number ?? $this->numberSequenceAllocator->nextNumber('taxes.withholding', 'WHT', $entry->entry_date);

            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $entry->entry_date,
                'financial_period_id' => $period->id,
                'branch_id' => null,
                'source_type' => 'withholding_tax_entry',
                'source_id' => $entry->id,
                'description' => "Withholding tax {$number} - {$entry->supplier->code}",
                'currency' => $entry->currency,
                'fx_rate_e6' => 1_000_000,
                'status' => 'approved',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => Carbon::now(),
                'lock_version' => 1,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 1,
                'account_id' => $apAccount->id,
                'memo' => "Withholding tax {$number}",
                'debit_minor' => $entry->withheld_amount_minor,
                'credit_minor' => 0,
                'debit_txn_minor' => $entry->withheld_amount_minor,
                'credit_txn_minor' => 0,
                'currency' => $entry->currency,
                'fx_rate_e6' => 1_000_000,
            ]);

            $journalEntry->lines()->create([
                'line_no' => 2,
                'account_id' => $whtPayableAccount->id,
                'memo' => "Withholding tax {$number}",
                'debit_minor' => 0,
                'credit_minor' => $entry->withheld_amount_minor,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $entry->withheld_amount_minor,
                'currency' => $entry->currency,
                'fx_rate_e6' => 1_000_000,
            ]);

            $postedJournal = $this->postingEngine->post($journalEntry, $actorId, allowControlAccounts: true);

            $before = $entry->toArray();
            $entry->update([
                'number' => $number,
                'status' => 'posted',
                'journal_entry_id' => $postedJournal->id,
                'posted_by' => $actorId,
                'posted_at' => Carbon::now(),
                'updated_by' => $actorId,
                'lock_version' => $entry->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'withholding_tax_entry.post', 'withholding_tax_entry', $entry->id, before: $before, after: $entry->fresh($this->relations())->toArray());

            return $entry->fresh($this->relations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): WithholdingTaxEntry
    {
        return DB::transaction(function () use ($id, $actorId): WithholdingTaxEntry {
            /** @var WithholdingTaxEntry $entry */
            $entry = WithholdingTaxEntry::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft withholding tax entries can be cancelled.')]]);
            }

            $before = $entry->toArray();
            $entry->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
                'lock_version' => $entry->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'withholding_tax_entry.cancel', 'withholding_tax_entry', $entry->id, before: $before, after: $entry->fresh()->toArray());

            return $entry->fresh($this->relations());
        });
    }

    private function validatePayload(array $data): array
    {
        $taxCodeId = (string) ($data['tax_code_id'] ?? '');
        /** @var TaxCode|null $taxCode */
        $taxCode = TaxCode::query()->whereKey($taxCodeId)->where('is_active', true)->first();
        if (! $taxCode || $taxCode->tax_type !== 'withholding') {
            throw ValidationException::withMessages(['tax_code_id' => [__('Selected tax code is inactive, missing, or not a withholding tax code.')]]);
        }

        $supplierId = (string) ($data['supplier_id'] ?? '');
        /** @var Supplier|null $supplier */
        $supplier = Supplier::query()->whereKey($supplierId)->where('status', 'active')->first();
        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => [__('Selected supplier is inactive or missing.')]]);
        }

        $entryDate = (string) ($data['entry_date'] ?? '');
        if ($entryDate === '') {
            throw ValidationException::withMessages(['entry_date' => [__('Entry date is required.')]]);
        }

        $baseAmountMinor = (int) ($data['base_amount_minor'] ?? 0);
        if ($baseAmountMinor <= 0) {
            throw ValidationException::withMessages(['base_amount_minor' => [__('Base amount must be greater than zero.')]]);
        }

        $currency = (string) ($data['currency'] ?? '');
        if ($currency === '') {
            throw ValidationException::withMessages(['currency' => [__('Currency is required.')]]);
        }

        $calculation = $this->taxCalculationService->calculateTax($taxCode->id, $baseAmountMinor, $entryDate);

        $period = $this->financialPeriodForDate($entryDate);

        return [
            'tax_code' => $taxCode,
            'supplier' => $supplier,
            'reference' => $data['reference'] ?? null,
            'entry_date' => $entryDate,
            'period' => $period,
            'currency' => $currency,
            'base_amount_minor' => $baseAmountMinor,
            'rate_bps' => $calculation['rate_bps'],
            'withheld_amount_minor' => $calculation['tax_minor'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function assertAccountCurrency(Account $account, string $currency, string $label): void
    {
        if ($account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => [__(':label currency does not match the entry currency.', ['label' => $label])]]);
        }
    }

    private function financialPeriodForDate(string $date): FinancialPeriod
    {
        $normalized = Carbon::parse($date)->toDateString();

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $normalized)
            ->where('end_date', '>=', $normalized)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages(['entry_date' => [__('No financial period covers date :date.', ['date' => $normalized])]]);
        }

        return $period;
    }

    private function relations(): array
    {
        return ['taxCode', 'supplier', 'financialPeriod'];
    }
}
