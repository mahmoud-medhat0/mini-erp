<?php

namespace App\Http\Controllers;

use App\Application\Rentals\RentableItemPageData;
use App\Application\Rentals\RentableItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RentableItemController extends Controller
{
    public function __construct(
        private readonly RentableItemService $rentableItemService,
        private readonly RentableItemPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Rentals/RentableItems', $this->pageData->indexData($request->only(['search', 'status', 'item_source', 'branch_id', 'warehouse_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->rentableItemService->create($this->validatedRentableItem($request), $request->user()?->id);

        return back()->with('success', __('Rentable item saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->rentableItemService->update($id, $this->validatedRentableItem($request, true), $request->user()?->id);

        return back()->with('success', __('Rentable item updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->rentableItemService->delete($id, $request->user()?->id);

        return back()->with('success', __('Rentable item deleted.'));
    }

    private function validatedRentableItem(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name.en' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],
            'item_source' => [$isUpdate ? 'sometimes' : 'required', Rule::in(RentableItemService::ITEM_SOURCES)],
            'product_id' => ['nullable', 'uuid', 'exists:product,id'],
            'fixed_asset_id' => ['nullable', 'uuid', 'exists:fixed_asset,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouse,id'],
            'status' => [$isUpdate ? 'sometimes' : 'required', Rule::in(RentableItemService::STATUSES)],
            'condition_status' => [$isUpdate ? 'sometimes' : 'required', Rule::in(RentableItemService::CONDITION_STATUSES)],
            'currency' => [$isUpdate ? 'sometimes' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'replacement_value_minor' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:0'],
            'daily_rate_minor' => ['nullable', 'integer', 'min:0'],
            'monthly_rate_minor' => ['nullable', 'integer', 'min:0'],
            'deposit_minor' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
