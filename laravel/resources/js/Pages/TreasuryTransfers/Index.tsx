import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

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
  transfers: { data: TreasuryTransferRow[]; links: unknown[] };
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
  filters,
}: TreasuryTransferProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.treasuryTransfers;
  const accDict = dict.app.accounting;
  const can = useCan();
  const [showModal, setShowModal] = useState(false);
  const [editingTransfer, setEditingTransfer] = useState<TreasuryTransferRow | null>(null);

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

  const formatMoney = (minor: number, currency: string) => (minor / 100).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ` ${currency}`;
  const formatTreasuryMoney = (minor: number, currency?: string | null) => (currency ? formatMoney(minor, currency) : accDict.notAvailable);

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
    if (window.confirm(pageDict.confirmPost)) {
      router.post(`/treasury-transfers/${id}/post`);
    }
  };

  const handleCancel = (id: string) => {
    if (window.confirm(pageDict.confirmCancel)) {
      router.post(`/treasury-transfers/${id}/cancel`);
    }
  };

  return (
    <AppLayout active="treasury-transfers.index">
      <Head title={pageDict.treasuryTransfersMiniErp} />

      <PageHeader
        title={pageDict.treasuryTransfers}
        description={pageDict.description}
        actions={can('cash.create') || can('banks.create') ? (
          <button type="button" onClick={openCreateModal} className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer">
            {pageDict.newTransfer}
          </button>
        ) : null}
      />

      <Card className="p-4 mb-6">
        <input
          type="text"
          placeholder={pageDict.searchPlaceholder}
          defaultValue={filters.search || ''}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              const target = e.target as HTMLInputElement;
              const params = new URLSearchParams();
              if (target.value) params.set('search', target.value);
              window.location.href = `/treasury-transfers${params.toString() ? `?${params.toString()}` : ''}`;
            }
          }}
          className="w-80 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
        />
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
                  <td className={`${tableClasses.td} space-x-2`}>
                    {row.status === 'draft' && (can('cash.edit') || can('banks.edit')) ? (
                      <button type="button" onClick={() => openEditModal(row)} className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer">
                        {pageDict.editTransfer}
                      </button>
                    ) : null}
                    {row.status === 'draft' && (can('cash.post') || can('banks.post')) && can('view_financials') ? (
                      <button type="button" onClick={() => handlePost(row.id)} className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer">
                        {pageDict.post}
                      </button>
                    ) : null}
                    {row.status === 'draft' && (can('cash.edit') || can('banks.edit')) ? (
                      <button type="button" onClick={() => handleCancel(row.id)} className="text-xs font-bold text-red-600 hover:underline cursor-pointer">
                        {pageDict.cancelTransfer}
                      </button>
                    ) : null}
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
                  <select
                    value={data.source_type}
                    onChange={(e) => setData({ ...data, source_type: e.target.value as 'cash' | 'bank', source_cash_account_id: '', source_bank_account_id: '' })}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                  >
                    <option value="cash">{pageDict.cash}</option>
                    <option value="bank">{pageDict.bank}</option>
                  </select>
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
                  <select
                    value={data.destination_type}
                    onChange={(e) => setData({ ...data, destination_type: e.target.value as 'cash' | 'bank', destination_cash_account_id: '', destination_bank_account_id: '' })}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                  >
                    <option value="cash">{pageDict.cash}</option>
                    <option value="bank">{pageDict.bank}</option>
                  </select>
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
                <button type="button" onClick={() => setShowModal(false)} className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer">
                  {pageDict.cancelTransfer}
                </button>
                <button type="submit" disabled={processing} className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50">
                  {processing ? pageDict.saving : pageDict.saveTransfer}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
