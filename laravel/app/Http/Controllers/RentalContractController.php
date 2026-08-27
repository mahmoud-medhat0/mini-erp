<?php

namespace App\Http\Controllers;

use App\Application\Rentals\RentalContractPageData;
use App\Application\Rentals\RentalContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RentalContractController extends Controller
{
    public function __construct(
        private readonly RentalContractService $rentalContractService,
        private readonly RentalContractPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Rentals/Contracts', $this->pageData->indexData($request->only(['search', 'status', 'customer_id', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->rentalContractService->create($this->validatedContract($request), $request->user()?->id);

        return back()->with('success', __('Rental contract saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->rentalContractService->update($id, $this->validatedContract($request, true), $request->user()?->id);

        return back()->with('success', __('Rental contract updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->rentalContractService->submit($id, $request->user()?->id);

        return back()->with('success', __('Rental contract submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->rentalContractService->approve($id, $request->user()?->id);

        return back()->with('success', __('Rental contract approved.'));
    }

    public function activate(Request $request, string $id): RedirectResponse
    {
        $this->rentalContractService->activate($id, $request->user()?->id);

        return back()->with('success', __('Rental contract activated.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->rentalContractService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Rental contract cancelled.'));
    }

    private function validatedContract(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'customer_id' => [$isUpdate ? 'sometimes' : 'required', 'uuid', 'exists:customer,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'contract_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'start_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'expected_end_date' => [$isUpdate ? 'sometimes' : 'required', 'date', 'after_or_equal:start_date'],
            'currency' => [$isUpdate ? 'sometimes' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'billing_cycle' => [$isUpdate ? 'sometimes' : 'required', Rule::in(RentalContractService::BILLING_CYCLES)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
            'lines' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'lines.*.rentable_item_id' => ['required', 'uuid', 'exists:rentable_item,id'],
            'lines.*.description.en' => ['nullable', 'string'],
            'lines.*.description.ar' => ['nullable', 'string'],
            'lines.*.start_date' => ['nullable', 'date'],
            'lines.*.end_date' => ['nullable', 'date'],
            'lines.*.rate_type' => ['required', Rule::in(RentalContractService::RATE_TYPES)],
            'lines.*.rate_minor' => ['required', 'integer', 'min:0'],
            'lines.*.estimated_units' => ['required', 'integer', 'min:1'],
            'lines.*.deposit_minor' => ['nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);
    }
}
