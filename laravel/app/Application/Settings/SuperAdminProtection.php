<?php

namespace App\Application\Settings;

use App\Models\User;
use Spatie\Permission\Models\Role;

class SuperAdminProtection
{
    public function isSuperAdmin(User $user): bool
    {
        return $user->roles()->whereRaw('LOWER(name) LIKE ?', ['%super%'])->exists();
    }

    public function isSuperAdminRole(string $roleName): bool
    {
        return str_contains(strtolower($roleName), 'super');
    }

    public function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%super%']))
            ->count();
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
