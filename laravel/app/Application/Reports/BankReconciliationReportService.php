<?php

namespace App\Application\Reports;

use App\Application\Accounting\BankReconciliationService;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class BankReconciliationReportService
{
    public function __construct(
        private readonly BankReconciliationService $bankReconciliationService,
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generateIndex(?string $bankAccountId = null, ?string $status = null, ?string $dateFrom = null, ?string $dateTo = null, ?int $limit = null): array
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

        $reconciliations = $query
            ->orderBy('date_from', 'desc')
            ->when($limit !== null, fn (Builder $builder) => $builder->limit(max(0, $limit)))
            ->get();

        $items = [];
        foreach ($reconciliations as $recon) {
            $summary = $this->bankReconciliationService->summary($recon->id);

            $items[] = [
                'id' => $recon->id,
                'bank_account' => [
                    'id' => $recon->bankAccount?->id,
                    'code' => $recon->bankAccount?->code,
                    'name' => $recon->bankAccount?->name,
                    'currency' => $recon->bankAccount?->currency ?? $this->currencyResolver->resolve(),
                ],
                'statement_reference' => $recon->statement_reference,
                'date_from' => $recon->date_from ? (is_string($recon->date_from) ? substr($recon->date_from, 0, 10) : $recon->date_from->format('Y-m-d')) : null,
                'date_to' => $recon->date_to ? (is_string($recon->date_to) ? substr($recon->date_to, 0, 10) : $recon->date_to->format('Y-m-d')) : null,
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

    /** @param array<string, mixed> $filters */
    public function indexDataTable(array $filters = []): JsonResponse
    {
        $query = BankReconciliation::query()
            ->with('bankAccount')
            ->leftJoin('bank_account', 'bank_account.id', '=', 'bank_reconciliation.bank_account_id')
            ->select('bank_reconciliation.*')
            ->withCount([
                'lines as total_statement_lines_count',
                'lines as matched_statement_lines_count' => fn (Builder $builder) => $builder->where('status', 'matched'),
                'lines as unmatched_statement_lines_count' => fn (Builder $builder) => $builder->where('status', '!=', 'matched'),
            ])
            ->when(! empty($filters['bank_account_id']), fn (Builder $builder) => $builder
                ->where('bank_reconciliation.bank_account_id', (string) $filters['bank_account_id']))
            ->when(! empty($filters['status']), fn (Builder $builder) => $builder
                ->where('bank_reconciliation.status', (string) $filters['status']))
            ->when(! empty($filters['date_from']), fn (Builder $builder) => $builder
                ->where('bank_reconciliation.date_from', '>=', (string) $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $builder) => $builder
                ->where('bank_reconciliation.date_to', '<=', (string) $filters['date_to']));

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(COALESCE(bank_reconciliation.statement_reference, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(bank_reconciliation.status, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(bank_account.code, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(bank_account.name AS TEXT), '')) LIKE ?", [$like]);
                });
            })
            ->orderColumn('bank_account_name', 'bank_account.code $1')
            ->orderColumn('statement_reference', 'bank_reconciliation.statement_reference $1')
            ->orderColumn('date_from', 'bank_reconciliation.date_from $1')
            ->orderColumn('status', 'bank_reconciliation.status $1')
            ->orderColumn('matched_statement_lines_count', 'matched_statement_lines_count $1')
            ->orderColumn('difference_minor', 'bank_reconciliation.difference_minor $1')
            ->addColumn('bank_account_code', fn (BankReconciliation $row) => $row->bankAccount?->code ?? '')
            ->addColumn('bank_account_name', fn (BankReconciliation $row) => $row->bankAccount?->name ?? '')
            ->addColumn('bank_account_currency', fn (BankReconciliation $row) => $row->bankAccount?->currency ?? $row->currency)
            ->addColumn('actions', fn (): null => null)
            ->toJson();
    }

    public function generateDetail(string $reconciliationId, bool $includeLines = true): array
    {
        $relations = ['bankAccount', 'financialPeriod'];
        if ($includeLines) {
            $relations[] = 'lines.matchedLedgerEntry.journalEntry';
        }

        $recon = BankReconciliation::query()
            ->with($relations)
            ->findOrFail($reconciliationId);

        $summary = $this->bankReconciliationService->summary($reconciliationId);

        return [
            'reconciliation' => [
                'id' => $recon->id,
                'bank_account' => [
                    'id' => $recon->bankAccount?->id,
                    'code' => $recon->bankAccount?->code,
                    'name' => $recon->bankAccount?->name,
                    'currency' => $recon->bankAccount?->currency ?? $this->currencyResolver->resolve(),
                ],
                'statement_reference' => $recon->statement_reference,
                'date_from' => $recon->date_from ? (is_string($recon->date_from) ? substr($recon->date_from, 0, 10) : $recon->date_from->format('Y-m-d')) : null,
                'date_to' => $recon->date_to ? (is_string($recon->date_to) ? substr($recon->date_to, 0, 10) : $recon->date_to->format('Y-m-d')) : null,
                'statement_opening_balance_minor' => (int) $recon->statement_opening_balance_minor,
                'statement_closing_balance_minor' => (int) $recon->statement_closing_balance_minor,
                'status' => $recon->status,
                'finalized_at' => $recon->finalized_at,
                'lines' => $includeLines ? $recon->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'statement_date' => $line->statement_date ? (is_string($line->statement_date) ? substr($line->statement_date, 0, 10) : $line->statement_date->format('Y-m-d')) : null,
                    'reference' => $line->reference,
                    'description' => $line->description,
                    'debit_minor' => (int) $line->debit_minor,
                    'credit_minor' => (int) $line->credit_minor,
                    'matched_ledger_entry_id' => $line->matched_ledger_entry_id,
                    'matched_at' => $line->matched_at,
                    'matched_ledger_entry' => $line->matchedLedgerEntry ? [
                        'id' => $line->matchedLedgerEntry->id,
                        'entry_date' => $line->matchedLedgerEntry->entry_date ? (is_string($line->matchedLedgerEntry->entry_date) ? substr($line->matchedLedgerEntry->entry_date, 0, 10) : $line->matchedLedgerEntry->entry_date->format('Y-m-d')) : null,
                        'description' => $line->matchedLedgerEntry->description,
                        'debit_minor' => (int) $line->matchedLedgerEntry->debit_minor,
                        'credit_minor' => (int) $line->matchedLedgerEntry->credit_minor,
                        'journal_number' => $line->matchedLedgerEntry->journalEntry?->number,
                    ] : null,
                ]) : [],
            ],
            'summary' => $summary,
        ];
    }

    public function detailDataTable(string $reconciliationId): JsonResponse
    {
        BankReconciliation::query()->whereKey($reconciliationId)->firstOrFail();

        $query = BankReconciliationLine::query()
            ->with('matchedLedgerEntry.journalEntry')
            ->where('bank_reconciliation_line.bank_reconciliation_id', $reconciliationId)
            ->select('bank_reconciliation_line.*');

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = "%{$search}%";
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('bank_reconciliation_line.reference', 'like', $like)
                        ->orWhere('bank_reconciliation_line.description', 'like', $like)
                        ->orWhereRaw('CAST(bank_reconciliation_line.statement_date AS TEXT) LIKE ?', [$like])
                        ->orWhereHas('matchedLedgerEntry.journalEntry', fn (Builder $journal) => $journal
                            ->where('number', 'like', $like));
                });
            })
            ->orderColumn('statement_date', 'bank_reconciliation_line.statement_date $1')
            ->orderColumn('reference', 'bank_reconciliation_line.reference $1')
            ->orderColumn('statement_net_minor', '(bank_reconciliation_line.debit_minor - bank_reconciliation_line.credit_minor) $1')
            ->addColumn('statement_net_minor', fn (BankReconciliationLine $line): int => (int) $line->debit_minor - (int) $line->credit_minor)
            ->addColumn('journal_number', fn (BankReconciliationLine $line) => $line->matchedLedgerEntry?->journalEntry?->number)
            ->addColumn('matched_entry_date', fn (BankReconciliationLine $line) => $line->matchedLedgerEntry?->entry_date)
            ->addColumn('matched_net_minor', fn (BankReconciliationLine $line): ?int => $line->matchedLedgerEntry
                ? (int) $line->matchedLedgerEntry->debit_minor - (int) $line->matchedLedgerEntry->credit_minor
                : null)
            ->toJson();
    }
}
