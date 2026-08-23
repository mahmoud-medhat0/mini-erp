<?php

namespace App\Http\Controllers\Taxes;

use App\Application\Taxes\TaxMasterDataService;
use App\Http\Controllers\Controller;
use App\Models\TaxCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxCodeController extends Controller
{
    public function __construct(
        private readonly TaxMasterDataService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'taxes.view');

        $query = TaxCode::query()->withCount('rates');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $taxCodes = $query->orderBy('code')->paginate(20)->withQueryString();

        return Inertia::render('Taxes/Codes/Index', [
            'taxCodes' => $taxCodes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'taxes.edit');

        return Inertia::render('Taxes/Codes/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'taxes.edit');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'tax_type' => ['nullable', 'string', 'in:vat'],
            'calculation_mode' => ['required', 'string', 'in:exclusive,inclusive,exempt'],
            'recoverability_mode' => ['required', 'string', 'in:full,none'],
            'is_active' => ['boolean'],
        ]);

        $this->service->createTaxCode($validated, $request->user()?->id);

        return redirect()->route('taxes.codes.index')
            ->with('success', 'Tax code created successfully.');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'taxes.edit');

        /** @var TaxCode $taxCode */
        $taxCode = TaxCode::query()->with('rates')->findOrFail($id);

        return Inertia::render('Taxes/Codes/Edit', [
            'taxCode' => $taxCode,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'taxes.edit');

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'array'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'calculation_mode' => ['sometimes', 'required', 'string', 'in:exclusive,inclusive,exempt'],
            'recoverability_mode' => ['sometimes', 'required', 'string', 'in:full,none'],
            'is_active' => ['boolean'],
        ]);

        $this->service->updateTaxCode($id, $validated, $request->user()?->id);

        return redirect()->route('taxes.codes.index')
            ->with('success', 'Tax code updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'taxes.edit');

        $this->service->deleteTaxCode($id, $request->user()?->id);

        return redirect()->route('taxes.codes.index')
            ->with('success', 'Tax code deleted successfully.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if (! $request->user()?->can($permission)) {
            abort(403);
        }
    }
}
