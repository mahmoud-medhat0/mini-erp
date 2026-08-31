<?php

namespace App\Http\Controllers;

use App\Application\Payroll\EmployeePayrollComponentService;
use App\Application\Payroll\EmployeeService;
use App\Application\Payroll\PayrollEmployeePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollEmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeePayrollComponentService $assignmentService,
        private readonly PayrollEmployeePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Payroll/Employees', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->employeeService->create($this->validatedEmployee($request), $request->user()?->id);

        return back()->with('success', __('Employee saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->employeeService->update($id, $this->validatedEmployee($request, true), $request->user()?->id);

        return back()->with('success', __('Employee updated.'));
    }

    public function storeComponent(Request $request, string $id): RedirectResponse
    {
        $this->assignmentService->create($id, $request->validate([
            'payroll_component_id' => ['required', 'uuid', 'exists:payroll_component,id'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'rate_bps' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]), $request->user()?->id);

        return back()->with('success', __('Employee payroll component saved.'));
    }

    public function destroyComponent(Request $request, string $id, string $assignmentId): RedirectResponse
    {
        $this->assignmentService->delete($id, $assignmentId, $request->user()?->id);

        return back()->with('success', __('Employee payroll component removed.'));
    }

    private function validatedEmployee(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name.en' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'status' => [$isUpdate ? 'sometimes' : 'required', Rule::in(EmployeeService::STATUSES)],
            'hire_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'termination_date' => ['nullable', 'date'],
            'currency' => [$isUpdate ? 'sometimes' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'base_salary_minor' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:0'],
            'payment_method' => [$isUpdate ? 'sometimes' : 'required', Rule::in(EmployeeService::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
