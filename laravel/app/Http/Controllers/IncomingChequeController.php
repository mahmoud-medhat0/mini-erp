<?php

namespace App\Http\Controllers;

use App\Application\Accounting\IncomingChequeService;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\IncomingCheque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomingChequeController extends Controller
{
    public function __construct(
        private readonly IncomingChequeService $service,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $customerId = $request->query('customer_id');

        $query = IncomingCheque::query()
            ->with(['customer', 'bankAccount', 'fiscalYear', 'financialPeriod']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $cheques = $query->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::query()->where('status', 'active')->orderBy('code')->get();
        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('code')->get();
        $fiscalYears = FiscalYear::query()->where('is_closed', false)->orderBy('year', 'desc')->get();
        $periods = FinancialPeriod::query()->where('is_closed', false)->orderBy('start_date', 'asc')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('IncomingCheques/Index', [
            'cheques' => $cheques,
            'customers' => $customers,
            'bankAccounts' => $bankAccounts,
            'fiscalYears' => $fiscalYears,
            'periods' => $periods,
            'currencies' => $currencies,
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'uuid', 'exists:customer,id'],
            'cheque_number' => ['required', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->createDraft($validated, (int) $request->user()->id);

        return back()->with('success', 'Incoming cheque created as draft.');
    }

    public function receive(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'received_date' => ['required', 'date'],
        ]);

        $this->service->receive($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Incoming cheque received successfully.');
    }

    public function deposit(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'string', 'uuid', 'exists:bank_account,id'],
            'deposited_date' => ['required', 'date'],
        ]);

        $this->service->deposit($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Incoming cheque deposited successfully.');
    }

    public function clear(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'cleared_date' => ['required', 'date'],
        ]);

        $this->service->clear($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Incoming cheque cleared successfully.');
    }

    public function bounce(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'bounced_date' => ['required', 'date'],
            'bounce_reason' => ['nullable', 'string'],
        ]);

        $this->service->bounce($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Incoming cheque bounced successfully.');
    }

    public function return(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'returned_date' => ['required', 'date'],
            'return_reason' => ['nullable', 'string'],
        ]);

        $this->service->return($id, $validated, (int) $request->user()->id);

        return back()->with('success', 'Incoming cheque returned successfully.');
    }
}
