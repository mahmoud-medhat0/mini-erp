<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialPeriodIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_shifted_fiscal_year_creates_twelve_contiguous_months_inside_its_bounds(): void
    {
        $fiscalYear = app(PeriodService::class)->createFiscalYear(
            2026,
            '2026-07-01',
            '2027-06-30',
        );

        $periods = $fiscalYear->periods->sortBy('month')->values();

        $this->assertCount(12, $periods);
        $this->assertSame('2026-07-01', $periods->first()->start_date->format('Y-m-d'));
        $this->assertSame('2026-07-31', $periods->first()->end_date->format('Y-m-d'));
        $this->assertSame('2027-06-01', $periods->last()->start_date->format('Y-m-d'));
        $this->assertSame('2027-06-30', $periods->last()->end_date->format('Y-m-d'));

        foreach ($periods as $index => $period) {
            $this->assertSame($index + 1, $period->month);
            $this->assertGreaterThanOrEqual('2026-07-01', $period->start_date->format('Y-m-d'));
            $this->assertLessThanOrEqual('2027-06-30', $period->end_date->format('Y-m-d'));

            if ($index > 0) {
                $previous = $periods[$index - 1];
                $this->assertSame(
                    $previous->end_date->copy()->addDay()->format('Y-m-d'),
                    $period->start_date->format('Y-m-d'),
                );
            }
        }
    }

    public function test_service_rejects_a_mid_month_fiscal_year_start_without_writing_partial_data(): void
    {
        try {
            app(PeriodService::class)->createFiscalYear(2026, '2026-01-15', '2027-01-14');
            $this->fail('A fiscal year must not start in the middle of a month.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }

        $this->assertDatabaseCount('fiscal_year', 0);
        $this->assertDatabaseCount('financial_period', 0);
    }

    public function test_service_rejects_an_end_date_that_is_not_exactly_twelve_complete_months(): void
    {
        try {
            app(PeriodService::class)->createFiscalYear(2026, '2026-01-01', '2026-11-30');
            $this->fail('A fiscal year must cover exactly twelve complete months.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('end_date', $exception->errors());
        }

        $this->assertDatabaseCount('fiscal_year', 0);
        $this->assertDatabaseCount('financial_period', 0);
    }

    public function test_service_rejects_overlapping_fiscal_years_but_accepts_an_adjacent_year(): void
    {
        $service = app(PeriodService::class);
        $service->createFiscalYear(2026, '2026-07-01', '2027-06-30');

        try {
            $service->createFiscalYear(2027, '2027-01-01', '2027-12-31');
            $this->fail('Overlapping fiscal years must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }

        $adjacent = $service->createFiscalYear(2028, '2027-07-01', '2028-06-30');

        $this->assertSame('2027-07-01', $adjacent->start_date->format('Y-m-d'));
        $this->assertDatabaseCount('fiscal_year', 2);
        $this->assertDatabaseCount('financial_period', 24);
    }

    public function test_controller_returns_a_field_error_for_inconsistent_fiscal_year_bounds(): void
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('settings.configure', 'web');
        $user->givePermissionTo($permission);

        $response = $this->actingAs($user)->post(route('accounting.periods.fiscal_years.store'), [
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-30',
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('fiscal_year', 0);
        $this->assertDatabaseCount('financial_period', 0);
    }
}
