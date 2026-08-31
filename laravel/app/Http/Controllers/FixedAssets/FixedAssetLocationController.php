<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetLocationPageData;
use App\Application\FixedAssets\FixedAssetLocationService;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetLocationController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetLocationService $locationService,
        private readonly FixedAssetLocationPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');

        $filters = $request->only(['search', 'branch_id', 'status']);

        return Inertia::render('FixedAssets/Locations', $this->pageData->indexData($filters, $request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:fixed_asset_location,code'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->locationService->createLocation($validated, $this->actorId($request));

        return redirect()->route('fixed-asset-locations.index')
            ->with('success', __('Fixed asset location created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.edit');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:fixed_asset_location,code,'.$id],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'is_active' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $this->locationService->updateLocation($id, $validated, $this->actorId($request));

        return redirect()->route('fixed-asset-locations.index')
            ->with('success', __('Fixed asset location updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.delete');

        $this->locationService->deleteLocation($id, $this->actorId($request));

        return redirect()->route('fixed-asset-locations.index')
            ->with('success', __('Fixed asset location deleted successfully.'));
    }

    private function actorId(Request $request): ?int
    {
        $actorId = $request->user()?->getAuthIdentifier();

        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
