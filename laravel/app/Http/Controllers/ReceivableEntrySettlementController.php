<?php

namespace App\Http\Controllers;

use App\Application\Accounting\ReceivableEntrySettlementPageData;
use App\Application\Accounting\ReceivableEntrySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableEntrySettlementController extends Controller
{
    public function __construct(
        private readonly ReceivableEntrySettlementPageData $pageData,
        private readonly ReceivableEntrySettlementService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/ReceivableSettlements', $this->pageData->indexData($request->only(['customer_id', 'source_entry_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_receivable_entry_id' => ['required', 'string', 'uuid', 'exists:receivable_entry,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.target_receivable_entry_id' => ['required', 'string', 'uuid', 'exists:receivable_entry,id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->settleCredit(
            $validated['source_receivable_entry_id'],
            $validated['lines'],
            (int) $request->user()->id
        );

        return back()->with('success', __('Credit settled successfully against target invoice(s).'));
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->reverseSettlement($id, $validated['reason'], (int) $request->user()->id);

        return back()->with('success', __('Receivable settlement reversed successfully.'));
    }
}
