<?php

namespace App\Http\Controllers\Budgeting;

use App\Application\Budgeting\BudgetPageData;
use App\Application\Budgeting\BudgetService;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgetService,
        private readonly BudgetPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Budgeting/Budgets', $this->pageData->indexData(
            $request->only(['search', 'fiscal_year_id', 'status'])
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $this->budgetService->create($data, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget created successfully.'));
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $data = $this->validatePayload($request, true);
        $this->budgetService->update((string) $budget->id, $data, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget updated successfully.'));
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->budgetService->delete((string) $budget->id, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget deleted successfully.'));
    }

    public function submit(Request $request, Budget $budget): RedirectResponse
    {
        $lockVersion = $request->has('lock_version') ? (int) $request->input('lock_version') : null;
        $this->budgetService->submit((string) $budget->id, $lockVersion, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget submitted successfully.'));
    }

    public function approve(Request $request, Budget $budget): RedirectResponse
    {
        $lockVersion = $request->has('lock_version') ? (int) $request->input('lock_version') : null;
        $this->budgetService->approve((string) $budget->id, $lockVersion, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget approved successfully.'));
    }

    public function activate(Request $request, Budget $budget): RedirectResponse
    {
        $lockVersion = $request->has('lock_version') ? (int) $request->input('lock_version') : null;
        $this->budgetService->activate((string) $budget->id, $lockVersion, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget activated successfully.'));
    }

    public function archive(Request $request, Budget $budget): RedirectResponse
    {
        $lockVersion = $request->has('lock_version') ? (int) $request->input('lock_version') : null;
        $this->budgetService->archive((string) $budget->id, $lockVersion, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget archived successfully.'));
    }

    public function cancel(Request $request, Budget $budget): RedirectResponse
    {
        $lockVersion = $request->has('lock_version') ? (int) $request->input('lock_version') : null;
        $this->budgetService->cancel((string) $budget->id, $lockVersion, $request->user()?->id);

        return redirect()->route('budgeting.budgets.index')->with('success', __('Budget cancelled successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'fiscal_year_id' => [$isUpdate ? 'sometimes' : 'required', 'uuid', 'exists:fiscal_year,id'],
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'version_code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'name.en' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_currency' => [$isUpdate ? 'sometimes' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
            'lines' => ['nullable', 'array'],
            'lines.*.financial_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:account,id'],
            'lines.*.project_id' => ['nullable', 'uuid', 'exists:project,id'],
            'lines.*.cost_center_id' => ['nullable', 'uuid', 'exists:cost_center,id'],
            'lines.*.currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
