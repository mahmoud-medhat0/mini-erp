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

export default function AccountTypes({ locale, accountTypes = [], accountCategories = [] }: AccountTypesProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = (dict.app as any).actions || {};
  const fieldsDict = (dict.app as any).fields || {};
  const statusDict = (dict.app as any).status || {};

  const [showAddModal, setShowAddModal] = useState(false);
  const [editingType, setEditingType] = useState<AccountTypeItem | null>(null);
  const [selectedTypeGroupsDetails, setSelectedTypeGroupsDetails] = useState<AccountTypeItem | null>(null);
  const [selectedTypeAccountsDetails, setSelectedTypeAccountsDetails] = useState<AccountTypeItem | null>(null);

  const form = useForm({
    account_category_id: accountCategories.length > 0 ? accountCategories[0].id : '',
    code: '',
    name_en: '',
    name_ar: '',
    normal_balance: 'debit',
    statement_type: 'balance_sheet',
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
    if (confirm(actionsDict.confirmDelete || (locale === 'ar' ? 'هل أنت متاكد من رغبتك في حذف هذا العنصر؟' : 'Are you sure you want to delete this Account Type?'))) {
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
    { value: 'debit', label: accDict.debitOption || (locale === 'ar' ? 'مدين' : 'Debit') },
    { value: 'credit', label: accDict.creditOption || (locale === 'ar' ? 'دائن' : 'Credit') },
  ];

  const statementTypeOptions = [
    { value: 'balance_sheet', label: accDict.balanceSheet || (locale === 'ar' ? 'الميزانية العمومية' : 'Balance Sheet') },
    { value: 'income_statement', label: accDict.incomeStatement || (locale === 'ar' ? 'قائمة الدخل' : 'Income Statement') },
  ];

  return (
    <AppLayout active="accounting.account_types">
      <Head title={accDict.accountTypes || 'Account Types'} />

      <PageHeader
        title={accDict.accountTypes || 'Account Types'}
        description={accDict.accountTypesDesc || 'Manage relational accounting classifications, normal balances, and financial statement categorization.'}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.addAccountType || 'Add Account Type'}</span>
          </button>
        }
      />

      {/* Modal / Card Form */}
      {showAddModal && (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {editingType ? (accDict.editAccountType || 'Edit Account Type') : (accDict.addAccountType || 'Add Account Type')}
            </h3>
            <button
              type="button"
              onClick={() => setShowAddModal(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Cancel'}</span>
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
                placeholder="e.g. ASSET_CURRENT"
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
                placeholder="e.g. Current Assets"
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
                placeholder="مثال: الأصول المتداولة"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {form.errors.name_ar && <span className="text-[10px] text-red-500 mt-1 block">{form.errors.name_ar}</span>}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.category || 'Category'}
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
                {accDict.normalBalance || 'Normal Balance'}
              </label>
              <SearchableSelect
                options={normalBalanceOptions}
                value={form.data.normal_balance}
                onChange={(val) => form.setData('normal_balance', (val as any) || 'debit')}
                isClearable={false}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.statementType || 'Statement Type'}
              </label>
              <SearchableSelect
                options={statementTypeOptions}
                value={form.data.statement_type}
                onChange={(val) => form.setData('statement_type', (val as any) || 'balance_sheet')}
                isClearable={false}
              />
            </div>

            <div className="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-6 pt-2">
              <ToggleSwitch
                checked={form.data.is_contra}
                onChange={(chk) => form.setData('is_contra', chk)}
                label={accDict.isContra || 'Contra Account Type'}
              />

              <ToggleSwitch
                checked={form.data.is_active}
                onChange={(chk) => form.setData('is_active', chk)}
                label={fieldsDict.isActive || 'Active'}
              />
            </div>

            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={form.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save || 'Save'}
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
              <th className={tableClasses.th}>{fieldsDict.code || (locale === 'ar' ? 'الكود' : 'Code')}</th>
              <th className={tableClasses.th}>{fieldsDict.name || (locale === 'ar' ? 'الاسم' : 'Name')}</th>
              <th className={tableClasses.th}>{accDict.category || (locale === 'ar' ? 'التصنيف' : 'Category')}</th>
              <th className={tableClasses.th}>{accDict.normalBalance || (locale === 'ar' ? 'الرصيد الطبيعي' : 'Normal Balance')}</th>
              <th className={tableClasses.th}>{accDict.statementType || (locale === 'ar' ? 'القائمة' : 'Statement')}</th>
              <th className={tableClasses.th}>{accDict.accountGroups || (locale === 'ar' ? 'المجموعات' : 'Groups')}</th>
              <th className={tableClasses.th}>{accDict.accounts || (locale === 'ar' ? 'الحسابات' : 'Accounts')}</th>
              <th className={tableClasses.th}>{fieldsDict.status || (locale === 'ar' ? 'الحالة' : 'Status')}</th>
              <th className={`${tableClasses.th} text-right`}>{actionsDict.actionsTitle || actionsDict.actions || (locale === 'ar' ? 'الإجراءات' : 'Actions')}</th>
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
                        <StatusBadge tone="muted">{accDict.systemBadge || 'SYSTEM'}</StatusBadge>
                      ) : (
                        <StatusBadge tone="info">{accDict.customBadge || 'CUSTOM'}</StatusBadge>
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
                        {accDict.debitBadge || (locale === 'ar' ? 'مدين' : 'DEBIT')}
                      </span>
                    ) : (
                      <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400">
                        {accDict.creditBadge || (locale === 'ar' ? 'دائن' : 'CREDIT')}
                      </span>
                    )}
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs text-[var(--text-secondary)]">
                      {at.statement_type === 'balance_sheet' ? (accDict.balanceSheet || (locale === 'ar' ? 'الميزانية العمومية' : 'Balance Sheet')) : (accDict.incomeStatement || (locale === 'ar' ? 'قائمة الدخل' : 'Income Statement'))}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => setSelectedTypeGroupsDetails(at)}
                      className="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer shadow-xs bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/20 active:scale-95 border border-indigo-500/20"
                      title={locale === 'ar' ? 'عرض تفاصيل المجموعات المحاسبية' : 'View Account Groups Details'}
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
                      title={locale === 'ar' ? 'عرض تفاصيل الحسابات' : 'View Accounts Details'}
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
                      {at.is_active ? (statusDict.active || 'Active') : (statusDict.inactive || 'Inactive')}
                    </StatusBadge>
                  </td>
                  <td className={`${tableClasses.td} text-right`}>
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => openEditModal(at)}
                        className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
                      >
                        {actionsDict.edit || 'Edit'}
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
                            ? (accDict.systemCannotDelete || (locale === 'ar' ? 'لا يمكن حذف السجلات النظامية الخاصة بالنظام.' : 'System account types cannot be deleted.'))
                            : !isDeletable
                            ? (accDict.inUseCannotDelete || (locale === 'ar' ? 'لا يمكن حذف نوع حساب مستخدم بواسطة مجموعات أو حسابات.' : 'Cannot delete account type in use by account groups or accounts.'))
                            : undefined
                        }
                      >
                        {actionsDict.delete || 'Delete'}
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
                  {locale === 'ar'
                    ? `تفاصيل المجموعات المحاسبية المرتبطة بهذا النوع (${selectedTypeGroupsDetails.groups_count ?? 0})`
                    : `Account Groups linked to this Type (${selectedTypeGroupsDetails.groups_count ?? 0})`}
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
                <span>{actionsDict.cancel || 'Close'}</span>
              </button>
            </div>

            <div className="overflow-y-auto flex-1">
              {(selectedTypeGroupsDetails.groups?.length ?? 0) === 0 ? (
                <div className="p-8 text-center text-xs font-bold text-[var(--text-muted)]">
                  {locale === 'ar' ? 'لا توجد مجموعات محاسبية مرتبطة بهذا النوع حالياً.' : 'No Account Groups linked to this type.'}
                </div>
              ) : (
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{fieldsDict.code || 'Code'}</th>
                      <th className={tableClasses.th}>{fieldsDict.name || 'Name'}</th>
                      <th className={tableClasses.th}>{accDict.statementType || 'Statement Section'}</th>
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
                  {locale === 'ar'
                    ? `تفاصيل الحسابات المرتبطة بهذا النوع (${selectedTypeAccountsDetails.accounts_count ?? 0})`
                    : `Accounts linked to this Type (${selectedTypeAccountsDetails.accounts_count ?? 0})`}
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
                <span>{actionsDict.cancel || 'Close'}</span>
              </button>
            </div>

            <div className="overflow-y-auto flex-1">
              {(selectedTypeAccountsDetails.accounts?.length ?? 0) === 0 ? (
                <div className="p-8 text-center text-xs font-bold text-[var(--text-muted)]">
                  {locale === 'ar' ? 'لا توجد حسابات مرتبطة بهذا النوع حالياً.' : 'No Accounts linked to this type.'}
                </div>
              ) : (
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{fieldsDict.code || 'Code'}</th>
                      <th className={tableClasses.th}>{fieldsDict.name || 'Name'}</th>
                      <th className={tableClasses.th}>{accDict.normalBalance || 'Nature'}</th>
                      <th className={tableClasses.th}>{accDict.currency || 'Currency'}</th>
                      <th className={tableClasses.th}>{accDict.isControl || 'Control Account'}</th>
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
                              {accDict.debitBadge || (locale === 'ar' ? 'مدين' : 'DEBIT')}
                            </span>
                          ) : (
                            <span className="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400">
                              {accDict.creditBadge || (locale === 'ar' ? 'دائن' : 'CREDIT')}
                            </span>
                          )}
                        </td>
                        <td className={tableClasses.td}>
                          <span className="font-mono text-xs font-bold text-[var(--text-secondary)]">{acc.currency}</span>
                        </td>
                        <td className={tableClasses.td}>
                          {acc.is_control ? (
                            <StatusBadge tone="info">{accDict.controlBadge || (locale === 'ar' ? 'حساب مراقبة' : 'CONTROL')}</StatusBadge>
                          ) : (
                            <span className="text-xs text-[var(--text-muted)]">—</span>
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
