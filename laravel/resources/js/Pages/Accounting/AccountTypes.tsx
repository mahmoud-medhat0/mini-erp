import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getCategoryLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { AccountCategoryItem, AccountGroupSubItem, AccountSubItem, AccountTypeItem, SharedPageProps } from '../../Types';

type AccountTypesProps = SharedPageProps & {
  accountTypes: AccountTypeItem[];
  accountCategories?: AccountCategoryItem[];
};

type NormalBalance = 'debit' | 'credit';
type StatementType = 'balance_sheet' | 'income_statement';

export default function AccountTypes({ locale, accountTypes = [], accountCategories = [] }: AccountTypesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.accountingAccountTypes;
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const fieldsDict = dict.app.fields;
  const statusDict = dict.app.status;

  const [showAddModal, setShowAddModal] = useState(false);
  const [editingType, setEditingType] = useState<AccountTypeItem | null>(null);
  const [selectedTypeGroupsDetails, setSelectedTypeGroupsDetails] = useState<AccountTypeItem | null>(null);
  const [selectedTypeAccountsDetails, setSelectedTypeAccountsDetails] = useState<AccountTypeItem | null>(null);

  const form = useForm({
    account_category_id: accountCategories.length > 0 ? accountCategories[0].id : '',
    code: '',
    name_en: '',
    name_ar: '',
    normal_balance: 'debit' as NormalBalance,
    statement_type: 'balance_sheet' as StatementType,
    category: 'asset',
    is_contra: false,
    sort_order: 0,
    is_active: true,
  });

  function openCreateModal() {
    setEditingType(null);
    form.reset();
    if (accountCategories.length > 0) {
      const defaultCat = accountCategories[0];
      form.setData((prev) => ({
        ...prev,
        account_category_id: defaultCat.id,
        normal_balance: defaultCat.normal_balance,
        statement_type: defaultCat.statement_type,
        category: strToLower(defaultCat.code),
        is_contra: defaultCat.is_contra,
      }));
    }
    setShowAddModal(true);
  }

  function strToLower(val?: string): string {
    return (val || '').toLowerCase();
  }

  function openEditModal(at: AccountTypeItem) {
    setEditingType(at);
    form.setData({
      account_category_id: at.account_category_id || '',
      code: at.code,
      name_en: typeof at.name === 'object' ? at.name.en || '' : at.name,
      name_ar: typeof at.name === 'object' ? at.name.ar || '' : at.name,
      normal_balance: at.normal_balance,
      statement_type: at.statement_type,
      category: at.category,
      is_contra: at.is_contra,
      sort_order: at.sort_order,
      is_active: at.is_active,
    });
    setShowAddModal(true);
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editingType) {
      form.patch(`/accounting/account-types/${editingType.id}`, {
        onSuccess: () => {
          setShowAddModal(false);
          setEditingType(null);
        },
      });
    } else {
      form.post('/accounting/account-types', {
        onSuccess: () => {
          setShowAddModal(false);
          form.reset();
        },
      });
    }
  }

  function handleDelete(at: AccountTypeItem) {
    if (at.is_system) return;
    if ((at.groups_count ?? 0) > 0 || (at.accounts_count ?? 0) > 0) return;
    const accountTypeName = getLocalizedName(at.name, locale) || at.code;
    if (confirm(dict.app.pages.accountingAccountTypes.confirmDeleteAccountType.replace('{name}', accountTypeName))) {
      router.delete(`/accounting/account-types/${at.id}`);
    }
  }

  const categoryOptions = accountCategories.map((cat) => ({
    value: cat.id,
    label: `${cat.code} - ${getLocalizedName(cat.name, locale)}`,
  }));

  const handleCategorySelect = (categoryId: string) => {
    const selectedCat = accountCategories.find((c) => c.id === categoryId);
    if (selectedCat) {
      form.setData((prev) => ({
        ...prev,
        account_category_id: categoryId,
        normal_balance: selectedCat.normal_balance,
        statement_type: selectedCat.statement_type,
        category: selectedCat.code.toLowerCase(),
        is_contra: selectedCat.is_contra,
      }));
    } else {
      form.setData('account_category_id', categoryId);
    }
  };

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
    <AppLayout active="accounting.account_types">
      <Head title={accDict.accountTypes || pageDict.accountTypes} />

      <PageHeader
        title={accDict.accountTypes || pageDict.accountTypes}
        description={accDict.accountTypesDesc || pageDict.accountTypesDesc}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.addAccountType || pageDict.addAccountType}</span>
          </button>
        }
      />

      {/* Modal / Card Form */}
      {showAddModal && (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {editingType ? (accDict.editAccountType || pageDict.editAccountType) : (accDict.addAccountType || pageDict.addAccountType)}
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
                {dict.app.fields.code}
              </label>
              <input
                type="text"
                value={form.data.code}
                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                placeholder={pageDict.codePlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {form.errors.code && <span className="text-[10px] text-red-500 mt-1 block">{form.errors.code}</span>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.fields.nameEn}
              </label>
              <input
                type="text"
                value={form.data.name_en}
                onChange={(e) => form.setData('name_en', e.target.value)}
                placeholder={pageDict.nameEnPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {form.errors.name_en && <span className="text-[10px] text-red-500 mt-1 block">{form.errors.name_en}</span>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.fields.nameAr}
              </label>
              <input
                type="text"
                value={form.data.name_ar}
                onChange={(e) => form.setData('name_ar', e.target.value)}
                placeholder={pageDict.nameArPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {form.errors.name_ar && <span className="text-[10px] text-red-500 mt-1 block">{form.errors.name_ar}</span>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.category || pageDict.category}
              </label>
              <SearchableSelect
                options={categoryOptions}
                value={form.data.account_category_id}
                onChange={(val) => handleCategorySelect(val || '')}
                isClearable={false}
              />
              {form.errors.account_category_id && (
                <span className="text-[10px] text-red-500 mt-1 block">{form.errors.account_category_id}</span>
              )}
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

            <div className="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-6 pt-2">
              <ToggleSwitch
                checked={form.data.is_contra}
                onChange={(chk) => form.setData('is_contra', chk)}
                label={accDict.isContra || pageDict.contraAccountType}
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

      {/* Account Types Table */}
      <div className={tableClasses.wrap}>
        <table className={tableClasses.table}>
          <thead>
            <tr>
              <th className={tableClasses.th}>{fieldsDict.code || pageDict.code}</th>
              <th className={tableClasses.th}>{fieldsDict.name || pageDict.name}</th>
              <th className={tableClasses.th}>{accDict.category || pageDict.category}</th>
              <th className={tableClasses.th}>{accDict.normalBalance || pageDict.normalBalance}</th>
              <th className={tableClasses.th}>{accDict.statementType || pageDict.statement}</th>
              <th className={tableClasses.th}>{pageDict.groups}</th>
              <th className={tableClasses.th}>{accDict.accounts || pageDict.accounts}</th>
              <th className={tableClasses.th}>{fieldsDict.status || pageDict.status}</th>
              <th className={`${tableClasses.th} text-right`}>{actionsDict.actionsTitle || pageDict.actions}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {accountTypes.map((at) => {
              const isDeletable = !at.is_system && (at.groups_count ?? 0) === 0 && (at.accounts_count ?? 0) === 0;

              return (
                <tr key={at.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{at.code}</span>
                      {at.is_system ? (
                        <StatusBadge tone="muted">{accDict.systemBadge || pageDict.systemBadge}</StatusBadge>
                      ) : (
                        <StatusBadge tone="info">{accDict.customBadge || pageDict.customBadge}</StatusBadge>
                      )}
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(at.name, locale)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                      {at.accountCategory ? getLocalizedName(at.accountCategory.name, locale) : getCategoryLabel(at.category, locale)}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    {at.normal_balance === 'debit' ? (
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
                      {at.statement_type === 'balance_sheet' ? (accDict.balanceSheet || pageDict.balanceSheet_2) : (accDict.incomeStatement || pageDict.incomeStatement_2)}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => setSelectedTypeGroupsDetails(at)}
                      className="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer shadow-xs bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/20 active:scale-95 border border-indigo-500/20"
                      title={pageDict.viewAccountGroupsDetails}
                    >
                      <span>{at.groups_count ?? 0}</span>
                      <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => setSelectedTypeAccountsDetails(at)}
                      className="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer shadow-xs bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 active:scale-95 border border-emerald-500/20"
                      title={pageDict.viewAccountsDetails}
                    >
                      <span>{at.accounts_count ?? 0}</span>
                      <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                  <td className={tableClasses.td}>
                   <StatusBadge tone={at.is_active ? 'ok' : 'muted'}>
                      {at.is_active ? statusDict.active : statusDict.inactive}
                    </StatusBadge>
                  </td>
                  <td className={`${tableClasses.td} text-right`}>
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => openEditModal(at)}
                        className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
                      >
                        {actionsDict.edit}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(at)}
                        disabled={!isDeletable}
                        className={`rounded-lg border px-2.5 py-1 text-xs font-bold transition-colors ${
                          isDeletable
                            ? 'border-red-500/30 bg-red-500/10 text-red-600 hover:bg-red-500/20 cursor-pointer'
                            : 'border-[var(--border)] bg-[var(--background)] text-[var(--text-muted)] cursor-not-allowed opacity-50'
                        }`}
                        title={
                          at.is_system
                            ? (accDict.systemCannotDelete || pageDict.systemAccountTypesCannotBeDeleted)
                            : !isDeletable
                            ? pageDict.cannotDeleteAccountTypeInUse
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

      {/* Groups Breakdown Modal */}
      {selectedTypeGroupsDetails && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
          <Card className="w-full max-w-3xl max-h-[85vh] flex flex-col p-6 border-2 border-[var(--primary)]/30 shadow-2xl bg-[var(--surface)]">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-4">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-xs px-2 py-0.5 rounded bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                    {selectedTypeGroupsDetails.code}
                  </span>
                  <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                    {getLocalizedName(selectedTypeGroupsDetails.name, locale)}
                  </h3>
                </div>
                <p className="mt-1 text-xs text-[var(--text-secondary)]">
                  {pageDict.accountGroupsLinkedDescription.replace('{count}', String(selectedTypeGroupsDetails.groups_count ?? 0))}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedTypeGroupsDetails(null)}
                className="inline-flex items-center gap-1 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{actionsDict.close}</span>
              </button>
            </div>

            <div className="overflow-y-auto flex-1">
              {(selectedTypeGroupsDetails.groups?.length ?? 0) === 0 ? (
                <div className="p-8 text-center text-xs font-bold text-[var(--text-muted)]">
                  {pageDict.noAccountGroupsLinkedToThis}
                </div>
              ) : (
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{fieldsDict.code || pageDict.code}</th>
                      <th className={tableClasses.th}>{fieldsDict.name || pageDict.name}</th>
                      <th className={tableClasses.th}>{accDict.statementType || pageDict.statementSection}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border)]">
                    {selectedTypeGroupsDetails.groups?.map((grp) => (
                      <tr key={grp.id} className="hover:bg-[var(--background)]/50 transition-colors">
                        <td className={tableClasses.td}>
                          <span className="font-mono font-bold text-xs text-indigo-600 dark:text-indigo-400">{grp.code}</span>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(grp.name, locale)}</span>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="text-xs text-[var(--text-secondary)]">{grp.statement_section}</span>
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

      {/* Accounts Breakdown Modal */}
      {selectedTypeAccountsDetails && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
          <Card className="w-full max-w-3xl max-h-[85vh] flex flex-col p-6 border-2 border-[var(--primary)]/30 shadow-2xl bg-[var(--surface)]">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-4">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-xs px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    {selectedTypeAccountsDetails.code}
                  </span>
                  <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                    {getLocalizedName(selectedTypeAccountsDetails.name, locale)}
                  </h3>
                </div>
                <p className="mt-1 text-xs text-[var(--text-secondary)]">
                  {pageDict.accountsLinkedDescription.replace('{count}', String(selectedTypeAccountsDetails.accounts_count ?? 0))}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedTypeAccountsDetails(null)}
                className="inline-flex items-center gap-1 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{actionsDict.close}</span>
              </button>
            </div>

            <div className="overflow-y-auto flex-1">
              {(selectedTypeAccountsDetails.accounts?.length ?? 0) === 0 ? (
                <div className="p-8 text-center text-xs font-bold text-[var(--text-muted)]">
                  {pageDict.noAccountsLinkedToThisType}
                </div>
              ) : (
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{fieldsDict.code || pageDict.code}</th>
                      <th className={tableClasses.th}>{fieldsDict.name || pageDict.name}</th>
                      <th className={tableClasses.th}>{accDict.normalBalance || pageDict.nature}</th>
                      <th className={tableClasses.th}>{accDict.currency || pageDict.currency}</th>
                      <th className={tableClasses.th}>{pageDict.controlAccount}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border)]">
                    {selectedTypeAccountsDetails.accounts?.map((acc) => (
                      <tr key={acc.id} className="hover:bg-[var(--background)]/50 transition-colors">
                        <td className={tableClasses.td}>
                          <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{acc.code}</span>
                        </td>
                        <td className={tableClasses.td}>
                          <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(acc.name, locale)}</span>
                        </td>
                        <td className={tableClasses.td}>
                          {acc.nature === 'debit' ? (
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
                          <span className="font-mono text-xs font-bold text-[var(--text-secondary)]">{acc.currency}</span>
                        </td>
                        <td className={tableClasses.td}>
                          {acc.is_control ? (
                            <StatusBadge tone="info">{accDict.controlBadge || pageDict.control}</StatusBadge>
                          ) : (
                            <span className="text-xs text-[var(--text-muted)]">{pageDict.emptyValue}</span>
                          )}
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
