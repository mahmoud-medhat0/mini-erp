<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\OutgoingCheque;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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

        return [
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

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $supplierId = (string) ($filters['supplier_id'] ?? '');

        $query = OutgoingCheque::query()
            ->join('supplier', 'supplier.id', '=', 'outgoing_cheque.supplier_id')
            ->join('bank_account', 'bank_account.id', '=', 'outgoing_cheque.bank_account_id')
            ->select([
                'outgoing_cheque.*',
                'supplier.code as supplier_code',
                'supplier.name as supplier_name',
                'bank_account.code as bank_account_code',
                'bank_account.name as bank_account_name',
            ])
            ->when(
                in_array($status, ['draft', 'issued', 'cleared', 'returned', 'cancelled'], true),
                fn ($builder) => $builder->where('outgoing_cheque.status', $status),
            )
            ->when($supplierId !== '', fn ($builder) => $builder->where('outgoing_cheque.supplier_id', $supplierId))
            ->orderBy('outgoing_cheque.due_date')
            ->orderByDesc('outgoing_cheque.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('cheque_number', fn ($builder, $keyword) => $builder->where('outgoing_cheque.cheque_number', 'like', "%{$keyword}%"))
            ->filterColumn('supplier_name', function ($builder, $keyword): void {
                $needle = '%'.mb_strtolower((string) $keyword).'%';
                $builder->where(function ($nested) use ($keyword, $needle): void {
                    $nested->where('supplier.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('bank_account_name', function ($builder, $keyword): void {
                $needle = '%'.mb_strtolower((string) $keyword).'%';
                $builder->where(function ($nested) use ($keyword, $needle): void {
                    $nested->where('bank_account.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(bank_account.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('due_date', fn ($builder, $keyword) => $builder->whereRaw('CAST(outgoing_cheque.due_date AS TEXT) LIKE ?', ["%{$keyword}%"]))
            ->orderColumn('cheque_number', 'outgoing_cheque.cheque_number $1')
            ->orderColumn('supplier_name', 'supplier.code $1')
            ->orderColumn('bank_account_name', 'bank_account.code $1')
            ->orderColumn('due_date', 'outgoing_cheque.due_date $1')
            ->orderColumn('amount_minor', 'outgoing_cheque.amount_minor $1')
            ->orderColumn('status', 'outgoing_cheque.status $1')
            ->orderColumn('id', 'outgoing_cheque.id $1')
            ->editColumn('supplier_name', fn ($row) => $this->decodeTranslations($row->supplier_name))
            ->editColumn('bank_account_name', fn ($row) => $this->decodeTranslations($row->bank_account_name))
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the decoded {en, ar} maps.
            ->rawColumns(['supplier_name', 'supplier_name.en', 'supplier_name.ar', 'bank_account_name', 'bank_account_name.en', 'bank_account_name.ar'])
            ->toJson();
    }

    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
