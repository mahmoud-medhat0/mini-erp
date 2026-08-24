<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountCategoryController extends Controller
{
    use AuthorizesAccountingRequests;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        return Inertia::render('Accounting/AccountCategories', [
            'accountCategories' => AccountCategory::query()
                ->with(['accountTypes'])
                ->withCount('accountTypes')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        $validated = $this->validateAccountCategory($request, uniqueRule: 'unique:account_category,code');

        AccountCategory::create($this->payload($validated, ['id' => (string) Str::uuid(), 'is_system' => false]));

        return redirect()->back()->with('success', __('Account Category created successfully.'));
    }

    public function update(Request $request, AccountCategory $accountCategory): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        $validated = $this->validateAccountCategory($request, uniqueRule: Rule::unique('account_category', 'code')->ignore($accountCategory->id));

        $accountCategory->update($this->payload($validated));

        return redirect()->back()->with('success', __('Account Category updated successfully.'));
    }

    public function destroy(Request $request, AccountCategory $accountCategory): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.delete');

        if ($accountCategory->is_system) {
            return redirect()->back()->withErrors(['account_category' => __('System account categories cannot be deleted.')]);
        }

        if ($accountCategory->accountTypes()->exists()) {
            return redirect()->back()->withErrors(['account_category' => __('Cannot delete account category in use by account types.')]);
        }

        $accountCategory->delete();

        return redirect()->back()->with('success', __('Account Category deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccountCategory(Request $request, mixed $uniqueRule): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', 'string', Rule::in(['debit', 'credit'])],
            'statement_type' => ['required', 'string', Rule::in(['balance_sheet', 'income_statement'])],
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
    private function payload(array $validated, array $overrides = []): array
    {
        return [
            ...$overrides,
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'normal_balance' => $validated['normal_balance'],
            'statement_type' => $validated['statement_type'],
            'is_contra' => $validated['is_contra'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }
}
