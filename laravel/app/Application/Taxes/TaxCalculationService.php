<?php

namespace App\Application\Taxes;

use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Validation\ValidationException;

class TaxCalculationService
{
    public function resolveEffectiveRate(string $taxCodeId, string $date): ?TaxRate
    {
        /** @var TaxRate|null $rate */
        $rate = TaxRate::query()
            ->where('tax_code_id', $taxCodeId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $rate;
    }

    public function calculateTax(string $taxCodeId, int $taxableBaseMinor, string $documentDate): array
    {
        /** @var TaxCode $taxCode */
        $taxCode = TaxCode::query()->findOrFail($taxCodeId);

        if (! $taxCode->is_active) {
            throw ValidationException::withMessages([
                'tax_code_id' => ["Tax code [{$taxCode->code}] is inactive."],
            ]);
        }

        if ($taxCode->calculation_mode === 'exempt') {
            return [
                'taxable_base_minor' => $taxableBaseMinor,
                'tax_minor' => 0,
                'gross_minor' => $taxableBaseMinor,
                'rate_bps' => 0,
                'calculation_mode' => 'exempt',
                'recoverability_mode' => $taxCode->recoverability_mode,
                'rounding_policy' => 'half_up',
            ];
        }

        $rateRecord = $this->resolveEffectiveRate($taxCodeId, $documentDate);

        if (! $rateRecord) {
            throw ValidationException::withMessages([
                'document_date' => ["No active tax rate found for tax code [{$taxCode->code}] on date [{$documentDate}]."],
            ]);
        }

        $rateBps = $rateRecord->rate_bps;

        if ($taxCode->calculation_mode === 'exclusive') {
            // Half-up integer math: intdiv((base * rate_bps) + 5000, 10000)
            $taxMinor = intdiv(($taxableBaseMinor * $rateBps) + 5000, 10000);
            $grossMinor = $taxableBaseMinor + $taxMinor;

            return [
                'taxable_base_minor' => $taxableBaseMinor,
                'tax_minor' => $taxMinor,
                'gross_minor' => $grossMinor,
                'rate_bps' => $rateBps,
                'calculation_mode' => 'exclusive',
                'recoverability_mode' => $taxCode->recoverability_mode,
                'rounding_policy' => 'half_up',
            ];
        }

        if ($taxCode->calculation_mode === 'inclusive') {
            // Inclusive math: net = intdiv((gross * 10000) + intdiv(10000 + rate_bps, 2), 10000 + rate_bps)
            $divisor = 10000 + $rateBps;
            $netMinor = intdiv(($taxableBaseMinor * 10000) + intdiv($divisor, 2), $divisor);
            $taxMinor = $taxableBaseMinor - $netMinor;

            return [
                'taxable_base_minor' => $netMinor,
                'tax_minor' => $taxMinor,
                'gross_minor' => $taxableBaseMinor,
                'rate_bps' => $rateBps,
                'calculation_mode' => 'inclusive',
                'recoverability_mode' => $taxCode->recoverability_mode,
                'rounding_policy' => 'half_up',
            ];
        }

        throw ValidationException::withMessages([
            'calculation_mode' => ["Unsupported calculation mode [{$taxCode->calculation_mode}]."],
        ]);
    }
}
