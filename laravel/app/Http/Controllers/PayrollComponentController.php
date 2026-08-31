<?php

namespace App\Http\Controllers;

use App\Application\Payroll\PayrollComponentPageData;
use App\Application\Payroll\PayrollComponentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollComponentController extends Controller
{
    public function __construct(
        private readonly PayrollComponentService $componentService,
        private readonly PayrollComponentPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Payroll/Components', $this->pageData->indexData($request->only(['search', 'type'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->componentService->create($this->validatedComponent($request), $request->user()?->id);

        return back()->with('success', __('Payroll component saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->componentService->update($id, $this->validatedComponent($request, true), $request->user()?->id);

        return back()->with('success', __('Payroll component updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->componentService->delete($id, $request->user()?->id);

        return back()->with('success', __('Payroll component deleted.'));
    }

    private function validatedComponent(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name.en' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(PayrollComponentService::TYPES)],
            'calculation_type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(PayrollComponentService::CALCULATION_TYPES)],
            'default_amount_minor' => ['nullable', 'integer', 'min:0'],
            'rate_bps' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'expense_account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'liability_account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
