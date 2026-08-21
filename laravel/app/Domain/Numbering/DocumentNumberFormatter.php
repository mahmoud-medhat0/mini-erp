<?php

namespace App\Domain\Numbering;

class DocumentNumberFormatter
{
    /**
     * @param  array{year?: int|string|null}  $context
     */
    public function format(NumberSequenceConfig $config, int $value, array $context = []): string
    {
        $parts = array_filter([
            $config->prefix,
            $config->includeYear ? ($context['year'] ?? now()->year) : null,
            str_pad((string) $value, $config->padding, '0', STR_PAD_LEFT),
        ], static fn (mixed $part): bool => $part !== null && $part !== '');

        return implode('-', $parts);
    }
}
