<?php

namespace App\Http\Controllers;

use App\Application\Rentals\RentalFulfillmentService;
use App\Application\Rentals\RentalReturnPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RentalReturnController extends Controller
{
    public function __construct(
        private readonly RentalFulfillmentService $rentalFulfillmentService,
        private readonly RentalReturnPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Rentals/Returns', $this->pageData->indexData($request->only(['search', 'status'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->rentalFulfillmentService->createReturn($this->validatedReturn($request), $request->user()?->id);

        return back()->with('success', __('Rental return saved.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->rentalFulfillmentService->submitReturn($id, $request->user()?->id);

        return back()->with('success', __('Rental return submitted.'));
    }

    public function complete(Request $request, string $id): RedirectResponse
    {
        $this->rentalFulfillmentService->completeReturn($id, $request->user()?->id);

        return back()->with('success', __('Rental return completed.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->rentalFulfillmentService->cancelReturn($id, $request->user()?->id);

        return back()->with('success', __('Rental return cancelled.'));
    }

    private function validatedReturn(Request $request): array
    {
        return $request->validate([
            'rental_contract_id' => ['required', 'uuid', 'exists:rental_contract,id'],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.rental_contract_line_id' => ['required', 'uuid', 'exists:rental_contract_line,id'],
            'lines.*.condition_in' => ['required', Rule::in(RentalFulfillmentService::CONDITIONS_IN)],
            'lines.*.outcome' => ['required', Rule::in(RentalFulfillmentService::RETURN_OUTCOMES)],
            'lines.*.estimated_damage_charge_minor' => ['nullable', 'integer', 'min:0'],
            'lines.*.accessories_in' => ['nullable'],
            'lines.*.inspection_notes' => ['nullable', 'string'],
        ]);
    }
}
