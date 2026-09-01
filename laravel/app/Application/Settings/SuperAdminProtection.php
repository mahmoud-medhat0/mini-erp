<?php

namespace App\Application\Settings;

use App\Models\User;
use Spatie\Permission\Models\Role;

class SuperAdminProtection
{
    public const ROLE_NAME = 'SUPER_ADMIN';

    public function isSuperAdmin(User $user): bool
    {
        return $user->roles()
            ->whereRaw('LOWER(name) = ?', [strtolower(self::ROLE_NAME)])
            ->exists();
    }

    public function isSuperAdminRole(string $roleName): bool
    {
        return strcasecmp(trim($roleName), self::ROLE_NAME) === 0;
    }

    public function isSystemRoleName(string $roleName): bool
    {
        foreach (array_keys(config('erp_rbac.role_templates', [])) as $systemRoleName) {
            if (strcasecmp(trim($roleName), (string) $systemRoleName) === 0) {
                return true;
            }
        }

        return false;
    }

    public function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereRaw('LOWER(name) = ?', [strtolower(self::ROLE_NAME)]))
            ->count();
    }

    public function isProtectedSystemRole(Role $role): bool
    {
        return (bool) $role->is_template || $this->isSystemRoleName($role->name);
    }

    public function wouldDeactivateLastActiveSuperAdmin(User $user, bool $fieldPresent, bool $nextIsActive): bool
    {
        return $this->isSuperAdmin($user)
            && $this->activeSuperAdminCount() <= 1
            && $fieldPresent
            && ! $nextIsActive;
    }

    public function wouldWeakenLastActiveSuperAdmin(User $user, bool $fieldPresent, mixed $nextRoleId): bool
    {
        if (! $this->isSuperAdmin($user) || $this->activeSuperAdminCount() > 1 || ! $fieldPresent) {
            return false;
        }

        $nextRole = $nextRoleId ? Role::query()->find($nextRoleId) : null;

        return ! $nextRole || ! $this->isSuperAdminRole($nextRole->name);
    }

    public function wouldRemoveLastActiveSuperAdmin(User $user, Role $role): bool
    {
        return $this->isSuperAdminRole($role->name)
            && $this->activeSuperAdminCount() <= 1
            && $user->is_active
            && $user->hasRole($role->name);
    }
}
