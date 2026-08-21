<?php

namespace App\Application\Accounting;

use App\Models\CashAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBookQueryService
{
    public function getStatement(string $cashAccountId, string $dateFrom, string $dateTo): array
    {
        /** @var CashAccount $cashAccount */
        $cashAccount = CashAccount::query()->with('glAccount')->where('id', $cashAccountId)->firstOrFail();

        if (! $cashAccount->glAccount) {
            throw ValidationException::withMessages([
                'cash_account_id' => ['Cash account does not have a linked GL account.'],
            ]);
        }

        $glAccountId = $cashAccount->gl_account_id;
        $currency = $cashAccount->currency;

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
            ];
        }

        $periodMovement = $periodDebit - $periodCredit;
        $closingBalance = $openingBalance + $periodMovement;

        return [
            'cash_account' => [
                'id' => $cashAccount->id,
                'code' => $cashAccount->code,
                'name' => $cashAccount->name,
                'currency' => $cashAccount->currency,
                'gl_account_id' => $cashAccount->gl_account_id,
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
