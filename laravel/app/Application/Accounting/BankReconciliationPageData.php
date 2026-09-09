<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class BankReconciliationPageData
{
    public function __construct(private readonly BankReconciliationService $service) {}

    /**
     * @param  array{status?: mixed, bank_account_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'status' => $filters['status'] ?? null,
            'bank_account_id' => $filters['bank_account_id'] ?? null,
        ];

        return [
            'bankAccounts' => $this->activeBankAccounts(),
            'periods' => $this->openPeriods(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(string $id): array
    {
        return [
            'reconciliation' => BankReconciliation::query()
                ->with(['bankAccount', 'financialPeriod'])
                ->findOrFail($id),
            'summary' => $this->service->summary($id),
            'candidates' => [],
        ];
    }

    public function linesDataTable(string $reconciliationId): JsonResponse
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
            ->orderColumn('description', 'bank_reconciliation_line.description $1')
            ->orderColumn('debit_minor', 'bank_reconciliation_line.debit_minor $1')
            ->orderColumn('credit_minor', 'bank_reconciliation_line.credit_minor $1')
            ->addColumn('matched_entry', fn (BankReconciliationLine $line): ?array => $line->matchedLedgerEntry ? [
                'id' => $line->matchedLedgerEntry->id,
                'debit_minor' => (int) $line->matchedLedgerEntry->debit_minor,
                'credit_minor' => (int) $line->matchedLedgerEntry->credit_minor,
                'journal_number' => $line->matchedLedgerEntry->journalEntry?->number,
            ] : null)
            ->removeColumn('matched_ledger_entry')
            ->addColumn('actions', fn (): null => null)
            ->toJson();
    }

    public function candidatesDataTable(string $reconciliationId): JsonResponse
    {
        $reconciliation = BankReconciliation::query()
            ->with('bankAccount')
            ->whereKey($reconciliationId)
            ->firstOrFail();

        if (! $reconciliation->bankAccount?->gl_account_id) {
            return DataTables::eloquent(LedgerEntry::query()->whereRaw('1 = 0'))->toJson();
        }

        $matchedEntries = BankReconciliationLine::query()
            ->select('matched_ledger_entry_id')
            ->whereNotNull('matched_ledger_entry_id');
        $query = LedgerEntry::query()
            ->with(['journalEntry', 'journalLine'])
            ->leftJoin('journal_entry as candidate_journal', 'candidate_journal.id', '=', 'ledger_entry.journal_entry_id')
            ->leftJoin('journal_line as candidate_line', 'candidate_line.id', '=', 'ledger_entry.journal_line_id')
            ->where('ledger_entry.account_id', $reconciliation->bankAccount->gl_account_id)
            ->where('ledger_entry.currency', $reconciliation->currency)
            ->whereBetween('ledger_entry.entry_date', [(string) $reconciliation->date_from, (string) $reconciliation->date_to])
            ->whereNotIn('ledger_entry.id', $matchedEntries)
            ->select('ledger_entry.*');

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = "%{$search}%";
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('candidate_line.memo', 'like', $like)
                        ->orWhere('candidate_journal.description', 'like', $like)
                        ->orWhere('candidate_journal.number', 'like', $like)
                        ->orWhere('candidate_journal.reference', 'like', $like)
                        ->orWhereRaw('CAST(ledger_entry.entry_date AS TEXT) LIKE ?', [$like])
                        ->orWhere('ledger_entry.id', 'like', $like);
                });
            })
            ->orderColumn('entry_date', 'ledger_entry.entry_date $1')
            ->orderColumn('journal_number', 'candidate_journal.number $1')
            ->orderColumn('description', 'COALESCE(candidate_line.memo, candidate_journal.description) $1')
            ->orderColumn('amount_minor', '(ledger_entry.debit_minor + ledger_entry.credit_minor) $1')
            ->addColumn('journal_number', fn (LedgerEntry $entry) => $entry->journalEntry?->number)
            ->editColumn('description', fn (LedgerEntry $entry) => $entry->journalLine?->memo ?: $entry->journalEntry?->description ?: $entry->description)
            ->addColumn('amount_minor', fn (LedgerEntry $entry): int => (int) $entry->debit_minor + (int) $entry->credit_minor)
            ->removeColumn('journal_entry')
            ->removeColumn('journal_line')
            ->addColumn('actions', fn (): null => null)
            ->toJson();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $bankAccountId = (string) ($filters['bank_account_id'] ?? '');

        $query = BankReconciliation::query()
            ->join('bank_account', 'bank_account.id', '=', 'bank_reconciliation.bank_account_id')
            ->select([
                'bank_reconciliation.*',
                'bank_account.code as bank_account_code',
                'bank_account.name as bank_account_name',
            ])
            ->when($status !== '', fn ($builder) => $builder->where('bank_reconciliation.status', $status))
            ->when($bankAccountId !== '', fn ($builder) => $builder->where('bank_reconciliation.bank_account_id', $bankAccountId))
            ->orderByDesc('bank_reconciliation.date_from')
            ->orderByDesc('bank_reconciliation.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('bank_account_name', function ($builder, $keyword): void {
                $needle = '%'.mb_strtolower((string) $keyword).'%';
                $builder->where(function ($nested) use ($keyword, $needle): void {
                    $nested->where('bank_account.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(bank_account.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('statement_reference', fn ($builder, $keyword) => $builder->where('bank_reconciliation.statement_reference', 'like', "%{$keyword}%"))
            ->filterColumn('date_from', function ($builder, $keyword): void {
                $builder->where(function ($nested) use ($keyword): void {
                    $nested->whereRaw('CAST(bank_reconciliation.date_from AS TEXT) LIKE ?', ["%{$keyword}%"])
                        ->orWhereRaw('CAST(bank_reconciliation.date_to AS TEXT) LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->orderColumn('bank_account_name', 'bank_account.code $1')
            ->orderColumn('statement_reference', 'bank_reconciliation.statement_reference $1')
            ->orderColumn('date_from', 'bank_reconciliation.date_from $1')
            ->orderColumn('statement_opening_balance_minor', 'bank_reconciliation.statement_opening_balance_minor $1')
            ->orderColumn('statement_closing_balance_minor', 'bank_reconciliation.statement_closing_balance_minor $1')
            ->orderColumn('status', 'bank_reconciliation.status $1')
            ->orderColumn('id', 'bank_reconciliation.id $1')
            ->editColumn('bank_account_name', fn ($row) => $this->decodeTranslations($row->bank_account_name))
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the decoded {en, ar} map.
            ->rawColumns(['bank_account_name', 'bank_account_name.en', 'bank_account_name.ar'])
            ->toJson();
    }

    /**
     * @return Collection<int, BankAccount>
     */
    private function activeBankAccounts(): Collection
    {
        return BankAccount::query()->where('is_active', true)->orderBy('code')->get();
    }

    /**
     * @return Collection<int, FinancialPeriod>
     */
    private function openPeriods(): Collection
    {
        return FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get();
    }

    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
