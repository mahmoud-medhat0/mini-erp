<?php

namespace App\Application\Reports;

use App\Models\TaxCode;

class VatSummaryReportService
{
    public function __construct(
        private readonly VatRegisterReportService $registerService
    ) {}

    public function generate(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $registerData = $this->registerService->generate([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => 'all',
        ]);

        $taxCodes = TaxCode::query()->with('rates')->get()->keyBy('id');

        $outputGrouped = [];
        $inputGrouped = [];

        foreach ($registerData['rows'] as $row) {
            $taxCodeId = $row['tax_code_id'];
            $taxCodeObj = $taxCodes->get($taxCodeId);

            $targetArray = &$outputGrouped;
            if ($row['tax_category'] === 'input') {
                $targetArray = &$inputGrouped;
            }

            if (! isset($targetArray[$taxCodeId])) {
                $name = $taxCodeObj ? $taxCodeObj->name : $row['tax_code'];
                $targetArray[$taxCodeId] = [
                    'tax_code_id' => $taxCodeId,
                    'code' => $row['tax_code'],
                    'name' => $name,
                    'tax_type' => $taxCodeObj?->tax_type ?? 'vat',
                    'calculation_mode' => $taxCodeObj?->calculation_mode ?? 'exclusive',
                    'rate_bps' => $row['tax_rate_bps'],
                    'subtotal_minor' => 0,
                    'tax_amount_minor' => 0,
                    'gross_amount_minor' => 0,
                ];
            }

            $targetArray[$taxCodeId]['subtotal_minor'] += $row['subtotal_minor'];
            $targetArray[$taxCodeId]['tax_amount_minor'] += $row['tax_amount_minor'];
            $targetArray[$taxCodeId]['gross_amount_minor'] += $row['gross_amount_minor'];
        }

        return [
            'from_date' => $registerData['from_date'],
            'to_date' => $registerData['to_date'],
            'output_vat_breakdown' => array_values($outputGrouped),
            'input_vat_breakdown' => array_values($inputGrouped),
            'summary' => $registerData['summary'],
        ];
    }
}
