import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, PaginationLink, SharedPageProps } from '../../Types';

type SupplierOBRow = {
  id: string;
  supplier_id: string;
  supplier?: { id: string; code: string; name: string };
  fiscal_year_id: string;
  financial_period_id: string;
  entry_date: string;
  reference?: string | null;
  currency: string;
  amount_minor: number;
  status: 'draft' | 'posted';
  posted_at?: string | null;
  created_at: string;
};

type SupplierOBTableRow = {
  id: string;
  supplier_id: string;
  supplier_code: string;
  supplier_name: Record<string, string> | string;
  entry_date: string;
  reference?: string | null;
  currency: string;
  amount_minor: number;
  status: 'draft' | 'posted';
};

type SupplierOBProps = SharedPageProps & {
  balances?: {
    data: SupplierOBRow[];
    links: PaginationLink[];
  };
  suppliers: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
};

export default function SupplierOpeningBalancesIndex({
  locale,
  suppliers = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
}: SupplierOBProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreateOpeningBalance = can('suppliers.opening_balances');
  const canPostOpeningBalance = can('suppliers.opening_balances') && can('view_financials');

  const [showModal, setShowModal] = useState(false);
  const [postingBalanceId, setPostingBalanceId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    supplier_id: '',
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    entry_date: new Date().toISOString().split('T')[0],
    due_date: '',
    reference: '',
    description: 'Supplier Opening Balance',
    currency: '',
    amount: '',
    amount_minor: 0,
    fx_rate_e6: 1000000,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const amountVal = parseFloat(data.amount || '0');
    const minorVal = Math.round(amountVal * 100);

    transform((data) => ({
      ...data,
      amount_minor: minorVal,
    }));

    post('/supplier-opening-balances', {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const handlePost = (id: string) => {
    setPostingBalanceId(id);
  };

  const statusOptions = useMemo(() => ([
    { value: 'draft', label: dict.app.pages.supplierOpeningBalances.draft },
    { value: 'posted', label: dict.app.pages.supplierOpeningBalances.posted },
  ]), [dict]);

  const columns = useMemo(() => [
    { data: 'supplier_name', name: 'supplier_name', title: dict.app.pages.supplierOpeningBalances.supplier },
    { data: 'entry_date', name: 'entry_date', title: dict.app.pages.supplierOpeningBalances.date, className: 'font-mono text-xs', width: '110px' },
    { data: 'reference', name: 'reference', title: dict.app.pages.supplierOpeningBalances.reference, className: 'font-mono text-xs' },
    { data: 'currency', name: 'currency', title: dict.app.pages.supplierOpeningBalances.currency, className: 'font-mono text-xs font-bold', width: '90px' },
    { data: 'amount_minor', name: 'amount_minor', title: dict.app.pages.supplierOpeningBalances.amount, searchable: false, className: 'text-end', width: '140px' },
    { data: 'status', name: 'status', title: dict.app.pages.supplierOpeningBalances.status, searchable: false, width: '100px' },
    { data: 'id', name: 'id', title: dict.app.pages.supplierOpeningBalances.actions, orderable: false, searchable: false, width: '110px', className: 'text-end' },
  ], [dict]);

  const slots = useMemo<DataTableSlots>(() => ({
    supplier_name: (data: SupplierOBTableRow['supplier_name'], _type: any, row: SupplierOBTableRow): ReactElement => (
      <div className="flex flex-col leading-tight">
        <span className="font-semibold">{getLocalizedName(data, locale)}</span>
        <span className="font-mono text-[11px] text-[var(--text-muted)]">{row?.supplier_code}</span>
      </div>
    ),
    reference: (data: string | null): ReactElement => (
      <span className="font-mono text-xs">{data || accDict.notAvailable}</span>
    ),
    amount_minor: (data: number, _type: any, row: SupplierOBTableRow): ReactElement => (
      <span className="font-mono text-xs font-bold">{formatMoney(data, row?.currency)}</span>
    ),
    status: (data: string): ReactElement => (
      <StatusBadge tone={data === 'posted' ? 'ok' : 'warning'}>
        {data === 'posted' ? dict.app.pages.supplierOpeningBalances.posted : dict.app.pages.supplierOpeningBalances.draft}
      </StatusBadge>
    ),
    id: (data: string, _type: any, row: SupplierOBTableRow): ReactElement => (
      row?.status === 'draft' ? (
        canPostOpeningBalance ? (
          <button
            type="button"
            onClick={() => handlePost(data)}
            title={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}
            aria-label={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}
            className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
          >
            {dict.app.pages.supplierOpeningBalances.post}
          </button>
        ) : (
          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
        )
      ) : (
        <span className="text-xs text-[var(--text-muted)] font-mono">{dict.app.pages.supplierOpeningBalances.immutable}</span>
      )
    ),
  } as Record<string, (data: any, type: any, row: any) => ReactElement>), [canPostOpeningBalance, dict, accDict, locale]);

  const tableFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  const toolbar = (
    <div className="flex items-center gap-2">
      <div className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-[color-mix(in_srgb,var(--primary)_8%,transparent)] text-xs font-bold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] whitespace-nowrap shrink-0">
        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        <span>{dict.common.datatable.filterStatus}</span>
      </div>
      <SearchableSelect
        options={[{ value: '', label: dict.app.pages.supplierOpeningBalances.allStatuses }, ...statusOptions]}
        value={statusFilter}
        onChange={(v) => setStatusFilter(v || '')}
        className="w-44"
        isSearchable={false}
      />
    </div>
  );

  const supplierSelectOptions = suppliers.map((s) => ({
    value: s.id,
    label: `${s.code} - ${getLocalizedName(s.name, locale)}`,
  }));

  const periodSelectOptions = periods.map((p) => ({
    value: p.id,
    label: p.name,
  }));

  const currencyOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} (${getLocalizedName(c.name, locale)})`,
  }));

  return (
    <AppLayout active="supplier-opening-balances.index">
      <Head title={dict.app.pages.supplierOpeningBalances.supplierOpeningBalancesMiniErp} />

      <PageHeader
        title={dict.app.pages.supplierOpeningBalances.supplierOpeningBalances}
        description={dict.app.pages.supplierOpeningBalances.recordAndPostOpeningAccountsPayable}
        actions={
          canCreateOpeningBalance ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              title={dict.app.pages.supplierOpeningBalances.newOpeningBalance}
              aria-label={dict.app.pages.supplierOpeningBalances.newOpeningBalance}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.supplierOpeningBalances.newOpeningBalance}
            </button>
          ) : null
        }
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/supplier-opening-balances/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[1, 'desc']]}
          pageLength={25}
          slots={slots}
          tableId="supplier-opening-balances-table"
          toolbar={toolbar}
        />
      </Card>

      {/* Modal Form */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {dict.app.pages.supplierOpeningBalances.newSupplierOpeningBalance}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.supplierOpeningBalances.supplier} *
                </label>
                <SearchableSelect
                  options={supplierSelectOptions}
                  value={data.supplier_id}
                  onChange={(val) => {
                    setData('supplier_id', val || '');
                  }}
                  required
                />
                {errors.supplier_id && <p className="text-xs text-red-500 mt-1">{errors.supplier_id}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {(dict.app.pages.customerOpeningBalances as any).fiscalYear || 'Fiscal Year'} *
                  </label>
                  <SearchableSelect
                    options={fiscalYears.map((fy) => ({ value: fy.id, label: `${fy.year} - ${fy.name}` }))}
                    value={data.fiscal_year_id}
                    onChange={(val) => setData('fiscal_year_id', val || '')}
                    isSearchable={false}
                    required
                  />
                  {errors.fiscal_year_id && <p className="text-xs text-red-500 mt-1">{errors.fiscal_year_id}</p>}
                </div>

                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierOpeningBalances.financialPeriod} *
                  </label>
                  <SearchableSelect
                    options={periodSelectOptions}
                    value={data.financial_period_id}
                    onChange={(val) => setData('financial_period_id', val || '')}
                    isSearchable={false}
                    required
                  />
                  {errors.financial_period_id && <p className="text-xs text-red-500 mt-1">{errors.financial_period_id}</p>}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierOpeningBalances.entryDate} *
                  </label>
                  <DatePicker
                    value={data.entry_date || ''}
                    onChange={(dateStr) => setData('entry_date', dateStr || '')}
                    required
                  />
                  {errors.entry_date && <p className="text-xs text-red-500 mt-1">{errors.entry_date}</p>}
                </div>

                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {(dict.app.pages.customerOpeningBalances as any).dueDate || 'Due Date'}
                  </label>
                  <DatePicker
                    value={data.due_date || ''}
                    onChange={(dateStr) => setData('due_date', dateStr || '')}
                  />
                  {errors.due_date && <p className="text-xs text-red-500 mt-1">{errors.due_date}</p>}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierOpeningBalances.currency} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={data.currency}
                    onChange={(val) => setData('currency', val || '')}
                    required
                  />
                  {errors.currency && <p className="text-xs text-red-500 mt-1">{errors.currency}</p>}
                </div>

                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierOpeningBalances.amount} *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    placeholder="0.00"
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                    required
                  />
                  {errors.amount_minor && <p className="text-xs text-red-500 mt-1">{errors.amount_minor}</p>}
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.supplierOpeningBalances.reference}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
                {errors.reference && <p className="text-xs text-red-500 mt-1">{errors.reference}</p>}
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {(dict.app.pages.customerOpeningBalances as any).description || 'Description'}
                </label>
                <textarea
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  rows={2}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs text-[var(--text-primary)]"
                />
                {errors.description && <p className="text-xs text-red-500 mt-1">{errors.description}</p>}
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  title={dict.app.pages.supplierOpeningBalances.cancel}
                  aria-label={dict.app.pages.supplierOpeningBalances.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.supplierOpeningBalances.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={dict.app.pages.supplierOpeningBalances.saveDraft}
                  aria-label={dict.app.pages.supplierOpeningBalances.saveDraft}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.supplierOpeningBalances.saving : dict.app.pages.supplierOpeningBalances.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      <SensitiveActionModal
        isOpen={Boolean(postingBalanceId)}
        onClose={() => setPostingBalanceId(null)}
        onConfirm={(payload) => {
          if (!postingBalanceId) return;
          router.post(`/supplier-opening-balances/${postingBalanceId}/post`, payload, {
            preserveScroll: true,
            onFinish: () => setPostingBalanceId(null),
          });
        }}
        confirmCode="POST_SUPPLIER_OPENING_BALANCE"
        message={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}
      />
    </AppLayout>
  );
}
