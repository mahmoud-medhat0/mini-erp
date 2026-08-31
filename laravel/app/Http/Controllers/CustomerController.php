<?php

namespace App\Http\Controllers;

use App\Application\MasterData\CustomerPageData;
use App\Application\MasterData\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Customers/Index', $this->pageData->indexData($request->only(['search', 'status'])));
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

        $this->customerService->create($validated, $request->user()?->id);

        return back()->with('success', __('Customer created successfully.'));
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

        $this->customerService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', __('Customer updated successfully.'));
    }
}
