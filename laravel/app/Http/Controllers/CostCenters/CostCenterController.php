<?php

namespace App\Http\Controllers\CostCenters;

use App\Application\CostCenters\CostCenterPageData;
use App\Application\CostCenters\CostCenterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostCenterController extends Controller
{
    public function __construct(
        private readonly CostCenterService $costCenterService,
        private readonly CostCenterPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('CostCenters/Index', $this->pageData->indexData(
            $request->only(['search', 'category', 'status'])
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $this->costCenterService->create($data, $request->user()?->id);

        return back()->with('success', __('Cost center created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $this->validatePayload($request, true);
        $this->costCenterService->update($id, $data, $request->user()?->id);

        return back()->with('success', __('Cost center updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->costCenterService->delete($id, $request->user()?->id);

        return back()->with('success', __('Cost center deleted successfully.'));
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
            'category' => ['nullable', 'string', 'in:administrative,sales,operations,finance,other'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
