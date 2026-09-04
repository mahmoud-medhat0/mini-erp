<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesPermissionsUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed only roles, permissions, and the bootstrap admin user — no
     * currencies, chart of accounts, warehouses, or other business/demo data.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            PermissionSeeder::class,
            BootstrapUserSeeder::class,
            FirstUserSuperAdminSeeder::class,
        ]);
    }
}
