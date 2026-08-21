<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\BankReconciliationLine;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankBookQueryService
{
    public function getStatement(string $bankAccountId, string $dateFrom, string $dateTo): array
    {
        /** @var BankAccount $bankAccount */
        $bankAccount = BankAccount::query()->with('glAccount')->where('id', $bankAccountId)->firstOrFail();

        if (! $bankAccount->glAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => ['Bank account does not have a linked GL account.'],
            ]);
        }

        $glAccountId = $bankAccount->gl_account_id;
        $currency = $bankAccount->currency;

        // Opening balance prior to dateFrom
        $openingBalance = (int) DB::table('ledger_entry')
            ->where('account_id', $glAccountId)
            ->where('currency', $currency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('debit_minor - credit_minor'));

        // Ledger entries within date range
        $entries = LedgerEntry::query()
            ->with(['journalEntry', 'journalLine'])
            ->where('account_id', $glAccountId)
            ->where('currency', $currency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->orderBy('entry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Get matched reconciliation line statuses for these entries
        $ledgerEntryIds = $entries->pluck('id')->all();
        $matchedLines = BankReconciliationLine::query()
            ->whereIn('matched_ledger_entry_id', $ledgerEntryIds)
            ->where('status', 'matched')
            ->get()
            ->keyBy('matched_ledger_entry_id');

        $runningBalance = $openingBalance;
        $periodDebit = 0;
        $periodCredit = 0;

        $items = [];
        foreach ($entries as $entry) {
            $debit = (int) $entry->debit_minor;
            $credit = (int) $entry->credit_minor;
            $movement = $debit - $credit;
            $runningBalance += $movement;

            $periodDebit += $debit;
            $periodCredit += $credit;

            $journal = $entry->journalEntry;
            $matchedLine = $matchedLines->get($entry->id);

            $items[] = [
                'ledger_entry_id' => $entry->id,
                'journal_entry_id' => $entry->journal_entry_id,
                'journal_number' => $journal?->number,
                'source_type' => $journal?->source_type,
                'source_id' => $journal?->source_id,
                'entry_date' => (string) $entry->entry_date,
                'reference' => $journal?->reference,
                'description' => $entry->journalLine?->memo ?: $journal?->description,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'signed_movement_minor' => $movement,
                'balance_after_minor' => $runningBalance,
                'is_reconciled' => $matchedLine !== null,
                'reconciliation_line_id' => $matchedLine?->id,
                'reconciliation_id' => $matchedLine?->bank_reconciliation_id,
            ];
        }

        $periodMovement = $periodDebit - $periodCredit;
        $closingBalance = $openingBalance + $periodMovement;

        return [
            'bank_account' => [
                'id' => $bankAccount->id,
                'code' => $bankAccount->code,
                'name' => $bankAccount->name,
                'account_number' => $bankAccount->account_number,
                'bank_name' => $bankAccount->bank_name,
                'currency' => $bankAccount->currency,
                'gl_account_id' => $bankAccount->gl_account_id,
            ],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
            'opening_balance_minor' => $openingBalance,
            'period_debit_minor' => $periodDebit,
            'period_credit_minor' => $periodCredit,
            'period_movement_minor' => $periodMovement,
            'closing_balance_minor' => $closingBalance,
            'entries' => $items,
        ];
    }
}
