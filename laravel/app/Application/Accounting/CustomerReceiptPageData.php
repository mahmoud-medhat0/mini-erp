<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class CustomerReceiptPageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }

    /**
     * Server-side DataTables feed for customer receipts grid.
     *
     * @param array<string, mixed> $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = CustomerReceipt::query()
            ->join('customer', 'customer.id', '=', 'customer_receipt.customer_id')
            ->leftJoin('cash_account', 'cash_account.id', '=', 'customer_receipt.cash_account_id')
            ->leftJoin('bank_account', 'bank_account.id', '=', 'customer_receipt.bank_account_id')
            ->select([
                'customer_receipt.id',
                'customer_receipt.number',
                'customer_receipt.customer_id',
                'customer_receipt.receipt_date',
                'customer_receipt.reference',
                'customer_receipt.description',
                'customer_receipt.cash_account_id',
                'customer_receipt.bank_account_id',
                'customer_receipt.currency',
                'customer_receipt.amount_minor',
                'customer_receipt.allocated_minor',
                'customer_receipt.unapplied_minor',
                'customer_receipt.status',
                'customer_receipt.posted_at',
                'customer_receipt.created_at',
                'customer.code as customer_code',
                'customer.name as customer_name',
                'cash_account.code as cash_account_code',
                'cash_account.name as cash_account_name',
                'bank_account.code as bank_account_code',
                'bank_account.name as bank_account_name',
            ])
            ->when(
                in_array($status, ['draft', 'posted'], true),
                fn ($q) => $q->where('customer_receipt.status', $status),
            )
            ->orderBy('customer_receipt.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('number', fn ($q, $kw) => $q->where('customer_receipt.number', 'like', "%{$kw}%"))
            ->filterColumn('customer_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'customer.code $1')
            ->editColumn('customer_name', fn ($row) => $this->decodeTranslations($row->customer_name))
            ->editColumn('cash_account_name', fn ($row) => $this->decodeTranslations($row->cash_account_name))
            ->editColumn('bank_account_name', fn ($row) => $this->decodeTranslations($row->bank_account_name))
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
