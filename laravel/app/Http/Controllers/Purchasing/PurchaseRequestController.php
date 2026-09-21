<?php

namespace App\Http\Controllers\Purchasing;

use App\Application\Purchasing\PurchaseRequestPageData;
use App\Application\Purchasing\PurchaseRequestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestPageData $pageData,
        private readonly PurchaseRequestService $requestService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Purchasing/Requests', $this->pageData->indexData($request->only(['search', 'status', 'supplier_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('purchasing.view');

        return $this->pageData->datatable($request->only(['status', 'supplier_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requestService->create($this->validatedRequest($request), $request->user()?->id);

        return back()->with('success', __('Purchase request created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->requestService->update($id, $this->validatedRequest($request, true), $request->user()?->id);

        return back()->with('success', __('Purchase request updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->requestService->submit($id, $request->user()?->id);

        return back()->with('success', __('Purchase request submitted successfully.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->requestService->approve($id, $request->user()?->id);

        return back()->with('success', __('Purchase request approved.'));
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $this->requestService->reject($id, $request->user()?->id);

        return back()->with('success', __('Purchase request rejected.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->requestService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Purchase request cancelled.'));
    }

    public function convert(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'uuid'],
            'order_date' => ['nullable', 'date'],
            'expected_receipt_date' => ['nullable', 'date'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'unit_price_minor_by_line' => ['nullable', 'array'],
            'unit_price_minor_by_line.*' => ['integer', 'min:1'],
        ]);

        $this->requestService->convertToPurchaseOrder($id, $validated, $request->user()?->id);

        return back()->with('success', __('Purchase request converted to a Purchase Order.'));
    }

    private function validatedRequest(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'supplier_id' => ['nullable', 'uuid'],
            'requested_date' => ['required', 'date'],
            'needed_by_date' => ['nullable', 'date', 'after_or_equal:requested_date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid'],
            'lines.*.unit_of_measure_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.quantity_e6' => ['required', 'integer', 'min:1'],
            'lines.*.estimated_unit_price_minor' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
