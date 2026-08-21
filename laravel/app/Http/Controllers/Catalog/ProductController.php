<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Catalog\ProductService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');
        $categoryId = $request->query('product_category_id');

        $query = Product::query()->with(['unitOfMeasure', 'category']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($type && in_array($type, ProductService::ALLOWED_TYPES, true)) {
            $query->where('type', $type);
        }

        if ($status && in_array($status, ProductService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        $products = $query->orderBy('code', 'asc')
            ->paginate(15)
            ->withQueryString();

        $uoms = UnitOfMeasure::query()->where('is_active', true)->orderBy('code', 'asc')->get();
        $categories = ProductCategory::query()->where('is_active', true)->orderBy('code', 'asc')->get();

        return Inertia::render('Catalog/Products', [
            'products' => $products,
            'uoms' => $uoms,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'product_category_id' => $categoryId,
            ],
        ]);
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

        return redirect()->back()->with('success', 'Product created successfully.');
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

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->productService->delete($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }
}
