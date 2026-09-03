<?php

namespace App\Application\Accounting;

use App\Models\PayableAllocation;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'filters' => [
                'supplier_id' => $supplierId,
                'payment_id' => $paymentId,
            ],
        ];
    }

    /**
     * Server-side DataTables feed for payable allocations history grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = PayableAllocation::query()
            ->join('supplier', 'supplier.id', '=', 'payable_allocation.supplier_id')
            ->leftJoin('supplier_payment', 'supplier_payment.id', '=', 'payable_allocation.supplier_payment_id')
            ->select([
                'payable_allocation.id',
                'payable_allocation.supplier_id',
                'payable_allocation.supplier_payment_id',
                'payable_allocation.payable_entry_id',
                'payable_allocation.currency',
                'payable_allocation.amount_minor',
                'payable_allocation.status',
                'payable_allocation.created_at',
                'supplier.code as supplier_code',
                'supplier.name as supplier_name',
                'supplier_payment.number as payment_number',
            ])
            ->orderBy('payable_allocation.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('payment_number', fn ($q, $kw) => $q->where('supplier_payment.number', 'like', "%{$kw}%"))
            ->filterColumn('supplier_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('supplier.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('supplier_name', 'supplier.code $1')
            ->editColumn('supplier_name', fn ($row) => $this->decodeTranslations($row->supplier_name))
            ->toJson();
    }

    /**
     * Spatie stores translations as a JSON column; hand the client the decoded
     * map so it can pick the active locale.
     */
    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
