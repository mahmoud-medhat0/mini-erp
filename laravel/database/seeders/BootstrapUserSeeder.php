<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BootstrapUserSeeder extends Seeder
{
    /**
     * Seed a local/testing bootstrap administrator for the migration app.
     */
    public function run(): void
    {
        if (! config('erp_auth.bootstrap_user.enabled')) {
            return;
        }

        $email = config('erp_auth.bootstrap_user.email');
        $password = config('erp_auth.bootstrap_user.password');

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => config('erp_auth.bootstrap_user.name'),
            'locale' => 'en',
            'theme' => 'system',
            'is_active' => true,
            'mfa_enabled' => false,
        ]);

        if (! $user->exists || ! Hash::check($password, (string) $user->password)) {
            $user->password = $password;
        }

        $user->save();
    }
}
