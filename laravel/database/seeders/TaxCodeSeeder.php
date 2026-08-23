<?php

namespace Database\Seeders;

use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        $defaultCodes = [
            [
                'code' => 'VAT_STD_14',
                'name' => ['en' => 'Standard VAT 14%', 'ar' => 'ضريبة القيمة المضافة الأساسية 14%'],
                'tax_type' => 'vat',
                'calculation_mode' => 'exclusive',
                'recoverability_mode' => 'full',
                'is_system' => true,
                'is_active' => true,
                'rate_bps' => 1400,
                'effective_from' => '2020-01-01',
            ],
            [
                'code' => 'VAT_ZERO',
                'name' => ['en' => 'Zero-Rated VAT 0%', 'ar' => 'ضريبة القيمة المضافة بسعر صفر 0%'],
                'tax_type' => 'vat',
                'calculation_mode' => 'exclusive',
                'recoverability_mode' => 'full',
                'is_system' => true,
                'is_active' => true,
                'rate_bps' => 0,
                'effective_from' => '2020-01-01',
            ],
            [
                'code' => 'EXEMPT',
                'name' => ['en' => 'Exempt / Out of Scope', 'ar' => 'معفى من الضريبة / خارج نطاق الخضوع'],
                'tax_type' => 'vat',
                'calculation_mode' => 'exempt',
                'recoverability_mode' => 'none',
                'is_system' => true,
                'is_active' => true,
                'rate_bps' => 0,
                'effective_from' => '2020-01-01',
            ],
        ];

        foreach ($defaultCodes as $item) {
            $rateBps = $item['rate_bps'];
            $effectiveFrom = $item['effective_from'];
            unset($item['rate_bps'], $item['effective_from']);

            /** @var TaxCode $taxCode */
            $taxCode = TaxCode::query()->firstOrCreate(
                ['code' => $item['code']],
                $item
            );

            if (! $taxCode->rates()->exists()) {
                TaxRate::query()->create([
                    'tax_code_id' => $taxCode->id,
                    'rate_bps' => $rateBps,
                    'effective_from' => $effectiveFrom,
                    'is_active' => true,
                ]);
            }
        }
    }
}
