<?php

namespace Tests\Feature;

use Database\Seeders\AccountingCoreSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StressCommandSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function stressCommands(): array
    {
        return [
            'accounting:concurrency-stress',
            'accounting:inventory-concurrency-stress',
            'accounting:allocation-concurrency-stress',
            'accounting:bank-reconciliation-concurrency-stress',
            'accounting:cheque-concurrency-stress',
            'accounting:fixed-asset-depreciation-stress',
            'accounting:fixed-asset-disposal-stress',
            'accounting:phase3-stress',
            'accounting:purchasing-tax-stress',
            'accounting:sales-tax-stress',
            'accounting:settlement-concurrency-stress',
            'accounting:stock-transfer-stress',
            'accounting:tax-filing-stress',
            'concurrency:stress',
        ];
    }

    public function test_all_stress_commands_refuse_to_run_in_production(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'production';

        try {
            foreach ($this->stressCommands() as $command) {
                $this->artisan($command, ['--workers' => 2])
                    ->expectsOutputToContain('disabled in production')
                    ->assertExitCode(Command::FAILURE);
            }
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_sequential_accounting_stress_rolls_back_all_fixtures(): void
    {
        $this->seed(AccountingCoreSeeder::class);

        $tables = ['fiscal_year', 'financial_period', 'account_group', 'account', 'journal_entry', 'number_sequence'];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->count(),
        ]);

        $this->artisan('accounting:concurrency-stress', ['--workers' => 2])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('PASSED CLEANLY');

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Stress fixtures leaked into [{$table}].");
        }
    }

    public function test_tax_filing_stress_uses_a_rollback_instead_of_deleting_previous_rows(): void
    {
        $beforePeriods = DB::table('tax_periods')->count();
        $beforeReturns = DB::table('tax_returns')->count();

        $this->artisan('accounting:tax-filing-stress', ['--workers' => 2])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Stress fixture run tag:');

        $this->assertSame($beforePeriods, DB::table('tax_periods')->count());
        $this->assertSame($beforeReturns, DB::table('tax_returns')->count());
    }
}
