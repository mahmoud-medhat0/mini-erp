<?php

namespace App\Application\Partners;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\PartnerLoan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PartnerLoanPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'loans' => [],
            'partners' => Partner::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'settlementMethods' => PartnerLoanService::SETTLEMENT_METHODS,
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
                'partner_id' => (string) ($filters['partner_id'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $partnerId = (string) ($filters['partner_id'] ?? '');

        $query = PartnerLoan::query()
            ->with(['partner'])
            ->select('partner_loan.*')
            ->when($status !== '', fn (Builder $q) => $q->where('partner_loan.status', $status))
            ->when($partnerId !== '', fn (Builder $q) => $q->where('partner_loan.partner_id', $partnerId));

        return DataTables::eloquent($query)
            ->addColumn('partner', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
