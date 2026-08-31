<?php

namespace App\Application\Settings;

use App\Models\Branch;
use App\Models\BranchApprovalRule;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettingsHomePageData
{
    /**
     * @return array{
     *     overview: array{
     *         companyRecords: int|null,
     *         activeBranches: int|null,
     *         totalBranches: int|null,
     *         numberSequences: int|null,
     *         activeUsers: int|null,
     *         totalUsers: int|null,
     *         activeApprovalRules: int|null,
     *         totalApprovalRules: int|null,
     *         completedEssentials: int,
     *         totalEssentials: int
     *     }
     * }
     */
    public function indexData(User $user): array
    {
        $canViewAll = $this->canAny($user, ['settings.view', 'settings.configure']);
        $canViewCompany = $canViewAll || $user->can('settings.company');
        $canViewBranches = $canViewAll || $user->can('settings.branches');
        $canViewNumbering = $canViewAll || $user->can('settings.numbering');
        $canViewUsers = $canViewAll || $user->can('users.configure');
        $canViewApprovalRules = $canViewAll || $user->can('approvals.configure');

        $companyRecords = $canViewCompany ? Company::query()->count() : null;
        $activeBranches = $canViewBranches ? Branch::query()->where('is_active', true)->count() : null;
        $totalBranches = $canViewBranches ? Branch::query()->count() : null;
        $numberSequences = $canViewNumbering ? DB::table('number_sequence')->count() : null;
        $activeUsers = $canViewUsers ? User::query()->where('is_active', true)->count() : null;
        $totalUsers = $canViewUsers ? User::query()->count() : null;
        $activeApprovalRules = $canViewApprovalRules ? BranchApprovalRule::query()->where('is_active', true)->count() : null;
        $totalApprovalRules = $canViewApprovalRules ? BranchApprovalRule::query()->count() : null;

        $essentialSteps = [];
        if ($companyRecords !== null) {
            $essentialSteps[] = $companyRecords > 0;
        }
        if ($activeBranches !== null) {
            $essentialSteps[] = $activeBranches > 0;
        }
        if ($numberSequences !== null) {
            $essentialSteps[] = $numberSequences > 0;
        }
        if ($activeUsers !== null) {
            $essentialSteps[] = $activeUsers > 0;
        }

        return [
            'overview' => [
                'companyRecords' => $companyRecords,
                'activeBranches' => $activeBranches,
                'totalBranches' => $totalBranches,
                'numberSequences' => $numberSequences,
                'activeUsers' => $activeUsers,
                'totalUsers' => $totalUsers,
                'activeApprovalRules' => $activeApprovalRules,
                'totalApprovalRules' => $totalApprovalRules,
                'completedEssentials' => count(array_filter($essentialSteps)),
                'totalEssentials' => count($essentialSteps),
            ],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
