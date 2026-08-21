<?php

namespace Database\Seeders;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class FirstUserSuperAdminSeeder extends Seeder
{
    /**
     * Assign the global SUPER_ADMIN role to the first user in this ERP installation.
     */
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            return;
        }

        $role = Role::query()
            ->where('name', 'SUPER_ADMIN')
            ->where('guard_name', config('erp_rbac.guard', 'web'))
            ->first();

        if (! $role || $user->hasRole($role)) {
            return;
        }

        $user->assignRole($role);

        $this->auditAssignment($user);
    }

    private function auditAssignment(User $user): void
    {
        if (! Schema::hasTable('audit_log')) {
            return;
        }

        app(AuditLogger::class)->record(
            actorId: null,
            action: 'first_user_super_admin.seed',
            entityType: 'user',
            entityId: (string) $user->id,
            after: [
                'email' => $user->email,
                'assigned_role' => 'SUPER_ADMIN',
            ],
        );
    }
}
