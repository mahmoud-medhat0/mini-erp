<?php

namespace Database\Seeders;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

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
        $wasCreated = ! $user->exists;
        $passwordChanged = false;

        $user->fill([
            'name' => config('erp_auth.bootstrap_user.name'),
            'locale' => 'en',
            'theme' => 'system',
            'is_active' => true,
            'mfa_enabled' => false,
        ]);

        if (! $user->exists || ! Hash::check($password, (string) $user->password)) {
            $user->password = $password;
            $passwordChanged = true;
        }

        $user->save();

        $assignedRole = $this->assignConfiguredRole($user);

        $this->auditBootstrap($user, $wasCreated, $passwordChanged, $assignedRole);
    }

    private function assignConfiguredRole(User $user): ?string
    {
        if (! config('erp_auth.bootstrap_user.assign_role')) {
            return null;
        }

        $roleName = config('erp_auth.bootstrap_user.role');

        if (! is_string($roleName) || $roleName === '') {
            return null;
        }

        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', config('erp_rbac.guard', 'web'))
            ->first();

        if (! $role) {
            return null;
        }

        if (! $user->hasRole($role)) {
            $user->assignRole($role);

            return $role->name;
        }

        return null;
    }

    private function auditBootstrap(User $user, bool $wasCreated, bool $passwordChanged, ?string $assignedRole): void
    {
        if (! Schema::hasTable('audit_log')) {
            return;
        }

        if (! $wasCreated && ! $passwordChanged && $assignedRole === null) {
            return;
        }

        app(AuditLogger::class)->record(
            actorId: null,
            action: 'bootstrap_user.seed',
            entityType: 'user',
            entityId: (string) $user->id,
            after: [
                'email' => $user->email,
                'created' => $wasCreated,
                'password_changed' => $passwordChanged,
                'assigned_role' => $assignedRole,
            ],
        );
    }
}
