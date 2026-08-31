import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type BranchRef = { id: string; code: string; name: Record<string, string> | string };
type EndpointAccount = {
  id: string;
  code: string;
  name: string;
  currency: string;
  branch_id?: string | null;
  branch?: BranchRef | null;
};

type TreasuryTransferRow = {
  id: string;
  number?: string | null;
  transfer_date: string;
  source_type: 'cash' | 'bank';
  destination_type: 'cash' | 'bank';
  source_cash_account?: EndpointAccount | null;
  source_bank_account?: EndpointAccount | null;
  destination_cash_account?: EndpointAccount | null;
  destination_bank_account?: EndpointAccount | null;
  source_branch?: BranchRef | null;
  destination_branch?: BranchRef | null;
  currency: string;
  amount_minor: number;
  fx_rate_e6: number;
  status: string;
  reference?: string | null;
  description?: string | null;
  fiscal_year_id: string;
  financial_period_id: string;
  lock_version: number;
};

type FiscalYearOption = { id: string; year: number; status: string };
type PeriodOption = { id: string; fiscal_year_id: string; month: number; start_date: string; end_date: string; status: string };

type TreasuryTransferProps = SharedPageProps & {
  transfers: { data: TreasuryTransferRow[]; links: PaginationLink[] };
  cashAccounts: EndpointAccount[];
  bankAccounts: EndpointAccount[];
  fiscalYears: FiscalYearOption[];
  financialPeriods: PeriodOption[];
  statuses: string[];
  filters: { search?: string; status?: string };
};

