<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            RbacSeeder::class,
            PermissionSeeder::class,
            BootstrapUserSeeder::class,
            FirstUserSuperAdminSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
            AccountingCoreSeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
        ]);

        if (app()->environment('local', 'testing', 'development')) {
            $this->call(AccountingDemoSeeder::class);
        }
    }
}
