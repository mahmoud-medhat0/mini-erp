<?php

namespace App\Http\Controllers\Recurring;

use App\Application\Recurring\RecurringTemplatePageData;
use App\Application\Recurring\RecurringTemplateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTemplateController extends Controller
{
    public function __construct(
        private readonly RecurringTemplatePageData $pageData,
        private readonly RecurringTemplateService $templateService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('recurring.view');

        return Inertia::render('Recurring/Templates', $this->pageData->indexData($request->only(['status'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('recurring.view');

        return $this->pageData->datatable($request->only(['status']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('recurring.create');

        $validated = $this->validatePayload($request);

        $this->templateService->create($validated, $request->user()?->id);

        return back()->with('success', __('Recurring template created.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('recurring.edit');

        $validated = $this->validatePayload($request, requireAll: false);
        $validated['lock_version'] = $request->integer('lock_version');

        $this->templateService->update($id, $validated, $request->user()?->id);

        return back()->with('success', __('Recurring template updated.'));
    }

    public function pause(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('recurring.edit');

        $this->templateService->pause($id, $request->user()?->id);

        return back()->with('success', __('Recurring template paused.'));
    }

    public function resume(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('recurring.edit');

        $this->templateService->resume($id, $request->user()?->id);

        return back()->with('success', __('Recurring template resumed.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('recurring.edit');

        $this->templateService->cancel($id, $request->user()?->id);

        return back()->with('success', __('Recurring template cancelled.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('recurring.delete');

        $this->templateService->delete($id, $request->user()?->id);

        return back()->with('success', __('Recurring template deleted.'));
    }

    private function validatePayload(Request $request, bool $requireAll = true): array
    {
        $req = $requireAll ? 'required' : 'sometimes';

        $validated = $request->validate([
            'code' => [$req, 'string', 'max:50'],
            'name.en' => [$req, 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'document_type' => ['sometimes', Rule::in(RecurringTemplateService::DOCUMENT_TYPES)],
            'frequency' => [$req, Rule::in(RecurringTemplateService::FREQUENCIES)],
            'interval_count' => ['sometimes', 'integer', 'min:1'],
            'start_date' => [$req, 'date'],
            'end_date' => ['nullable', 'date'],
            'template_payload.branch_id' => ['nullable', 'uuid'],
            'template_payload.settlement_method' => [$req, 'in:payable,cash,bank'],
            'template_payload.supplier_id' => ['nullable', 'uuid'],
            'template_payload.cash_account_id' => ['nullable', 'uuid'],
            'template_payload.bank_account_id' => ['nullable', 'uuid'],
            'template_payload.payee_name' => ['nullable', 'string', 'max:255'],
            'template_payload.currency' => [$req, 'string', 'size:3', 'exists:currency,code'],
            'template_payload.reference' => ['nullable', 'string', 'max:255'],
            'template_payload.description' => ['nullable', 'string'],
            'template_payload.expense_category_id' => [$req, 'uuid'],
            'template_payload.expense_account_id' => ['nullable', 'uuid'],
            'template_payload.unit_amount_minor' => [$req, 'integer', 'min:1'],
            'template_payload.tax_code_id' => ['nullable', 'uuid'],
        ]);

        return $validated;
    }
}
