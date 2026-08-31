<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetDepreciationPostingService;
use App\Application\FixedAssets\FixedAssetDepreciationRunPageData;
use App\Domain\Accounting\PeriodClosedException;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FixedAssetDepreciationRunController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetDepreciationPostingService $postingService,
        private readonly FixedAssetDepreciationRunPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        return Inertia::render('FixedAssets/DepreciationRuns/Index', $this->pageData->indexData($request->user()));
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

        return Inertia::render('FixedAssets/DepreciationRuns/Show', $this->pageData->showData($id, $request->user()));
    }

    public function preview(Request $request, string $financialPeriodId): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        return Inertia::render('FixedAssets/DepreciationRuns/Preview', $this->pageData->previewData($financialPeriodId, $request->user()));
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
}
