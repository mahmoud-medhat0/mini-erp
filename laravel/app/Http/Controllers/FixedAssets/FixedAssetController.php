<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetPageData;
use App\Application\FixedAssets\FixedAssetRegisterService;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetRegisterService $assetService,
        private readonly FixedAssetPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');

        $filters = $request->only(['search', 'category_id', 'status', 'branch_id', 'location_id']);

        return Inertia::render('FixedAssets/Index', $this->pageData->indexData($filters, $request->user()));
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'fixedAssets.view');

        return $this->pageData->datatable($request->only(['category_id', 'status', 'branch_id', 'location_id']));
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.create');

        return Inertia::render('FixedAssets/Create', $this->pageData->createData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.create');

        $validated = $request->validate([
            'asset_number' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fixed_asset_category_id' => ['required', 'uuid', 'exists:fixed_asset_category,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['required', 'date'],
            'cost_minor' => ['required', 'integer', 'gt:0'],
            'salvage_value_minor' => ['sometimes', 'integer', 'min:0'],
            'useful_life_months' => ['sometimes', 'integer', 'min:1'],
            'opening_accumulated_depreciation_minor' => ['sometimes', 'integer', 'min:0'],
            'status' => ['prohibited'],
            'serial_number' => ['nullable', 'string', 'max:100'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        $asset = $this->assetService->createAsset($validated, $userActorId);

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset created successfully.'));
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');

        return Inertia::render('FixedAssets/Show', $this->pageData->showData($id, $request->user()));
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'fixedAssets.edit');

        $asset = $this->pageData->assetForEditing($id);

        if ($asset->status !== 'draft') {
            abort(403);
        }

        return Inertia::render('FixedAssets/Edit', $this->pageData->editData($asset));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.edit');

        $validated = $request->validate([
            'name' => ['sometimes', 'array'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'acquisition_date' => ['sometimes', 'date'],
            'in_service_date' => ['sometimes', 'date'],
            'cost_minor' => ['sometimes', 'integer', 'gt:0'],
            'salvage_value_minor' => ['sometimes', 'integer', 'min:0'],
            'useful_life_months' => ['sometimes', 'integer', 'min:1'],
            'opening_accumulated_depreciation_minor' => ['sometimes', 'integer', 'min:0'],
            'status' => ['prohibited'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        $asset = $this->assetService->updateAsset($id, $validated, $userActorId);

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.delete');

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        $this->assetService->deleteAsset($id, $userActorId);

        return redirect()->route('fixed-assets.index')
            ->with('success', __('Fixed asset deleted successfully.'));
    }
}
