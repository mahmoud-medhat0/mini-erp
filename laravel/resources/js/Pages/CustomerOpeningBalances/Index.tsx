import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
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
  balances,
  customers = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
}: CustomerOBProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);

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
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const handlePost = (id: string) => {
    if (confirm(dict.app.pages.customerOpeningBalances.confirmPostOpeningBalance)) {
      post(`/customer-opening-balances/${id}/post`);
    }
  };

  const customerSelectOptions = customers.map((c) => ({
    value: c.id,
    label: `${c.code} - ${c.name}`,
  }));

  const periodSelectOptions = periods.map((p) => ({
    value: p.id,
    label: p.name,
  }));

  const currencyOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} (${c.name})`,
  }));

  return (
    <AppLayout active="customer-opening-balances.index">
      <Head title={dict.app.pages.customerOpeningBalances.customerOpeningBalancesMiniErp} />

      <PageHeader
        title={dict.app.pages.customerOpeningBalances.customerOpeningBalances}
        description={dict.app.pages.customerOpeningBalances.recordAndPostOpeningAccountsReceivable}
        actions={
          can('customers.opening_balances') ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.customerOpeningBalances.newOpeningBalance}
            </button>
          ) : null
        }
      />

      {balances.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.customerOpeningBalances.noOpeningBalancesFound}
          description={dict.app.pages.customerOpeningBalances.getStartedByRecordingCustomerOpening}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.customer}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.date}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.reference}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.currency}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.amount}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerOpeningBalances.actions}</th>
              </tr>
            </thead>
            <tbody>
              {balances.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.customer ? `${row.customer.code} - ${row.customer.name}` : accDict.notAvailable}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.entry_date}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.reference || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{row.currency}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={row.status === 'posted' ? 'ok' : 'warning'}>
                      {row.status === 'posted' ? dict.app.pages.customerOpeningBalances.posted : dict.app.pages.customerOpeningBalances.draft}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    {row.status === 'draft' ? (
                      can('customers.opening_balances') && can('view_financials') ? (
                        <button
                          type="button"
                          onClick={() => handlePost(row.id)}
                          className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
                        >
                          {dict.app.pages.customerOpeningBalances.post}
                        </button>
                      ) : null
                    ) : (
                      <span className="text-xs text-[var(--text-muted)] font-mono">{dict.app.pages.customerOpeningBalances.immutable}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

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
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.customerOpeningBalances.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.customerOpeningBalances.saving : dict.app.pages.customerOpeningBalances.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
