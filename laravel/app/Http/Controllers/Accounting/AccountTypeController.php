<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\AccountTypePageData;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountTypeController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly AccountTypePageData $pageData) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.account_types');

        return Inertia::render('Accounting/AccountTypes', $this->pageData->indexData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_types');

        $validated = $this->validateAccountType($request, uniqueRule: 'unique:account_type,code');
        $accountCategory = AccountCategory::findOrFail($validated['account_category_id']);

        AccountType::create($this->payload($validated, $accountCategory, ['id' => (string) Str::uuid(), 'is_system' => false]));

        return redirect()->back()->with('success', __('Account Type created successfully.'));
    }

    public function update(Request $request, AccountType $accountType): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_types');

        $validated = $this->validateAccountType($request, uniqueRule: Rule::unique('account_type', 'code')->ignore($accountType->id));
        $accountCategory = AccountCategory::findOrFail($validated['account_category_id']);

        $accountType->update($this->payload($validated, $accountCategory));

        return redirect()->back()->with('success', __('Account Type updated successfully.'));
    }

    public function destroy(Request $request, AccountType $accountType): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.delete');

        if ($accountType->is_system) {
            return redirect()->back()->withErrors(['account_type' => __('System account types cannot be deleted.')]);
        }

        if ($accountType->groups()->exists() || $accountType->accounts()->exists()) {
            return redirect()->back()->withErrors(['account_type' => __('Cannot delete account type in use by account groups or accounts.')]);
        }

        $accountType->delete();

        return redirect()->back()->with('success', __('Account Type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccountType(Request $request, mixed $uniqueRule): array
    {
        return $request->validate([
            'account_category_id' => ['required', 'uuid', 'exists:account_category,id'],
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'normal_balance' => ['nullable', 'string', Rule::in(['debit', 'credit'])],
            'statement_type' => ['nullable', 'string', Rule::in(['balance_sheet', 'income_statement'])],
            'is_contra' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $validated, AccountCategory $accountCategory, array $overrides = []): array
    {
        return [
            ...$overrides,
            'account_category_id' => $accountCategory->id,
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'normal_balance' => $validated['normal_balance'] ?? $accountCategory->normal_balance,
            'statement_type' => $validated['statement_type'] ?? $accountCategory->statement_type,
            'category' => strtolower($accountCategory->code),
            'is_contra' => $validated['is_contra'] ?? $accountCategory->is_contra,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }
}
