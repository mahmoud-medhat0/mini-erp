<?php

namespace App\Application\Accounting;

use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\ReceivableAllocation;
use App\Models\ReceivableEntry;

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
                $openReceivables = ReceivableEntry::query()
                    ->where('customer_id', $selectedReceipt->customer_id)
                    ->where('currency', $selectedReceipt->currency)
                    ->where('unapplied_minor', '>', 0)
                    ->orderBy('entry_date', 'asc')
                    ->get();
            }
        }

        return [
            'receipts' => $receiptsQuery->orderBy('created_at', 'desc')->get(),
            'selectedReceipt' => $selectedReceipt,
            'openReceivables' => $openReceivables,
            'existingAllocations' => ReceivableAllocation::query()
                ->with(['customerReceipt', 'receivableEntry', 'customer'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'filters' => [
                'customer_id' => $customerId,
                'receipt_id' => $receiptId,
            ],
        ];
    }
}
