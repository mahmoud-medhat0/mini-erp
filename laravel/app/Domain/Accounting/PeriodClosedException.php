<?php

namespace App\Domain\Accounting;

use InvalidArgumentException;

class PeriodClosedException extends InvalidArgumentException
{
    public function __construct(
        string $message = 'Target financial period is closed or locked.',
        public readonly ?string $periodId = null,
        public readonly ?string $date = null,
    ) {
        parent::__construct($message);
    }
}
