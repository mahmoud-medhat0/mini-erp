<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\IncomingCheque;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class IncomingChequePageData
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
        $customerId = $filters['customer_id'] ?? null;

        return [
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $customerId = (string) ($filters['customer_id'] ?? '');

        $query = IncomingCheque::query()
            ->join('customer', 'customer.id', '=', 'incoming_cheque.customer_id')
            ->select([
                'incoming_cheque.*',
                'customer.code as customer_code',
                'customer.name as customer_name',
            ])
            ->when(
                in_array($status, ['draft', 'received', 'deposited', 'cleared', 'bounced', 'returned'], true),
                fn ($builder) => $builder->where('incoming_cheque.status', $status),
            )
            ->when($customerId !== '', fn ($builder) => $builder->where('incoming_cheque.customer_id', $customerId))
            ->orderBy('incoming_cheque.due_date')
            ->orderByDesc('incoming_cheque.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('cheque_number', fn ($builder, $keyword) => $builder->where('incoming_cheque.cheque_number', 'like', "%{$keyword}%"))
            ->filterColumn('customer_name', function ($builder, $keyword): void {
                $needle = '%'.mb_strtolower((string) $keyword).'%';
                $builder->where(function ($nested) use ($keyword, $needle): void {
                    $nested->where('customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('bank_name', fn ($builder, $keyword) => $builder->where('incoming_cheque.drawer_bank_name', 'like', "%{$keyword}%"))
            ->filterColumn('due_date', fn ($builder, $keyword) => $builder->whereRaw('CAST(incoming_cheque.due_date AS TEXT) LIKE ?', ["%{$keyword}%"]))
            ->orderColumn('cheque_number', 'incoming_cheque.cheque_number $1')
            ->orderColumn('customer_name', 'customer.code $1')
            ->orderColumn('bank_name', 'incoming_cheque.drawer_bank_name $1')
            ->orderColumn('due_date', 'incoming_cheque.due_date $1')
            ->orderColumn('amount_minor', 'incoming_cheque.amount_minor $1')
            ->orderColumn('status', 'incoming_cheque.status $1')
            ->orderColumn('id', 'incoming_cheque.id $1')
            ->editColumn('customer_name', fn ($row) => $this->decodeTranslations($row->customer_name))
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
