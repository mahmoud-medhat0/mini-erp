<?php

namespace App\Http\Controllers\Taxes;

use App\Application\Taxes\TaxMasterDataService;
use App\Application\Taxes\TaxRatePageData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxRateController extends Controller
{
    public function __construct(
        private readonly TaxMasterDataService $service,
        private readonly TaxRatePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'taxes.view');

        return Inertia::render('Taxes/Rates/Index', $this->pageData->indexData($request->only(['tax_code_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'taxes.edit');

        $validated = $request->validate([
            'tax_code_id' => ['required', 'uuid', 'exists:tax_codes,id'],
            'rate_bps' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        $this->service->createTaxRate($validated, $request->user()?->id);

        return redirect()->back()
            ->with('success', __('Tax rate created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'taxes.edit');

        $validated = $request->validate([
            'rate_bps' => ['sometimes', 'required', 'integer', 'min:0'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $this->service->updateTaxRate($id, $validated, $request->user()?->id);

        return redirect()->back()
            ->with('success', __('Tax rate updated successfully.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'taxes.edit');

        $this->service->deleteTaxRate($id, $request->user()?->id);

        return redirect()->back()
            ->with('success', __('Tax rate deleted successfully.'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if (! $request->user()?->can($permission)) {
            abort(403);
        }
    }
}
