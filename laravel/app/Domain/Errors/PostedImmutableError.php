<?php

namespace App\Domain\Errors;

class PostedImmutableError extends DomainError
{
    public function __construct(string $entityType, string $entityId)
    {
        parent::__construct('posted_immutable', "Posted {$entityType} is immutable and cannot be edited/deleted: {$entityId}", 409, [
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);
    }
}
