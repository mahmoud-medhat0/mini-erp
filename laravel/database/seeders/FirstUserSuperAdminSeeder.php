<?php

namespace Database\Seeders;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Role;

class FirstUserSuperAdminSeeder extends Seeder
{
    /**
     * Assign the global SUPER_ADMIN role to the earliest existing user when explicitly enabled.
     */
    public function run(): void
    {
        if (! config('erp_auth.first_user_super_admin.enabled')) {
            return;
        }

        if (app()->environment('production')) {
            $confirmation = config('erp_auth.first_user_super_admin.production_confirmation');
            $requiredConfirmation = config('erp_auth.first_user_super_admin.required_production_confirmation', 'CONFIRM_ASSIGN_FIRST_USER_SUPER_ADMIN');

            if (
                ! is_string($confirmation)
                || ! is_string($requiredConfirmation)
                || $confirmation === ''
                || $requiredConfirmation === ''
                || ! hash_equals($requiredConfirmation, $confirmation)
            ) {
                throw new RuntimeException('Production first-user Super Admin assignment requires exact confirmation phrase match.');
            }
        }

        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            return;
        }

        $roleName = (string) config('erp_auth.first_user_super_admin.role', 'SUPER_ADMIN');

        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', config('erp_rbac.guard', 'web'))
            ->first();

        if (! $role || $user->hasRole($role)) {
            return;
        }

        $user->assignRole($role);

        $this->auditAssignment($user, $role->name);
    }

    private function auditAssignment(User $user, string $roleName): void
    {
        if (! Schema::hasTable('activity_log') && ! Schema::hasTable('audit_log')) {
            return;
        }

        app(AuditLogger::class)->record(
            actorId: null,
            action: 'first_user_super_admin.seed',
            entityType: 'user',
            entityId: (string) $user->id,
            after: [
                'email' => $user->email,
                'assigned_role' => $roleName,
            ],
        );
    }
}
