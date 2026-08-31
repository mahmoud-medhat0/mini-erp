<?php

namespace App\Http\Controllers;

use App\Application\Expenses\ExpenseCategoryPageData;
use App\Application\Expenses\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly ExpenseCategoryService $categoryService,
        private readonly ExpenseCategoryPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Expenses/Categories', $this->pageData->indexData($request->only(['search'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->categoryService->create($data, $request->user()?->id);

        return back()->with('success', __('Expense category saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $this->validated($request, true);
        $this->categoryService->update($id, $data, $request->user()?->id);

        return back()->with('success', __('Expense category updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->categoryService->delete($id, $request->user()?->id);

        return back()->with('success', __('Expense category deleted.'));
    }

    private function validated(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'name.en' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'default_expense_account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'default_tax_code_id' => ['nullable', 'uuid', 'exists:tax_codes,id'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
