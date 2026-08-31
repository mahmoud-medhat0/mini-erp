<?php

namespace App\Domain\Numbering;

readonly class NumberSequenceConfig
{
    public function __construct(
        public string $docType,
        public string $prefix,
        public bool $includeYear = true,
        public int $padding = 5,
        public string $resetPolicy = 'yearly',
    ) {}
}
