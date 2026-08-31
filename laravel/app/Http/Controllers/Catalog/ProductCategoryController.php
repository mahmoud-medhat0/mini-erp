<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Catalog\ProductCategoryPageData;
use App\Application\Catalog\ProductCategoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $categoryService,
        private readonly ProductCategoryPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Catalog/ProductCategories', $this->pageData->indexData($request->only(['search'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required'],
            'description' => ['nullable'],
            'is_active' => ['boolean'],
        ]);

        $this->categoryService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Product Category created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required'],
            'description' => ['nullable'],
            'is_active' => ['boolean'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $this->categoryService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Product Category updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->categoryService->delete($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Product Category deleted successfully.'));
    }
}
