<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\Attachments\AttachmentService;
use App\Application\FixedAssets\FixedAssetCapitalizationService;
use App\Application\FixedAssets\FixedAssetCategoryService;
use App\Application\FixedAssets\FixedAssetRegisterService;
use App\Domain\Accounting\PeriodClosedException;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FixedAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FixedAssetController extends Controller
{
    public function __construct(
        private readonly FixedAssetRegisterService $assetService,
        private readonly FixedAssetCategoryService $categoryService,
        private readonly FixedAssetCapitalizationService $capitalizationService,
        private readonly AttachmentService $attachmentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');

        $filters = $request->only(['search', 'category_id', 'status']);
        $assets = $this->assetService->listAssets($filters);
        $categories = $this->categoryService->listCategories();

        return Inertia::render('FixedAssets/Index', [
            'assets' => $assets,
            'categories' => $categories,
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('fixedAssets.create') ?? false,
                'edit' => $request->user()?->can('fixedAssets.edit') ?? false,
                'delete' => $request->user()?->can('fixedAssets.delete') ?? false,
                'post' => $request->user()?->can('fixedAssets.post') ?? false,
                'reverse' => $request->user()?->can('fixedAssets.reverse') ?? false,
                'export' => $request->user()?->can('fixedAssets.export') ?? false,
                'view_financials' => $request->user()?->can('view_financials') ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.create');

        $categories = $this->categoryService->listCategories()->where('is_active', true)->values();
        $currencies = Currency::query()->get(['code', 'name', 'symbol']);

        return Inertia::render('FixedAssets/Create', [
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
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

        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()
            ->with(['category', 'currencyModel', 'journalEntry', 'capitalizer', 'creator', 'updater'])
            ->findOrFail($id);

        $attachments = [];
        if ($request->user()) {
            $attachments = $this->attachmentService->listForEntity('fixed_asset', $asset->id, $request->user());
        }

        return Inertia::render('FixedAssets/Show', [
            'asset' => $asset,
            'attachments' => $attachments,
            'can' => [
                'edit' => ($request->user()?->can('fixedAssets.edit') ?? false) && $asset->status === 'draft',
                'delete' => ($request->user()?->can('fixedAssets.delete') ?? false) && $asset->status === 'draft',
                'post' => ($request->user()?->can('fixedAssets.post') ?? false) && ($request->user()?->can('view_financials') ?? false) && $asset->status === 'draft',
                'reverse' => ($request->user()?->can('fixedAssets.reverse') ?? false) && ($request->user()?->can('view_financials') ?? false) && $asset->status === 'active' && $asset->capitalization_mode === 'manual_capitalization',
                'view_financials' => $request->user()?->can('view_financials') ?? false,
            ],
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'fixedAssets.edit');

        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()->with('category')->findOrFail($id);

        if ($asset->status !== 'draft') {
            abort(403);
        }

        $categories = $this->categoryService->listCategories()->where('is_active', true)->values();
        $currencies = Currency::query()->get(['code', 'name', 'symbol']);

        return Inertia::render('FixedAssets/Edit', [
            'asset' => $asset,
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
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

    public function capitalize(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.post');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $validated = $request->validate([
            'capitalization_mode' => ['required', 'string', 'in:opening_already_capitalized,manual_capitalization'],
            'capitalization_date' => ['nullable', 'date'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        try {
            $asset = $this->capitalizationService->capitalize(
                $id,
                $validated['capitalization_mode'],
                $validated['capitalization_date'] ?? null,
                $userActorId
            );
        } catch (PeriodClosedException $e) {
            throw ValidationException::withMessages([
                'capitalization_date' => [$e->getMessage()],
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'capitalization_mode' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset capitalized successfully.'));
    }

    public function reverseCapitalization(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.reverse');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        try {
            $asset = $this->capitalizationService->reverseCapitalization($id, $userActorId);
        } catch (PeriodClosedException $e) {
            throw ValidationException::withMessages([
                'asset' => [$e->getMessage()],
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'asset' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset capitalization reversed successfully.'));
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
