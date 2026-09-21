<?php

namespace App\Http\Controllers\Equipment;

use App\Application\Equipment\ToolCategoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ToolCategoryController extends Controller
{
    public function __construct(
        private readonly ToolCategoryService $toolCategoryService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('equipment.view');

        return Inertia::render('Equipment/Categories', [
            'categories' => [],
            'can' => [
                'create' => $request->user()?->can('equipment.create') ?? false,
                'edit' => $request->user()?->can('equipment.edit') ?? false,
                'delete' => $request->user()?->can('equipment.delete') ?? false,
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('equipment.view');

        return $this->toolCategoryService->datatable($request->only(['status']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('equipment.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->toolCategoryService->create($validated, $request->user()?->id);

        return back()->with('success', __('Tool category saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.edit');

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50'],
            'name.en' => ['sometimes', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $this->toolCategoryService->update($id, $validated, $request->user()?->id);

        return back()->with('success', __('Tool category updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('equipment.delete');

        $this->toolCategoryService->delete($id, $request->user()?->id);

        return back()->with('success', __('Tool category deleted.'));
    }
}
