<?php

namespace App\Http\Controllers;

use App\Application\Payroll\PayrollRunPageData;
use App\Application\Payroll\PayrollRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollRunService $runService,
        private readonly PayrollRunPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Payroll/Runs', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->runService->createRun($this->validatedRun($request), $request->user()?->id);

        return back()->with('success', __('Payroll run generated.'));
    }

    public function regenerate(Request $request, string $id): RedirectResponse
    {
        $this->runService->regenerate($id, $request->user()?->id);

        return back()->with('success', __('Payroll run regenerated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->runService->submit($id, $request->user()?->id);

        return back()->with('success', __('Payroll run submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->runService->approve($id, $request->user()?->id);

        return back()->with('success', __('Payroll run approved.'));
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $this->runService->post($id, $request->user()?->id);

        return back()->with('success', __('Payroll run posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->runService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Payroll run cancelled.'));
    }

    private function validatedRun(Request $request): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'payment_date' => ['required', 'date'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'run_type' => ['required', Rule::in(PayrollRunService::RUN_TYPES)],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
