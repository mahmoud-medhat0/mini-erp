<?php

namespace App\Domain\Accounting;

use DateTimeInterface;

readonly class DraftEntry
{
    /**
     * @param  list<DraftLine>  $lines
     */
    public function __construct(
        public string $sourceType,
        public string $sourceId,
        public DateTimeInterface $date,
        public string $currency,
        public int $fxRate,
        public array $lines,
        public ?string $description = null,
    ) {}
}
