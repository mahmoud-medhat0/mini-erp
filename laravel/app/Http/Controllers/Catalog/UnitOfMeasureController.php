<?php

namespace App\Http\Controllers\Catalog;

use App\Application\Catalog\UnitOfMeasurePageData;
use App\Application\Catalog\UnitOfMeasureService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitOfMeasureController extends Controller
{
    public function __construct(
        private readonly UnitOfMeasureService $uomService,
        private readonly UnitOfMeasurePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Catalog/UnitsOfMeasure', $this->pageData->indexData($request->only(['search'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
        ]);

        $this->uomService->create($validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Unit of Measure created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $this->uomService->update($id, $validated, $request->user()?->id);

        return redirect()->back()->with('success', __('Unit of Measure updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->uomService->delete($id, $request->user()?->id);

        return redirect()->back()->with('success', __('Unit of Measure deleted successfully.'));
    }
}
