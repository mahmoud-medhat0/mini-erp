<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetDepreciationPostingService;
use App\Domain\Accounting\PeriodClosedException;
use App\Http\Controllers\Controller;
use App\Models\FinancialPeriod;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FixedAssetDepreciationRunController extends Controller
{
    public function __construct(
        private readonly FixedAssetDepreciationPostingService $postingService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $runs = FixedAssetDepreciationRun::query()
            ->with(['financialPeriod', 'journalEntry', 'poster'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $openPeriods = FinancialPeriod::query()
            ->whereIn('status', ['open', 'reopened'])
            ->orderBy('start_date')
            ->get();

        return Inertia::render('FixedAssets/DepreciationRuns/Index', [
            'runs' => $runs,
            'openPeriods' => $openPeriods,
            'can' => [
                'post' => ($request->user()?->can('fixedAssets.post') ?? false) && ($request->user()?->can('view_financials') ?? false),
                'reverse' => ($request->user()?->can('fixedAssets.reverse') ?? false) && ($request->user()?->can('view_financials') ?? false),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.post');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $validated = $request->validate([
            'financial_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($actorId) ? (int) $actorId : null;

        try {
            $run = $this->postingService->postDepreciationRun(
                financialPeriodId: $validated['financial_period_id'],
                userId: $userId
            );
        } catch (PeriodClosedException $e) {
            throw ValidationException::withMessages([
                'financial_period_id' => [$e->getMessage()],
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'financial_period_id' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets.depreciation-runs.show', $run->id)
            ->with('success', __('Depreciation run posted successfully.'));
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $run = FixedAssetDepreciationRun::query()
            ->with(['financialPeriod', 'journalEntry', 'poster', 'schedules.asset.category'])
            ->findOrFail($id);

        return Inertia::render('FixedAssets/DepreciationRuns/Show', [
            'run' => $run,
            'can' => [
                'reverse' => ($request->user()?->can('fixedAssets.reverse') ?? false) && ($request->user()?->can('view_financials') ?? false) && $run->status === 'posted',
            ],
        ]);
    }

    public function preview(Request $request, string $financialPeriodId): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $period = FinancialPeriod::query()->findOrFail($financialPeriodId);

        $schedules = FixedAssetDepreciationSchedule::query()
            ->with(['asset.category'])
            ->where('financial_period_id', $period->id)
            ->where('status', 'planned')
            ->whereHas('asset', function ($q) {
                $q->where('status', 'active');
            })
            ->orderBy('id')
            ->get();

        $totalDepreciationMinor = (int) $schedules->sum('depreciation_minor');

        return Inertia::render('FixedAssets/DepreciationRuns/Preview', [
            'period' => $period,
            'schedules' => $schedules,
            'totalDepreciationMinor' => $totalDepreciationMinor,
            'assetCount' => $schedules->pluck('fixed_asset_id')->unique()->count(),
            'can' => [
                'post' => ($request->user()?->can('fixedAssets.post') ?? false) && ($request->user()?->can('view_financials') ?? false),
            ],
        ]);
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.reverse');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $actorId = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($actorId) ? (int) $actorId : null;

        try {
            $run = $this->postingService->reverseDepreciationRun($id, $userId);
        } catch (PeriodClosedException $e) {
            throw ValidationException::withMessages([
                'run' => [$e->getMessage()],
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'run' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets.depreciation-runs.show', $run->id)
            ->with('success', __('Depreciation run reversed successfully.'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if (! $request->user()?->can($permission)) {
            abort(403);
        }
    }

    private function authorizeSensitiveCapability(Request $request, string $capability): void
    {
        if (! $request->user()?->can($capability)) {
            abort(403);
        }
    }
}
