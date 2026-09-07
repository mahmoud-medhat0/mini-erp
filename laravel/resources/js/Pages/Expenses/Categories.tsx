import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge, ToggleSwitch } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { AccountOption, SharedPageProps } from '../../Types';

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

type Props = SharedPageProps & {
  categories?: ExpenseCategoryRow[];
  expenseAccounts: ExpenseAccountOption[];
  taxCodes: TaxCodeOption[];
  filters: {
    search?: string;
  };
};

export default function ExpenseCategoriesIndex({ locale, expenseAccounts = [], taxCodes = [], filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.expenseCategories;
  const can = useCan();
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<ExpenseCategoryRow | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

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
  const activeFilterCount = [filters.search].filter(Boolean).length;

  function clearFilters() {
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
      form.put(`/expenses/categories/${editing.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowForm(false);
          setReloadToken((value) => value + 1);
        },
      });
      return;
    }

    form.post('/expenses/categories', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setReloadToken((value) => value + 1);
      },
    });
  }

  function deleteCategory(category: ExpenseCategoryRow) {
    if ((category.expense_lines_count || 0) > 0) return;
    const categoryName = getLocalizedName(category.name, locale) || category.code;
    if (window.confirm(pageDict.confirmDeleteCategory.replace('{name}', categoryName))) {
      router.delete(`/expenses/categories/${category.id}`, {
        preserveScroll: true,
        onSuccess: () => setReloadToken((value) => value + 1),
      });
    }
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: pageDict.code },
    { data: 'name_text', name: 'name_text', title: pageDict.nameEn, orderable: false },
    { data: 'default_expense_account_text', name: 'default_expense_account_text', title: pageDict.defaultExpenseAccount, orderable: false, searchable: false },
    { data: 'default_tax_code_text', name: 'default_tax_code_text', title: pageDict.defaultTaxCode, orderable: false, searchable: false },
    { data: 'requires_attachment', name: 'requires_attachment', title: pageDict.requiresAttachment, searchable: false },
    { data: 'expense_lines_count', name: 'expense_lines_count', title: pageDict.usage, searchable: false },
    { data: 'is_active', name: 'is_active', title: pageDict.status, searchable: false },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    code: (value: any) => <span className="font-mono text-xs font-bold">{value}</span>,
    name_text: (_value: any, _type: any, category: ExpenseCategoryRow) => (
      <span className="font-semibold">{getLocalizedName(category.name, locale)}</span>
    ),
    default_expense_account_text: (_value: any, _type: any, category: ExpenseCategoryRow) => (
      <span>
        {category.default_expense_account
          ? `${category.default_expense_account.code} - ${getLocalizedName(category.default_expense_account.name, locale)}`
          : pageDict.notMapped}
      </span>
    ),
    default_tax_code_text: (_value: any, _type: any, category: ExpenseCategoryRow) => (
      <span>{category.default_tax_code ? `${category.default_tax_code.code} - ${getLocalizedName(category.default_tax_code.name, locale)}` : pageDict.noTax}</span>
    ),
    requires_attachment: (_value: any, _type: any, category: ExpenseCategoryRow) => (
      <StatusBadge tone={category.requires_attachment ? 'warning' : 'muted'}>
        {category.requires_attachment ? pageDict.attachmentRequired : pageDict.attachmentOptional}
      </StatusBadge>
    ),
    expense_lines_count: (value: any) => (
      <span className="font-mono text-xs font-bold">{Number(value || 0)} {pageDict.expenseLinesCount}</span>
    ),
    is_active: (_value: any, _type: any, category: ExpenseCategoryRow) => (
      <StatusBadge tone={category.is_active ? 'ok' : 'muted'}>
        {category.is_active ? pageDict.active : pageDict.inactive}
      </StatusBadge>
    ),
    actions: (_value: any, _type: any, category: ExpenseCategoryRow) => (
      <div className="flex flex-wrap items-center gap-3">
        {can('expenses.edit') ? (
          <button type="button" onClick={() => openEdit(category)} className="text-xs font-bold text-[var(--primary)] hover:underline" title={pageDict.edit} aria-label={pageDict.edit}>
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
    ),
  }), [can, locale, pageDict]);

  const toolbar = (
    <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
  );

  return (
    <AppLayout active="expense-categories.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('expenses.create') ? <Button onClick={openCreate}>{pageDict.createCategory}</Button> : null}
      />

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

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/expenses/categories/data"
          columns={columns}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          reloadToken={reloadToken}
          slots={slots}
          tableId="expense-categories-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
