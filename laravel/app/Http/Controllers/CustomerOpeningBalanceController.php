<?php

namespace App\Http\Controllers;

use App\Application\Accounting\CustomerOpeningBalanceService;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOpeningBalanceController extends Controller
{
    public function __construct(
        private readonly CustomerOpeningBalanceService $service,
    ) {}

    public function index(Request $request): Response
    {
        $balances = CustomerOpeningBalance::query()
            ->with(['customer', 'fiscalYear', 'financialPeriod'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $customers = Customer::query()->where('status', 'active')->orderBy('code')->get();
        $fiscalYears = FiscalYear::query()->where('is_closed', false)->orderBy('year', 'desc')->get();
        $periods = FinancialPeriod::query()->where('is_closed', false)->orderBy('start_date', 'asc')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('CustomerOpeningBalances/Index', [
            'balances' => $balances,
            'customers' => $customers,
            'fiscalYears' => $fiscalYears,
            'periods' => $periods,
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'uuid', 'exists:customer,id'],
            'fiscal_year_id' => ['required', 'string', 'uuid', 'exists:fiscal_year,id'],
            'financial_period_id' => ['required', 'string', 'uuid', 'exists:financial_period,id'],
            'entry_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->service->create($validated, $request->user()?->id);

        return back()->with('success', 'Customer opening balance created as draft.');
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->service->post($id, $request->user()?->id);

        return back()->with('success', 'Customer opening balance posted successfully.');
    }
}
