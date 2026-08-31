<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure default supported ISO currencies exist in the currency table
        $supportedCurrencies = config('erp_currencies.supported', [
            ['code' => 'EGP', 'name' => ['en' => 'Egyptian Pound', 'ar' => 'الجنيه المصري'], 'symbol' => 'ج.م', 'exponent' => 2],
            ['code' => 'USD', 'name' => ['en' => 'US Dollar', 'ar' => 'الدولار الأمريكي'], 'symbol' => '$', 'exponent' => 2],
            ['code' => 'EUR', 'name' => ['en' => 'Euro', 'ar' => 'اليورو'], 'symbol' => '€', 'exponent' => 2],
            ['code' => 'SAR', 'name' => ['en' => 'Saudi Riyal', 'ar' => 'الريال السعودي'], 'symbol' => 'ر.س', 'exponent' => 2],
            ['code' => 'AED', 'name' => ['en' => 'UAE Dirham', 'ar' => 'الدرهم الإماراتي'], 'symbol' => 'د.إ', 'exponent' => 2],
            ['code' => 'GBP', 'name' => ['en' => 'Pound Sterling', 'ar' => 'الجنيه الإسترليني'], 'symbol' => '£', 'exponent' => 2],
            ['code' => 'KWD', 'name' => ['en' => 'Kuwaiti Dinar', 'ar' => 'الدينار الكويتي'], 'symbol' => 'د.ك', 'exponent' => 3],
        ]);

        foreach ($supportedCurrencies as $currency) {
            Currency::query()->firstOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'exponent' => $currency['exponent'],
                ]
            );
        }

        // Validate existing foreign key references
        $tablesAndColumns = [
            'exchange_rate' => 'currency',
            'account' => 'currency',
            'journal_entry' => 'currency',
            'journal_line' => 'currency',
            'ledger_entry' => 'currency',
            'opening_balance' => 'currency',
            'company' => 'base_currency',
        ];

        $validCodes = DB::table('currency')->pluck('code')->all();

        foreach ($tablesAndColumns as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $invalidValues = DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, $validCodes)
                ->distinct()
                ->pluck($column)
                ->filter()
                ->all();

            if (! empty($invalidValues)) {
                throw new RuntimeException(sprintf(
                    'Migration aborted: Table "%s" column "%s" contains invalid currency codes not found in currency table: %s',
                    $table,
                    $column,
                    implode(', ', $invalidValues)
                ));
            }
        }

        // Add foreign key constraints
        foreach ($tablesAndColumns as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->foreign($column)
                        ->references('code')
                        ->on('currency')
                        ->onUpdate('cascade')
                        ->onDelete('restrict');
                });
            }
        }

        // In SQLite, altering tables drops attached triggers. Re-apply immutability triggers on ledger_entry if SQLite.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_ledger_entry_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS enforce_ledger_entry_no_delete');

            DB::unprepared("
                CREATE TRIGGER enforce_ledger_entry_no_update
                BEFORE UPDATE ON ledger_entry
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(FAIL, 'Ledger entries are immutable. UPDATE operations are strictly prohibited.');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER enforce_ledger_entry_no_delete
                BEFORE DELETE ON ledger_entry
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(FAIL, 'Ledger entries are immutable. DELETE operations are strictly prohibited.');
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tablesAndColumns = [
            'exchange_rate' => 'currency',
            'account' => 'currency',
            'journal_entry' => 'currency',
            'journal_line' => 'currency',
            'ledger_entry' => 'currency',
            'opening_balance' => 'currency',
            'company' => 'base_currency',
        ];

        foreach ($tablesAndColumns as $table => $column) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropForeign([$column]);
                });
            }
        }
    }
};
