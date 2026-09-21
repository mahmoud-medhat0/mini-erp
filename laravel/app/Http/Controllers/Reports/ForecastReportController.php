<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ForecastService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForecastReportController extends Controller
{
    public function __construct(
        private readonly ForecastService $forecastService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $lookbackMonths = max(1, min((int) $request->query('lookback_months', 3), 24));
        $monthsAhead = max(1, min((int) $request->query('months_ahead', 3), 12));
        $salesGrowthPct = (float) $request->query('sales_growth_pct', 0);
        $expenseGrowthPct = (float) $request->query('expense_growth_pct', 0);

        $forecast = $this->forecastService->forecast($lookbackMonths, $monthsAhead, $salesGrowthPct, $expenseGrowthPct);

        return Inertia::render('Reports/Forecast', [
            'forecast' => $forecast,
            'filters' => [
                'lookback_months' => $lookbackMonths,
                'months_ahead' => $monthsAhead,
                'sales_growth_pct' => $salesGrowthPct,
                'expense_growth_pct' => $expenseGrowthPct,
            ],
        ]);
    }
}
