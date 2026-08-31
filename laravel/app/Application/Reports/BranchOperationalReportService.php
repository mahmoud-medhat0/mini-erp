<?php

namespace App\Application\Reports;

use App\Models\Branch;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchOperationalReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(?string $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $branches = Branch::query()
            ->when($branchId, fn ($query) => $query->where('id', $branchId))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active']);

        $warehouseStats = $this->warehouseStats($branchId);
        $stockStats = $this->stockBalanceStats($branchId);
        $stockMovementStats = $this->stockMovementStats($branchId, $dateFrom, $dateTo);
        $cashStats = $this->cashOrBankStats('cash_account', $branchId, $dateFrom, $dateTo);
        $bankStats = $this->cashOrBankStats('bank_account', $branchId, $dateFrom, $dateTo);
        $assetStats = $this->fixedAssetStats($branchId);
        $assetMovementStats = $this->fixedAssetMovementStats($branchId, $dateFrom, $dateTo);
        $treasuryStats = $this->treasuryTransferStats($branchId, $dateFrom, $dateTo);

        $rows = $branches->map(function (Branch $branch) use (
            $warehouseStats,
            $stockStats,
            $stockMovementStats,
            $cashStats,
            $bankStats,
            $assetStats,
            $assetMovementStats,
            $treasuryStats
        ): array {
            $key = (string) $branch->id;
            $warehouse = $warehouseStats->get($key, $this->emptyRow());
            $stock = $stockStats->get($key, $this->emptyRow());
            $stockMovement = $stockMovementStats->get($key, $this->emptyRow());
            $cash = $cashStats->get($key, $this->emptyRow());
            $bank = $bankStats->get($key, $this->emptyRow());
            $assets = $assetStats->get($key, $this->emptyRow());
            $assetMovements = $assetMovementStats->get($key, $this->emptyMovementRow());
            $treasury = $treasuryStats->get($key, $this->emptyTreasuryRow());

            return [
                'branch_id' => (string) $branch->id,
                'branch_code' => (string) $branch->code,
                'branch_name' => $branch->name,
                'is_active' => (bool) $branch->is_active,
                'warehouse_count' => (int) ($warehouse->record_count ?? 0),
                'stock_balance_rows' => (int) ($stock->record_count ?? 0),
                'stock_quantity_e6' => (int) ($stock->quantity_e6 ?? 0),
                'stock_valuation_minor' => (int) ($stock->amount_minor ?? 0),
                'stock_movement_count' => (int) ($stockMovement->record_count ?? 0),
                'stock_movement_value_minor' => (int) ($stockMovement->amount_minor ?? 0),
                'cash_account_count' => (int) ($cash->record_count ?? 0),
                'cash_balance_minor' => (int) ($cash->amount_minor ?? 0),
                'bank_account_count' => (int) ($bank->record_count ?? 0),
                'bank_balance_minor' => (int) ($bank->amount_minor ?? 0),
                'fixed_asset_count' => (int) ($assets->record_count ?? 0),
                'fixed_asset_cost_minor' => (int) ($assets->amount_minor ?? 0),
                'asset_movement_in_count' => (int) ($assetMovements->in_count ?? 0),
                'asset_movement_out_count' => (int) ($assetMovements->out_count ?? 0),
                'treasury_in_count' => (int) ($treasury->in_count ?? 0),
                'treasury_out_count' => (int) ($treasury->out_count ?? 0),
                'treasury_in_minor' => (int) ($treasury->in_minor ?? 0),
                'treasury_out_minor' => (int) ($treasury->out_minor ?? 0),
                'operational_score' => $this->operationalScore($warehouse, $cash, $bank, $assets),
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'summary' => $this->summary($rows),
            'base_currency' => $this->baseCurrency(),
            'readiness' => [
                'branch_profitability_status' => 'enabled_via_optional_gl_branch_dimension',
                'currency_codes' => $this->currencyCodes($branchId),
                'unassigned_warehouse_count' => $this->unassignedWarehouseCount(),
                'unassigned_stock_balance_rows' => $this->unassignedStockBalanceRows(),
                'unassigned_stock_valuation_minor' => $this->unassignedStockValuationMinor(),
                'unassigned_cash_account_count' => $this->unassignedAccountCount('cash_account'),
                'unassigned_bank_account_count' => $this->unassignedAccountCount('bank_account'),
                'unassigned_fixed_asset_count' => $this->unassignedFixedAssetCount(),
            ],
        ];
    }

    private function warehouseStats(?string $branchId): Collection
    {
        return $this->keyByBranch(DB::table('warehouse')
            ->select('branch_id')
            ->selectRaw('COUNT(*) as record_count')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('branch_id')
            ->get());
    }

    private function stockBalanceStats(?string $branchId): Collection
    {
        return $this->keyByBranch(DB::table('stock_balance')
            ->join('warehouse', 'warehouse.id', '=', 'stock_balance.warehouse_id')
            ->select('warehouse.branch_id')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COALESCE(SUM(stock_balance.quantity_e6), 0) as quantity_e6')
            ->selectRaw('COALESCE(SUM(stock_balance.valuation_amount_minor), 0) as amount_minor')
            ->when($branchId, fn ($query) => $query->where('warehouse.branch_id', $branchId))
            ->groupBy('warehouse.branch_id')
            ->get());
    }

    private function stockMovementStats(?string $branchId, ?string $dateFrom, ?string $dateTo): Collection
    {
        return $this->keyByBranch(DB::table('stock_movement_ledger')
            ->join('warehouse', 'warehouse.id', '=', 'stock_movement_ledger.warehouse_id')
            ->select('warehouse.branch_id')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COALESCE(SUM(stock_movement_ledger.value_delta_minor), 0) as amount_minor')
            ->when($branchId, fn ($query) => $query->where('warehouse.branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->where('stock_movement_ledger.movement_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('stock_movement_ledger.movement_date', '<=', $dateTo))
            ->groupBy('warehouse.branch_id')
            ->get());
    }

    private function cashOrBankStats(string $table, ?string $branchId, ?string $dateFrom, ?string $dateTo): Collection
    {
        return $this->keyByBranch(DB::table($table)
            ->leftJoin('ledger_entry', function (JoinClause $join) use ($table, $dateFrom, $dateTo): void {
                $join->on('ledger_entry.account_id', '=', "{$table}.gl_account_id");

                if ($dateFrom) {
                    $join->where('ledger_entry.entry_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $join->where('ledger_entry.entry_date', '<=', $dateTo);
                }
            })
            ->select("{$table}.branch_id")
            ->selectRaw("COUNT(DISTINCT {$table}.id) as record_count")
            ->selectRaw('COALESCE(SUM(ledger_entry.debit_minor - ledger_entry.credit_minor), 0) as amount_minor')
            ->when($branchId, fn ($query) => $query->where("{$table}.branch_id", $branchId))
            ->groupBy("{$table}.branch_id")
            ->get());
    }

    private function fixedAssetStats(?string $branchId): Collection
    {
        return $this->keyByBranch(DB::table('fixed_asset')
            ->select('branch_id')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COALESCE(SUM(cost_minor), 0) as amount_minor')
            ->where('status', '!=', 'disposed')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('branch_id')
            ->get());
    }

    private function fixedAssetMovementStats(?string $branchId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $incoming = DB::table('fixed_asset_movement')
            ->select('to_branch_id as branch_id')
            ->selectRaw('COUNT(*) as in_count')
            ->when($branchId, fn ($query) => $query->where('to_branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->where('movement_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('movement_date', '<=', $dateTo))
            ->groupBy('to_branch_id')
            ->get();

        $outgoing = DB::table('fixed_asset_movement')
            ->select('from_branch_id as branch_id')
            ->selectRaw('COUNT(*) as out_count')
            ->when($branchId, fn ($query) => $query->where('from_branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->where('movement_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('movement_date', '<=', $dateTo))
            ->groupBy('from_branch_id')
            ->get();

        return $this->mergeMovementRows($incoming, $outgoing);
    }

    private function treasuryTransferStats(?string $branchId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $incoming = DB::table('treasury_transfer')
            ->select('destination_branch_id as branch_id')
            ->selectRaw('COUNT(*) as in_count')
            ->selectRaw('COALESCE(SUM(amount_minor), 0) as in_minor')
            ->where('status', 'posted')
            ->when($branchId, fn ($query) => $query->where('destination_branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->where('transfer_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('transfer_date', '<=', $dateTo))
            ->groupBy('destination_branch_id')
            ->get();

        $outgoing = DB::table('treasury_transfer')
            ->select('source_branch_id as branch_id')
            ->selectRaw('COUNT(*) as out_count')
            ->selectRaw('COALESCE(SUM(amount_minor), 0) as out_minor')
            ->where('status', 'posted')
            ->when($branchId, fn ($query) => $query->where('source_branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->where('transfer_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('transfer_date', '<=', $dateTo))
            ->groupBy('source_branch_id')
            ->get();

        return $this->mergeTreasuryRows($incoming, $outgoing);
    }

    private function summary(Collection $rows): array
    {
        return [
            'branch_count' => $rows->count(),
            'warehouse_count' => (int) $rows->sum('warehouse_count'),
            'stock_quantity_e6' => (int) $rows->sum('stock_quantity_e6'),
            'stock_valuation_minor' => (int) $rows->sum('stock_valuation_minor'),
            'stock_movement_value_minor' => (int) $rows->sum('stock_movement_value_minor'),
            'cash_balance_minor' => (int) $rows->sum('cash_balance_minor'),
            'bank_balance_minor' => (int) $rows->sum('bank_balance_minor'),
            'fixed_asset_count' => (int) $rows->sum('fixed_asset_count'),
            'fixed_asset_cost_minor' => (int) $rows->sum('fixed_asset_cost_minor'),
            'treasury_in_minor' => (int) $rows->sum('treasury_in_minor'),
            'treasury_out_minor' => (int) $rows->sum('treasury_out_minor'),
        ];
    }

    private function keyByBranch(Collection $rows): Collection
    {
        return $rows->keyBy(fn ($row) => (string) ($row->branch_id ?? ''));
    }

    private function mergeMovementRows(Collection $incoming, Collection $outgoing): Collection
    {
        $rows = collect();

        foreach ($incoming as $row) {
            $key = (string) ($row->branch_id ?? '');
            $rows->put($key, (object) [
                'in_count' => (int) ($row->in_count ?? 0),
                'out_count' => 0,
            ]);
        }

        foreach ($outgoing as $row) {
            $key = (string) ($row->branch_id ?? '');
            $current = $rows->get($key, (object) ['in_count' => 0, 'out_count' => 0]);
            $current->out_count = (int) ($row->out_count ?? 0);
            $rows->put($key, $current);
        }

        return $rows;
    }

    private function mergeTreasuryRows(Collection $incoming, Collection $outgoing): Collection
    {
        $rows = collect();

        foreach ($incoming as $row) {
            $key = (string) ($row->branch_id ?? '');
            $rows->put($key, (object) [
                'in_count' => (int) ($row->in_count ?? 0),
                'out_count' => 0,
                'in_minor' => (int) ($row->in_minor ?? 0),
                'out_minor' => 0,
            ]);
        }

        foreach ($outgoing as $row) {
            $key = (string) ($row->branch_id ?? '');
            $current = $rows->get($key, (object) ['in_count' => 0, 'out_count' => 0, 'in_minor' => 0, 'out_minor' => 0]);
            $current->out_count = (int) ($row->out_count ?? 0);
            $current->out_minor = (int) ($row->out_minor ?? 0);
            $rows->put($key, $current);
        }

        return $rows;
    }

    private function operationalScore(object $warehouse, object $cash, object $bank, object $assets): string
    {
        $hasWarehouse = (int) ($warehouse->record_count ?? 0) > 0;
        $hasTreasury = ((int) ($cash->record_count ?? 0) + (int) ($bank->record_count ?? 0)) > 0;
        $hasAssets = (int) ($assets->record_count ?? 0) > 0;

        if ($hasWarehouse && $hasTreasury && $hasAssets) {
            return 'ready';
        }

        if ($hasWarehouse || $hasTreasury || $hasAssets) {
            return 'partial';
        }

        return 'not_configured';
    }

    private function emptyRow(): object
    {
        return (object) [
            'record_count' => 0,
            'quantity_e6' => 0,
            'amount_minor' => 0,
        ];
    }

    private function emptyMovementRow(): object
    {
        return (object) [
            'in_count' => 0,
            'out_count' => 0,
        ];
    }

    private function emptyTreasuryRow(): object
    {
        return (object) [
            'in_count' => 0,
            'out_count' => 0,
            'in_minor' => 0,
            'out_minor' => 0,
        ];
    }

    private function unassignedWarehouseCount(): int
    {
        return (int) DB::table('warehouse')->whereNull('branch_id')->count();
    }

    private function unassignedStockBalanceRows(): int
    {
        return (int) DB::table('stock_balance')
            ->join('warehouse', 'warehouse.id', '=', 'stock_balance.warehouse_id')
            ->whereNull('warehouse.branch_id')
            ->count();
    }

    private function unassignedStockValuationMinor(): int
    {
        return (int) DB::table('stock_balance')
            ->join('warehouse', 'warehouse.id', '=', 'stock_balance.warehouse_id')
            ->whereNull('warehouse.branch_id')
            ->sum('stock_balance.valuation_amount_minor');
    }

    private function unassignedAccountCount(string $table): int
    {
        return (int) DB::table($table)->whereNull('branch_id')->count();
    }

    private function unassignedFixedAssetCount(): int
    {
        return (int) DB::table('fixed_asset')
            ->whereNull('branch_id')
            ->where('status', '!=', 'disposed')
            ->count();
    }

    private function baseCurrency(): string
    {
        return $this->currencyResolver->resolve();
    }

    private function currencyCodes(?string $branchId): array
    {
        $codes = collect();

        $codes = $codes->merge(DB::table('stock_balance')
            ->join('warehouse', 'warehouse.id', '=', 'stock_balance.warehouse_id')
            ->when($branchId, fn ($query) => $query->where('warehouse.branch_id', $branchId))
            ->pluck('stock_balance.currency'));

        foreach (['cash_account', 'bank_account', 'fixed_asset'] as $table) {
            $codes = $codes->merge(DB::table($table)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->pluck('currency'));
        }

        $codes = $codes->merge(DB::table('treasury_transfer')
            ->when($branchId, function ($query) use ($branchId): void {
                $query->where(function ($branchQuery) use ($branchId): void {
                    $branchQuery
                        ->where('source_branch_id', $branchId)
                        ->orWhere('destination_branch_id', $branchId);
                });
            })
            ->pluck('currency'));

        return $codes
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
