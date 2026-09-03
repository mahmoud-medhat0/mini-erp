<?php

namespace App\Http\Controllers;

use App\Application\Accounting\PayableAllocationPageData;
use App\Application\Accounting\PayableAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayableAllocationController extends Controller
{
    public function __construct(
        private readonly PayableAllocationPageData $pageData,
        private readonly PayableAllocationService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('PayableAllocations/Index', $this->pageData->indexData($request->only(['supplier_id', 'payment_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('suppliers.view');

        return $this->pageData->datatable($request->all());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'string', 'uuid', 'exists:supplier_payment,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.payable_entry_id' => ['required', 'string', 'uuid', 'exists:payable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->allocatePayment(
            $validated['payment_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', __('Payment allocated successfully.'));
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->service->reverseAllocation($id, (int) $request->user()->id);

        return back()->with('success', __('Allocation reversed successfully.'));
    }
}
