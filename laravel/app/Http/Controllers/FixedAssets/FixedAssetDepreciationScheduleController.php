<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetDepreciationEngineService;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FixedAssetDepreciationScheduleController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetDepreciationEngineService $depreciationEngine,
    ) {}

    public function store(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.edit');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $this->depreciationEngine->generateSchedule($id);

        return redirect()->route('fixed-assets.show', $id)
            ->with('success', __('Depreciation schedule generated successfully.'));
    }
}
