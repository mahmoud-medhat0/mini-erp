<?php

namespace App\Http\Controllers;

use App\Application\Accounting\TreasuryTransferPageData;
use App\Application\Accounting\TreasuryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TreasuryTransferController extends Controller
{
    public function __construct(
        private readonly TreasuryTransferService $treasuryTransferService,
        private readonly TreasuryTransferPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('TreasuryTransfers/Index', $this->pageData->indexData($request->only(['search', 'status'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->treasuryTransferService->create($this->validatedTransfer($request), $request->user()?->id);

        return back()->with('success', __('Treasury transfer created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validatedTransfer($request);
        $expectedVersion = $request->integer('lock_version');

        $this->treasuryTransferService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', __('Treasury transfer updated.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->treasuryTransferService->post($id, $request->user()?->id);

        return back()->with('success', __('Treasury transfer posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->treasuryTransferService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Treasury transfer cancelled.'));
    }

    private function validatedTransfer(Request $request): array
    {
        return $request->validate([
            'transfer_date' => ['required', 'date'],
            'source_type' => ['required', 'string', 'in:cash,bank'],
            'source_cash_account_id' => ['nullable', 'uuid', 'exists:cash_account,id'],
            'source_bank_account_id' => ['nullable', 'uuid', 'exists:bank_account,id'],
            'destination_type' => ['required', 'string', 'in:cash,bank'],
            'destination_cash_account_id' => ['nullable', 'uuid', 'exists:cash_account,id'],
            'destination_bank_account_id' => ['nullable', 'uuid', 'exists:bank_account,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fiscal_year_id' => ['required', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
        ]);
    }
}
