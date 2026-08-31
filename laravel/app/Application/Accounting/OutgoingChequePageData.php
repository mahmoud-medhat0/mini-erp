<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\OutgoingCheque;
use App\Models\Supplier;

class OutgoingChequePageData
{
    public function period(string $periodId): FinancialPeriod
    {
        return FinancialPeriod::query()->whereKey($periodId)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $status = $filters['status'] ?? null;
        $supplierId = $filters['supplier_id'] ?? null;

        $query = OutgoingCheque::query()
            ->with(['supplier', 'bankAccount']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return [
            'cheques' => $query->orderBy('due_date', 'asc')
                ->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString(),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'filters' => [
                'status' => $status,
                'supplier_id' => $supplierId,
            ],
        ];
    }
}
