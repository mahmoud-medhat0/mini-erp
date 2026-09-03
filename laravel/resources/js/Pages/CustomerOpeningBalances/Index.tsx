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

type CustomerOBRow = {
  id: string;
  customer_id: string;
  customer?: { id: string; code: string; name: string };
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

/** Row shape served by /customer-opening-balances/data (flat, joined customer). */
type CustomerOBTableRow = {
  id: string;
  customer_id: string;
  customer_code: string;
  customer_name: Record<string, string> | string;
  entry_date: string;
  reference?: string | null;
  currency: string;
  amount_minor: number;
  status: 'draft' | 'posted';
};

type CustomerOBProps = SharedPageProps & {
  balances: {
    data: CustomerOBRow[];
    links: PaginationLink[];
  };
  customers: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
};

export default function CustomerOpeningBalancesIndex({
  locale,
  customers = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
}: CustomerOBProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreateOpeningBalance = can('customers.opening_balances');
  const canPostOpeningBalance = can('customers.opening_balances') && can('view_financials');

  const [showModal, setShowModal] = useState(false);
  const [postingBalanceId, setPostingBalanceId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    customer_id: '',
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    entry_date: new Date().toISOString().split('T')[0],
    due_date: '',
    reference: '',
    description: 'Customer Opening Balance',
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

    post('/customer-opening-balances', {
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

  // ── grid configuration ────────────────────────────────────────────────────
  const statusOptions = useMemo(() => ([
    { value: 'draft', label: dict.app.pages.customerOpeningBalances.draft },
    { value: 'posted', label: dict.app.pages.customerOpeningBalances.posted },
  ]), [dict]);

  // `name` is what slots target (datatables.net-react maps them via `<name>:name`),
  // so it must equal the slot key. Ambiguous joined columns are disambiguated
  // server-side with orderColumn overrides rather than a qualified name here.
  const columns = useMemo(() => [
    { data: 'customer_name', name: 'customer_name', title: dict.app.pages.customerOpeningBalances.customer },
    { data: 'entry_date', name: 'entry_date', title: dict.app.pages.customerOpeningBalances.date, className: 'font-mono text-xs', width: '110px' },
    { data: 'reference', name: 'reference', title: dict.app.pages.customerOpeningBalances.reference, className: 'font-mono text-xs' },
    { data: 'currency', name: 'currency', title: dict.app.pages.customerOpeningBalances.currency, className: 'font-mono text-xs font-bold', width: '90px' },
    { data: 'amount_minor', name: 'amount_minor', title: dict.app.pages.customerOpeningBalances.amount, searchable: false, className: 'text-end', width: '140px' },
    { data: 'status', name: 'status', title: dict.app.pages.customerOpeningBalances.status, searchable: false, width: '100px' },
    { data: 'id', name: 'id', title: dict.app.pages.customerOpeningBalances.actions, orderable: false, searchable: false, width: '110px', className: 'text-end' },
  ], [dict]);

  const slots = useMemo<DataTableSlots>(() => ({
    // The feed sends the raw translations object, so the active locale wins here.
    customer_name: (data: CustomerOBTableRow['customer_name'], _type: any, row: CustomerOBTableRow): ReactElement => (
      <div className="flex flex-col leading-tight">
        <span className="font-semibold">{getLocalizedName(data, locale)}</span>
        <span className="font-mono text-[11px] text-[var(--text-muted)]">{row?.customer_code}</span>
      </div>
    ),
    reference: (data: string | null): ReactElement => (
      <span className="font-mono text-xs">{data || accDict.notAvailable}</span>
    ),
    amount_minor: (data: number, _type: any, row: CustomerOBTableRow): ReactElement => (
      <span className="font-mono text-xs font-bold">{formatMoney(data, row?.currency)}</span>
    ),
    status: (data: string): ReactElement => (
      <StatusBadge tone={data === 'posted' ? 'ok' : 'warning'}>
        {data === 'posted' ? dict.app.pages.customerOpeningBalances.posted : dict.app.pages.customerOpeningBalances.draft}
      </StatusBadge>
    ),
    id: (data: string, _type: any, row: CustomerOBTableRow): ReactElement => (
      row?.status === 'draft' ? (
        canPostOpeningBalance ? (
          <button
            type="button"
            onClick={() => handlePost(data)}
            title={dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance}
            aria-label={dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance}
            className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
          >
            {dict.app.pages.customerOpeningBalances.post}
          </button>
        ) : (
          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
        )
      ) : (
        <span className="text-xs text-[var(--text-muted)] font-mono">{dict.app.pages.customerOpeningBalances.immutable}</span>
      )
    ),
  } as unknown as DataTableSlots), [dict, accDict, locale, canPostOpeningBalance]);

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
        options={[{ value: '', label: accDict.allStatuses }, ...statusOptions]}
        value={statusFilter}
        onChange={(v) => setStatusFilter(v || '')}
        className="w-44"
        isSearchable={false}
      />
    </div>
  );

  const customerSelectOptions = customers.map((c) => ({
    value: c.id,
    label: `${c.code} - ${getLocalizedName(c.name, locale)}`,
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
    <AppLayout active="customer-opening-balances.index">
      <Head title={dict.app.pages.customerOpeningBalances.customerOpeningBalancesMiniErp} />

      <PageHeader
        title={dict.app.pages.customerOpeningBalances.customerOpeningBalances}
        description={dict.app.pages.customerOpeningBalances.recordAndPostOpeningAccountsReceivable}
        actions={
          canCreateOpeningBalance ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              title={dict.app.pages.customerOpeningBalances.newOpeningBalance}
              aria-label={dict.app.pages.customerOpeningBalances.newOpeningBalance}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.customerOpeningBalances.newOpeningBalance}
            </button>
          ) : null
        }
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/customer-opening-balances/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[1, 'desc']]}
          pageLength={25}
          slots={slots}
          tableId="customer-opening-balances-table"
          toolbar={toolbar}
        />
      </Card>

      {/* Modal Form */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {dict.app.pages.customerOpeningBalances.newCustomerOpeningBalance}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.customerOpeningBalances.customer_2} *
                </label>
                <SearchableSelect
                  options={customerSelectOptions}
                  value={data.customer_id}
                  onChange={(val) => setData('customer_id', val || '')}
                  isClearable={false}
                />
                {errors.customer_id && <p className="text-xs text-red-500 mt-1">{errors.customer_id}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <DatePicker
                    label={dict.app.pages.customerOpeningBalances.entryDate}
                    value={data.entry_date}
                    onChange={(val) => setData('entry_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.customerOpeningBalances.financialPeriod} *
                  </label>
                  <SearchableSelect
                    options={periodSelectOptions}
                    value={data.financial_period_id}
                    onChange={(val) => setData('financial_period_id', val || '')}
                    isClearable={false}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.customerOpeningBalances.currency_2} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={data.currency}
                    onChange={(val) => setData('currency', val || '')}
                    isClearable={false}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.customerOpeningBalances.amount_2} *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                    placeholder="0.00"
                    required
                  />
                  {errors.amount_minor && <p className="text-xs text-red-500 mt-1">{errors.amount_minor}</p>}
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.customerOpeningBalances.reference_2}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder="OB-CUST-001"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  title={dict.app.pages.customerOpeningBalances.cancel}
                  aria-label={dict.app.pages.customerOpeningBalances.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.customerOpeningBalances.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={dict.app.pages.customerOpeningBalances.saveDraft}
                  aria-label={dict.app.pages.customerOpeningBalances.saveDraft}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.customerOpeningBalances.saving : dict.app.pages.customerOpeningBalances.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      <SensitiveActionModal
        isOpen={postingBalanceId !== null}
        onClose={() => setPostingBalanceId(null)}
        onConfirm={(payload) => {
          if (!postingBalanceId) return;
          router.post(`/customer-opening-balances/${postingBalanceId}/post`, payload, {
            preserveScroll: true,
            onSuccess: () => setPostingBalanceId(null),
          });
        }}
        confirmCode="POST_CUSTOMER_OPENING_BALANCE"
        message={dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance}
        locale={locale}
      />
    </AppLayout>
  );
}
