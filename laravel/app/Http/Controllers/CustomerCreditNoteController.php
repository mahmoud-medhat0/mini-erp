<?php

namespace App\Http\Controllers;

use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceRevisionService;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerInvoice;
use App\Models\SalesReturn;
use App\Models\TaxCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerCreditNoteController extends Controller
{
    public function __construct(
        private readonly CustomerCreditNoteService $customerCreditNoteService,
        private readonly CustomerInvoiceRevisionService $customerInvoiceRevisionService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');

        $query = CustomerCreditNote::query()->with([
            'customer',
            'customerInvoice',
            'salesReturn',
            'lines',
            'journalEntry',
            'receivableEntry',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, CustomerCreditNoteService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $customerCreditNotes = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $activeCustomers = Customer::query()->where('status', 'active')->orderBy('name', 'asc')->get();

        $postedCustomerInvoices = CustomerInvoice::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();

        $postedSalesReturns = SalesReturn::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();

        $taxCodes = TaxCode::query()
            ->with(['rates' => fn ($q) => $q->where('is_active', true)->orderBy('effective_from', 'desc')])
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        return Inertia::render('Sales/CustomerCreditNotes', [
            'customerCreditNotes' => $customerCreditNotes,
            'activeCustomers' => $activeCustomers,
            'postedCustomerInvoices' => $postedCustomerInvoices,
            'postedSalesReturns' => $postedSalesReturns,
            'taxCodes' => $taxCodes,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'customer_id' => $customerId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'customer_invoice_id' => ['nullable', 'uuid'],
            'sales_return_id' => ['nullable', 'uuid'],
            'credit_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3'],
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

        return redirect()->back()->with('success', 'Customer Credit Note created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'customer_invoice_id' => ['nullable', 'uuid'],
            'sales_return_id' => ['nullable', 'uuid'],
            'credit_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
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

        return redirect()->back()->with('success', 'Customer Credit Note updated successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->customerCreditNoteService->submit($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Credit Note submitted successfully.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->customerCreditNoteService->approve($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Credit Note approved successfully.');
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
            ? 'Customer Credit Note posted to AR/GL successfully. Invoice revision generated.'
            : 'Customer Credit Note posted to AR/GL successfully.';

        return redirect()->back()->with('success', $message);
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->customerCreditNoteService->cancel($id, $request->user()?->id);

        return redirect()->back()->with('success', 'Customer Credit Note cancelled successfully.');
    }
}
