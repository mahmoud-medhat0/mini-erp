<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function setPermissionsTeamId;

class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            $this->clearContext();

            return $next($request);
        }

        $company = $this->resolveCompany($request, $user);

        if (! $company instanceof Company) {
            $this->clearContext();
            $request->session()->forget(['active_company_id', 'active_branch_id']);

            if (! $this->isTenantOptionalRoute($request)) {
                return redirect()->route('onboarding.create');
            }

            return $next($request);
        }

        $branch = $this->resolveBranch($request, $company);

        $request->session()->put('active_company_id', $company->id);

        if ($branch instanceof Branch) {
            $request->session()->put('active_branch_id', $branch->id);
        } else {
            $request->session()->forget('active_branch_id');
        }

        $this->tenantContext->set($company, $branch);
        setPermissionsTeamId($company->id);

        return $next($request);
    }

    private function resolveCompany(Request $request, User $user): ?Company
    {
        $activeCompanyId = $request->session()->get('active_company_id');

        $companies = $user->companies()
            ->orderBy('company.created_at')
            ->get();

        if ($activeCompanyId !== null) {
            $company = $companies->firstWhere('id', (string) $activeCompanyId);

            if ($company instanceof Company) {
                return $company;
            }
        }

        return $companies->first();
    }

    private function resolveBranch(Request $request, Company $company): ?Branch
    {
        $activeBranchId = $request->session()->get('active_branch_id');

        if ($activeBranchId !== null) {
            $branch = $company->branches()
                ->whereKey((string) $activeBranchId)
                ->where('is_active', true)
                ->first();

            if ($branch instanceof Branch) {
                return $branch;
            }
        }

        return $company->branches()
            ->where('is_active', true)
            ->orderBy('code')
            ->first();
    }

    private function clearContext(): void
    {
        $this->tenantContext->set(null, null);
        setPermissionsTeamId(null);
    }

    private function isTenantOptionalRoute(Request $request): bool
    {
        return $request->routeIs(
            'health',
            'locale.update',
            'login',
            'login.store',
            'logout',
            'onboarding.*',
        );
    }
}
