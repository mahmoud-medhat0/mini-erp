import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getAccountTypeLabel, getLocalizedName, getAccountNatureLabel } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type AccountTypeItem = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  normal_balance: 'debit' | 'credit';
  category: string;
};

type AccountGroupRow = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  account_type_id?: string | null;
  accountType?: AccountTypeItem | null;
  type: string;
  statement_section?: string | null;
  parent_id?: string | null;
  sort_order: number;
  is_active: boolean;
  accounts?: AccountRow[];
  children?: AccountGroupRow[];
};

type AccountRow = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  account_type_id?: string | null;
  accountType?: AccountTypeItem | null;
  type: string;
  nature: string;
  account_group_id?: string | null;
  currency: string;
  is_control: boolean;
  allow_manual_posting: boolean;
  is_active: boolean;
  group?: AccountGroupRow | null;
};

type CurrencyRow = {
  code: string;
  name: Record<string, string> | string;
  symbol: string;
};

type CoaProps = SharedPageProps & {
  groups: AccountGroupRow[];
  accounts: AccountRow[];
  accountTypes?: AccountTypeItem[];
  currencies?: CurrencyRow[];
};

export default function ChartOfAccounts({ locale, groups = [], accounts = [], accountTypes = [], currencies = [] }: CoaProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = dict.app.actions || {};

  const [showAddGroup, setShowAddGroup] = useState(false);
  const [showAddAccount, setShowAddAccount] = useState(false);

  const defaultTypeId = accountTypes[0]?.id || '';

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
    nature: accountTypes[0]?.normal_balance || 'debit',
    account_group_id: '',
    currency: 'EGP',
    is_control: false,
    allow_manual_posting: true,
  });

  function submitGroup(e: FormEvent) {
    e.preventDefault();
    groupForm.post('/accounting/coa/groups', {
      onSuccess: () => {
        groupForm.reset();
        setShowAddGroup(false);
      },
    });
  }

  function submitAccount(e: FormEvent) {
    e.preventDefault();
    accountForm.post('/accounting/coa/accounts', {
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
    { value: 'debit', label: accDict.debitOption || 'Debit (مدين)' },
    { value: 'credit', label: accDict.creditOption || 'Credit (دائن)' },
  ];

  // Filter groups by account_type_id if selected in accountForm
  const filteredGroups = accountForm.data.account_type_id
    ? groups.filter((g) => !g.account_type_id || g.account_type_id === accountForm.data.account_type_id)
    : groups;

  const groupOptions = filteredGroups.map((g) => ({
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
      nature: (selectedAt ? selectedAt.normal_balance : prev.nature) as 'debit' | 'credit',
      account_group_id: '', // reset group if incompatible
    }));
  };

  return (
    <AppLayout active="accounting.coa">
      <Head title={accDict.coa || 'Chart of Accounts'} />

      <PageHeader
        title={accDict.coa || 'Chart of Accounts'}
        description={accDict.coaDesc || 'Hierarchical account classification and General Ledger accounts matrix.'}
        actions={
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => {
                setShowAddAccount(false);
                setShowAddGroup(!showAddGroup);
              }}
              className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:border-[var(--primary)] transition-colors cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{accDict.addGroup || 'Add Account Group'}</span>
            </button>

            <button
              type="button"
              onClick={() => {
                setShowAddGroup(false);
                setShowAddAccount(!showAddAccount);
              }}
              className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{accDict.addAccount || 'Add Account'}</span>
            </button>
          </div>
        }
      />

      {/* Add Group Modal/Card */}
      {showAddGroup ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.addGroup || 'Add Account Group'}</h3>
            <button
              type="button"
              onClick={() => setShowAddGroup(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Cancel'}</span>
            </button>
          </div>

          <form onSubmit={submitGroup} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.code}</label>
              <input
                type="text"
                value={groupForm.data.code}
                onChange={(e) => groupForm.setData('code', e.target.value)}
                placeholder="e.g. 1000"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameEn}</label>
              <input
                type="text"
                value={groupForm.data.name_en}
                onChange={(e) => groupForm.setData('name_en', e.target.value)}
                placeholder="e.g. Current Assets"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameAr}</label>
              <input
                type="text"
                value={groupForm.data.name_ar}
                onChange={(e) => groupForm.setData('name_ar', e.target.value)}
                placeholder={accDict.groupPlaceholderAr || 'مثال: الأصول المتداولة'}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountTypes || 'Account Type'}
              </label>
              <SearchableSelect
                options={accountTypeSelectOptions}
                value={groupForm.data.account_type_id}
                onChange={(val) => groupForm.setData('account_type_id', val || '')}
                isClearable={false}
              />
            </div>
            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowAddGroup(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={groupForm.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save || 'Save'}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Add Account Modal/Card */}
      {showAddAccount ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{accDict.addAccount || 'Add Account'}</h3>
            <button
              type="button"
              onClick={() => setShowAddAccount(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Cancel'}</span>
            </button>
          </div>

          <form onSubmit={submitAccount} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.code}</label>
              <input
                type="text"
                value={accountForm.data.code}
                onChange={(e) => accountForm.setData('code', e.target.value)}
                placeholder="e.g. 1101"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameEn}</label>
              <input
                type="text"
                value={accountForm.data.name_en}
                onChange={(e) => accountForm.setData('name_en', e.target.value)}
                placeholder="e.g. Petty Cash"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{dict.app.fields.nameAr}</label>
              <input
                type="text"
                value={accountForm.data.name_ar}
                onChange={(e) => accountForm.setData('name_ar', e.target.value)}
                placeholder={accDict.accountPlaceholderAr || 'مثال: النقدية بالخزينة'}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)]"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountTypes || 'Account Type'}
              </label>
              <SearchableSelect
                options={accountTypeSelectOptions}
                value={accountForm.data.account_type_id}
                onChange={(val) => handleAccountTypeChange(val || '')}
                isClearable={false}
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountGroup || 'Account Group'}
              </label>
              <SearchableSelect
                options={groupOptions}
                value={accountForm.data.account_group_id}
                onChange={(val) => accountForm.setData('account_group_id', val || '')}
                placeholder={dict.common.select.placeholder}
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.accountNature || 'Nature'}
              </label>
              <SearchableSelect
                options={natureOptions}
                value={accountForm.data.nature}
                onChange={(val) => accountForm.setData('nature', (val as 'debit' | 'credit') || 'debit')}
                isClearable={false}
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.currency || 'Currency'}
              </label>
              <SearchableSelect
                options={currencyOptions}
                value={accountForm.data.currency}
                onChange={(val) => accountForm.setData('currency', val || 'EGP')}
                isClearable={false}
              />
            </div>
            <div className="sm:col-span-2 lg:col-span-3 pt-2">
              <ToggleSwitch
                checked={accountForm.data.is_control}
                onChange={(chk) => accountForm.setData('is_control', chk)}
                label={accDict.controlAccountLabel || 'Control Account (Subledger Only)'}
                description={accDict.controlAccountDesc || 'If enabled, direct manual postings will be blocked unless posting via authorized subledger handlers.'}
              />
            </div>
            <div className="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowAddAccount(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={accountForm.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {actionsDict.save || 'Save'}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Accounts Table */}
      <div className={tableClasses.wrap}>
        <table className={tableClasses.table}>
          <thead>
            <tr>
              <th className={tableClasses.th}>{dict.app.fields.code}</th>
              <th className={tableClasses.th}>{accDict.accountName || 'Account Name'}</th>
              <th className={tableClasses.th}>{accDict.accountGroup || 'Group'}</th>
              <th className={tableClasses.th}>{accDict.accountType || 'Type'}</th>
              <th className={tableClasses.th}>{accDict.currency || 'Currency'}</th>
              <th className={tableClasses.th}>{accDict.accountNature || 'Nature'}</th>
              <th className={tableClasses.th}>{accDict.controlAccountHeader || 'Control Account'}</th>
              <th className={tableClasses.th}>{dict.app.fields.status}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {accounts.map((acc) => (
              <tr key={acc.id} className="hover:bg-[var(--background)]/50 transition-colors">
                <td className={tableClasses.td}>
                  <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{acc.code}</span>
                </td>
                <td className={tableClasses.td}>
                  <span className="font-bold text-xs text-[var(--text-primary)]">{getLocalizedName(acc.name, locale)}</span>
                </td>
                <td className={tableClasses.td}>
                  <span className="text-xs text-[var(--text-secondary)]">{acc.group ? getLocalizedName(acc.group.name, locale) : '-'}</span>
                </td>
                <td className={tableClasses.td}>
                  <span className="text-xs font-bold px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                    {acc.accountType ? getLocalizedName(acc.accountType.name, locale) : getAccountTypeLabel(acc.type, locale)}
                  </span>
                </td>
                <td className={tableClasses.td}>
                  <span className="font-mono font-bold text-xs text-[var(--text-secondary)]">{acc.currency || 'EGP'}</span>
                </td>
                <td className={tableClasses.td}>
                  {acc.nature.toLowerCase() === 'debit' ? (
                    <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                      {accDict.debitBadge || 'DEBIT'}
                    </span>
                  ) : (
                    <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-purple-500/15 text-purple-600 dark:text-purple-400 border border-purple-500/30">
                      {accDict.creditBadge || 'CREDIT'}
                    </span>
                  )}
                </td>
                <td className={tableClasses.td}>
                  {acc.is_control ? (
                    <StatusBadge tone="warning">{accDict.controlBadge || 'CONTROL'}</StatusBadge>
                  ) : (
                    <span className="text-xs text-[var(--text-muted)]">{accDict.standardBadge || 'Standard'}</span>
                  )}
                </td>
                <td className={tableClasses.td}>
                  <StatusBadge tone={acc.is_active ? 'ok' : 'danger'}>
                    {acc.is_active ? dict.app.status.active : dict.app.status.inactive}
                  </StatusBadge>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
