<?php

namespace Tests\Invariants;

use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\NumberSequenceConfig;
use PHPUnit\Framework\TestCase;

class NumberingInvariantTest extends TestCase
{
    public function test_document_number_formatting_is_deterministic(): void
    {
        $formatter = new DocumentNumberFormatter;
        $config = new NumberSequenceConfig(
            docType: 'SalesInvoice',
            prefix: 'INV',
            includeYear: true,
            padding: 5,
        );

        $this->assertSame('INV-2026-00042', $formatter->format($config, 42, [
            'year' => 2026,
        ]));
    }

    public function test_document_number_formatting_can_omit_context_parts(): void
    {
        $formatter = new DocumentNumberFormatter;
        $config = new NumberSequenceConfig(
            docType: 'Payment',
            prefix: 'PAY',
            includeYear: false,
            padding: 3,
            resetPolicy: 'never',
        );

        $this->assertSame('PAY-007', $formatter->format($config, 7));
    }
}
