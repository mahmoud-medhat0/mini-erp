<?php

namespace Tests\Invariants;

use App\Domain\Accounting\AccountingKernel;
use App\Domain\Accounting\DraftEntry;
use App\Domain\Accounting\DraftLine;
use App\Domain\Errors\UnbalancedEntryError;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AccountingKernelInvariantTest extends TestCase
{
    public function test_balanced_sales_invoice_entry_is_accepted(): void
    {
        $entry = $this->entry([
            ['debit' => 11400],
            ['credit' => 10000],
            ['credit' => 1400],
        ]);

        AccountingKernel::assertBalanced($entry);

        $this->assertSame(AccountingKernel::sumDebits($entry->lines), AccountingKernel::sumCredits($entry->lines));
    }

    public function test_unbalanced_entry_is_rejected(): void
    {
        $entry = $this->entry([
            ['debit' => 11400],
            ['credit' => 10000],
        ]);

        $this->expectException(UnbalancedEntryError::class);

        AccountingKernel::assertBalanced($entry);
    }

    public function test_line_with_both_debit_and_credit_is_rejected(): void
    {
        $entry = $this->entry([
            ['debit' => 100, 'credit' => 100],
            ['credit' => 100],
        ]);

        $this->expectException(UnbalancedEntryError::class);

        AccountingKernel::assertBalanced($entry);
    }

    public function test_single_line_entry_is_rejected(): void
    {
        $this->expectException(UnbalancedEntryError::class);

        AccountingKernel::assertBalanced($this->entry([['debit' => 100]]));
    }

    public function test_idempotency_key_is_deterministic_per_source_and_action(): void
    {
        $this->assertSame('SalesInvoice:inv-1:post', AccountingKernel::postingIdempotencyKey('SalesInvoice', 'inv-1'));
        $this->assertSame('SalesInvoice:inv-1:reverse', AccountingKernel::postingIdempotencyKey('SalesInvoice', 'inv-1', 'reverse'));
    }

    /**
     * @param  list<array{debit?: int, credit?: int}>  $lines
     */
    private function entry(array $lines): DraftEntry
    {
        return new DraftEntry(
            sourceType: 'SalesInvoice',
            sourceId: 'inv-1',
            date: CarbonImmutable::parse('2026-08-20'),
            currency: 'EGP',
            fxRate: 1_000_000,
            lines: array_map(
                static fn (array $line, int $index): DraftLine => new DraftLine(
                    accountId: "acc-{$index}",
                    debitMinor: $line['debit'] ?? 0,
                    creditMinor: $line['credit'] ?? 0,
                ),
                $lines,
                array_keys($lines),
            ),
        );
    }
}
