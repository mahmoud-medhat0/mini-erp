<?php

namespace App\Domain\Accounting;

use App\Domain\Errors\UnbalancedEntryError;

class AccountingKernel
{
    /**
     * @param  list<DraftLine>  $lines
     */
    public static function sumDebits(array $lines): int
    {
        return array_reduce($lines, static fn (int $sum, DraftLine $line): int => $sum + $line->debitMinor, 0);
    }

    /**
     * @param  list<DraftLine>  $lines
     */
    public static function sumCredits(array $lines): int
    {
        return array_reduce($lines, static fn (int $sum, DraftLine $line): int => $sum + $line->creditMinor, 0);
    }

    /**
     * @param  list<DraftLine>  $lines
     */
    public static function assertWellFormedLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new UnbalancedEntryError(self::sumDebits($lines), self::sumCredits($lines));
        }

        foreach ($lines as $line) {
            if ($line->debitMinor < 0 || $line->creditMinor < 0) {
                throw new UnbalancedEntryError(self::sumDebits($lines), self::sumCredits($lines));
            }

            if ($line->debitMinor > 0 && $line->creditMinor > 0) {
                throw new UnbalancedEntryError(self::sumDebits($lines), self::sumCredits($lines));
            }

            if ($line->debitMinor === 0 && $line->creditMinor === 0) {
                throw new UnbalancedEntryError(self::sumDebits($lines), self::sumCredits($lines));
            }
        }
    }

    public static function assertBalanced(DraftEntry $entry): void
    {
        self::assertWellFormedLines($entry->lines);

        $debits = self::sumDebits($entry->lines);
        $credits = self::sumCredits($entry->lines);

        if ($debits !== $credits) {
            throw new UnbalancedEntryError($debits, $credits);
        }
    }

    public static function isBalanced(DraftEntry $entry): bool
    {
        try {
            self::assertBalanced($entry);

            return true;
        } catch (UnbalancedEntryError) {
            return false;
        }
    }

    public static function postingIdempotencyKey(string $sourceType, string $sourceId, string $action = 'post'): string
    {
        return "{$sourceType}:{$sourceId}:{$action}";
    }
}
