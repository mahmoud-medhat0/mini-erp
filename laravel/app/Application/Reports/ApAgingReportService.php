<?php

namespace App\Application\Reports;

use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use Illuminate\Support\Carbon;

class ApAgingReportService
{
    public function generate(string $asOfDate, ?string $supplierId = null, ?string $currency = null): array
    {
        $targetCurrency = $currency ?? 'EGP';
        $asOf = Carbon::parse($asOfDate)->startOfDay();

        $query = PayableEntry::query()
            ->with('supplier')
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<=', $asOfDate);

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

            $allocatedSum = (int) PayableAllocation::query()
                ->where('payable_entry_id', $entry->id)
                ->where('status', 'active')
                ->where('created_at', '<=', $asOfDate.' 23:59:59')
                ->sum('amount_minor');

            $unappliedMinor = $origNet - $allocatedSum;

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
