<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\ExchangeRatePageData;
use App\Application\Accounting\ExchangeRateService;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly ExchangeRatePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('Accounting/ExchangeRates', $this->pageData->indexData($validated['search'] ?? null));
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
