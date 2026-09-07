<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\TreasuryTransfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class TreasuryTransferPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     cashAccounts: EloquentCollection<int, CashAccount>,
     *     bankAccounts: EloquentCollection<int, BankAccount>,
     *     fiscalYears: EloquentCollection<int, FiscalYear>,
     *     financialPeriods: EloquentCollection<int, FinancialPeriod>,
     *     statuses: array<int, string>,
     *     filters: array{search: mixed, status: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
        ];

        return [
            'cashAccounts' => $this->activeCashAccounts(),
            'bankAccounts' => $this->activeBankAccounts(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('year')->get(['id', 'year', 'status']),
            'financialPeriods' => FinancialPeriod::query()->orderBy('start_date')->get(['id', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'status']),
            'statuses' => TreasuryTransferService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * Server-side DataTables feed for treasury transfers.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['query'] ?? ''));

        $query = TreasuryTransfer::query()
            ->with([
                'sourceCashAccount.branch',
                'sourceBankAccount.branch',
                'destinationCashAccount.branch',
                'destinationBankAccount.branch',
                'sourceBranch',
                'destinationBranch',
                'journalEntry',
            ])
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested->where('treasury_transfer.number', 'like', "%{$search}%")
                        ->orWhere('treasury_transfer.reference', 'like', "%{$search}%")
                        ->orWhere('treasury_transfer.description', 'like', "%{$search}%");
                });
            })
            ->when(
                $status !== '' && in_array($status, TreasuryTransferService::ALLOWED_STATUSES, true),
                fn (Builder $builder) => $builder->where('treasury_transfer.status', $status)
            )
            ->orderByDesc('treasury_transfer.transfer_date')
            ->orderByDesc('treasury_transfer.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $builder, string $keyword): void {
                $builder->where(function (Builder $nested) use ($keyword): void {
                    $nested->where('treasury_transfer.number', 'like', "%{$keyword}%")
                        ->orWhere('treasury_transfer.reference', 'like', "%{$keyword}%")
                        ->orWhere('treasury_transfer.description', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('transfer_date', fn (Builder $builder, string $keyword) => $builder->whereRaw('CAST(treasury_transfer.transfer_date AS TEXT) LIKE ?', ["%{$keyword}%"]))
            ->filterColumn('source_label', fn (Builder $builder, string $keyword) => $this->filterEndpoint($builder, 'source', $keyword))
            ->filterColumn('destination_label', fn (Builder $builder, string $keyword) => $this->filterEndpoint($builder, 'destination', $keyword))
            ->orderColumn('number', 'treasury_transfer.number $1')
            ->orderColumn('transfer_date', 'treasury_transfer.transfer_date $1')
            ->orderColumn('amount_minor', 'treasury_transfer.amount_minor $1')
            ->orderColumn('status', 'treasury_transfer.status $1')
            ->orderColumn('id', 'treasury_transfer.id $1')
            ->addColumn('source_label', fn (TreasuryTransfer $row) => $this->endpointCode($row, 'source'))
            ->addColumn('destination_label', fn (TreasuryTransfer $row) => $this->endpointCode($row, 'destination'))
            ->toJson();
    }

    private function filterEndpoint(Builder $builder, string $side, string $keyword): void
    {
        $cashRelation = $side === 'source' ? 'sourceCashAccount' : 'destinationCashAccount';
        $bankRelation = $side === 'source' ? 'sourceBankAccount' : 'destinationBankAccount';
        $needle = '%'.mb_strtolower($keyword).'%';

        $builder->where(function (Builder $nested) use ($cashRelation, $bankRelation, $keyword, $needle): void {
            $nested->whereHas($cashRelation, function (Builder $account) use ($keyword, $needle): void {
                $account->where('code', 'like', "%{$keyword}%")
                    ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$needle])
                    ->orWhereHas('branch', fn (Builder $branch) => $branch->where('code', 'like', "%{$keyword}%"));
            })->orWhereHas($bankRelation, function (Builder $account) use ($keyword, $needle): void {
                $account->where('code', 'like', "%{$keyword}%")
                    ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$needle])
                    ->orWhereHas('branch', fn (Builder $branch) => $branch->where('code', 'like', "%{$keyword}%"));
            });
        });
    }

    private function endpointCode(TreasuryTransfer $transfer, string $side): ?string
    {
        if ($side === 'source') {
            return $transfer->source_type === 'cash'
                ? $transfer->sourceCashAccount?->code
                : $transfer->sourceBankAccount?->code;
        }

        return $transfer->destination_type === 'cash'
            ? $transfer->destinationCashAccount?->code
            : $transfer->destinationBankAccount?->code;
    }

    /**
     * @return EloquentCollection<int, CashAccount>
     */
    private function activeCashAccounts(): EloquentCollection
    {
        return CashAccount::query()
            ->where('is_active', true)
            ->with(['branch', 'glAccount'])
            ->orderBy('code')
            ->get();
    }

    /**
     * @return EloquentCollection<int, BankAccount>
     */
    private function activeBankAccounts(): EloquentCollection
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->with(['branch', 'glAccount'])
            ->orderBy('code')
            ->get();
    }
}
