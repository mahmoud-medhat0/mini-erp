<?php

namespace App\Http\Controllers;

use App\Application\Accounting\OutgoingChequeService;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\OutgoingCheque;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutgoingChequeController extends Controller
{
    public function __construct(
        private readonly OutgoingChequeService $service,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');

        $query = OutgoingCheque::query()
            ->with(['supplier', 'bankAccount', 'fiscalYear', 'financialPeriod']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $cheques = $query->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::query()->where('status', 'active')->orderBy('code')->get();
        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('code')->get();
        $fiscalYears = FiscalYear::query()->where('is_closed', false)->orderBy('year', 'desc')->get();
        $periods = FinancialPeriod::query()->where('is_closed', false)->orderBy('start_date', 'asc')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('OutgoingCheques/Index', [
            'cheques' => $cheques,
            'suppliers' => $suppliers,
            'bankAccounts' => $bankAccounts,
            'fiscalYears' => $fiscalYears,
            'periods' => $periods,
            'currencies' => $currencies,
            'filters' => [
                'status' => $status,
                'supplier_id' => $supplierId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'string', 'uuid', 'exists:supplier,id'],
            'bank_account_id' => ['required', 'string', 'uuid', 'exists:bank_account,id'],
            'cheque_number' => ['required', 'string', 'max:50'],
            'due_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->createDraft($validated, (int) $request->user()->id);

        return back()->with('success', 'Outgoing cheque created as draft.');
    }

    public function issue(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'issued_date' => ['required', 'date'],
        ]);

        $this->service->issue($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Outgoing cheque issued successfully.');
    }

    public function clear(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'cleared_date' => ['required', 'date'],
        ]);

        $this->service->clear($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Outgoing cheque cleared successfully.');
    }

    public function return(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'returned_date' => ['required', 'date'],
            'return_reason' => ['nullable', 'string'],
        ]);

        $this->service->return($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Outgoing cheque returned successfully.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'cancelled_date' => ['required', 'date'],
            'cancel_reason' => ['nullable', 'string'],
        ]);

        $this->service->cancel($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Outgoing cheque cancelled successfully.');
    }
}
