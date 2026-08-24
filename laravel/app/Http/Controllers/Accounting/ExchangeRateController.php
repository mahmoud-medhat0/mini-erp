<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\ExchangeRateService;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/ExchangeRates', [
            'rates' => ExchangeRate::query()->with('currencyRef')->orderBy('date', 'desc')->paginate(30),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->exchangeRateService->setRate($validated['currency'], $validated['date'], $validated['rate'], $request->user()->id);

        return redirect()->back()->with('success', __('Exchange rate saved successfully.'));
    }
}
