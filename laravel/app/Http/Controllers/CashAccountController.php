<?php

namespace App\Http\Controllers;

use App\Application\MasterData\CashAccountPageData;
use App\Application\MasterData\CashAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashAccountController extends Controller
{
    public function __construct(
        private readonly CashAccountService $cashAccountService,
        private readonly CashAccountPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('CashAccounts/Index', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'gl_account_id' => ['required', 'string', 'uuid', 'exists:account,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->cashAccountService->create($validated, $request->user()?->id);

        return back()->with('success', __('Cash account created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'exists:currency,code'],
            'gl_account_id' => ['sometimes', 'required', 'string', 'uuid', 'exists:account,id'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $this->cashAccountService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', __('Cash account updated successfully.'));
    }
}
