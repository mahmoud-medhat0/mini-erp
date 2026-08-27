<?php

namespace App\Http\Controllers;

use App\Application\Rentals\RentalInvoicePageData;
use App\Application\Rentals\RentalInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RentalInvoiceController extends Controller
{
    public function __construct(
        private readonly RentalInvoiceService $rentalInvoiceService,
        private readonly RentalInvoicePageData $rentalInvoicePageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Rentals/Invoices', $this->rentalInvoicePageData->indexData([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'invoice_type' => $request->query('invoice_type'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->rentalInvoiceService->create($this->validatedInvoice($request), $request->user()?->id);

        return back()->with('success', __('Rental invoice saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->rentalInvoiceService->update($id, $this->validatedInvoice($request, true), $request->user()?->id);

        return back()->with('success', __('Rental invoice updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->rentalInvoiceService->submit($id, $request->user()?->id);

        return back()->with('success', __('Rental invoice submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->rentalInvoiceService->approve($id, $request->user()?->id);

        return back()->with('success', __('Rental invoice approved.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->rentalInvoiceService->post($id, $request->user()?->id);

        return back()->with('success', __('Rental invoice posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->rentalInvoiceService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Rental invoice cancelled.'));
    }

    private function validatedInvoice(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'rental_contract_id' => [$isUpdate ? 'sometimes' : 'required', 'uuid', 'exists:rental_contract,id'],
            'invoice_type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(RentalInvoiceService::INVOICE_TYPES)],
            'invoice_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'billing_period_start' => ['nullable', 'date'],
            'billing_period_end' => ['nullable', 'date', 'after_or_equal:billing_period_start'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
            'lines' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'lines.*.line_type' => ['required', Rule::in(RentalInvoiceService::LINE_TYPES)],
            'lines.*.rental_contract_line_id' => ['nullable', 'uuid', 'exists:rental_contract_line,id'],
            'lines.*.rental_return_line_id' => ['nullable', 'uuid', 'exists:rental_return_line,id'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.quantity_e6' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_amount_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);
    }
}
