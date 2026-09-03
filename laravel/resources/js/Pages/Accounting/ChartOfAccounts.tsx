import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect, StatusBadge, ToggleSwitch } from '../../Components/Primitives';
import { getAccountTypeLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { AccountGroupRow, AccountTypeItem, CurrencyRow, SharedPageProps } from '../../Types';

type AccountNature = 'debit' | 'credit';

type CoaProps = SharedPageProps & {
  groups: AccountGroupRow[];
  accountTypes?: AccountTypeItem[];
  currencies?: CurrencyRow[];
};

function toAccountNature(value: string | number | null, fallback: AccountNature): AccountNature {
  return value === 'credit' || value === 'debit' ? value : fallback;
}

export default function ChartOfAccounts({ locale, groups = [], accountTypes = [], currencies = [] }: CoaProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canManageCoa = can('accounting.create') || can('settings.configure');

  const [showAddGroup, setShowAddGroup] = useState(false);
  const [showAddAccount, setShowAddAccount] = useState(false);
  const [selectedGroupFilter, setSelectedGroupFilter] = useState('');
  const [selectedTypeFilter, setSelectedTypeFilter] = useState('');
  const [selectedCurrencyFilter, setSelectedCurrencyFilter] = useState('');
  const [selectedNatureFilter, setSelectedNatureFilter] = useState('');
  const [selectedControlFilter, setSelectedControlFilter] = useState('');
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('');

  const defaultTypeId = accountTypes[0]?.id || '';
  const defaultNature: AccountNature = accountTypes[0]?.normal_balance === 'credit' ? 'credit' : 'debit';

  const groupForm = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    account_type_id: defaultTypeId,
    statement_section: 'balance_sheet',
    parent_id: '',
    sort_order: 0,
  });

  const accountForm = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    account_type_id: defaultTypeId,
    nature: defaultNature,
    account_group_id: '',
    currency: '',
    is_control: false,
    allow_manual_posting: true,
  });

  function submitGroup(e: FormEvent) {
    e.preventDefault();
    groupForm.post('/accounting/coa/groups', {
      preserveScroll: true,
      onSuccess: () => {
        groupForm.reset();
        setShowAddGroup(false);
      },
    });
  }

  function submitAccount(e: FormEvent) {
    e.preventDefault();
    accountForm.post('/accounting/coa/accounts', {
      preserveScroll: true,
      onSuccess: () => {
        accountForm.reset();
        setShowAddAccount(false);
      },
    });
  }

  const accountTypeSelectOptions = accountTypes.map((at) => ({
    value: at.id,
    label: `${at.code} - ${getLocalizedName(at.name, locale)}`,
  }));

  const natureOptions = [
    { value: 'debit', label: accDict.debitOption },
    { value: 'credit', label: accDict.creditOption },
  ];

  const natureFilterOptions = [
    { value: '', label: locale === 'ar' ? 'جميع الطبيعات' : 'All Natures' },
    { value: 'debit', label: accDict.debitOption },
    { value: 'credit', label: accDict.creditOption },
  ];

  const controlFilterOptions = [
    { value: '', label: locale === 'ar' ? 'جميع أنواع الحساب' : 'All Account Modes' },
    { value: 'true', label: accDict.controlBadge },
    { value: 'false', label: accDict.standardBadge },
  ];

  const statusFilterOptions = [
    { value: '', label: locale === 'ar' ? 'جميع الحالات' : 'All Statuses' },
    { value: 'true', label: dict.app.status.active },
    { value: 'false', label: dict.app.status.inactive },
  ];

  // Filter groups by account_type_id if selected in accountForm
  const filteredGroups = accountForm.data.account_type_id
    ? groups.filter((g) => !g.account_type_id || g.account_type_id === accountForm.data.account_type_id)
    : groups;

  const groupOptions = filteredGroups.map((g) => ({
    value: g.id,
    label: `${g.code} - ${getLocalizedName(g.name, locale)}`,
  }));

  const allGroupOptions = groups.map((g) => ({
    value: g.id,
    label: `${g.code} - ${getLocalizedName(g.name, locale)}`,
  }));

  const currencyOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} - ${getLocalizedName(c.name, locale)} (${c.symbol})`,
  }));

  const handleAccountTypeChange = (typeId: string) => {
    const selectedAt = accountTypes.find((at) => at.id === typeId);
    accountForm.setData((prev) => ({
      ...prev,
      account_type_id: typeId,
      nature: selectedAt ? selectedAt.normal_balance : toAccountNature(prev.nature, defaultNature),
      account_group_id: '', // reset group if incompatible
    }));
  };

  // ── DataTables columns ────────────────────────────────────────────────────
  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: dict.app.fields.code, className: 'font-mono font-bold text-xs', width: '120px' },
    { data: 'name', name: 'name', title: accDict.accountName },
    { data: 'group_name', name: 'group_name', title: accDict.accountGroup },
    { data: 'account_type_name', name: 'account_type_name', title: accDict.accountType },
    { data: 'currency', name: 'currency', title: accDict.currency, width: '90px' },
    { data: 'nature', name: 'nature', title: accDict.accountNature, width: '100px', searchable: false },
    { data: 'is_control', name: 'is_control', title: accDict.controlAccountHeader, width: '120px', searchable: false },
    { data: 'is_active', name: 'is_active', title: dict.app.fields.status, searchable: false, width: '100px' },
  ], [dict, accDict]);

  // ── DataTables slots ──────────────────────────────────────────────────────
  const slots = useMemo<DataTableSlots>(() => ({
    code: (d: any) => (
      <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
        {String(d || '')}
      </span>
    ),
    name: (d: any) => (
      <span className="font-bold text-xs text-[var(--text-primary)]">
        {getLocalizedName(d, locale)}
      </span>
    ),
    group_name: (d: any) => (
      <span className="text-xs text-[var(--text-secondary)]">
        {d ? getLocalizedName(d, locale) : accDict.notAvailable}
      </span>
    ),
    account_type_name: (d: any, _t: any, row: any) => (
      <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
        {d ? getLocalizedName(d, locale) : getAccountTypeLabel(row?.type, locale)}
      </span>
    ),
    currency: (d: any) => (
      <span className="font-mono font-bold text-xs text-[var(--text-secondary)]">
        {d || accDict.missingCurrency}
      </span>
    ),
    nature: (d: any) => (
      String(d || '').toLowerCase() === 'debit' ? (
        <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
          {accDict.debitBadge}
        </span>
      ) : (
        <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400 border border-purple-500/30">
          {accDict.creditBadge}
        </span>
      )
    ),
    is_control: (d: any) => (
      d === true || d === 1 || d === '1' ? (
        <StatusBadge tone="warning">{accDict.controlBadge}</StatusBadge>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.standardBadge}</span>
      )
    ),
    is_active: (d: any) => (
      <StatusBadge tone={d === true || d === 1 || d === '1' ? 'ok' : 'danger'}>
        {d === true || d === 1 || d === '1' ? dict.app.status.active : dict.app.status.inactive}
      </StatusBadge>
    ),
  } as Record<string, (data: any, type: any, row: any) => ReactElement>), [dict, accDict, locale]);

  const tableFilters = useMemo(() => ({
    group_id: selectedGroupFilter,
    account_type_id: selectedTypeFilter,
    currency: selectedCurrencyFilter,
    nature: selectedNatureFilter,
    is_control: selectedControlFilter,
    status: selectedStatusFilter,
  }), [selectedGroupFilter, selectedTypeFilter, selectedCurrencyFilter, selectedNatureFilter, selectedControlFilter, selectedStatusFilter]);

  const toolbar = (
    <div className="flex flex-col md:flex-row md:items-center gap-2.5 w-full">
      <div className="flex items-center gap-1.5 px-2.5 py-2 rounded-xl bg-[color-mix(in_srgb,var(--primary)_8%,transparent)] text-xs font-bold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] whitespace-nowrap shrink-0 self-start md:self-auto">
        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        <span>{locale === 'ar' ? 'التصفية' : 'Filters'}</span>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 flex-1 w-full">
        <SearchableSelect
          options={[{ value: '', label: locale === 'ar' ? 'نوع الحساب (الكل)' : 'Account Type (All)' }, ...accountTypeSelectOptions]}
          value={selectedTypeFilter}
          onChange={(v) => setSelectedTypeFilter(v || '')}
          placeholder={locale === 'ar' ? 'نوع الحساب' : 'Account Type'}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={[{ value: '', label: locale === 'ar' ? 'المجموعة (الكل)' : 'Group (All)' }, ...allGroupOptions]}
          value={selectedGroupFilter}
          onChange={(v) => setSelectedGroupFilter(v || '')}
          placeholder={locale === 'ar' ? 'المجموعة' : 'Group'}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={[{ value: '', label: locale === 'ar' ? 'العملة (الكل)' : 'Currency (All)' }, ...currencyOptions]}
          value={selectedCurrencyFilter}
          onChange={(v) => setSelectedCurrencyFilter(v || '')}
          placeholder={locale === 'ar' ? 'العملة' : 'Currency'}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={natureFilterOptions}
          value={selectedNatureFilter}
          onChange={(v) => setSelectedNatureFilter(v || '')}
          placeholder={locale === 'ar' ? 'الطبيعة' : 'Nature'}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={controlFilterOptions}
          value={selectedControlFilter}
          onChange={(v) => setSelectedControlFilter(v || '')}
          placeholder={locale === 'ar' ? 'نمط الحساب' : 'Account Mode'}
          isSearchable={true}
          className="w-full"
        />
        <SearchableSelect
          options={statusFilterOptions}
          value={selectedStatusFilter}
          onChange={(v) => setSelectedStatusFilter(v || '')}
          placeholder={locale === 'ar' ? 'الحالة' : 'Status'}
          isSearchable={true}
          className="w-full"
        />
      </div>

      {(selectedGroupFilter || selectedTypeFilter || selectedCurrencyFilter || selectedNatureFilter || selectedControlFilter || selectedStatusFilter) ? (
        <button
          type="button"
          onClick={() => {
            setSelectedGroupFilter('');
            setSelectedTypeFilter('');
            setSelectedCurrencyFilter('');
            setSelectedNatureFilter('');
            setSelectedControlFilter('');
            setSelectedStatusFilter('');
          }}
          title={accDict.clearFilters}
          aria-label={accDict.clearFilters}
          className="inline-flex items-center justify-center gap-1 text-xs font-bold text-red-600 dark:text-red-400 hover:text-red-700 px-3 py-2 rounded-xl border border-red-500/20 bg-red-500/10 transition-all cursor-pointer whitespace-nowrap shrink-0 self-start md:self-auto"
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>{locale === 'ar' ? 'إعادة ضبط' : 'Reset'}</span>
        </button>
      ) : null}
    </div>
  );

  return (
    <AppLayout active="accounting.coa">
      <Head title={accDict.coa} />

      <PageHeader
        title={accDict.coa}
        description={accDict.coaDesc}
        actions={
          canManageCoa ? (
            <div className="flex items-center gap-3">
              <button
                type="button"
                title={accDict.addGroup}
                aria-label={accDict.addGroup}
                onClick={() => {
                  setShowAddAccount(false);
                  setShowAddGroup(!showAddGroup);
                }}
                className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:border-[var(--primary)] transition-colors cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>{accDict.addGroup}</span>
              </button>

              <button
                type="button"
                title={accDict.addAccount}
                aria-label={accDict.addAccount}
                onClick={() => {
                  setShowAddGroup(false);
                  setShowAddAccount(!showAddAccount);
                }}
                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>{accDict.addAccount}</span>
              </button>
            </div>
          ) : null
        }
      />

      {/* Add Group Modal/Card */}
      {showAddGroup ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.addGroup}</h3>
            <button
              type="button"
              title={actionsDict.cancel}
              aria-label={actionsDict.cancel}
              onClick={() => setShowAddGroup(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel}</span>
            </button>
          </div>

          <form onSubmit={submitGroup} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.code}</label>
              <input
                type="text"
                value={groupForm.data.code}
                onChange={(e) => groupForm.setData('code', e.target.value)}
                placeholder={accDict.groupCodePlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {groupForm.errors.code ? <p className="mt-1 text-xs text-red-500 font-bold">{groupForm.errors.code}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameEn}</label>
              <input
                type="text"
                value={groupForm.data.name_en}
                onChange={(e) => groupForm.setData('name_en', e.target.value)}
                placeholder={accDict.groupNameEnPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {groupForm.errors.name_en ? <p className="mt-1 text-xs text-red-500 font-bold">{groupForm.errors.name_en}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameAr}</label>
              <input
                type="text"
                value={groupForm.data.name_ar}
                onChange={(e) => groupForm.setData('name_ar', e.target.value)}
                placeholder={accDict.groupNameArPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {groupForm.errors.name_ar ? <p className="mt-1 text-xs text-red-500 font-bold">{groupForm.errors.name_ar}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountTypes}
              </label>
              <SearchableSelect
                options={accountTypeSelectOptions}
                value={groupForm.data.account_type_id}
                onChange={(val) => groupForm.setData('account_type_id', val || '')}
                isClearable={false}
              />
              {groupForm.errors.account_type_id ? <p className="mt-1 text-xs text-red-500 font-bold">{groupForm.errors.account_type_id}</p> : null}
            </div>
            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                title={actionsDict.cancel}
                aria-label={actionsDict.cancel}
                onClick={() => setShowAddGroup(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel}
              </button>
              <button
                type="submit"
                title={actionsDict.save}
                aria-label={actionsDict.save}
                disabled={groupForm.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Add Account Modal/Card */}
      {showAddAccount ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.addAccount}</h3>
            <button
              type="button"
              title={actionsDict.cancel}
              aria-label={actionsDict.cancel}
              onClick={() => setShowAddAccount(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel}</span>
            </button>
          </div>

          <form onSubmit={submitAccount} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.code}</label>
              <input
                type="text"
                value={accountForm.data.code}
                onChange={(e) => accountForm.setData('code', e.target.value)}
                placeholder={accDict.accountCodePlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {accountForm.errors.code ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.code}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameEn}</label>
              <input
                type="text"
                value={accountForm.data.name_en}
                onChange={(e) => accountForm.setData('name_en', e.target.value)}
                placeholder={accDict.accountNameEnPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {accountForm.errors.name_en ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.name_en}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameAr}</label>
              <input
                type="text"
                value={accountForm.data.name_ar}
                onChange={(e) => accountForm.setData('name_ar', e.target.value)}
                placeholder={accDict.accountNameArPlaceholder}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
              {accountForm.errors.name_ar ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.name_ar}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountTypes}
              </label>
              <SearchableSelect
                options={accountTypeSelectOptions}
                value={accountForm.data.account_type_id}
                onChange={(val) => handleAccountTypeChange(val || '')}
                isClearable={false}
              />
              {accountForm.errors.account_type_id ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.account_type_id}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountGroup}
              </label>
              <SearchableSelect
                options={groupOptions}
                value={accountForm.data.account_group_id}
                onChange={(val) => accountForm.setData('account_group_id', val || '')}
                placeholder={dict.common.select.placeholder}
              />
              {accountForm.errors.account_group_id ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.account_group_id}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountNature}
              </label>
              <SearchableSelect
                options={natureOptions}
                value={accountForm.data.nature}
                onChange={(val) => accountForm.setData('nature', toAccountNature(val, defaultNature))}
                isClearable={false}
              />
              {accountForm.errors.nature ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.nature}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.currency}
              </label>
              <SearchableSelect
                options={currencyOptions}
                value={accountForm.data.currency}
                onChange={(val) => accountForm.setData('currency', val || '')}
                isClearable={false}
                placeholder={accDict.selectAccountCurrency}
              />
              {accountForm.errors.currency ? <p className="mt-1 text-xs text-red-500 font-bold">{accountForm.errors.currency}</p> : null}
              {currencyOptions.length === 0 ? (
                <p className="mt-2 text-xs font-semibold text-amber-600 dark:text-amber-400">{accDict.noCurrencyOptions}</p>
              ) : null}
            </div>
            <div className="sm:col-span-2 lg:col-span-3 pt-2">
              <ToggleSwitch
                checked={accountForm.data.is_control}
                onChange={(chk) => accountForm.setData('is_control', chk)}
                label={accDict.controlAccountLabel}
                description={accDict.controlAccountDesc}
              />
            </div>
            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                title={actionsDict.cancel}
                aria-label={actionsDict.cancel}
                onClick={() => setShowAddAccount(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel}
              </button>
              <button
                type="submit"
                title={actionsDict.save}
                aria-label={actionsDict.save}
                disabled={accountForm.processing || !accountForm.data.currency || currencyOptions.length === 0}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Accounts ServerDataTable */}
      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/accounting/coa/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          slots={slots}
          tableId="chart-of-accounts-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
