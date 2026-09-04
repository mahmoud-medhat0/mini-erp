<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Catalog\ProductPageData;
use App\Application\Catalog\ProductService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Catalog/Products', $this->pageData->indexData($request->only(['search', 'type', 'status', 'product_category_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('products.view');

        return $this->pageData->datatable($request->only(['type', 'status', 'product_category_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required'],
            'description' => ['nullable'],
            'type' => ['required', 'string', 'in:stock,service,non_stock'],
            'unit_of_measure_id' => ['required', 'uuid'],
            'product_category_id' => ['nullable', 'uuid'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'is_sales_enabled' => ['boolean'],
            'is_purchase_enabled' => ['boolean'],
        ]);

        $this->productService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Product created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required'],
            'description' => ['nullable'],
            'type' => ['required', 'string', 'in:stock,service,non_stock'],
            'unit_of_measure_id' => ['required', 'uuid'],
            'product_category_id' => ['nullable', 'uuid'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'is_sales_enabled' => ['boolean'],
            'is_purchase_enabled' => ['boolean'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $this->productService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Product updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->productService->delete($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Product deleted successfully.'));
    }
}
