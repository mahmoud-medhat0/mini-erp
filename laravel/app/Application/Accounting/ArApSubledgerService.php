<?php

namespace App\Application\Accounting;

use App\Models\LedgerEntry;
use App\Models\PayableEntry;
use App\Models\ReceivableEntry;

class ArApSubledgerService
{
    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
    ) {}

    public function getCustomerBalance(string $customerId): int
    {
        $debit = (int) ReceivableEntry::query()->where('customer_id', $customerId)->sum('debit_minor');
        $credit = (int) ReceivableEntry::query()->where('customer_id', $customerId)->sum('credit_minor');

        return $debit - $credit;
    }

    public function getSupplierBalance(string $supplierId): int
    {
        $debit = (int) PayableEntry::query()->where('supplier_id', $supplierId)->sum('debit_minor');
        $credit = (int) PayableEntry::query()->where('supplier_id', $supplierId)->sum('credit_minor');

        return $credit - $debit;
    }

    /**
     * Reconciles total AR Subledger entries against AR Control GL Account ledger entries.
     *
     * @return array{subledger_total: int, gl_control_total: int, is_reconciled: bool, difference: int}
     */
    public function reconcileCustomerSubledgerToGl(): array
    {
        $subledgerDebit = (int) ReceivableEntry::query()->sum('debit_minor');
        $subledgerCredit = (int) ReceivableEntry::query()->sum('credit_minor');
        $subledgerTotal = $subledgerDebit - $subledgerCredit;

        $arControlId = $this->mappingService->getAccountId('ar_control');

        $glDebit = (int) LedgerEntry::query()->where('account_id', $arControlId)->sum('debit_minor');
        $glCredit = (int) LedgerEntry::query()->where('account_id', $arControlId)->sum('credit_minor');
        $glControlTotal = $glDebit - $glCredit;

        return [
            'subledger_total' => $subledgerTotal,
            'gl_control_total' => $glControlTotal,
            'is_reconciled' => $subledgerTotal === $glControlTotal,
            'difference' => $subledgerTotal - $glControlTotal,
        ];
    }

    /**
     * Reconciles total AP Subledger entries against AP Control GL Account ledger entries.
     *
     * @return array{subledger_total: int, gl_control_total: int, is_reconciled: bool, difference: int}
     */
    public function reconcileSupplierSubledgerToGl(): array
    {
        $subledgerDebit = (int) PayableEntry::query()->sum('debit_minor');
        $subledgerCredit = (int) PayableEntry::query()->sum('credit_minor');
        $subledgerTotal = $subledgerCredit - $subledgerDebit;

        $apControlId = $this->mappingService->getAccountId('ap_control');

        $glDebit = (int) LedgerEntry::query()->where('account_id', $apControlId)->sum('debit_minor');
        $glCredit = (int) LedgerEntry::query()->where('account_id', $apControlId)->sum('credit_minor');
        $glControlTotal = $glCredit - $glDebit;

        return [
            'subledger_total' => $subledgerTotal,
            'gl_control_total' => $glControlTotal,
            'is_reconciled' => $subledgerTotal === $glControlTotal,
            'difference' => $subledgerTotal - $glControlTotal,
        ];
    }
}
