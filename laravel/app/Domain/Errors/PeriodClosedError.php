<?php

namespace App\Domain\Errors;

class PeriodClosedError extends DomainError
{
    public function __construct(string $periodId)
    {
        parent::__construct('period_closed', "Cannot post into a closed period: {$periodId}", 409, [
            'periodId' => $periodId,
        ]);
    }
}
