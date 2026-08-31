<?php

namespace App\Http\Controllers;

use App\Application\Accounting\PayableEntrySettlementPageData;
use App\Application\Accounting\PayableEntrySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayableEntrySettlementController extends Controller
{
    public function __construct(
        private readonly PayableEntrySettlementPageData $pageData,
        private readonly PayableEntrySettlementService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Purchasing/PayableSettlements', $this->pageData->indexData($request->only(['supplier_id', 'source_entry_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_payable_entry_id' => ['required', 'string', 'uuid', 'exists:payable_entry,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.target_payable_entry_id' => ['required', 'string', 'uuid', 'exists:payable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->settleDebit(
            $validated['source_payable_entry_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', __('Adjustment debit settled successfully against target bill(s).'));
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->reverseSettlement($id, $validated['reason'], (int) $request->user()->id);

        return back()->with('success', __('Payable settlement reversed successfully.'));
    }
}
