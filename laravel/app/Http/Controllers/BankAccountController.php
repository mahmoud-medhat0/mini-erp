<?php

namespace App\Http\Controllers;

use App\Application\MasterData\BankAccountService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $bankAccountService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = BankAccount::query()->with('glAccount');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        $bankAccounts = $query->orderBy('code', 'asc')
            ->paginate(15)
            ->withQueryString();

        $glAccounts = Account::query()->where('is_active', true)->where('type', 'asset')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('BankAccounts/Index', [
            'bankAccounts' => $bankAccounts,
            'glAccounts' => $glAccounts,
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
            'account_number' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'gl_account_id' => ['required', 'string', 'uuid', 'exists:account,id'],
            'iban' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->bankAccountService->create($validated, $request->user()?->id);

        return back()->with('success', 'Bank account created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number' => ['sometimes', 'required', 'string', 'max:100'],
            'bank_name' => ['sometimes', 'required', 'string', 'max:255'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'gl_account_id' => ['sometimes', 'required', 'string', 'uuid', 'exists:account,id'],
            'iban' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $this->bankAccountService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', 'Bank account updated successfully.');
    }
}
