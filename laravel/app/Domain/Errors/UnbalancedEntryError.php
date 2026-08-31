<?php

namespace App\Domain\Errors;

class UnbalancedEntryError extends DomainError
{
    public function __construct(int $debitMinor, int $creditMinor)
    {
        parent::__construct(
            'unbalanced_entry',
            "Journal entry not balanced: debit={$debitMinor} credit={$creditMinor}",
            422,
            [
                'debitMinor' => (string) $debitMinor,
                'creditMinor' => (string) $creditMinor,
            ],
        );
    }
}
