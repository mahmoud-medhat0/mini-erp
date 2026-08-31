<?php

namespace App\Application\Taxes;

use App\Application\Support\BaseCurrencyResolver;
use App\Models\TaxPeriod;
use Illuminate\Support\Collection;

class TaxPeriodPageData
{
    public function __construct(
        private readonly TaxPeriodService $periodService,
        private readonly BaseCurrencyResolver $baseCurrencyResolver,
    ) {}

    /**
     * @return array{periods: Collection<int, TaxPeriod>}
     */
    public function indexData(): array
    {
        return [
            'periods' => $this->periodService->listPeriods(),
        ];
    }

    /**
     * @return array{period: TaxPeriod, latestReturn: mixed, filedReturn: mixed, currency: string}
     */
    public function showData(string $id): array
    {
        $period = $this->periodService->getPeriod($id);

        return [
            'period' => $period,
            'latestReturn' => $period->latestReturn,
            'filedReturn' => $period->filedReturn,
            'currency' => $this->baseCurrencyResolver->resolve(),
        ];
    }
}
