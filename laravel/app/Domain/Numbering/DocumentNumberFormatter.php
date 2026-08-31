<?php

namespace App\Domain\Numbering;

class DocumentNumberFormatter
{
    /**
     * @param  array{year?: int|string|null, month?: int|string|null}  $context
     */
    public function format(NumberSequenceConfig $config, int $value, array $context = []): string
    {
        $prefix = trim($config->prefix);
        $prefix = trim($prefix, "- \t\n\r\0\x0B");

        $period = null;
        if ($config->includeYear) {
            $year = $context['year'] ?? now()->year;
            $period = (string) $year;

            if ($config->resetPolicy === 'monthly') {
                $month = $context['month'] ?? now()->month;
                $period .= '-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            }
        }

        $parts = array_filter([
            $prefix,
            $period,
            str_pad((string) $value, $config->padding, '0', STR_PAD_LEFT),
        ], static fn (mixed $part): bool => $part !== null && $part !== '');

        return implode('-', $parts);
    }
}
