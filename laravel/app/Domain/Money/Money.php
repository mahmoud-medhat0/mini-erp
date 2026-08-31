<?php

namespace App\Domain\Money;

use App\Domain\Currency\CurrencyRegistry;
use App\Domain\Errors\CurrencyMismatchError;
use InvalidArgumentException;

readonly class Money
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
    ) {}

    public static function ofMinor(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromMajorString(string $value, string $currency, ?CurrencyRegistry $registry = null): self
    {
        $registry ??= app(CurrencyRegistry::class);
        $exponent = $registry->exponent($currency);
        $trimmed = trim($value);
        $negative = str_starts_with($trimmed, '-');
        $unsigned = $negative ? substr($trimmed, 1) : $trimmed;

        if (! preg_match('/^\d+(\.\d+)?$/', $unsigned)) {
            throw new InvalidArgumentException("Invalid money string: {$value}");
        }

        [$integerPart, $fractionPart] = array_pad(explode('.', $unsigned, 2), 2, '');

        if (strlen($fractionPart) > $exponent) {
            throw new InvalidArgumentException("Too many decimals for {$currency} (max {$exponent}): {$value}");
        }

        $minorString = ltrim($integerPart.str_pad($fractionPart, $exponent, '0'), '0');
        $magnitude = $minorString === '' ? 0 : (int) $minorString;

        return new self($negative ? -$magnitude : $magnitude, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amountMinor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amountMinor === $other->amountMinor;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor <=> $other->amountMinor;
    }

    public function format(?CurrencyRegistry $registry = null): string
    {
        $registry ??= app(CurrencyRegistry::class);
        $exponent = $registry->exponent($this->currency);
        $negative = $this->amountMinor < 0;
        $magnitude = (string) abs($this->amountMinor);
        $padded = str_pad($magnitude, $exponent + 1, '0', STR_PAD_LEFT);
        $integer = $exponent > 0 ? substr($padded, 0, -$exponent) : $padded;
        $fraction = $exponent > 0 ? '.'.substr($padded, -$exponent) : '';

        return ($negative ? '-' : '').number_format((int) $integer).$fraction;
    }

    /**
     * @param  list<int>  $weights
     * @return list<self>
     */
    public function allocate(array $weights): array
    {
        if ($weights === []) {
            throw new InvalidArgumentException('allocate: weights required');
        }

        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('allocate: weights must be non-negative');
            }
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            throw new InvalidArgumentException('allocate: weights sum to zero');
        }

        $sign = $this->amountMinor < 0 ? -1 : 1;
        $magnitude = abs($this->amountMinor);
        $rawParts = [];
        $distributed = 0;

        foreach ($weights as $weight) {
            $part = intdiv($magnitude * $weight, $totalWeight);
            $rawParts[] = $part;
            $distributed += $part;
        }

        $remainder = $magnitude - $distributed;
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $remainders[] = [
                'index' => $index,
                'fraction' => ($magnitude * $weight) - ($rawParts[$index] * $totalWeight),
            ];
        }

        usort($remainders, static fn (array $left, array $right): int => $right['fraction'] <=> $left['fraction'] ?: $left['index'] <=> $right['index']);

        $cursor = 0;

        while ($remainder > 0) {
            $rawParts[$remainders[$cursor % count($remainders)]['index']]++;
            $remainder--;
            $cursor++;
        }

        return array_map(
            fn (int $part): self => new self($sign * $part, $this->currency),
            $rawParts,
        );
    }

    /**
     * @param  list<self>  $items
     */
    public static function sum(array $items, string $currency): self
    {
        return array_reduce(
            $items,
            static fn (self $sum, self $item): self => $sum->add($item),
            self::zero($currency),
        );
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchError($this->currency, $other->currency);
        }
    }
}
