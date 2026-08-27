<?php

namespace App\Http\Controllers;

use App\Application\Inventory\WarehousePageData;
use App\Application\Inventory\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly WarehousePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Warehouses', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'warehouse_type' => ['required', 'string', 'max:30'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $this->warehouseService->createWarehouse($validated, $request->user()?->id);

        return back()->with('success', __('Warehouse saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'warehouse_type' => ['required', 'string', 'max:30'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $this->warehouseService->updateWarehouse($id, $validated, $request->user()?->id);

        return back()->with('success', __('Warehouse updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->warehouseService->deleteWarehouse($id, $request->user()?->id);

        return back()->with('success', __('Warehouse deleted.'));
    }
}
