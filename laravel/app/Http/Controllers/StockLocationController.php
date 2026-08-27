<?php

namespace App\Http\Controllers;

use App\Application\Inventory\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouse,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'location_type' => ['required', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $this->warehouseService->createLocation($validated, $request->user()?->id);

        return back()->with('success', __('Stock location saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouse,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'location_type' => ['required', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $this->warehouseService->updateLocation($id, $validated, $request->user()?->id);

        return back()->with('success', __('Stock location updated.'));
    }
}
