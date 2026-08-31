<?php

namespace App\Http\Controllers;

use App\Application\Sales\CustomerCreditNotePageData;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerCreditNoteController extends Controller
{
    public function __construct(
        private readonly CustomerCreditNoteService $customerCreditNoteService,
        private readonly CustomerInvoiceRevisionService $customerInvoiceRevisionService,
        private readonly CustomerCreditNotePageData $customerCreditNotePageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/CustomerCreditNotes', $this->customerCreditNotePageData->indexData([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'customer_id' => $request->query('customer_id'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'customer_invoice_id' => ['nullable', 'uuid'],
            'sales_return_id' => ['nullable', 'uuid'],
            'credit_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'tax_mode' => ['nullable', 'string', 'in:none,manual_rate,manual_amount'],
            'tax_rate_bps' => ['nullable', 'integer', 'min:0'],
            'tax_minor_override' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.customer_invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity_e6' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_rate_bps' => ['nullable', 'integer', 'min:0'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
        ]);

        $this->customerCreditNoteService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Credit Note created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'customer_invoice_id' => ['nullable', 'uuid'],
            'sales_return_id' => ['nullable', 'uuid'],
            'credit_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'tax_mode' => ['nullable', 'string', 'in:none,manual_rate,manual_amount'],
            'tax_rate_bps' => ['nullable', 'integer', 'min:0'],
            'tax_minor_override' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.customer_invoice_line_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity_e6' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.tax_rate_bps' => ['nullable', 'integer', 'min:0'],
            'lines.*.tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
        ]);

        $this->customerCreditNoteService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Credit Note updated successfully.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->customerCreditNoteService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Credit Note submitted successfully.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->customerCreditNoteService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Credit Note approved successfully.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $actorId = $request->user()?->id;

        $note = $this->customerCreditNoteService->post($id, $actorId);

        if ($note->customer_invoice_id) {
            $this->customerInvoiceRevisionService->generate(
                $note->customer_invoice_id,
                $note->id,
                $note->sales_return_id,
                (int) $actorId,
            );
        }

        $message = $note->customer_invoice_id
            ? __('Customer Credit Note posted to AR/GL successfully. Invoice revision generated.')
            : __('Customer Credit Note posted to AR/GL successfully.');

        return redirect()->back()->with('success', $message);
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->customerCreditNoteService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Customer Credit Note cancelled successfully.'));
    }
}
