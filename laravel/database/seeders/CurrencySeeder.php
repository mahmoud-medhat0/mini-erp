<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Seed the supported ISO currency registry.
     */
    public function run(): void
    {
        foreach (config('erp_currencies.supported') as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'exponent' => $currency['exponent'],
                ],
            );
        }
    }
}
