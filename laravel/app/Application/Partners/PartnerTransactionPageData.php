<?php

namespace App\Application\Partners;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PartnerTransactionPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'transactions' => [],
            'partners' => Partner::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'currency']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'transactionTypes' => PartnerTransactionService::TRANSACTION_TYPES,
            'settlementMethods' => PartnerTransactionService::SETTLEMENT_METHODS,
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
                'partner_id' => (string) ($filters['partner_id'] ?? ''),
                'transaction_type' => (string) ($filters['transaction_type'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $partnerId = (string) ($filters['partner_id'] ?? '');
        $transactionType = (string) ($filters['transaction_type'] ?? '');

        $query = PartnerTransaction::query()
            ->with(['partner'])
            ->select('partner_transaction.*')
            ->when($status !== '', fn (Builder $q) => $q->where('partner_transaction.status', $status))
            ->when($partnerId !== '', fn (Builder $q) => $q->where('partner_transaction.partner_id', $partnerId))
            ->when($transactionType !== '', fn (Builder $q) => $q->where('partner_transaction.transaction_type', $transactionType));

        return DataTables::eloquent($query)
            ->addColumn('partner', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
