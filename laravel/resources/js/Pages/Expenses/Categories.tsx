import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, AccountOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type TaxCodeOption = {
  id: string;
  code: string;
  name: TranslatedName;
};

type ExpenseCategoryRow = {
  id: string;
  code: string;
  name: TranslatedName;
  default_expense_account_id?: string | null;
  default_tax_code_id?: string | null;
  requires_attachment: boolean;
  is_active: boolean;
  lock_version: number;
  expense_lines_count?: number;
  default_expense_account?: AccountOption | null;
  default_tax_code?: TaxCodeOption | null;
};

type ExpenseAccountOption = AccountOption & {
  currency?: string | null;
};

type PaginatedData<T> = {
  data: T[];
  total: number;
  links: PaginationLink[];
};

type Props = SharedPageProps & {
  categories: PaginatedData<ExpenseCategoryRow>;
  expenseAccounts: ExpenseAccountOption[];
  taxCodes: TaxCodeOption[];
  filters: {
    search?: string;
  };
};

export default function ExpenseCategoriesIndex({ locale, categories, expenseAccounts = [], taxCodes = [], filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.expenseCategories;
  const can = useCan();
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<ExpenseCategoryRow | null>(null);
  const [search, setSearch] = useState(filters.search || '');

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    default_expense_account_id: '',
    default_tax_code_id: '',
    requires_attachment: false,
    is_active: true,
    lock_version: 1,
  });

  const accountOptions = useMemo(() => expenseAccounts.map((account) => ({
    value: account.id,
    label: `${account.code} - ${getLocalizedName(account.name, locale)}`,
    sublabel: account.currency_code || account.currency || undefined,
  })), [expenseAccounts, locale]);

  const taxOptions = useMemo(() => taxCodes.map((taxCode) => ({
    value: taxCode.id,
    label: `${taxCode.code} - ${getLocalizedName(taxCode.name, locale)}`,
  })), [taxCodes, locale]);
  const activeFilterCount = [search].filter(Boolean).length;

  function applyFilters() {
    router.get('/expenses/categories', { search }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    router.get('/expenses/categories', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      default_expense_account_id: '',
      default_tax_code_id: '',
      requires_attachment: false,
      is_active: true,
      lock_version: 1,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEdit(category: ExpenseCategoryRow) {
    setEditing(category);
    form.setData({
      code: category.code,
      name: {
        en: typeof category.name === 'object' && category.name ? category.name.en || '' : String(category.name || ''),
        ar: typeof category.name === 'object' && category.name ? category.name.ar || '' : String(category.name || ''),
      },
      default_expense_account_id: category.default_expense_account_id || '',
      default_tax_code_id: category.default_tax_code_id || '',
      requires_attachment: category.requires_attachment,
      is_active: category.is_active,
      lock_version: category.lock_version,
    });
    form.clearErrors();
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();

    if (editing) {
      form.put(`/expenses/categories/${editing.id}`, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    form.post('/expenses/categories', { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function deleteCategory(category: ExpenseCategoryRow) {
    if ((category.expense_lines_count || 0) > 0) return;
    const categoryName = getLocalizedName(category.name, locale) || category.code;
    if (window.confirm(pageDict.confirmDeleteCategory.replace('{name}', categoryName))) {
      router.delete(`/expenses/categories/${category.id}`, { preserveScroll: true });
    }
  }

  return (
    <AppLayout active="expense-categories.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('expenses.create') ? <Button onClick={openCreate}>{pageDict.createCategory}</Button> : null}
      />

      <Card className="mb-5 p-4">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') applyFilters();
            }}
            placeholder={pageDict.search}
            className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)] sm:w-80"
          />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="space-y-4">
            <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
              <h2 className="text-base font-bold text-[var(--text-primary)]">
                {editing ? pageDict.editCategory : pageDict.createCategory}
              </h2>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <label className="block">
                <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.code}</span>
                <input
                  type="text"
                  value={form.data.code}
                  onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm font-bold text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                  required
                />
                {form.errors.code ? <span className="mt-1 block text-xs font-semibold text-red-500">{form.errors.code}</span> : null}
              </label>

              <label className="block">
                <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.nameEn}</span>
                <input
                  type="text"
                  value={form.data.name.en}
                  onChange={(event) => form.setData('name', { ...form.data.name, en: event.target.value })}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                  required
                />
              </label>

              <label className="block">
                <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.nameAr}</span>
                <input
                  type="text"
                  value={form.data.name.ar}
                  onChange={(event) => form.setData('name', { ...form.data.name, ar: event.target.value })}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                />
              </label>

              <div>
                <SearchableSelect
                  label={pageDict.defaultExpenseAccount}
                  options={accountOptions}
                  value={form.data.default_expense_account_id || null}
                  onChange={(value) => form.setData('default_expense_account_id', value || '')}
                  error={form.errors.default_expense_account_id}
                />
              </div>

              <div>
                <SearchableSelect
                  label={pageDict.defaultTaxCode}
                  options={taxOptions}
                  value={form.data.default_tax_code_id || null}
                  onChange={(value) => form.setData('default_tax_code_id', value || '')}
                  error={form.errors.default_tax_code_id}
                />
              </div>

              <div className="flex items-end gap-6">
                <ToggleSwitch
                  checked={form.data.requires_attachment}
                  onChange={(checked) => form.setData('requires_attachment', checked)}
                  label={pageDict.requiresAttachment}
                />
                <ToggleSwitch
                  checked={form.data.is_active}
                  onChange={(checked) => form.setData('is_active', checked)}
                  label={pageDict.active}
                />
              </div>
            </div>

            <div className="flex justify-end">
              <Button type="submit" disabled={form.processing}>{pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {categories.data.length === 0 ? (
        <EmptyState title={pageDict.noCategories} description={pageDict.noCategoriesDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.code}</th>
                <th className={tableClasses.th}>{pageDict.nameEn}</th>
                <th className={tableClasses.th}>{pageDict.defaultExpenseAccount}</th>
                <th className={tableClasses.th}>{pageDict.defaultTaxCode}</th>
                <th className={tableClasses.th}>{pageDict.requiresAttachment}</th>
                <th className={tableClasses.th}>{pageDict.usage}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {categories.data.map((category) => (
                <tr key={category.id} className="hover:bg-[var(--background)]/60">
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{category.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{getLocalizedName(category.name, locale)}</td>
                  <td className={tableClasses.td}>
                    {category.default_expense_account
                      ? `${category.default_expense_account.code} - ${getLocalizedName(category.default_expense_account.name, locale)}`
                      : pageDict.notMapped}
                  </td>
                  <td className={tableClasses.td}>
                    {category.default_tax_code ? `${category.default_tax_code.code} - ${getLocalizedName(category.default_tax_code.name, locale)}` : pageDict.noTax}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={category.requires_attachment ? 'warning' : 'muted'}>
                      {category.requires_attachment ? pageDict.attachmentRequired : pageDict.attachmentOptional}
                    </StatusBadge>
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>
                    {category.expense_lines_count || 0} {pageDict.expenseLinesCount}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={category.is_active ? 'ok' : 'muted'}>
                      {category.is_active ? pageDict.active : pageDict.inactive}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center gap-3">
                      {can('expenses.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEdit(category)}
                          className="text-xs font-bold text-[var(--primary)] hover:underline"
                          title={pageDict.edit}
                          aria-label={pageDict.edit}
                        >
                          {pageDict.edit}
                        </button>
                      ) : null}
                      {can('expenses.delete') ? (
                        <button
                          type="button"
                          onClick={() => deleteCategory(category)}
                          disabled={(category.expense_lines_count || 0) > 0}
                          className="text-xs font-bold text-red-500 hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                          title={(category.expense_lines_count || 0) > 0 ? pageDict.deleteBlocked : undefined}
                        >
                          {pageDict.delete}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
