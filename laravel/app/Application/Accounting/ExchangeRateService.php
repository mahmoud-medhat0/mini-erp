<?php

namespace App\Application\Accounting;

use App\Application\Support\BaseCurrencyResolver;
use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExchangeRateService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BaseCurrencyResolver $baseCurrencyResolver,
    ) {}

    public function setRate(string $currency, string $date, int|string $rate, int $userId): void
    {
        $rateE6 = self::parseRateToE6($rate);
        $currUpper = CurrencyInput::required($currency);

        $existing = DB::table('exchange_rate')
            ->where('currency', $currUpper)
            ->where('date', $date)
            ->first();

        if ($existing) {
            DB::table('exchange_rate')
                ->where('id', $existing->id)
                ->update([
                    'rate_e6' => $rateE6,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('exchange_rate')->insert([
                'id' => (string) Str::uuid(),
                'currency' => $currUpper,
                'date' => $date,
                'rate_e6' => $rateE6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->auditLogger->record($userId, 'exchange_rate.set', 'exchange_rate', "{$currUpper}:{$date}", after: [
            'currency' => $currUpper,
            'date' => $date,
            'rate_e6' => $rateE6,
        ]);
    }

    public static function parseRateToE6(int|string $rate): int
    {
        $str = trim((string) $rate);
        if ($str === '' || ! preg_match('/^\d+(\.\d+)?$/', $str)) {
            throw new InvalidArgumentException(__('Exchange rate must be a valid positive number.'));
        }

        $parts = explode('.', $str);
        $integerPart = $parts[0];
        $fractionalPart = $parts[1] ?? '';

        if (strlen($fractionalPart) > 6) {
            throw new InvalidArgumentException(__('Exchange rate precision cannot exceed 6 decimal places.'));
        }

        $fractionalPart = str_pad($fractionalPart, 6, '0');

        $combined = ltrim($integerPart.$fractionalPart, '0');
        $rateE6 = $combined === '' ? 0 : (int) $combined;

        if ($rateE6 <= 0) {
            throw new InvalidArgumentException(__('Exchange rate must be positive.'));
        }

        return $rateE6;
    }

    public function getRateE6(string $currency, string $date): int
    {
        $currencyCode = CurrencyInput::required($currency);

        if ($currencyCode === $this->baseCurrencyResolver->resolve()) {
            return 1000000;
        }

        $row = DB::table('exchange_rate')
            ->where('currency', $currencyCode)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        if (! $row) {
            throw new InvalidArgumentException(__('Exchange rate is required for the selected currency and date.'));
        }

        return (int) $row->rate_e6;
    }
}
