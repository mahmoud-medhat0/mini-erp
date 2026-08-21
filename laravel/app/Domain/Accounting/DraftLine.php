<?php

namespace App\Domain\Accounting;

readonly class DraftLine
{
    public function __construct(
        public string $accountId,
        public int $debitMinor = 0,
        public int $creditMinor = 0,
        public ?string $memo = null,
    ) {}
}
