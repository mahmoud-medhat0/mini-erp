import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { AccountOption, CurrencyOption, PaginationLink, SharedPageProps, TranslatedName } from '../../Types';

type CashAccountRow = {
  id: string;
  code: string;
  name: TranslatedName;
  branch_id?: string | null;
  branch?: { id: string; code: string; name: Record<string, string> | string } | null;
  currency: string;
  gl_account_id: string;
  gl_account?: { id: string; code: string; name: string };
  is_active: boolean;
  lock_version: number;
};

type CashAccountsProps = SharedPageProps & {
  cashAccounts?: CashAccountRow[] | { data: CashAccountRow[]; links: PaginationLink[] };
  glAccounts: AccountOption[];
  currencies: CurrencyOption[];
  branches: Array<{ id: string; code: string; name: Record<string, string> | string }>;
  filters: {
    search?: string;
    status?: string;
    branch_id?: string;
  };
};

export default function CashAccountsIndex({ locale, glAccounts = [], currencies = [], branches = [], filters }: CashAccountsProps) {
  const dict = getDictionary(locale);
  const can = useCan();
  const pageDict = dict.app.pages.cashAccounts;
  const accDict = dict.app.accounting;

  const [showModal, setShowModal] = useState(false);
  const [editingAccount, setEditingAccount] = useState<CashAccountRow | null>(null);
  const [status, setStatus] = useState(filters.status || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [reloadToken, setReloadToken] = useState(0);

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    code: '',
    name: '',
    branch_id: '',
    currency: '',
    gl_account_id: '',
    is_active: true,
    lock_version: 0,
  });

  const openCreateModal = () => {
    setEditingAccount(null);
    reset();
    setShowModal(true);
  };

  const openEditModal = (acc: CashAccountRow) => {
    setEditingAccount(acc);
    setData({
      code: acc.code,
      name: getLocalizedName(acc.name, locale),
      branch_id: acc.branch_id || '',
      currency: acc.currency,
      gl_account_id: acc.gl_account_id,
      is_active: acc.is_active,
      lock_version: acc.lock_version,
    });
    setShowModal(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (editingAccount) {
      patch(`/cash-accounts/${editingAccount.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          reset();
          setReloadToken((value) => value + 1);
        },
      });
    } else {
      post('/cash-accounts', {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          reset();
          setReloadToken((value) => value + 1);
        },
      });
    }
  };

  const glSelectOptions = glAccounts.map((a) => ({
    value: a.id,
    label: `${a.code} - ${getLocalizedName(a.name, locale)}`,
  }));

  const currencyOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} (${getLocalizedName(c.name, locale)})`,
  }));
  const branchOptions = branches.map((b) => ({
    value: b.id,
    label: `${b.code} - ${getLocalizedName(b.name, locale)}`,
  }));
  const statusOptions = [
    { value: '', label: pageDict.allStatuses },
    { value: 'active', label: pageDict.active },
    { value: 'inactive', label: pageDict.inactive },
  ];
  const activeFilterCount = [filters.search, status, branchId].filter(Boolean).length;

  function clearFilters() {
    setStatus('');
    setBranchId('');
    router.get('/cash-accounts', {}, { preserveScroll: true, preserveState: true });
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: pageDict.code },
    { data: 'name_text', name: 'name_text', title: pageDict.name, orderable: false },
    { data: 'branch_label', name: 'branch_label', title: pageDict.branch, orderable: false, searchable: false },
    { data: 'currency', name: 'currency', title: pageDict.currency },
    { data: 'gl_account_label', name: 'gl_account_label', title: pageDict.linkedGlAccount, orderable: false, searchable: false },
    { data: 'is_active', name: 'is_active', title: pageDict.status, searchable: false },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    code: (value: any) => <span className="font-mono text-xs font-bold">{value}</span>,
    name_text: (_value: any, _type: any, account: CashAccountRow) => (
      <span className="font-semibold">{getLocalizedName(account.name, locale)}</span>
    ),
    branch_label: (_value: any, _type: any, account: CashAccountRow) => (
      <span>{account.branch ? `${account.branch.code} - ${getLocalizedName(account.branch.name, locale)}` : pageDict.noBranch}</span>
    ),
    currency: (value: any) => <span className="font-mono text-xs font-bold">{value}</span>,
    gl_account_label: (_value: any, _type: any, account: CashAccountRow) => (
      <span>{account.gl_account ? `${account.gl_account.code} - ${getLocalizedName(account.gl_account.name, locale)}` : accDict.notAvailable}</span>
    ),
    is_active: (_value: any, _type: any, account: CashAccountRow) => (
      <StatusBadge tone={account.is_active ? 'ok' : 'muted'}>
        {account.is_active ? pageDict.active : pageDict.inactive}
      </StatusBadge>
    ),
    actions: (_value: any, _type: any, account: CashAccountRow) => (
      <div className="flex flex-wrap items-center justify-end gap-2">
        {can('cash.edit') ? (
          <button
            type="button"
            onClick={() => openEditModal(account)}
            title={pageDict.edit}
            aria-label={pageDict.edit}
            className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
          >
            {pageDict.edit}
          </button>
        ) : (
          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
        )}
      </div>
    ),
  }), [accDict.notAvailable, can, dict.app.actions.restricted, locale, pageDict]);

  const tableFilters = useMemo(() => ({ status, branch_id: branchId }), [branchId, status]);
  const toolbar = (
    <div className="flex flex-wrap items-center gap-3">
      <SearchableSelect
        options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]}
        value={branchId || null}
        onChange={(value) => setBranchId(value || '')}
        className="w-56"
        isSearchable
      />
      <SearchableSelect
        options={statusOptions}
        value={status || null}
        onChange={(value) => setStatus(value || '')}
        className="w-44"
        isSearchable={false}
      />
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>
    </div>
  );

  return (
    <AppLayout active="cash-accounts.index">
      <Head title={dict.app.pages.cashAccounts.cashAccountsMiniErp} />

      <PageHeader
        title={dict.app.pages.cashAccounts.cashAccounts}
        description={dict.app.pages.cashAccounts.manageCashRegistersAndLinkThem}
        actions={
          can('cash.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={pageDict.createCashAccount}
              aria-label={pageDict.createCashAccount}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.cashAccounts.createCashAccount}
            </button>
          ) : null
        }
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/cash-accounts/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          reloadToken={reloadToken}
          slots={slots}
          tableId="cash-accounts-data-table"
          toolbar={toolbar}
        />
      </Card>

      {/* Modal Form */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {editingAccount ? dict.app.pages.cashAccounts.editCashAccount : dict.app.pages.cashAccounts.createCashAccount_2}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.cashAccounts.code_2} *
                  </label>
                  <input
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                    required
                  />
                  {errors.code && <p className="text-xs text-red-500 mt-1">{errors.code}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.cashAccounts.currency_2} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={data.currency}
                    onChange={(val) => setData('currency', val || '')}
                    isClearable={false}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {pageDict.branch}
                </label>
                <SearchableSelect
                  options={branchOptions}
                  value={data.branch_id}
                  onChange={(val) => setData('branch_id', val || '')}
                  placeholder={pageDict.noBranch}
                />
                {errors.branch_id && <p className="text-xs text-red-500 mt-1">{errors.branch_id}</p>}
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.cashAccounts.cashAccountName} *
                </label>
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-semibold"
                  required
                />
                {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.cashAccounts.linkedAssetGlAccount} *
                </label>
                <SearchableSelect
                  options={glSelectOptions}
                  value={data.gl_account_id}
                  onChange={(val) => setData('gl_account_id', val || '')}
                  isClearable={false}
                />
                {errors.gl_account_id && <p className="text-xs text-red-500 mt-1">{errors.gl_account_id}</p>}
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={data.is_active}
                  onChange={(e) => setData('is_active', e.target.checked)}
                  className="rounded-md border-[var(--border)] text-[var(--primary)]"
                />
                <label htmlFor="is_active" className="text-xs font-semibold text-[var(--text-primary)]">
                  {dict.app.pages.cashAccounts.cashAccountActive}
                </label>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.cashAccounts.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={pageDict.saveAccount}
                  aria-label={pageDict.saveAccount}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.cashAccounts.saving : dict.app.pages.cashAccounts.saveAccount}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
