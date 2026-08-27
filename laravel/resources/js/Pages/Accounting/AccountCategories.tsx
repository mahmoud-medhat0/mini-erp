import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { AccountCategoryItem, AccountTypeSubItem, SharedPageProps } from '../../Types';

type AccountCategoriesProps = SharedPageProps & {
  accountCategories: AccountCategoryItem[];
};

type NormalBalance = 'debit' | 'credit';
type StatementType = 'balance_sheet' | 'income_statement';

export default function AccountCategories({ locale, accountCategories = [] }: AccountCategoriesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.accountingAccountCategories;
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const fieldsDict = dict.app.fields;
  const statusDict = dict.app.status;

  const [showAddModal, setShowAddModal] = useState(false);
  const [editingCategory, setEditingCategory] = useState<AccountCategoryItem | null>(null);
  const [selectedCategoryDetails, setSelectedCategoryDetails] = useState<AccountCategoryItem | null>(null);

  const form = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    normal_balance: 'debit' as 'debit' | 'credit',
    statement_type: 'balance_sheet' as 'balance_sheet' | 'income_statement',
    is_contra: false,
    sort_order: 0,
    is_active: true,
  });

  function openCreateModal() {
    setEditingCategory(null);
    form.reset();
    setShowAddModal(true);
  }

  function openEditModal(cat: AccountCategoryItem) {
    setEditingCategory(cat);
    form.setData({
      code: cat.code,
      name_en: typeof cat.name === 'object' ? cat.name.en || '' : cat.name,
      name_ar: typeof cat.name === 'object' ? cat.name.ar || '' : cat.name,
      normal_balance: cat.normal_balance,
      statement_type: cat.statement_type,
      is_contra: cat.is_contra,
      sort_order: cat.sort_order,
      is_active: cat.is_active,
    });
    setShowAddModal(true);
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editingCategory) {
      form.patch(`/accounting/account-categories/${editingCategory.id}`, {
        onSuccess: () => {
          setShowAddModal(false);
          setEditingCategory(null);
        },
      });
    } else {
      form.post('/accounting/account-categories', {
        onSuccess: () => {
          setShowAddModal(false);
          form.reset();
        },
      });
    }
  }

  function handleDelete(cat: AccountCategoryItem) {
    if (cat.is_system) return;
    if ((cat.account_types_count ?? 0) > 0) return;
    const categoryName = getLocalizedName(cat.name, locale) || cat.code;
    if (confirm(dict.app.pages.accountingAccountCategories.confirmDeleteAccountCategory.replace('{name}', categoryName))) {
      router.delete(`/accounting/account-categories/${cat.id}`);
    }
  }

  const normalBalanceOptions = [
    { value: 'debit', label: accDict.debitOption || pageDict.debit },
    { value: 'credit', label: accDict.creditOption || pageDict.credit },
  ];

  const statementTypeOptions = [
    { value: 'balance_sheet', label: accDict.balanceSheet || pageDict.balanceSheet },
    { value: 'income_statement', label: accDict.incomeStatement || pageDict.incomeStatement },
  ];

  function toNormalBalance(value: string | number | null): NormalBalance {
    return value === 'credit' ? 'credit' : 'debit';
  }

  function toStatementType(value: string | number | null): StatementType {
    return value === 'income_statement' ? 'income_statement' : 'balance_sheet';
  }

  return (
    <AppLayout active="accounting.account_categories">
      <Head title={accDict.accountCategories || pageDict.accountCategories} />

      <PageHeader
        title={accDict.accountCategories || pageDict.accountCategories_2}
        description={accDict.accountCategoriesDesc || pageDict.manageRootAccountingClassificationTaxonomy}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.addAccountCategory || pageDict.addAccountCategory}</span>
          </button>
        }
      />

      {/* Create / Edit Form Modal */}
      {showAddModal && (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {editingCategory
                ? (accDict.editAccountCategory || pageDict.editAccountCategory)
                : (accDict.addAccountCategory || pageDict.addAccountCategory_2)}
            </h3>
            <button
              type="button"
              onClick={() => setShowAddModal(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel}</span>
            </button>
          </div>

          <form onSubmit={handleSubmit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {fieldsDict.code}
              </label>
              <input
                type="text"
                value={form.data.code}
                onChange={(e) => form.setData('code', e.target.value)}
                placeholder={pageDict.codePlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] focus:border-blue-500 focus:outline-none uppercase"
                required
              />
              {form.errors.code && <p className="mt-1 text-xs text-red-500">{form.errors.code}</p>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {fieldsDict.nameEn}
              </label>
              <input
                type="text"
                value={form.data.name_en}
                onChange={(e) => form.setData('name_en', e.target.value)}
                placeholder={pageDict.nameEnPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] focus:border-blue-500 focus:outline-none"
                required
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {fieldsDict.nameAr}
              </label>
              <input
                type="text"
                value={form.data.name_ar}
                onChange={(e) => form.setData('name_ar', e.target.value)}
                placeholder={pageDict.nameArPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] focus:border-blue-500 focus:outline-none"
                required
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.normalBalance || pageDict.normalBalance}
              </label>
              <SearchableSelect
                options={normalBalanceOptions}
                value={form.data.normal_balance}
                onChange={(val) => form.setData('normal_balance', toNormalBalance(val))}
                isClearable={false}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.statementType || pageDict.statement}
              </label>
              <SearchableSelect
                options={statementTypeOptions}
                value={form.data.statement_type}
                onChange={(val) => form.setData('statement_type', toStatementType(val))}
                isClearable={false}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.sortOrder || pageDict.sortOrder}
              </label>
              <input
                type="number"
                value={form.data.sort_order}
                onChange={(e) => form.setData('sort_order', parseInt(e.target.value) || 0)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] focus:border-blue-500 focus:outline-none"
              />
            </div>

            <div className="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-6 pt-2">
              <ToggleSwitch
                checked={form.data.is_contra}
                onChange={(chk) => form.setData('is_contra', chk)}
                label={accDict.isContraCategory || accDict.isContra || pageDict.contraCategory}
              />

              <ToggleSwitch
                checked={form.data.is_active}
                onChange={(chk) => form.setData('is_active', chk)}
                label={statusDict.active}
              />
            </div>

            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel}
              </button>
              <button
                type="submit"
                disabled={form.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save}
              </button>
            </div>
          </form>
        </Card>
      )}

      {/* Account Categories Table */}
      <div className={tableClasses.wrap}>
        <table className={tableClasses.table}>
          <thead>
            <tr>
              <th className={tableClasses.th}>{fieldsDict.code || pageDict.code}</th>
              <th className={tableClasses.th}>{fieldsDict.name || pageDict.name}</th>
              <th className={tableClasses.th}>{accDict.normalBalance || pageDict.normalBalance}</th>
              <th className={tableClasses.th}>{accDict.statementType || pageDict.statement}</th>
              <th className={tableClasses.th}>{accDict.accountTypesCount || pageDict.accountTypes}</th>
              <th className={tableClasses.th}>{fieldsDict.status || pageDict.status}</th>
              <th className={`${tableClasses.th} text-right`}>
                {actionsDict.actionsTitle || pageDict.actions}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {accountCategories.map((cat) => {
              const isDeletable = !cat.is_system && (cat.account_types_count ?? 0) === 0;

              return (
                <tr key={cat.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{cat.code}</span>
                      {cat.is_system ? (
                        <StatusBadge tone="muted">{accDict.systemBadge || pageDict.systemBadge}</StatusBadge>
                      ) : (
                        <StatusBadge tone="info">{accDict.customBadge || pageDict.customBadge}</StatusBadge>
                      )}
                      {cat.is_contra && (
                        <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                          {pageDict.contraBadge}
                        </span>
                      )}
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(cat.name, locale)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    {cat.normal_balance === 'debit' ? (
                      <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400">
                        {accDict.debitBadge || pageDict.debit_2}
                      </span>
                    ) : (
                      <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400">
                        {accDict.creditBadge || pageDict.credit_2}
                      </span>
                    )}
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs text-[var(--text-secondary)]">
                      {cat.statement_type === 'balance_sheet'
                        ? (accDict.balanceSheet || pageDict.balanceSheet_2)
                        : (accDict.incomeStatement || pageDict.incomeStatement_2)}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => setSelectedCategoryDetails(cat)}
                      className="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer shadow-xs bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/20 active:scale-95 border border-indigo-500/20"
                      title={pageDict.viewAccountTypesDetails}
                    >
                      <span>{cat.account_types_count ?? 0}</span>
                      <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={cat.is_active ? 'ok' : 'muted'}>
                      {cat.is_active ? statusDict.active : statusDict.inactive}
                    </StatusBadge>
                  </td>
                  <td className={`${tableClasses.td} text-right`}>
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => openEditModal(cat)}
                        className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
                      >
                        {actionsDict.edit}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(cat)}
                        disabled={!isDeletable}
                        className={`rounded-lg border px-2.5 py-1 text-xs font-bold transition-colors ${
                          isDeletable
                            ? 'border-red-500/30 bg-red-500/10 text-red-600 hover:bg-red-500/20 cursor-pointer'
                            : 'border-[var(--border)] bg-[var(--background)] text-[var(--text-muted)] cursor-not-allowed opacity-50'
                        }`}
                        title={
                          cat.is_system
                            ? (accDict.systemCannotDelete || pageDict.systemAccountCategoriesCannotBeDeleted)
                            : !isDeletable
                            ? (accDict.inUseCategoryCannotDelete || pageDict.cannotDeleteAccountCategoryInUse)
                            : undefined
                        }
                      >
                        {actionsDict.delete}
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Account Types Breakdown Modal */}
      {selectedCategoryDetails && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
          <Card className="w-full max-w-3xl max-h-[85vh] flex flex-col p-6 border-2 border-[var(--primary)]/30 shadow-2xl bg-[var(--surface)]">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-4">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-xs px-2 py-0.5 rounded bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                    {selectedCategoryDetails.code}
                  </span>
                  <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                    {getLocalizedName(selectedCategoryDetails.name, locale)}
                  </h3>
                </div>
                <p className="mt-1 text-xs text-[var(--text-secondary)]">
                  {pageDict.accountTypesLinkedDescription.replace('{count}', String(selectedCategoryDetails.account_types_count ?? 0))}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedCategoryDetails(null)}
                className="inline-flex items-center gap-1 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{actionsDict.close}</span>
              </button>
            </div>

            <div className="overflow-y-auto flex-1">
              {(selectedCategoryDetails.account_types?.length ?? 0) === 0 ? (
                <div className="p-8 text-center text-xs font-bold text-[var(--text-muted)]">
                  {pageDict.noAccountTypesLinkedToThis}
                </div>
              ) : (
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{fieldsDict.code || pageDict.code}</th>
                      <th className={tableClasses.th}>{fieldsDict.name || pageDict.name}</th>
                      <th className={tableClasses.th}>{accDict.normalBalance || pageDict.normalBalance}</th>
                      <th className={tableClasses.th}>{accDict.statementType || pageDict.statement}</th>
                      <th className={tableClasses.th}>{fieldsDict.status || pageDict.status}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border)]">
                    {selectedCategoryDetails.account_types?.map((type) => (
                      <tr key={type.id} className="hover:bg-[var(--background)]/50 transition-colors">
                        <td className={tableClasses.td}>
                          <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{type.code}</span>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(type.name, locale)}</span>
                        </td>
                        <td className={tableClasses.td}>
                          {type.normal_balance === 'debit' ? (
                            <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400">
                              {accDict.debitBadge || pageDict.debit_3}
                            </span>
                          ) : (
                            <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400">
                              {accDict.creditBadge || pageDict.credit_3}
                            </span>
                          )}
                        </td>
                        <td className={tableClasses.td}>
                          <span className="text-xs text-[var(--text-secondary)]">
                            {type.statement_type === 'balance_sheet'
                              ? (accDict.balanceSheet || pageDict.balanceSheet_3)
                              : (accDict.incomeStatement || pageDict.incomeStatement_3)}
                          </span>
                        </td>
                        <td className={tableClasses.td}>
                          <StatusBadge tone={type.is_active ? 'ok' : 'muted'}>
                            {type.is_active ? statusDict.active : statusDict.inactive}
                          </StatusBadge>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </Card>
        </div>
      )}
    </AppLayout>
  );
}
