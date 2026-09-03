<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class SupplierPaymentPageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }

    /**
     * Server-side DataTables feed for supplier payments grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = SupplierPayment::query()
            ->join('supplier', 'supplier.id', '=', 'supplier_payment.supplier_id')
            ->leftJoin('cash_account', 'cash_account.id', '=', 'supplier_payment.cash_account_id')
            ->leftJoin('bank_account', 'bank_account.id', '=', 'supplier_payment.bank_account_id')
            ->select([
                'supplier_payment.id',
                'supplier_payment.number',
                'supplier_payment.supplier_id',
                'supplier_payment.payment_date',
                'supplier_payment.reference',
                'supplier_payment.description',
                'supplier_payment.cash_account_id',
                'supplier_payment.bank_account_id',
                'supplier_payment.currency',
                'supplier_payment.amount_minor',
                'supplier_payment.allocated_minor',
                'supplier_payment.unapplied_minor',
                'supplier_payment.status',
                'supplier_payment.posted_at',
                'supplier_payment.created_at',
                'supplier.code as supplier_code',
                'supplier.name as supplier_name',
                'cash_account.code as cash_account_code',
                'cash_account.name as cash_account_name',
                'bank_account.code as bank_account_code',
                'bank_account.name as bank_account_name',
            ])
            ->when(
                in_array($status, ['draft', 'posted'], true),
                fn ($q) => $q->where('supplier_payment.status', $status),
            )
            ->orderBy('supplier_payment.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('number', fn ($q, $kw) => $q->where('supplier_payment.number', 'like', "%{$kw}%"))
            ->filterColumn('supplier_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('supplier.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('supplier_name', 'supplier.code $1')
            ->editColumn('supplier_name', fn ($row) => $this->decodeTranslations($row->supplier_name))
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
