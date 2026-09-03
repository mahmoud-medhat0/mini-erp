<?php

namespace App\Application\Accounting;

use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ReceivableAllocationPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $customerId = $filters['customer_id'] ?? null;
        $receiptId = $filters['receipt_id'] ?? null;

        $receiptsQuery = CustomerReceipt::query()
            ->with('customer')
            ->where('status', 'posted')
            ->where('unapplied_minor', '>', 0);

        if ($customerId) {
            $receiptsQuery->where('customer_id', $customerId);
        }

        $selectedReceipt = null;
        $openReceivables = [];

        if ($receiptId) {
            $selectedReceipt = CustomerReceipt::query()->with('customer')->find($receiptId);

            if ($selectedReceipt) {
                $entries = ReceivableEntry::query()
                    ->where('customer_id', $selectedReceipt->customer_id)
                    ->where('currency', $selectedReceipt->currency)
                    ->whereRaw('debit_minor > credit_minor')
                    ->orderBy('entry_date', 'asc')
                    ->get();

                $activeAllocations = ReceivableAllocation::query()
                    ->whereIn('receivable_entry_id', $entries->pluck('id'))
                    ->where('status', 'active')
                    ->selectRaw('receivable_entry_id, SUM(amount_minor) as total_allocated')
                    ->groupBy('receivable_entry_id')
                    ->pluck('total_allocated', 'receivable_entry_id');

                $openReceivables = $entries->map(function (ReceivableEntry $entry) use ($activeAllocations): array {
                    $originalMinor = max(0, $entry->debit_minor - $entry->credit_minor);
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
            'receipts' => $receiptsQuery->orderBy('created_at', 'desc')->get(),
            'selectedReceipt' => $selectedReceipt,
            'openReceivables' => $openReceivables,
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'filters' => [
                'customer_id' => $customerId,
                'receipt_id' => $receiptId,
            ],
        ];
    }

    /**
     * Server-side DataTables feed for receivable allocations history grid.
     *
     * @param array<string, mixed> $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = ReceivableAllocation::query()
            ->join('customer', 'customer.id', '=', 'receivable_allocation.customer_id')
            ->leftJoin('customer_receipt', 'customer_receipt.id', '=', 'receivable_allocation.customer_receipt_id')
            ->select([
                'receivable_allocation.id',
                'receivable_allocation.customer_id',
                'receivable_allocation.customer_receipt_id',
                'receivable_allocation.receivable_entry_id',
                'receivable_allocation.currency',
                'receivable_allocation.amount_minor',
                'receivable_allocation.status',
                'receivable_allocation.created_at',
                'customer.code as customer_code',
                'customer.name as customer_name',
                'customer_receipt.number as receipt_number',
            ])
            ->orderBy('receivable_allocation.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('receipt_number', fn ($q, $kw) => $q->where('customer_receipt.number', 'like', "%{$kw}%"))
            ->filterColumn('customer_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'customer.code $1')
            ->editColumn('customer_name', fn ($row) => $this->decodeTranslations($row->customer_name))
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
