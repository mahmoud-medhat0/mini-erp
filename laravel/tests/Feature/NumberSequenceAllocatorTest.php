<?php

namespace Tests\Feature;

use App\Application\Settings\NumberingSettingsService;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NumberSequenceAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_format_and_monthly_reset_are_applied_by_the_allocator(): void
    {
        DB::table('number_sequence')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'customer.invoice',
            'doc_type' => 'CustomerInvoice',
            'prefix' => 'CUSTOM-',
            'include_year' => true,
            'padding' => 4,
            'reset_policy' => 'monthly',
            'last_reset_period' => '2026-08',
            'next_value' => 12,
        ]);

        $allocator = app(NumberSequenceAllocator::class);

        $this->assertSame(
            'CUSTOM-2026-08-0012',
            $allocator->nextNumber('customer.invoice', 'INV', '2026-08-31'),
        );
        $this->assertSame(
            'CUSTOM-2026-09-0001',
            $allocator->nextNumber('customer.invoice', 'INV', '2026-09-01'),
        );

        $this->assertDatabaseHas('number_sequence', [
            'key' => 'customer.invoice',
            'next_value' => 2,
            'last_reset_period' => '2026-09',
        ]);
    }

    public function test_next_value_is_the_value_that_will_be_allocated_next(): void
    {
        DB::table('number_sequence')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'plain.sequence',
            'doc_type' => 'PlainSequence',
            'prefix' => 'PLAIN',
            'include_year' => false,
            'padding' => 2,
            'reset_policy' => 'never',
            'last_reset_period' => 'never',
            'next_value' => 9,
        ]);

        $allocator = app(NumberSequenceAllocator::class);

        $this->assertSame(9, $allocator->nextValue('plain.sequence', '2030-01-01'));
        $this->assertSame(10, DB::table('number_sequence')->where('key', 'plain.sequence')->value('next_value'));
    }

    public function test_settings_preview_shows_the_value_that_will_be_used_after_a_period_reset(): void
    {
        CarbonImmutable::setTestNow('2026-09-01 09:00:00');

        try {
            DB::table('number_sequence')->insert([
                'id' => (string) Str::uuid(),
                'key' => 'monthly.preview',
                'doc_type' => 'MonthlyPreview',
                'prefix' => 'MP-',
                'include_year' => true,
                'padding' => 3,
                'reset_policy' => 'monthly',
                'last_reset_period' => '2026-08',
                'next_value' => 91,
            ]);

            $sequence = app(NumberingSettingsService::class)->sequences()->first();

            $this->assertSame(1, $sequence['nextValue']);
            $this->assertSame('MP-2026-09-001', $sequence['preview']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
