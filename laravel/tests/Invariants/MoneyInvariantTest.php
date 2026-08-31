<?php

namespace Tests\Invariants;

use App\Domain\Errors\CurrencyMismatchError;
use App\Domain\Money\Money;
use Tests\TestCase;

class MoneyInvariantTest extends TestCase
{
    public function test_decimal_strings_are_parsed_to_exact_minor_units(): void
    {
        $this->assertSame(123450, Money::fromMajorString('1234.50', 'EGP')->amountMinor);
        $this->assertSame(1, Money::fromMajorString('0.01', 'EGP')->amountMinor);
        $this->assertSame(-1250000, Money::fromMajorString('-12500.00', 'EGP')->amountMinor);
        $this->assertSame(100000, Money::fromMajorString('100', 'KWD')->amountMinor);
    }

    public function test_excess_precision_is_rejected_instead_of_rounded(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromMajorString('1.005', 'EGP');
    }

    public function test_addition_is_exact_without_float_drift(): void
    {
        $sum = Money::fromMajorString('0.10', 'EGP')
            ->add(Money::fromMajorString('0.20', 'EGP'));

        $this->assertSame(30, $sum->amountMinor);
        $this->assertSame('0.30', $sum->format());
    }

    public function test_cross_currency_arithmetic_is_rejected(): void
    {
        $this->expectException(CurrencyMismatchError::class);

        Money::ofMinor(1, 'EGP')->add(Money::ofMinor(1, 'USD'));
    }

    public function test_formatting_uses_minor_units_and_thousands_separators(): void
    {
        $this->assertSame('1,284,500.00', Money::ofMinor(128450000, 'EGP')->format());
        $this->assertSame('-12,500.00', Money::ofMinor(-1250000, 'EGP')->format());
    }

    public function test_allocation_distributes_remainders_without_rounding_loss(): void
    {
        $parts = Money::ofMinor(100, 'EGP')->allocate([1, 1, 1]);

        $this->assertSame([34, 33, 33], array_map(fn (Money $money): int => $money->amountMinor, $parts));
        $this->assertSame(100, Money::sum($parts, 'EGP')->amountMinor);
    }

    public function test_weighted_allocation_and_negative_allocation_are_exact(): void
    {
        $weighted = Money::ofMinor(1000, 'EGP')->allocate([5, 3, 2]);
        $negative = Money::ofMinor(-100, 'EGP')->allocate([1, 1, 1]);

        $this->assertSame(1000, Money::sum($weighted, 'EGP')->amountMinor);
        $this->assertSame(-100, Money::sum($negative, 'EGP')->amountMinor);
    }

    public function test_property_random_amounts_and_weights_always_sum_exactly(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $amount = random_int(-1_000_000, 1_000_000);
            $count = random_int(1, 6);
            $weights = array_map(static fn (): int => random_int(1, 9), range(1, $count));
            $parts = Money::ofMinor($amount, 'EGP')->allocate($weights);

            $this->assertSame($amount, Money::sum($parts, 'EGP')->amountMinor);
        }
    }
}
