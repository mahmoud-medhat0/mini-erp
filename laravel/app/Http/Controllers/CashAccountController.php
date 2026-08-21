<?php

namespace App\Http\Controllers;

use App\Application\MasterData\CashAccountService;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashAccountController extends Controller
{
    public function __construct(
        private readonly CashAccountService $cashAccountService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = CashAccount::query()->with('glAccount');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        $cashAccounts = $query->orderBy('code', 'asc')
            ->paginate(15)
            ->withQueryString();

        $glAccounts = Account::query()->where('is_active', true)->where('type', 'asset')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('CashAccounts/Index', [
            'cashAccounts' => $cashAccounts,
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
            'currency' => ['required', 'string', 'size:3'],
            'gl_account_id' => ['required', 'string', 'uuid', 'exists:account,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->cashAccountService->create($validated, $request->user()?->id);

        return back()->with('success', 'Cash account created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'gl_account_id' => ['sometimes', 'required', 'string', 'uuid', 'exists:account,id'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $expectedVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $this->cashAccountService->update($id, $validated, $expectedVersion, $request->user()?->id);

        return back()->with('success', 'Cash account updated successfully.');
    }
}
