<?php

namespace App\Application\Recurring;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\RecurringTemplate;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class RecurringTemplatePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'templates' => [],
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'expenseCategories' => ExpenseCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'default_expense_account_id']),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'frequencies' => RecurringTemplateService::FREQUENCIES,
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = RecurringTemplate::query()
            ->select('recurring_template.*')
            ->when($status !== '', fn (Builder $q) => $q->where('recurring_template.status', $status));

        return DataTables::eloquent($query)
            ->addColumn('name_text', fn (RecurringTemplate $row) => is_array($row->name) ? ($row->name['en'] ?? '') : (string) $row->name)
            ->filterColumn('name_text', fn ($q, $kw) => $q->where(function ($q2) use ($kw): void {
                $q2->where('recurring_template.name->en', 'like', "%{$kw}%")
                    ->orWhere('recurring_template.name->ar', 'like', "%{$kw}%");
            }))
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
