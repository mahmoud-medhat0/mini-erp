<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\PeriodService;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialPeriodController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly PeriodService $periodService) {}

    public function index(Request $request): Response
    {
        if (! $request->user()?->hasAnyPermission(['accounting.periods', 'accounting.view', 'settings.configure'])) {
            abort(403);
        }

        return Inertia::render('Accounting/Periods', [
            'fiscalYears' => FiscalYear::query()->with('periods')->orderBy('year', 'desc')->get(),
        ]);
    }

    public function closeReadiness(Request $request, FinancialPeriod $period): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['close_period', 'accounting.periods', 'accounting.view'])) {
            abort(403);
        }

        return response()->json($this->periodService->checkCloseReadiness($period));
    }

    public function storeFiscalYear(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.configure');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:fiscal_year,year'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ], [
            'year.unique' => __('Fiscal year :year already exists.', ['year' => $request->input('year')]),
            'year.required' => __('Fiscal year is required.'),
            'start_date.required' => __('Start date is required.'),
            'end_date.required' => __('End date is required.'),
            'end_date.after' => __('End date must be after start date.'),
        ]);

        try {
            $this->periodService->createFiscalYear($validated['year'], $validated['start_date'], $validated['end_date']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['year' => __('Fiscal year :year already exists.', ['year' => $validated['year']])]);
        }

        return redirect()->back()->with('success', __('Fiscal Year created with 12 monthly periods.'));
    }

    public function close(Request $request, FinancialPeriod $period): RedirectResponse
    {
        $this->authorizePermission($request, 'close_period');

        $validated = $request->validate([
            'close_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->periodService->closePeriod($period, (int) $request->user()->id, $validated['close_note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            $readiness = $this->periodService->checkCloseReadiness($period);

            return redirect()->back()->withErrors([
                'period' => $e->getMessage(),
                'blockers' => $readiness['blockers'],
            ]);
        }

        return redirect()->back()->with('success', __('Financial period closed successfully.'));
    }

    public function reopen(Request $request, FinancialPeriod $period): RedirectResponse
    {
        $this->authorizePermission($request, 'reopen_period');

        $validated = $request->validate([
            'close_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->periodService->reopenPeriod($period, (int) $request->user()->id, $validated['close_note'] ?? null);

        return redirect()->back()->with('success', __('Financial period reopened successfully.'));
    }
}
