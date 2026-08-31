<?php

namespace App\Application\Reports;

use App\Models\Company;
use App\Models\Currency;
use App\Models\TaxCode;

class VatReportPageData
{
    public function __construct(
        private readonly VatRegisterReportService $registerService,
        private readonly VatSummaryReportService $summaryService,
        private readonly VatToGlReconciliationService $reconciliationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function register(array $filters): array
    {
        $report = $this->registerService->generate($filters);
        $report['currency'] = $this->baseCurrency();

        return [
            'report' => $report,
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code', 'asc')->get(),
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
                'type' => $report['type'],
                'tax_code_id' => $report['tax_code_id'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $report = $this->summaryService->generate($filters);
        $report['currency'] = $this->baseCurrency();

        return [
            'report' => $report,
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function reconciliation(array $filters): array
    {
        $report = $this->reconciliationService->generate($filters);

        return [
            'report' => $report,
            'currencies' => Currency::query()->orderBy('code', 'asc')->get(),
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
                'currency' => $report['currency'],
            ],
        ];
    }

    private function baseCurrency(): ?string
    {
        return Company::query()->orderBy('created_at')->value('base_currency')
            ?: Currency::query()->orderBy('code', 'asc')->value('code');
    }
}
