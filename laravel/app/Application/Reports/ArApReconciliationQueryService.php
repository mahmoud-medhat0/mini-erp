<?php

namespace App\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class ArApReconciliationQueryService
{
    /**
     * Return one signed subledger balance per partner as of the requested day.
     *
     * @param  'receivable'|'payable'  $type
     */
    public function partnerBalances(string $type, string $asOfDate, string $currency): Builder
    {
        $entries = $this->entryBalances($type, $asOfDate, $currency);

        return DB::query()
            ->fromSub($entries, 'reconciliation_entries')
            ->select([
                'reconciliation_entries.partner_id',
                'reconciliation_entries.partner_code',
                'reconciliation_entries.partner_name',
                'reconciliation_entries.currency',
            ])
            ->selectRaw('SUM(reconciliation_entries.open_minor) AS subledger_balance_minor')
            ->groupBy([
                'reconciliation_entries.partner_id',
                'reconciliation_entries.partner_code',
                'reconciliation_entries.partner_name',
                'reconciliation_entries.currency',
            ])
            ->havingRaw('SUM(reconciliation_entries.open_minor) <> 0');
    }

    /** @param  'receivable'|'payable'  $type */
    public function subledgerTotal(string $type, string $asOfDate, string $currency): int
    {
        return (int) DB::query()
            ->fromSub($this->partnerBalances($type, $asOfDate, $currency), 'reconciliation_partners')
            ->sum('subledger_balance_minor');
    }

    public function translatableName(mixed $name): array|string
    {
        if (! is_string($name)) {
            return is_array($name) ? $name : (string) $name;
        }

        $decoded = json_decode($name, true);

        return is_array($decoded) ? $decoded : $name;
    }

    public function localizedName(mixed $name): string
    {
        $translated = $this->translatableName($name);

        if (is_string($translated)) {
            return $translated;
        }

        $locale = app()->getLocale();
        $fallback = reset($translated);

        return (string) ($translated[$locale] ?? $translated['en'] ?? $translated['ar'] ?? ($fallback === false ? '—' : $fallback));
    }

    /**
     * Build one row per AR/AP entry, including positive target entries and
     * negative source entries. Lifecycle aggregates are joined once each.
     *
     * @param  'receivable'|'payable'  $type
     */
    public function entryBalances(string $type, string $asOfDate, string $currency): Builder
    {
        $isReceivable = $type === 'receivable';
        $entryTable = $isReceivable ? 'receivable_entry' : 'payable_entry';
        $allocationTable = $isReceivable ? 'receivable_allocation' : 'payable_allocation';
        $settlementTable = $isReceivable ? 'receivable_entry_settlement' : 'payable_entry_settlement';
        $partnerTable = $isReceivable ? 'customer' : 'supplier';
        $partnerForeignKey = $isReceivable ? 'customer_id' : 'supplier_id';
        $allocationForeignKey = $isReceivable ? 'receivable_entry_id' : 'payable_entry_id';
        $targetSettlementForeignKey = $isReceivable ? 'target_receivable_entry_id' : 'target_payable_entry_id';
        $sourceSettlementForeignKey = $isReceivable ? 'source_receivable_entry_id' : 'source_payable_entry_id';
        $netExpression = $isReceivable
            ? '(reconciliation_entry.debit_minor - reconciliation_entry.credit_minor)'
            : '(reconciliation_entry.credit_minor - reconciliation_entry.debit_minor)';

        $asOf = CarbonImmutable::parse($asOfDate)->startOfDay();
        $cutoff = $asOf->addDay()->format('Y-m-d H:i:s');
        $asOfDay = $asOf->format('Y-m-d');
        $allocations = $this->lifecycleAggregate(
            $allocationTable,
            $allocationForeignKey,
            'allocated_at',
            $cutoff,
            $currency,
            'allocated_minor',
        );
        $targetSettlements = $this->lifecycleAggregate(
            $settlementTable,
            $targetSettlementForeignKey,
            'settled_at',
            $cutoff,
            $currency,
            'target_settled_minor',
        );
        $sourceSettlements = $this->lifecycleAggregate(
            $settlementTable,
            $sourceSettlementForeignKey,
            'settled_at',
            $cutoff,
            $currency,
            'source_settled_minor',
        );
        $openExpression = "CASE WHEN {$netExpression} >= 0 "
            ."THEN {$netExpression} - COALESCE(reconciliation_allocations.allocated_minor, 0) - COALESCE(reconciliation_target_settlements.target_settled_minor, 0) "
            ."ELSE {$netExpression} + COALESCE(reconciliation_source_settlements.source_settled_minor, 0) END";

        return DB::table("{$entryTable} as reconciliation_entry")
            ->join("{$partnerTable} as reconciliation_partner", 'reconciliation_partner.id', '=', "reconciliation_entry.{$partnerForeignKey}")
            ->leftJoinSub($allocations, 'reconciliation_allocations', function (JoinClause $join) use ($allocationForeignKey): void {
                $join->on("reconciliation_allocations.{$allocationForeignKey}", '=', 'reconciliation_entry.id');
            })
            ->leftJoinSub($targetSettlements, 'reconciliation_target_settlements', function (JoinClause $join) use ($targetSettlementForeignKey): void {
                $join->on("reconciliation_target_settlements.{$targetSettlementForeignKey}", '=', 'reconciliation_entry.id');
            })
            ->leftJoinSub($sourceSettlements, 'reconciliation_source_settlements', function (JoinClause $join) use ($sourceSettlementForeignKey): void {
                $join->on("reconciliation_source_settlements.{$sourceSettlementForeignKey}", '=', 'reconciliation_entry.id');
            })
            ->where('reconciliation_entry.currency', $currency)
            ->where('reconciliation_entry.entry_date', '<=', $asOfDay)
            ->select([
                'reconciliation_entry.id as entry_id',
                "reconciliation_entry.{$partnerForeignKey} as partner_id",
                'reconciliation_partner.code as partner_code',
                'reconciliation_partner.name as partner_name',
                'reconciliation_entry.currency',
            ])
            ->selectRaw("({$openExpression}) AS open_minor");
    }

    private function lifecycleAggregate(
        string $table,
        string $foreignKey,
        string $effectiveAt,
        string $cutoff,
        string $currency,
        string $amountAlias,
    ): Builder {
        return DB::table($table)
            ->select($foreignKey)
            ->selectRaw("SUM(amount_minor) AS {$amountAlias}")
            ->where('currency', $currency)
            ->where($effectiveAt, '<', $cutoff)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where(function (Builder $active): void {
                    $active->where('status', 'active')->whereNull('reversed_at');
                })->orWhere(function (Builder $reversed) use ($cutoff): void {
                    $reversed->where('status', 'reversed')->where('reversed_at', '>=', $cutoff);
                });
            })
            ->groupBy($foreignKey);
    }
}
