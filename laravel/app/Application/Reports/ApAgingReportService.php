<?php

namespace App\Application\Reports;

use App\Models\PayableEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ApAgingReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $asOfDate, ?string $supplierId = null, ?string $currency = null): array
    {
        $targetCurrency = $this->currencyResolver->resolve($currency);
        $asOf = Carbon::parse($asOfDate)->startOfDay();
        $asOfEnd = $asOf->copy()->endOfDay();
        $cutoff = $asOf->copy()->addDay();

        $query = PayableEntry::query()
            ->with('supplier')
            ->withSum(['allocations as allocated_as_of_minor' => function (Builder $query) use ($cutoff): void {
                $query
                    ->where('allocated_at', '<', $cutoff)
                    ->where(function (Builder $lifecycle) use ($cutoff): void {
                        $lifecycle->where(function (Builder $active): void {
                            $active->where('status', 'active')->whereNull('reversed_at');
                        })->orWhere(function (Builder $reversed) use ($cutoff): void {
                            $reversed->where('status', 'reversed')->where('reversed_at', '>=', $cutoff);
                        });
                    });
            }], 'amount_minor')
            ->withSum(['targetSettlements as settled_as_of_minor' => function (Builder $query) use ($cutoff): void {
                $query
                    ->where('settled_at', '<', $cutoff)
                    ->where(function (Builder $lifecycle) use ($cutoff): void {
                        $lifecycle->where(function (Builder $active): void {
                            $active->where('status', 'active')->whereNull('reversed_at');
                        })->orWhere(function (Builder $reversed) use ($cutoff): void {
                            $reversed->where('status', 'reversed')->where('reversed_at', '>=', $cutoff);
                        });
                    });
            }], 'amount_minor')
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<=', $asOfEnd)
            ->whereColumn('credit_minor', '>', 'debit_minor');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $entries = $query->get();

        $supplierGroups = [];
        $grandTotals = [
            'current' => 0,
            'b1_30' => 0,
            'b31_60' => 0,
            'b61_90' => 0,
            'over_90' => 0,
            'total' => 0,
        ];

        foreach ($entries as $entry) {
            $origNet = (int) $entry->credit_minor - (int) $entry->debit_minor;
            if ($origNet <= 0) {
                continue;
            }

            $allocatedSum = (int) ($entry->allocated_as_of_minor ?? 0);
            $settledSum = (int) ($entry->settled_as_of_minor ?? 0);

            $unappliedMinor = $origNet - $allocatedSum - $settledSum;

            if ($unappliedMinor <= 0) {
                continue;
            }

            $sId = $entry->supplier_id;

            if (! isset($supplierGroups[$sId])) {
                $supplierGroups[$sId] = [
                    'supplier' => [
                        'id' => $entry->supplier?->id ?? $sId,
                        'code' => $entry->supplier?->code ?? '',
                        'name' => $entry->supplier?->name ?? 'Unknown Supplier',
                    ],
                    'items' => [],
                    'totals' => [
                        'current' => 0,
                        'b1_30' => 0,
                        'b31_60' => 0,
                        'b61_90' => 0,
                        'over_90' => 0,
                        'total' => 0,
                    ],
                ];
            }

            // Calculate age in days relative to asOfDate
            $basisDateStr = $entry->due_date ?? $entry->entry_date;
            $basisDate = Carbon::parse($basisDateStr)->startOfDay();
            $ageDays = $basisDate->diffInDays($asOf, false);

            $bucket = 'current';
            if ($ageDays <= 0) {
                $bucket = 'current';
            } elseif ($ageDays <= 30) {
                $bucket = 'b1_30';
            } elseif ($ageDays <= 60) {
                $bucket = 'b31_60';
            } elseif ($ageDays <= 90) {
                $bucket = 'b61_90';
            } else {
                $bucket = 'over_90';
            }

            $supplierGroups[$sId]['items'][] = [
                'id' => $entry->id,
                'reference' => 'PE-'.$entry->id,
                'entry_date' => $entry->entry_date,
                'due_date' => $entry->due_date,
                'basis_used' => $entry->due_date ? 'due_date' : 'entry_date_basis',
                'age_days' => max(0, (int) $ageDays),
                'original_amount_minor' => $origNet,
                'allocated_minor' => $allocatedSum,
                'unapplied_minor' => $unappliedMinor,
                'bucket' => $bucket,
            ];

            $supplierGroups[$sId]['totals'][$bucket] += $unappliedMinor;
            $supplierGroups[$sId]['totals']['total'] += $unappliedMinor;

            $grandTotals[$bucket] += $unappliedMinor;
            $grandTotals['total'] += $unappliedMinor;
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $targetCurrency,
            'suppliers' => array_values($supplierGroups),
            'grand_totals' => $grandTotals,
        ];
    }
}
