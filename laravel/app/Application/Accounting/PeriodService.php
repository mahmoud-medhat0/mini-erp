<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PeriodService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createFiscalYear(int $year, string $startDate, string $endDate): FiscalYear
    {
        return DB::transaction(function () use ($year, $startDate, $endDate): FiscalYear {
            $exists = FiscalYear::query()->where('year', $year)->exists();
            if ($exists) {
                throw new InvalidArgumentException(__('Fiscal year :year already exists.', ['year' => $year]));
            }

            $fiscalYear = FiscalYear::create([
                'id' => (string) Str::uuid(),
                'year' => $year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'open',
            ]);

            // Generate 12 monthly periods
            $start = Carbon::parse($startDate);
            for ($month = 1; $month <= 12; $month++) {
                $periodStart = $start->copy()->addMonths($month - 1)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();

                FinancialPeriod::create([
                    'id' => (string) Str::uuid(),
                    'fiscal_year_id' => $fiscalYear->id,
                    'month' => $month,
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'open',
                ]);
            }

            return $fiscalYear->fresh(['periods']);
        });
    }

    public function checkCloseReadiness(FinancialPeriod $period): array
    {
        $startDate = Carbon::parse($period->start_date)->toDateString();
        $endDate = Carbon::parse($period->end_date)->toDateString();
        $blockers = [];

        // 1. Journal Entries
        $journals = DB::table('journal_entry')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('entry_date', [$startDate, $endDate]);
            })->get();
        foreach ($journals as $j) {
            $blockers[] = [
                'entity_type' => 'journal_entry',
                'id' => (string) $j->id,
                'number_or_reference' => (string) ($j->number ?? $j->id),
                'status' => (string) $j->status,
                'date' => (string) $j->entry_date,
                'reason_code' => 'unposted_journal_entry',
            ];
        }

        // 2. Customer Invoices
        $invoices = DB::table('customer_invoice')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('invoice_date', [$startDate, $endDate]);
            })->get();
        foreach ($invoices as $inv) {
            $blockers[] = [
                'entity_type' => 'customer_invoice',
                'id' => (string) $inv->id,
                'number_or_reference' => (string) ($inv->number ?? $inv->id),
                'status' => (string) $inv->status,
                'date' => (string) $inv->invoice_date,
                'reason_code' => 'unposted_customer_invoice',
            ];
        }

        // 3. Supplier Bills
        $bills = DB::table('supplier_bill')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('bill_date', [$startDate, $endDate]);
            })->get();
        foreach ($bills as $b) {
            $blockers[] = [
                'entity_type' => 'supplier_bill',
                'id' => (string) $b->id,
                'number_or_reference' => (string) ($b->number ?? $b->id),
                'status' => (string) $b->status,
                'date' => (string) $b->bill_date,
                'reason_code' => 'unposted_supplier_bill',
            ];
        }

        // 4. Customer Receipts
        $receipts = DB::table('customer_receipt')
            ->whereIn('status', ['draft', 'submitted'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('receipt_date', [$startDate, $endDate]);
            })->get();
        foreach ($receipts as $r) {
            $blockers[] = [
                'entity_type' => 'customer_receipt',
                'id' => (string) $r->id,
                'number_or_reference' => (string) ($r->number ?? $r->id),
                'status' => (string) $r->status,
                'date' => (string) $r->receipt_date,
                'reason_code' => 'unposted_customer_receipt',
            ];
        }

        // 5. Supplier Payments
        $payments = DB::table('supplier_payment')
            ->whereIn('status', ['draft', 'submitted'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('payment_date', [$startDate, $endDate]);
            })->get();
        foreach ($payments as $p) {
            $blockers[] = [
                'entity_type' => 'supplier_payment',
                'id' => (string) $p->id,
                'number_or_reference' => (string) ($p->number ?? $p->id),
                'status' => (string) $p->status,
                'date' => (string) $p->payment_date,
                'reason_code' => 'unposted_supplier_payment',
            ];
        }

        // 6. Incoming Cheques
        $inCheques = DB::table('incoming_cheque')
            ->whereIn('status', ['received', 'deposited'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('received_financial_period_id', $period->id)
                    ->orWhereBetween('received_date', [$startDate, $endDate]);
            })->get();
        foreach ($inCheques as $ic) {
            $blockers[] = [
                'entity_type' => 'incoming_cheque',
                'id' => (string) $ic->id,
                'number_or_reference' => (string) ($ic->number ?? $ic->cheque_number ?? $ic->id),
                'status' => (string) $ic->status,
                'date' => (string) ($ic->received_date ?? $startDate),
                'reason_code' => 'pending_incoming_cheque',
            ];
        }

        // 7. Outgoing Cheques
        $outCheques = DB::table('outgoing_cheque')
            ->whereIn('status', ['issued'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('issued_financial_period_id', $period->id)
                    ->orWhereBetween('issued_date', [$startDate, $endDate]);
            })->get();
        foreach ($outCheques as $oc) {
            $blockers[] = [
                'entity_type' => 'outgoing_cheque',
                'id' => (string) $oc->id,
                'number_or_reference' => (string) ($oc->number ?? $oc->cheque_number ?? $oc->id),
                'status' => (string) $oc->status,
                'date' => (string) ($oc->issued_date ?? $startDate),
                'reason_code' => 'pending_outgoing_cheque',
            ];
        }

        // 8. Bank Reconciliations
        $recons = DB::table('bank_reconciliation')
            ->whereIn('status', ['draft', 'in_progress'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('date_to', [$startDate, $endDate]);
            })->get();
        foreach ($recons as $rec) {
            $blockers[] = [
                'entity_type' => 'bank_reconciliation',
                'id' => (string) $rec->id,
                'number_or_reference' => (string) ($rec->statement_reference ?? $rec->id),
                'status' => (string) $rec->status,
                'date' => (string) ($rec->date_to ?? $startDate),
                'reason_code' => 'open_bank_reconciliation',
            ];
        }

        // 9. Goods Receipts
        $grs = DB::table('goods_receipt')
            ->where('status', 'draft')
            ->whereBetween('receipt_date', [$startDate, $endDate])->get();
        foreach ($grs as $gr) {
            $blockers[] = [
                'entity_type' => 'goods_receipt',
                'id' => (string) $gr->id,
                'number_or_reference' => (string) ($gr->number ?? $gr->id),
                'status' => (string) $gr->status,
                'date' => (string) $gr->receipt_date,
                'reason_code' => 'unposted_goods_receipt',
            ];
        }

        // 10. Landed Cost Allocations
        $landedCosts = DB::table('landed_cost_allocation')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('allocation_date', [$startDate, $endDate]);
            })->get();
        foreach ($landedCosts as $landedCost) {
            $blockers[] = [
                'entity_type' => 'landed_cost_allocation',
                'id' => (string) $landedCost->id,
                'number_or_reference' => (string) ($landedCost->number ?? $landedCost->reference ?? $landedCost->id),
                'status' => (string) $landedCost->status,
                'date' => (string) $landedCost->allocation_date,
                'reason_code' => 'unposted_landed_cost_allocation',
            ];
        }

        // 11. Expenses
        if (Schema::hasTable('expense')) {
            $expenses = DB::table('expense')
                ->whereIn('status', ['draft', 'submitted', 'approved'])
                ->where(function ($q) use ($period, $startDate, $endDate) {
                    $q->where('financial_period_id', $period->id)
                        ->orWhereBetween('expense_date', [$startDate, $endDate]);
                })->get();
            foreach ($expenses as $expense) {
                $blockers[] = [
                    'entity_type' => 'expense',
                    'id' => (string) $expense->id,
                    'number_or_reference' => (string) ($expense->number ?? $expense->reference ?? $expense->id),
                    'status' => (string) $expense->status,
                    'date' => (string) $expense->expense_date,
                    'reason_code' => 'unposted_expense',
                ];
            }
        }

        // 12. Prepaid Recognitions
        if (Schema::hasTable('prepaid_recognition')) {
            $prepaidRecognitions = DB::table('prepaid_recognition')
                ->join('prepaid_schedule', 'prepaid_schedule.id', '=', 'prepaid_recognition.prepaid_schedule_id')
                ->where('prepaid_recognition.status', 'pending')
                ->whereIn('prepaid_schedule.status', ['approved', 'active'])
                ->where(function ($q) use ($period, $startDate, $endDate) {
                    $q->where('prepaid_recognition.financial_period_id', $period->id)
                        ->orWhereBetween('prepaid_recognition.recognition_date', [$startDate, $endDate]);
                })
                ->select('prepaid_recognition.*', 'prepaid_schedule.number as schedule_number', 'prepaid_schedule.reference as schedule_reference')
                ->get();
            foreach ($prepaidRecognitions as $recognition) {
                $blockers[] = [
                    'entity_type' => 'prepaid_recognition',
                    'id' => (string) $recognition->id,
                    'number_or_reference' => (string) ($recognition->schedule_number ?? $recognition->schedule_reference ?? $recognition->id),
                    'status' => (string) $recognition->status,
                    'date' => (string) $recognition->recognition_date,
                    'reason_code' => 'pending_prepaid_recognition',
                ];
            }
        }

        // 13. Accrual Entries
        if (Schema::hasTable('accrual_entry')) {
            $accrualEntries = DB::table('accrual_entry')
                ->join('accrual_schedule', 'accrual_schedule.id', '=', 'accrual_entry.accrual_schedule_id')
                ->where('accrual_entry.status', 'pending')
                ->whereIn('accrual_schedule.status', ['approved', 'active'])
                ->where(function ($q) use ($period, $startDate, $endDate) {
                    $q->where('accrual_entry.financial_period_id', $period->id)
                        ->orWhereBetween('accrual_entry.accrual_date', [$startDate, $endDate]);
                })
                ->select('accrual_entry.*', 'accrual_schedule.number as schedule_number', 'accrual_schedule.reference as schedule_reference')
                ->get();
            foreach ($accrualEntries as $entry) {
                $blockers[] = [
                    'entity_type' => 'accrual_entry',
                    'id' => (string) $entry->id,
                    'number_or_reference' => (string) ($entry->schedule_number ?? $entry->schedule_reference ?? $entry->id),
                    'status' => (string) $entry->status,
                    'date' => (string) $entry->accrual_date,
                    'reason_code' => 'pending_accrual_entry',
                ];
            }
        }

        // 14. Payroll Runs
        if (Schema::hasTable('payroll_run')) {
            $payrollRuns = DB::table('payroll_run')
                ->whereIn('status', ['draft', 'submitted', 'approved'])
                ->where(function ($q) use ($period, $startDate, $endDate) {
                    $q->where('financial_period_id', $period->id)
                        ->orWhereBetween('payroll_date', [$startDate, $endDate]);
                })->get();
            foreach ($payrollRuns as $run) {
                $blockers[] = [
                    'entity_type' => 'payroll_run',
                    'id' => (string) $run->id,
                    'number_or_reference' => (string) ($run->number ?? $run->reference ?? $run->id),
                    'status' => (string) $run->status,
                    'date' => (string) $run->payroll_date,
                    'reason_code' => 'unposted_payroll_run',
                ];
            }
        }

        // 15. Rental Invoices
        if (Schema::hasTable('rental_invoice')) {
            $rentalInvoices = DB::table('rental_invoice')
                ->whereIn('status', ['draft', 'submitted', 'approved'])
                ->where(function ($q) use ($period, $startDate, $endDate) {
                    $q->where('financial_period_id', $period->id)
                        ->orWhereBetween('invoice_date', [$startDate, $endDate]);
                })->get();
            foreach ($rentalInvoices as $invoice) {
                $blockers[] = [
                    'entity_type' => 'rental_invoice',
                    'id' => (string) $invoice->id,
                    'number_or_reference' => (string) ($invoice->number ?? $invoice->reference ?? $invoice->id),
                    'status' => (string) $invoice->status,
                    'date' => (string) $invoice->invoice_date,
                    'reason_code' => 'unposted_rental_invoice',
                ];
            }
        }

        // 16. Delivery Notes
        $dns = DB::table('delivery_note')
            ->where('status', 'draft')
            ->whereBetween('delivery_date', [$startDate, $endDate])->get();
        foreach ($dns as $dn) {
            $blockers[] = [
                'entity_type' => 'delivery_note',
                'id' => (string) $dn->id,
                'number_or_reference' => (string) ($dn->number ?? $dn->id),
                'status' => (string) $dn->status,
                'date' => (string) $dn->delivery_date,
                'reason_code' => 'unposted_delivery_note',
            ];
        }

        // 17. Sales Returns
        $srs = DB::table('sales_return')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->whereBetween('return_date', [$startDate, $endDate])->get();
        foreach ($srs as $sr) {
            $blockers[] = [
                'entity_type' => 'sales_return',
                'id' => (string) $sr->id,
                'number_or_reference' => (string) ($sr->number ?? $sr->id),
                'status' => (string) $sr->status,
                'date' => (string) $sr->return_date,
                'reason_code' => 'unposted_sales_return',
            ];
        }

        // 18. Customer Credit Notes
        $cns = DB::table('customer_credit_note')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->whereBetween('credit_note_date', [$startDate, $endDate])->get();
        foreach ($cns as $cn) {
            $blockers[] = [
                'entity_type' => 'customer_credit_note',
                'id' => (string) $cn->id,
                'number_or_reference' => (string) ($cn->number ?? $cn->id),
                'status' => (string) $cn->status,
                'date' => (string) $cn->credit_note_date,
                'reason_code' => 'unposted_customer_credit_note',
            ];
        }

        // 19. Purchase Returns
        $prs = DB::table('purchase_return')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->whereBetween('return_date', [$startDate, $endDate])->get();
        foreach ($prs as $pr) {
            $blockers[] = [
                'entity_type' => 'purchase_return',
                'id' => (string) $pr->id,
                'number_or_reference' => (string) ($pr->number ?? $pr->id),
                'status' => (string) $pr->status,
                'date' => (string) $pr->return_date,
                'reason_code' => 'unposted_purchase_return',
            ];
        }

        // 20. Supplier Adjustment Notes
        $sans = DB::table('supplier_adjustment_note')
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->whereBetween('note_date', [$startDate, $endDate])->get();
        foreach ($sans as $san) {
            $blockers[] = [
                'entity_type' => 'supplier_adjustment_note',
                'id' => (string) $san->id,
                'number_or_reference' => (string) ($san->number ?? $san->id),
                'status' => (string) $san->status,
                'date' => (string) $san->note_date,
                'reason_code' => 'unposted_supplier_adjustment_note',
            ];
        }

        // 21. Opening Balances (GL, Customer, Supplier)
        $obs = DB::table('opening_balance')
            ->where('status', 'draft')
            ->where(function ($q) use ($period, $startDate, $endDate) {
                $q->where('financial_period_id', $period->id)
                    ->orWhereBetween('entry_date', [$startDate, $endDate]);
            })->get();
        foreach ($obs as $ob) {
            $blockers[] = [
                'entity_type' => 'opening_balance',
                'id' => (string) $ob->id,
                'number_or_reference' => (string) ($ob->number ?? $ob->id),
                'status' => (string) $ob->status,
                'date' => (string) $ob->entry_date,
                'reason_code' => 'unposted_opening_balance',
            ];
        }

        $cObs = DB::table('customer_opening_balance')
            ->where('status', 'draft')
            ->whereBetween('entry_date', [$startDate, $endDate])->get();
        foreach ($cObs as $cob) {
            $blockers[] = [
                'entity_type' => 'customer_opening_balance',
                'id' => (string) $cob->id,
                'number_or_reference' => (string) ($cob->number ?? $cob->id),
                'status' => (string) $cob->status,
                'date' => (string) $cob->entry_date,
                'reason_code' => 'unposted_customer_opening_balance',
            ];
        }

        $sObs = DB::table('supplier_opening_balance')
            ->where('status', 'draft')
            ->whereBetween('entry_date', [$startDate, $endDate])->get();
        foreach ($sObs as $sob) {
            $blockers[] = [
                'entity_type' => 'supplier_opening_balance',
                'id' => (string) $sob->id,
                'number_or_reference' => (string) ($sob->number ?? $sob->id),
                'status' => (string) $sob->status,
                'date' => (string) $sob->entry_date,
                'reason_code' => 'unposted_supplier_opening_balance',
            ];
        }

        return [
            'can_close' => count($blockers) === 0,
            'blockers' => $blockers,
        ];
    }

    public function closePeriod(FinancialPeriod $period, int $userId, ?string $note = null): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $userId, $note): FinancialPeriod {
            $lockedPeriod = FinancialPeriod::query()
                ->where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPeriod->status === 'closed') {
                return $lockedPeriod;
            }

            // Re-check blockers inside transaction after lock
            $readiness = $this->checkCloseReadiness($lockedPeriod);
            if (! $readiness['can_close']) {
                throw new InvalidArgumentException(
                    __('Cannot close financial period because unposted documents exist.')
                );
            }

            $before = $lockedPeriod->toArray();
            $lockedPeriod->update([
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => now(),
                'close_note' => $note,
            ]);

            $this->auditLogger->record($userId, 'financial_period.close', 'financial_period', (string) $lockedPeriod->id, before: $before, after: $lockedPeriod->toArray());

            return $lockedPeriod;
        });
    }

    public function reopenPeriod(FinancialPeriod $period, int $userId, ?string $note = null): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $userId, $note): FinancialPeriod {
            $lockedPeriod = FinancialPeriod::query()
                ->where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedPeriod->toArray();
            $lockedPeriod->update([
                'status' => 'reopened',
                'reopened_by' => $userId,
                'reopened_at' => now(),
                'close_note' => $note,
            ]);

            $this->auditLogger->record($userId, 'financial_period.reopen', 'financial_period', (string) $lockedPeriod->id, before: $before, after: $lockedPeriod->toArray());

            return $lockedPeriod;
        });
    }
}
