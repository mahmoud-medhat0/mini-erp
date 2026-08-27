<?php

namespace App\Http\Controllers;

use App\Application\Rentals\RentalFulfillmentService;
use App\Application\Rentals\RentalHandoverPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RentalHandoverController extends Controller
{
    public function __construct(
        private readonly RentalFulfillmentService $rentalFulfillmentService,
        private readonly RentalHandoverPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Rentals/Handovers', $this->pageData->indexData($request->only(['search', 'status'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->rentalFulfillmentService->createHandover($this->validatedHandover($request), $request->user()?->id);

        return back()->with('success', __('Rental handover saved.'));
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $this->rentalFulfillmentService->confirmHandover($id, $request->user()?->id);

        return back()->with('success', __('Rental handover confirmed.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->rentalFulfillmentService->cancelHandover($id, $request->user()?->id);

        return back()->with('success', __('Rental handover cancelled.'));
    }

    private function validatedHandover(Request $request): array
    {
        return $request->validate([
            'rental_contract_id' => ['required', 'uuid', 'exists:rental_contract,id'],
            'handover_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.rental_contract_line_id' => ['required', 'uuid', 'exists:rental_contract_line,id'],
            'lines.*.condition_out' => ['required', Rule::in(RentalFulfillmentService::CONDITIONS_OUT)],
            'lines.*.accessories_out' => ['nullable'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);
    }
}
