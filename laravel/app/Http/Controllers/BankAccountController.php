<?php

namespace App\Http\Controllers;

use App\Application\MasterData\BankAccountPageData;
use App\Application\MasterData\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $bankAccountService,
        private readonly BankAccountPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('BankAccounts/Index', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'gl_account_id' => ['required', 'string', 'uuid', 'exists:account,id'],
            'iban' => ['nullable', 'string', 'max:100'],
            'swift' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->bankAccountService->create($validated, $request->user()?->id);

        return back()->with('success', __('Bank account created successfully.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number' => ['sometimes', 'required', 'string', 'max:100'],
            'bank_name' => ['sometimes', 'required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'exists:currency,code'],
            'gl_account_id' => ['sometimes', 'required', 'string', 'uuid', 'exists:account,id'],
            'iban' => ['nullable', 'string', 'max:100'],
            'swift' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $this->bankAccountService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', __('Bank account updated successfully.'));
    }
}
