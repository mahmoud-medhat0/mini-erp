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
                $openPayables = PayableEntry::query()
                    ->where('supplier_id', $selectedPayment->supplier_id)
                    ->where('currency', $selectedPayment->currency)
                    ->where('unapplied_minor', '>', 0)
                    ->orderBy('entry_date', 'asc')
                    ->get();
            }
        }

        return [
            'payments' => $paymentsQuery->orderBy('created_at', 'desc')->get(),
            'selectedPayment' => $selectedPayment,
            'openPayables' => $openPayables,
            'existingAllocations' => PayableAllocation::query()
                ->with(['supplierPayment', 'payableEntry', 'supplier'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'filters' => [
                'supplier_id' => $supplierId,
                'payment_id' => $paymentId,
            ],
        ];
    }
}
