<?php

namespace App\Http\Controllers;

use App\Application\MasterData\SupplierService;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Supplier::query();

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $suppliers = $query->orderBy('code', 'asc')
            ->paginate(15)
            ->withQueryString();

        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:50'],
        ]);

        $this->supplierService->create($validated, $request->user()?->id);

        return back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $this->supplierService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', 'Supplier updated successfully.');
    }
}
