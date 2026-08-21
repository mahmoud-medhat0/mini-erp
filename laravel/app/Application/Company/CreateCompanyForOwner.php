<?php

namespace App\Application\Company;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function setPermissionsTeamId;

class CreateCompanyForOwner
{
    /**
     * @param  array{
     *     company: array{name: array{en: string, ar: string}, base_currency?: string},
     *     branch: array{code: string, name: array{en: string, ar: string}}
     * }  $payload
     * @return array{company: Company, branch: Branch}
     */
    public function handle(User $owner, array $payload): array
    {
        $guard = config('erp_rbac.guard', 'web');

        return DB::transaction(function () use ($owner, $payload, $guard): array {
            $company = Company::query()->create([
                'name' => $payload['company']['name'],
                'base_currency' => $payload['company']['base_currency'] ?? 'EGP',
                'settings_json' => [],
            ]);

            $branch = $company->branches()->create([
                'code' => Str::upper($payload['branch']['code']),
                'name' => $payload['branch']['name'],
                'is_active' => true,
            ]);

            $company->users()->syncWithoutDetaching([$owner->id]);

            $roles = $this->createCompanyRoles($company, $guard);
            $companyAdmin = $roles['COMPANY_ADMIN'] ?? null;

            if ($companyAdmin instanceof Role) {
                setPermissionsTeamId($company->id);
                $owner->assignRole($companyAdmin);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'company' => $company->refresh(),
                'branch' => $branch->refresh(),
            ];
        });
    }

    /**
     * @return array<string, Role>
     */
    private function createCompanyRoles(Company $company, string $guard): array
    {
        $roles = [];

        Role::query()
            ->where('guard_name', $guard)
            ->whereNull('company_id')
            ->where('is_template', true)
            ->with('permissions')
            ->get()
            ->each(function (Role $template) use ($company, $guard, &$roles): void {
                $role = Role::query()->updateOrCreate(
                    [
                        'name' => $template->name,
                        'guard_name' => $guard,
                        'company_id' => $company->id,
                    ],
                    [
                        'is_template' => false,
                    ],
                );

                $role->permissions()->sync($template->permissions->pluck('id')->all());
                $roles[$role->name] = $role->refresh();
            });

        return $roles;
    }
}
