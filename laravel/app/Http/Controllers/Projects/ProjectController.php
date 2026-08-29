<?php

namespace App\Http\Controllers\Projects;

use App\Application\Projects\ProjectPageData;
use App\Application\Projects\ProjectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Projects/Index', $this->pageData->indexData(
            $request->only(['search', 'status', 'is_billable'])
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $this->projectService->create($data, $request->user()?->id);

        return back()->with('success', __('Project created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $this->validatePayload($request, true);
        $this->projectService->update($id, $data, $request->user()?->id);

        return back()->with('success', __('Project updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->projectService->delete($id, $request->user()?->id);

        return back()->with('success', __('Project deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'name.en' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => [$isUpdate ? 'sometimes' : 'required', 'string', 'in:active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