export default function TreasuryTransfersIndex({
  locale,
  transfers,
  cashAccounts = [],
  bankAccounts = [],
  fiscalYears = [],
  financialPeriods = [],
  statuses = [],
  filters,
}: TreasuryTransferProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.treasuryTransfers;
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreateTreasuryTransfers = can('cash.create') || can('banks.create');
  const canEditTreasuryTransfers = can('cash.edit') || can('banks.edit');
  const canPostTreasuryTransfers = (can('cash.post') || can('banks.post')) && can('view_financials');
  const [showModal, setShowModal] = useState(false);
  const [editingTransfer, setEditingTransfer] = useState<TreasuryTransferRow | null>(null);
  const [postingTransferId, setPostingTransferId] = useState<string | null>(null);

  const defaultYear = fiscalYears.find((year) => year.status === 'open') ?? fiscalYears[0];
  const defaultPeriod = financialPeriods.find((period) => period.status === 'open' && period.fiscal_year_id === defaultYear?.id) ?? financialPeriods[0];

  const { data, setData, post, patch, transform, processing, errors, reset } = useForm({
    transfer_date: new Date().toISOString().slice(0, 10),
    source_type: 'cash' as 'cash' | 'bank',
    source_cash_account_id: '',
    source_bank_account_id: '',
    destination_type: 'bank' as 'cash' | 'bank',
    destination_cash_account_id: '',
    destination_bank_account_id: '',
    currency: '',
    amount: '',
    amount_minor: 0,
    fx_rate_e6: 1000000,
    reference: '',
    description: '',
    fiscal_year_id: defaultYear?.id || '',
    financial_period_id: defaultPeriod?.id || '',
    lock_version: 0,
  });

  const endpointLabel = (account?: EndpointAccount | null) => {
    if (!account) return accDict.notAvailable;
    const branch = account.branch ? ` · ${account.branch.code}` : ` · ${pageDict.noBranch}`;
    return `${account.code} - ${account.name}${branch}`;
  };

  const rowEndpoint = (row: TreasuryTransferRow, side: 'source' | 'destination') => {
    if (side === 'source') {
      return row.source_type === 'cash' ? row.source_cash_account : row.source_bank_account;
    }

    return row.destination_type === 'cash' ? row.destination_cash_account : row.destination_bank_account;
  };

  const statusLabel = (status: string) => {
    if (status === 'posted') return pageDict.posted;
    if (status === 'cancelled') return pageDict.cancelled;
    return pageDict.draft;
  };

  const statusTone = (status: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
    if (status === 'posted') return 'ok';
    if (status === 'cancelled') return 'danger';
    return 'muted';
  };

  const accountOptions = (type: 'cash' | 'bank') => (type === 'cash' ? cashAccounts : bankAccounts).map((account) => ({
    value: account.id,
    label: endpointLabel(account),
    badge: account.currency,
  }));

  const fiscalYearOptions = fiscalYears.map((year) => ({ value: year.id, label: `${year.year}` }));
  const periodOptions = financialPeriods
    .filter((period) => !data.fiscal_year_id || period.fiscal_year_id === data.fiscal_year_id)
    .map((period) => ({ value: period.id, label: `${period.start_date} - ${period.end_date}` }));
  const endpointTypeOptions = [
    { value: 'cash', label: pageDict.cash },
    { value: 'bank', label: pageDict.bank },
  ];
  const statusOptions = (statuses.length > 0 ? statuses : ['draft', 'posted', 'cancelled']).map((status) => ({
    value: status,
    label: statusLabel(status),
  }));
  const activeFilterCount = [filters.search, filters.status].filter(Boolean).length;
  const isTreasuryTransferActionable = (row: TreasuryTransferRow) => row.status === 'draft';
  const hasAvailableTreasuryTransferAction = (row: TreasuryTransferRow) => isTreasuryTransferActionable(row) && (canEditTreasuryTransfers || canPostTreasuryTransfers);
  const getTreasuryTransferActionState = (row: TreasuryTransferRow) => {
    if (isTreasuryTransferActionable(row) && !hasAvailableTreasuryTransferAction(row)) {
      return dict.app.actions.restricted;
    }

    if (!isTreasuryTransferActionable(row)) {
      return dict.app.actions.noActions;
    }

    return null;
  };

  const formatMoney = (minor: number, currency: string) => (minor / 100).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ` ${currency}`;
  const formatTreasuryMoney = (minor: number, currency?: string | null) => (currency ? formatMoney(minor, currency) : accDict.notAvailable);

  const applyFilters = (next: Record<string, string>) => {
    const search = next.search ?? filters.search ?? '';
    const status = next.status ?? filters.status ?? '';
    const params: Record<string, string> = {};

    if (search) params.search = search;
    if (status) params.status = status;

    router.get('/treasury-transfers', params, { preserveScroll: true, preserveState: true });
  };

  function clearFilters() {
    router.get('/treasury-transfers', {}, { preserveScroll: true, preserveState: true });
  }

  const openCreateModal = () => {
    setEditingTransfer(null);
    reset();
    setShowModal(true);
  };

  const openEditModal = (row: TreasuryTransferRow) => {
    setEditingTransfer(row);
    setData({
      transfer_date: row.transfer_date,
      source_type: row.source_type,
      source_cash_account_id: row.source_cash_account?.id || '',
      source_bank_account_id: row.source_bank_account?.id || '',
      destination_type: row.destination_type,
      destination_cash_account_id: row.destination_cash_account?.id || '',
      destination_bank_account_id: row.destination_bank_account?.id || '',
      currency: row.currency,
      amount: String(row.amount_minor / 100),
      amount_minor: row.amount_minor,
      fx_rate_e6: row.fx_rate_e6 || 1000000,
      reference: row.reference || '',
      description: row.description || '',
      fiscal_year_id: row.fiscal_year_id,
      financial_period_id: row.financial_period_id,
      lock_version: row.lock_version,
    });
    setShowModal(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const amountMinor = Math.round(Number.parseFloat(data.amount || '0') * 100);

    transform((values) => ({
      ...values,
      source_cash_account_id: values.source_type === 'cash' ? values.source_cash_account_id : null,
      source_bank_account_id: values.source_type === 'bank' ? values.source_bank_account_id : null,
      destination_cash_account_id: values.destination_type === 'cash' ? values.destination_cash_account_id : null,
      destination_bank_account_id: values.destination_type === 'bank' ? values.destination_bank_account_id : null,
      amount_minor: amountMinor,
    }));

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    };

    if (editingTransfer) {
      patch(`/treasury-transfers/${editingTransfer.id}`, options);
    } else {
      post('/treasury-transfers', options);
    }
  };

  const handlePost = (id: string) => {
    setPostingTransferId(id);
  };

  const handleCancel = (id: string) => {
    if (window.confirm(pageDict.confirmCancel)) {
      router.post(`/treasury-transfers/${id}/cancel`, {}, { preserveScroll: true });
    }
  };

  return (
    <AppLayout active="treasury-transfers.index">
      <Head title={pageDict.treasuryTransfersMiniErp} />

      <PageHeader
        title={pageDict.treasuryTransfers}
        description={pageDict.description}
        actions={canCreateTreasuryTransfers ? (
          <button type="button" onClick={openCreateModal} title={pageDict.newTransfer} aria-label={pageDict.newTransfer} className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer">
            {pageDict.newTransfer}
          </button>
        ) : null}
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={pageDict.searchPlaceholder}
            defaultValue={filters.search || ''}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                const target = e.target as HTMLInputElement;
                applyFilters({ search: target.value });
              }
            }}
            className="w-80 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
          <SearchableSelect
            options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]}
            value={filters.status || ''}
            onChange={(value) => applyFilters({ status: value || '' })}
            className="w-44"
            isSearchable={false}
          />
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>
        </div>
      </Card>

      {transfers.data.length === 0 ? (
        <EmptyState title={pageDict.noTransfersFound} description={pageDict.emptyDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.number}</th>
                <th className={tableClasses.th}>{pageDict.date}</th>
                <th className={tableClasses.th}>{pageDict.source}</th>
                <th className={tableClasses.th}>{pageDict.destination}</th>
                <th className={tableClasses.th}>{pageDict.amount}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {transfers.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.number || pageDict.draft}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.transfer_date}</td>
                  <td className={tableClasses.td}>{endpointLabel(rowEndpoint(row, 'source'))}</td>
                  <td className={tableClasses.td}>{endpointLabel(rowEndpoint(row, 'destination'))}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{formatTreasuryMoney(row.amount_minor, row.currency)}</td>
                  <td className={tableClasses.td}><StatusBadge tone={statusTone(row.status)}>{statusLabel(row.status)}</StatusBadge></td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                      {row.status === 'draft' && canEditTreasuryTransfers ? (
                        <button type="button" onClick={() => openEditModal(row)} title={pageDict.editTransfer} aria-label={pageDict.editTransfer} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">
                          {pageDict.editTransfer}
                        </button>
                      ) : null}
                      {row.status === 'draft' && canPostTreasuryTransfers ? (
                        <button type="button" onClick={() => handlePost(row.id)} title={pageDict.confirmPost} aria-label={pageDict.confirmPost} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">
                          {pageDict.post}
                        </button>
                      ) : null}
                      {row.status === 'draft' && canEditTreasuryTransfers ? (
                        <button type="button" onClick={() => handleCancel(row.id)} title={pageDict.confirmCancel} aria-label={pageDict.confirmCancel} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">
                          {pageDict.cancelTransfer}
                        </button>
                      ) : null}
                      {getTreasuryTransferActionState(row) ? (
                        <StatusBadge tone="muted">{getTreasuryTransferActionState(row)}</StatusBadge>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-2xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {editingTransfer ? pageDict.editTransfer : pageDict.createTransfer}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.date}</label>
                  <DatePicker value={data.transfer_date} onChange={(value) => setData('transfer_date', value || '')} />
                  {errors.transfer_date && <p className="text-xs text-red-500 mt-1">{errors.transfer_date}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.fiscalYear}</label>
                  <SearchableSelect options={fiscalYearOptions} value={data.fiscal_year_id} onChange={(value) => setData('fiscal_year_id', value || '')} isClearable={false} />
                  {errors.fiscal_year_id && <p className="text-xs text-red-500 mt-1">{errors.fiscal_year_id}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.financialPeriod}</label>
                  <SearchableSelect options={periodOptions} value={data.financial_period_id} onChange={(value) => setData('financial_period_id', value || '')} isClearable={false} />
                  {errors.financial_period_id && <p className="text-xs text-red-500 mt-1">{errors.financial_period_id}</p>}
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.sourceType}</label>
                  <SearchableSelect
                    options={endpointTypeOptions}
                    value={data.source_type}
                    onChange={(value) => {
                      const sourceType = value === 'bank' ? 'bank' : 'cash';
                      setData({ ...data, source_type: sourceType, source_cash_account_id: '', source_bank_account_id: '' });
                    }}
                    isClearable={false}
                    isSearchable={false}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.sourceAccount}</label>
                  <SearchableSelect
                    options={accountOptions(data.source_type)}
                    value={data.source_type === 'cash' ? data.source_cash_account_id : data.source_bank_account_id}
                    onChange={(value) => data.source_type === 'cash' ? setData('source_cash_account_id', value || '') : setData('source_bank_account_id', value || '')}
                    placeholder={pageDict.selectSource}
                    isClearable={false}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.destinationType}</label>
                  <SearchableSelect
                    options={endpointTypeOptions}
                    value={data.destination_type}
                    onChange={(value) => {
                      const destinationType = value === 'cash' ? 'cash' : 'bank';
                      setData({ ...data, destination_type: destinationType, destination_cash_account_id: '', destination_bank_account_id: '' });
                    }}
                    isClearable={false}
                    isSearchable={false}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.destinationAccount}</label>
                  <SearchableSelect
                    options={accountOptions(data.destination_type)}
                    value={data.destination_type === 'cash' ? data.destination_cash_account_id : data.destination_bank_account_id}
                    onChange={(value) => data.destination_type === 'cash' ? setData('destination_cash_account_id', value || '') : setData('destination_bank_account_id', value || '')}
                    placeholder={pageDict.selectDestination}
                    isClearable={false}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.amount}</label>
                  <input type="number" min="0.01" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]" required />
                  {errors.amount_minor && <p className="text-xs text-red-500 mt-1">{errors.amount_minor}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.currency}</label>
                  <input type="text" value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]" required />
                  {errors.currency && <p className="text-xs text-red-500 mt-1">{errors.currency}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.reference}</label>
                  <input type="text" value={data.reference} onChange={(e) => setData('reference', e.target.value)} className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.descriptionLabel}</label>
                <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]" rows={3} />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button type="button" onClick={() => setShowModal(false)} title={pageDict.cancelTransfer} aria-label={pageDict.cancelTransfer} className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer">
                  {pageDict.cancelTransfer}
                </button>
                <button type="submit" disabled={processing} title={pageDict.saveTransfer} aria-label={pageDict.saveTransfer} className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50">
                  {processing ? pageDict.saving : pageDict.saveTransfer}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      <SensitiveActionModal
        isOpen={postingTransferId !== null}
        onClose={() => setPostingTransferId(null)}
        onConfirm={(payload) => {
          if (!postingTransferId) return;
          router.post(`/treasury-transfers/${postingTransferId}/post`, payload, {
            preserveScroll: true,
            onSuccess: () => setPostingTransferId(null),
          });
        }}
        confirmCode="POST_TREASURY_TRANSFER"
        message={pageDict.confirmPost}
        locale={locale}
      />
    </AppLayout>
  );
}
