<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ChartOfAccountsPageData
{
    /**
     * The grid streams its rows from {@see self::datatable()}, so the page load
     * only carries the option lists its filters and create forms need. Shipping
     * every account here as well cost ~290KB and several seconds per visit,
     * which also queued the page's own JS chunk behind it.
     *
     * @return array{
     *     groups: EloquentCollection<int, AccountGroup>,
     *     accountTypes: EloquentCollection<int, AccountType>,
     *     currencies: EloquentCollection<int, Currency>
     * }
     */
    public function indexData(): array
    {
        return [
            'groups' => AccountGroup::query()
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'account_type_id', 'type', 'sort_order']),
            'accountTypes' => AccountType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }

    /**
     * Server-side DataTables feed for chart of accounts grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $groupId = (string) ($filters['group_id'] ?? '');
        $accountTypeId = (string) ($filters['account_type_id'] ?? '');
        $currency = (string) ($filters['currency'] ?? '');
        $nature = (string) ($filters['nature'] ?? '');
        $isControl = (string) ($filters['is_control'] ?? '');
        $status = (string) ($filters['status'] ?? '');

        $query = Account::query()
            ->leftJoin('account_type', 'account_type.id', '=', 'account.account_type_id')
            ->leftJoin('account_group', 'account_group.id', '=', 'account.account_group_id')
            ->select([
                'account.id',
                'account.code',
                'account.name',
                'account.type',
                'account.nature',
                'account.currency',
                'account.is_control',
                'account.is_active',
                'account.account_type_id',
                'account.account_group_id',
                'account_type.name as account_type_name',
                'account_group.name as group_name',
            ]);

        if ($groupId !== '') {
            $query->where('account.account_group_id', $groupId);
        }

        if ($accountTypeId !== '') {
            $query->where('account.account_type_id', $accountTypeId);
        }

        if ($currency !== '') {
            $query->where('account.currency', $currency);
        }

        if ($nature !== '') {
            $query->where('account.nature', $nature);
        }

        if ($isControl !== '') {
            $query->where('account.is_control', filter_var($isControl, FILTER_VALIDATE_BOOLEAN));
        }

        if ($status !== '') {
            $query->where('account.is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return DataTables::eloquent($query)
            ->filterColumn('code', fn ($q, $kw) => $q->where('account.code', 'like', "%{$kw}%"))
            ->filterColumn('name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->whereRaw('LOWER(CAST(account.name AS TEXT)) LIKE ?', [$needle]);
            })
            ->filterColumn('group_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->whereRaw('LOWER(CAST(account_group.name AS TEXT)) LIKE ?', [$needle]);
            })
            ->filterColumn('account_type_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->whereRaw('LOWER(CAST(account_type.name AS TEXT)) LIKE ?', [$needle]);
            })
            ->editColumn('name', fn ($row) => $this->decodeTranslations($row->name))
            ->editColumn('group_name', fn ($row) => $this->decodeTranslations($row->group_name))
            ->editColumn('account_type_name', fn ($row) => $this->decodeTranslations($row->account_type_name))
            ->toJson();
    }

    private function decodeTranslations(mixed $val): mixed
    {
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $val;
    }
}
