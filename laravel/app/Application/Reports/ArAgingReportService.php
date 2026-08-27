<?php

namespace App\Application\Reports;

use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use Illuminate\Support\Carbon;

class ArAgingReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $asOfDate, ?string $customerId = null, ?string $currency = null): array
    {
        $targetCurrency = $this->currencyResolver->resolve($currency);
        $asOf = Carbon::parse($asOfDate)->startOfDay();

        $query = ReceivableEntry::query()
            ->with('customer')
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<=', $asOfDate.' 23:59:59');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $entries = $query->get();

        $customerGroups = [];
        $grandTotals = [
            'current' => 0,
            'b1_30' => 0,
            'b31_60' => 0,
            'b61_90' => 0,
            'over_90' => 0,
            'total' => 0,
        ];

        foreach ($entries as $entry) {
            $origNet = (int) $entry->debit_minor - (int) $entry->credit_minor;
            if ($origNet <= 0) {
                continue;
            }

            $allocatedSum = (int) ReceivableAllocation::query()
                ->where('receivable_entry_id', $entry->id)
                ->where('status', 'active')
                ->where('created_at', '<=', $asOfDate.' 23:59:59')
                ->sum('amount_minor');

            $settledSum = (int) ReceivableEntrySettlement::query()
                ->where('target_receivable_entry_id', $entry->id)
                ->where('status', 'active')
                ->where('settled_at', '<=', Carbon::parse($asOfDate)->endOfDay())
                ->sum('amount_minor');

            $unappliedMinor = $origNet - $allocatedSum - $settledSum;

            if ($unappliedMinor <= 0) {
                continue;
            }

            $cId = $entry->customer_id;

            if (! isset($customerGroups[$cId])) {
                $customerGroups[$cId] = [
                    'customer' => [
                        'id' => $entry->customer?->id ?? $cId,
                        'code' => $entry->customer?->code ?? '',
                        'name' => $entry->customer?->name ?? 'Unknown Customer',
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

            $customerGroups[$cId]['items'][] = [
                'id' => $entry->id,
                'reference' => 'RE-'.$entry->id,
                'entry_date' => $entry->entry_date,
                'due_date' => $entry->due_date,
                'basis_used' => $entry->due_date ? 'due_date' : 'entry_date_basis',
                'age_days' => max(0, (int) $ageDays),
                'original_amount_minor' => $origNet,
                'allocated_minor' => $allocatedSum,
                'unapplied_minor' => $unappliedMinor,
                'bucket' => $bucket,
            ];

            $customerGroups[$cId]['totals'][$bucket] += $unappliedMinor;
            $customerGroups[$cId]['totals']['total'] += $unappliedMinor;

            $grandTotals[$bucket] += $unappliedMinor;
            $grandTotals['total'] += $unappliedMinor;
        }

        return [
            'as_of_date' => $asOfDate,
            'currency' => $targetCurrency,
            'customers' => array_values($customerGroups),
            'grand_totals' => $grandTotals,
        ];
    }
}
