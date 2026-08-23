<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetCategoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetCategoryController extends Controller
{
    public function __construct(
        private readonly FixedAssetCategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');

        $categories = $this->categoryService->listCategories();

        return Inertia::render('FixedAssets/Categories', [
            'categories' => $categories,
            'can' => [
                'create' => $request->user()?->can('fixedAssets.create') ?? false,
                'edit' => $request->user()?->can('fixedAssets.edit') ?? false,
                'delete' => $request->user()?->can('fixedAssets.delete') ?? false,
                'view_financials' => $request->user()?->can('view_financials') ?? false,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'salvage_value_minor' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        $this->categoryService->createCategory($validated, $userActorId);

        return redirect()->back()->with('success', __('Fixed asset category created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.edit');

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'array'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'useful_life_months' => ['sometimes', 'integer', 'min:1'],
            'salvage_value_minor' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        $this->categoryService->updateCategory($id, $validated, $userActorId);

        return redirect()->back()->with('success', __('Fixed asset category updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.delete');

        $actorId = $request->user()?->getAuthIdentifier();
        $userActorId = is_numeric($actorId) ? (int) $actorId : null;

        $this->categoryService->deleteCategory($id, $userActorId);

        return redirect()->back()->with('success', __('Fixed asset category deleted successfully.'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if (! $request->user()?->can($permission)) {
            abort(403, 'Unauthorized.');
        }
    }
}
