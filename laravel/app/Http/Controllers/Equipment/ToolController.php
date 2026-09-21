<?php

namespace App\Http\Controllers\Equipment;

use App\Application\Equipment\ToolPageData;
use App\Application\Equipment\ToolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ToolController extends Controller
{
    public function __construct(
        private readonly ToolService $toolService,
        private readonly ToolPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('equipment.view');

        return Inertia::render('Equipment/Tools', $this->pageData->indexData(
            $request->only(['search', 'status', 'tool_category_id', 'branch_id'])
        ));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('equipment.view');

        return $this->pageData->datatable($request->only(['status', 'tool_category_id', 'branch_id']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('equipment.create');

        $this->toolService->create($this->validatedTool($request), $request->user()?->id);

        return back()->with('success', __('Tool saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.edit');

        $this->toolService->update($id, $this->validatedTool($request, true), $request->user()?->id);

        return back()->with('success', __('Tool updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.delete');

        $this->toolService->delete($id, $request->user()?->id);

        return back()->with('success', __('Tool deleted.'));
    }

    public function issue(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.edit');

        $validated = $request->validate([
            'custodian_employee_id' => ['required', 'uuid', 'exists:employee,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'reason' => ['nullable', 'string'],
        ]);

        $this->toolService->issue(
            $id,
            $validated['custodian_employee_id'],
            $validated['branch_id'] ?? null,
            $validated['reason'] ?? null,
            $request->user()?->id,
        );

        return back()->with('success', __('Tool issued to custodian.'));
    }

    public function returnToStock(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.edit');

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $this->toolService->returnToStock($id, $validated['reason'] ?? null, $request->user()?->id);

        return back()->with('success', __('Tool returned to stock.'));
    }

    public function transfer(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.edit');

        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'custodian_employee_id' => ['nullable', 'uuid', 'exists:employee,id'],
            'reason' => ['nullable', 'string'],
        ]);

        $this->toolService->transfer(
            $id,
            $validated['branch_id'] ?? null,
            $validated['custodian_employee_id'] ?? null,
            $validated['reason'] ?? null,
            $request->user()?->id,
        );

        return back()->with('success', __('Tool transferred.'));
    }

    public function markStatus(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.edit');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['available', 'damaged', 'lost', 'maintenance', 'retired'])],
            'reason' => ['nullable', 'string'],
        ]);

        $this->toolService->markStatus($id, $validated['status'], $validated['reason'] ?? null, $request->user()?->id);

        return back()->with('success', __('Tool status updated.'));
    }

    private function validatedTool(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name.en' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],
            'tool_category_id' => [$isUpdate ? 'sometimes' : 'required', 'uuid', 'exists:tool_category,id'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'quantity' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'location_note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
