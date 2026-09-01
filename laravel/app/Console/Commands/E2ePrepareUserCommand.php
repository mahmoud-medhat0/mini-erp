<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Creates or resets the browser-test sign-in account.
 *
 * This exists because the ERP deliberately ships no fixed bootstrap credentials.
 * Browser tests still need a deterministic account, so they provision one here
 * instead of hardcoding a password that would then live in the repository.
 */
class E2ePrepareUserCommand extends Command
{
    protected $signature = 'e2e:prepare-user
        {--email=e2e-admin@mini-erp.test : Email for the browser-test account}
        {--password= : Password to set; required, never defaulted}
        {--role=SUPER_ADMIN : Role to assign}';

    protected $description = 'Create or reset a deterministic browser-test user. Refuses to run in production.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('e2e:prepare-user must never run in production.');

            return SymfonyCommand::FAILURE;
        }

        $password = (string) $this->option('password');

        if ($password === '') {
            $this->error('A --password value is required. This command never invents or defaults a credential.');

            return SymfonyCommand::FAILURE;
        }

        if (mb_strlen($password) < 12) {
            $this->error('The browser-test password must be at least 12 characters.');

            return SymfonyCommand::FAILURE;
        }

        $email = (string) $this->option('email');
        $roleName = (string) $this->option('role');

        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', config('erp_rbac.guard', 'web'))
            ->first();

        if (! $role) {
            $this->error("Role [{$roleName}] does not exist. Seed roles before preparing the browser-test user.");

            return SymfonyCommand::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'E2E Automation';
        $user->password = Hash::make($password);
        $user->is_active = true;

        if ($user->exists === false) {
            $user->locale = 'en';
        }

        $user->save();

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $this->info("Browser-test user ready: {$email} (role {$roleName}).");

        return SymfonyCommand::SUCCESS;
    }
}
