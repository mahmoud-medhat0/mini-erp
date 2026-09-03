<?php

namespace App\Application\Accounting;

use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierPayment;

class PayableAllocationPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $paymentId = $filters['payment_id'] ?? null;

        $paymentsQuery = SupplierPayment::query()
            ->with('supplier')
            ->where('status', 'posted')
            ->where('unapplied_minor', '>', 0);

        if ($supplierId) {
            $paymentsQuery->where('supplier_id', $supplierId);
        }

        $selectedPayment = null;
        $openPayables = [];

        if ($paymentId) {
            $selectedPayment = SupplierPayment::query()->with('supplier')->find($paymentId);

            if ($selectedPayment) {
                $entries = PayableEntry::query()
                    ->where('supplier_id', $selectedPayment->supplier_id)
                    ->where('currency', $selectedPayment->currency)
                    ->whereRaw('credit_minor > debit_minor')
                    ->orderBy('entry_date', 'asc')
                    ->get();

                $activeAllocations = PayableAllocation::query()
                    ->whereIn('payable_entry_id', $entries->pluck('id'))
                    ->where('status', 'active')
                    ->selectRaw('payable_entry_id, SUM(amount_minor) as total_allocated')
                    ->groupBy('payable_entry_id')
                    ->pluck('total_allocated', 'payable_entry_id');

                $openPayables = $entries->map(function (PayableEntry $entry) use ($activeAllocations): array {
                    $originalMinor = max(0, $entry->credit_minor - $entry->debit_minor);
                    $allocatedMinor = (int) ($activeAllocations[$entry->id] ?? 0);
                    $unappliedMinor = max(0, $originalMinor - $allocatedMinor);

                    return array_merge($entry->toArray(), [
                        'original_amount_minor' => $originalMinor,
                        'unapplied_minor' => $unappliedMinor,
                        'remaining_minor' => $unappliedMinor,
                    ]);
                })
                ->filter(fn (array $entry): bool => $entry['unapplied_minor'] > 0)
                ->values()
                ->toArray();
            }
        }

        return [
            'payments' => $paymentsQuery->orderBy('created_at', 'desc')->get(),
            'selectedPayment' => $selectedPayment,
            'openPayables' => $openPayables,
            'existingAllocations' => PayableAllocation::query()
                ->with(['supplierPayment', 'payableEntry', 'supplier'])
                ->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString(),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'filters' => [
                'supplier_id' => $supplierId,
                'payment_id' => $paymentId,
            ],
        ];
    }
}
