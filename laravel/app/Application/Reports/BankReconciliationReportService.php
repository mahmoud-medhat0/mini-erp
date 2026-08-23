<?php

namespace App\Application\Reports;

use App\Application\Accounting\BankReconciliationService;
use App\Models\BankReconciliation;

class BankReconciliationReportService
{
    public function __construct(
        private readonly BankReconciliationService $bankReconciliationService,
    ) {}

    public function generateIndex(?string $bankAccountId = null, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = BankReconciliation::query()
            ->with(['bankAccount', 'financialPeriod', 'lines']);

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('date_from', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date_to', '<=', $dateTo);
        }

        $reconciliations = $query->orderBy('date_from', 'desc')->get();

        $items = [];
        foreach ($reconciliations as $recon) {
            $summary = $this->bankReconciliationService->summary($recon->id);

            $items[] = [
                'id' => $recon->id,
                'bank_account' => [
                    'id' => $recon->bankAccount?->id,
                    'code' => $recon->bankAccount?->code,
                    'name' => $recon->bankAccount?->name,
                    'currency' => $recon->bankAccount?->currency ?? 'EGP',
                ],
                'statement_reference' => $recon->statement_reference,
                'date_from' => $recon->date_from,
                'date_to' => $recon->date_to,
                'statement_opening_balance_minor' => (int) $recon->statement_opening_balance_minor,
                'statement_closing_balance_minor' => (int) $recon->statement_closing_balance_minor,
                'status' => $recon->status,
                'finalized_at' => $recon->finalized_at,
                'summary' => $summary,
            ];
        }

        return [
            'filters' => [
                'bank_account_id' => $bankAccountId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'reconciliations' => $items,
        ];
    }

    public function generateDetail(string $reconciliationId): array
    {
        $recon = BankReconciliation::query()
            ->with(['bankAccount', 'financialPeriod', 'lines.matchedLedgerEntry.journalEntry'])
            ->findOrFail($reconciliationId);

        $summary = $this->bankReconciliationService->summary($reconciliationId);

        return [
            'reconciliation' => [
                'id' => $recon->id,
                'bank_account' => [
                    'id' => $recon->bankAccount?->id,
                    'code' => $recon->bankAccount?->code,
                    'name' => $recon->bankAccount?->name,
                    'currency' => $recon->bankAccount?->currency ?? 'EGP',
                ],
                'statement_reference' => $recon->statement_reference,
                'date_from' => $recon->date_from,
                'date_to' => $recon->date_to,
                'statement_opening_balance_minor' => (int) $recon->statement_opening_balance_minor,
                'statement_closing_balance_minor' => (int) $recon->statement_closing_balance_minor,
                'status' => $recon->status,
                'finalized_at' => $recon->finalized_at,
                'lines' => $recon->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'statement_date' => $line->statement_date,
                    'reference' => $line->reference,
                    'description' => $line->description,
                    'debit_minor' => (int) $line->debit_minor,
                    'credit_minor' => (int) $line->credit_minor,
                    'matched_ledger_entry_id' => $line->matched_ledger_entry_id,
                    'matched_at' => $line->matched_at,
                    'matched_ledger_entry' => $line->matchedLedgerEntry ? [
                        'id' => $line->matchedLedgerEntry->id,
                        'entry_date' => $line->matchedLedgerEntry->entry_date,
                        'description' => $line->matchedLedgerEntry->description,
                        'debit_minor' => (int) $line->matchedLedgerEntry->debit_minor,
                        'credit_minor' => (int) $line->matchedLedgerEntry->credit_minor,
                        'journal_number' => $line->matchedLedgerEntry->journalEntry?->number,
                    ] : null,
                ]),
            ],
            'summary' => $summary,
        ];
    }
}
