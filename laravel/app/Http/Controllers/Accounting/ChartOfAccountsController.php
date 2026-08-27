<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\ChartOfAccountsPageData;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountsController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly ChartOfAccountsPageData $pageData) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/ChartOfAccounts', $this->pageData->indexData());
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:account_group,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'uuid', 'exists:account_type,id'],
            'statement_section' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'uuid', 'exists:account_group,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $accountType = AccountType::findOrFail($validated['account_type_id']);

        if (! empty($validated['parent_id'])) {
            $parentGroup = AccountGroup::findOrFail($validated['parent_id']);
            if ($parentGroup->account_type_id && $parentGroup->account_type_id !== $accountType->id) {
                return redirect()->back()->withErrors(['account_type_id' => __('Parent group must share the same account type.')]);
            }
        }

        AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => $validated['code'],
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'account_type_id' => $accountType->id,
            'type' => $accountType->category,
            'statement_section' => $validated['statement_section'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back()->with('success', __('Account Group created successfully.'));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:account,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'uuid', 'exists:account_type,id'],
            'nature' => ['nullable', 'string', 'in:debit,credit'],
            'account_group_id' => ['nullable', 'uuid', 'exists:account_group,id'],
            'parent_id' => ['nullable', 'uuid', 'exists:account,id'],
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'is_control' => ['nullable', 'boolean'],
            'allow_manual_posting' => ['nullable', 'boolean'],
        ]);

        $accountType = AccountType::findOrFail($validated['account_type_id']);

        if (! empty($validated['account_group_id'])) {
            $group = AccountGroup::findOrFail($validated['account_group_id']);
            if ($group->account_type_id && $group->account_type_id !== $accountType->id) {
                return redirect()->back()->withErrors(['account_group_id' => __('Selected account group does not match the account type.')]);
            }
        }

        Account::create([
            'id' => (string) Str::uuid(),
            'code' => $validated['code'],
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'account_type_id' => $accountType->id,
            'type' => $accountType->category,
            'nature' => $validated['nature'] ?? $accountType->normal_balance,
            'account_group_id' => $validated['account_group_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'currency' => $validated['currency'],
            'is_control' => $validated['is_control'] ?? false,
            'allow_manual_posting' => $validated['allow_manual_posting'] ?? true,
        ]);

        return redirect()->back()->with('success', __('Account created successfully.'));
    }
}
