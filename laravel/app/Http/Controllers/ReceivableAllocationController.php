<?php

namespace App\Http\Controllers;

use App\Application\Accounting\ReceivableAllocationPageData;
use App\Application\Accounting\ReceivableAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableAllocationController extends Controller
{
    public function __construct(
        private readonly ReceivableAllocationPageData $pageData,
        private readonly ReceivableAllocationService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('ReceivableAllocations/Index', $this->pageData->indexData($request->only(['customer_id', 'receipt_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('customers.view');

        return $this->pageData->datatable($request->all());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_id' => ['required', 'string', 'uuid', 'exists:customer_receipt,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.receivable_entry_id' => ['required', 'string', 'uuid', 'exists:receivable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->allocateReceipt(
            $validated['receipt_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', __('Receipt allocated successfully.'));
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->service->reverseAllocation($id, (int) $request->user()->id);

        return back()->with('success', __('Allocation reversed successfully.'));
    }
}
