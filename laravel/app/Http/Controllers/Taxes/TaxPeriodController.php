<?php

namespace App\Http\Controllers\Taxes;

use App\Application\Taxes\TaxPeriodPageData;
use App\Application\Taxes\TaxPeriodService;
use App\Application\Taxes\TaxReturnService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaxPeriodController extends Controller
{
    public function __construct(
        private readonly TaxPeriodService $periodService,
        private readonly TaxReturnService $returnService,
        private readonly TaxPeriodPageData $pageData,
    ) {}

    public function index(): Response
    {
        Gate::authorize('taxes.view');

        return Inertia::render('Taxes/Periods/Index', $this->pageData->indexData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('taxes.edit');

        $validated = $request->validate([
            'period_label' => ['required', 'string', 'max:64'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = $this->periodService->createPeriod($validated);

        return redirect()->route('taxes.periods.show', $period->id)
            ->with('success', __('Tax period created successfully.'));
    }

    public function show(string $id): Response
    {
        Gate::authorize('taxes.view');

        return Inertia::render('Taxes/Periods/Show', $this->pageData->showData($id));
    }

    public function generateDraft(string $id, Request $request): RedirectResponse
    {
        Gate::authorize('taxes.edit');

        $user = $request->user();
        $taxReturn = $this->returnService->generateDraftReturn($id, $user?->id);

        return redirect()->route('taxes.periods.show', $id)
            ->with('success', __('Draft tax return :number generated successfully.', ['number' => $taxReturn->number]));
    }

    public function fileReturn(string $returnId, Request $request): RedirectResponse
    {
        Gate::authorize('taxes.file');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $filedReturn = $this->returnService->fileReturn($returnId, $user?->id, $validated['notes'] ?? null);

        return redirect()->route('taxes.periods.show', $filedReturn->tax_period_id)
            ->with('success', __('Tax return :number filed successfully and period locked.', ['number' => $filedReturn->number]));
    }
}
