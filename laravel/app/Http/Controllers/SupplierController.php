<?php

namespace App\Http\Controllers;

use App\Application\MasterData\SupplierPageData;
use App\Application\MasterData\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly SupplierPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Suppliers/Index', $this->pageData->indexData($request->only(['search', 'status'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('suppliers.view');

        return $this->pageData->datatable($request->only(['status']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:50'],
        ]);

        if (is_string($validated['name']) && str_starts_with(trim($validated['name']), '{')) {
            $decoded = json_decode($validated['name'], true);
            if (is_array($decoded)) {
                $validated['name'] = $decoded;
            }
        }

        $this->supplierService->create($validated, $request->user()?->id);

        return back()->with('success', __('Supplier created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        if (isset($validated['name']) && is_string($validated['name']) && str_starts_with(trim($validated['name']), '{')) {
            $decoded = json_decode($validated['name'], true);
            if (is_array($decoded)) {
                $validated['name'] = $decoded;
            }
        }

        $expectedVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $this->supplierService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', __('Supplier updated successfully.'));
    }
}
