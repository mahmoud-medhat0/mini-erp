<?php

namespace App\Http\Controllers;

use App\Application\Purchasing\LandedCostAllocationPageData;
use App\Application\Purchasing\LandedCostAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LandedCostAllocationController extends Controller
{
    public function __construct(
        private readonly LandedCostAllocationService $landedCostService,
        private readonly LandedCostAllocationPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Purchasing/LandedCosts', $this->pageData->indexData($request->only(['search', 'status'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->landedCostService->create($this->validatedAllocation($request), $request->user()?->id);

        return back()->with('success', __('Landed cost allocation created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->landedCostService->update($id, $this->validatedAllocation($request, true), $request->user()?->id);

        return back()->with('success', __('Landed cost allocation updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->landedCostService->submit($id, $request->user()?->id);

        return back()->with('success', __('Landed cost allocation submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->landedCostService->approve($id, $request->user()?->id);

        return back()->with('success', __('Landed cost allocation approved.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->landedCostService->post($id, $request->user()?->id);

        return back()->with('success', __('Landed cost allocation posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->landedCostService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Landed cost allocation cancelled.'));
    }

    private function validatedAllocation(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'goods_receipt_id' => [$isUpdate ? 'nullable' : 'required', 'uuid', 'exists:goods_receipt,id'],
            'supplier_id' => [$isUpdate ? 'nullable' : 'required', 'uuid', 'exists:supplier,id'],
            'allocation_date' => [$isUpdate ? 'nullable' : 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => [$isUpdate ? 'nullable' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer'],
            'allocation_method' => [$isUpdate ? 'nullable' : 'required', Rule::in(LandedCostAllocationService::ALLOCATION_METHODS)],
            'cost_amount_minor' => [$isUpdate ? 'nullable' : 'required', 'integer', 'min:1'],
            'tax_amount_minor' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
            'lines' => ['nullable', 'array'],
            'lines.*.goods_receipt_line_id' => ['required_with:lines', 'uuid', 'exists:goods_receipt_line,id'],
            'lines.*.allocated_cost_minor' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
