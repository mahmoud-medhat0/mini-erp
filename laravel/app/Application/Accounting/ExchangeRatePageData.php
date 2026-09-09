<?php

namespace App\Application\Accounting;

use App\Application\Support\BaseCurrencyResolver;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ExchangeRatePageData
{
    public function __construct(private readonly BaseCurrencyResolver $baseCurrencyResolver) {}

    /**
     * @return array{
     *     rateEntryCount: int,
     *     currencies: EloquentCollection<int, Currency>,
     *     baseCurrency: string,
     *     baseCurrencyRef: Currency|null,
     *     filters: array{search: string|null},
     *     activeCurrencyCount: int
     * }
     */
    public function indexData(?string $search = null): array
    {
        $baseCurrency = $this->baseCurrencyResolver->resolve();
        $search = trim((string) $search);
        $query = $this->filteredQuery($search);

        return [
            'rateEntryCount' => (clone $query)->count(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'baseCurrency' => $baseCurrency,
            'baseCurrencyRef' => Currency::query()->where('code', $baseCurrency)->first(),
            'filters' => ['search' => $search !== '' ? $search : null],
            'activeCurrencyCount' => (clone $query)->distinct()->count('currency'),
        ];
    }

    /**
     * Server-side DataTables feed for exchange-rate history.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = ExchangeRate::query()
            ->leftJoin('currency as currency_ref', 'currency_ref.code', '=', 'exchange_rate.currency')
            ->select([
                'exchange_rate.id',
                'exchange_rate.currency',
                'exchange_rate.date',
                'exchange_rate.rate_e6',
                'exchange_rate.created_at',
                'currency_ref.name as currency_name',
                'currency_ref.symbol as currency_symbol',
            ])
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function (Builder $builder) use ($filters): void {
                $search = trim((string) $filters['search']);
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested->where('exchange_rate.currency', 'like', "%{$search}%")
                        ->orWhereRaw('LOWER(CAST(currency_ref.name AS TEXT)) LIKE ?', ['%'.mb_strtolower($search).'%']);

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) === 1) {
                        $nested->orWhereDate('exchange_rate.date', $search);
                    }
                });
            })
            ->orderByDesc('exchange_rate.date')
            ->orderByDesc('exchange_rate.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('currency_name', function ($builder, $keyword): void {
                $needle = '%'.mb_strtolower((string) $keyword).'%';
                $builder->where(function ($nested) use ($keyword, $needle): void {
                    $nested->where('exchange_rate.currency', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(currency_ref.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('date', fn ($builder, $keyword) => $builder->whereRaw('CAST(exchange_rate.date AS TEXT) LIKE ?', ["%{$keyword}%"]))
            ->orderColumn('currency_name', 'exchange_rate.currency $1')
            ->orderColumn('date', 'exchange_rate.date $1')
            ->orderColumn('rate_decimal', 'exchange_rate.rate_e6 $1')
            ->orderColumn('rate_e6', 'exchange_rate.rate_e6 $1')
            ->editColumn('currency_name', fn ($row) => $this->decodeTranslations($row->currency_name))
            ->addColumn('rate_decimal', fn ($row) => ((int) $row->rate_e6) / 1_000_000)
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the decoded {en, ar} map.
            ->rawColumns(['currency_name', 'currency_name.en', 'currency_name.ar'])
            ->toJson();
    }

    private function filteredQuery(string $search): Builder
    {
        return ExchangeRate::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('currency', 'like', "%{$search}%")
                        ->orWhereHas('currencyRef', fn (Builder $currency) => $currency
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name->en', 'like', "%{$search}%")
                            ->orWhere('name->ar', 'like', "%{$search}%"));

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) === 1) {
                        $nested->orWhereDate('date', $search);
                    }
                });
            });
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
