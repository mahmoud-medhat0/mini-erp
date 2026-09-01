<?php

namespace App\Support\Numbering;

use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\NumberSequenceConfig;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberSequenceAllocator
{
    public function __construct(private readonly ?DocumentNumberFormatter $formatter = null) {}

    public function nextValue(string $key, DateTimeInterface|string|null $date = null): int
    {
        return $this->allocate($key, '', $date)['value'];
    }

    public function nextNumber(
        string $key,
        string $defaultPrefix,
        DateTimeInterface|string|null $date = null,
    ): string {
        $allocation = $this->allocate($key, $defaultPrefix, $date);

        return ($this->formatter ?? new DocumentNumberFormatter)->format(
            $allocation['config'],
            $allocation['value'],
            [
                'year' => $allocation['date']->year,
                'month' => $allocation['date']->month,
            ],
        );
    }

    /**
     * @return array{config: NumberSequenceConfig, value: int, date: CarbonImmutable}
     */
    private function allocate(
        string $key,
        string $defaultPrefix,
        DateTimeInterface|string|null $date,
    ): array {
        $allocationDate = $this->allocationDate($date);

        return DB::transaction(function () use ($key, $defaultPrefix, $allocationDate): array {
            DB::table('number_sequence')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'doc_type' => $key,
                'prefix' => trim($defaultPrefix, "- \t\n\r\0\x0B"),
                'include_year' => true,
                'padding' => 5,
                'reset_policy' => 'yearly',
                'last_reset_period' => $allocationDate->format('Y'),
                'next_value' => 1,
            ]);

            $sequence = DB::table('number_sequence')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new \RuntimeException("Unable to allocate number sequence [{$key}].");
            }

            $resetPolicy = in_array($sequence->reset_policy, ['never', 'yearly', 'monthly'], true)
                ? $sequence->reset_policy
                : 'yearly';
            $period = $this->periodKey($resetPolicy, $allocationDate);
            $value = max(1, (int) $sequence->next_value);

            if ($sequence->last_reset_period !== null && $sequence->last_reset_period !== $period) {
                $value = 1;
            }

            $prefix = trim((string) $sequence->prefix);
            if ($prefix === '' && trim($defaultPrefix) !== '') {
                $prefix = trim($defaultPrefix, "- \t\n\r\0\x0B");
            }

            DB::table('number_sequence')
                ->where('id', $sequence->id)
                ->update([
                    'prefix' => $prefix,
                    'next_value' => $value + 1,
                    'last_reset_period' => $period,
                ]);

            return [
                'config' => new NumberSequenceConfig(
                    docType: (string) $sequence->doc_type,
                    prefix: $prefix,
                    includeYear: (bool) $sequence->include_year,
                    padding: max(1, (int) $sequence->padding),
                    resetPolicy: $resetPolicy,
                ),
                'value' => $value,
                'date' => $allocationDate,
            ];
        });
    }

    private function allocationDate(DateTimeInterface|string|null $date): CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date);
        }

        return $date === null ? CarbonImmutable::now() : CarbonImmutable::parse($date);
    }

    private function periodKey(string $resetPolicy, CarbonImmutable $date): string
    {
        return match ($resetPolicy) {
            'monthly' => $date->format('Y-m'),
            'yearly' => $date->format('Y'),
            default => 'never',
        };
    }
}
