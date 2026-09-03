import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
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

type SupplierOBProps = SharedPageProps & {
  balances: {
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
  balances,
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

      {balances.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.supplierOpeningBalances.noOpeningBalancesFound}
          description={dict.app.pages.supplierOpeningBalances.getStartedByRecordingSupplierOpening}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.supplier}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.date}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.reference}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.currency}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.amount}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierOpeningBalances.actions}</th>
              </tr>
            </thead>
            <tbody>
              {balances.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.supplier ? `${row.supplier.code} - ${getLocalizedName(row.supplier.name, locale)}` : accDict.notAvailable}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.entry_date}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.reference || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{row.currency}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={row.status === 'posted' ? 'ok' : 'warning'}>
                      {row.status === 'posted' ? dict.app.pages.supplierOpeningBalances.posted : dict.app.pages.supplierOpeningBalances.draft}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center gap-2">
                      {row.status === 'draft' ? (
                        canPostOpeningBalance ? (
                          <button
                            type="button"
                            onClick={() => handlePost(row.id)}
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
                      )}
                    </div>
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
              {dict.app.pages.supplierOpeningBalances.newSupplierOpeningBalance}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.supplierOpeningBalances.supplier_2} *
                </label>
                <SearchableSelect
                  options={supplierSelectOptions}
                  value={data.supplier_id}
                  onChange={(val) => setData('supplier_id', val || '')}
                  isClearable={false}
                />
                {errors.supplier_id && <p className="text-xs text-red-500 mt-1">{errors.supplier_id}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <DatePicker
                    label={dict.app.pages.supplierOpeningBalances.entryDate}
                    value={data.entry_date}
                    onChange={(val) => setData('entry_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierOpeningBalances.financialPeriod} *
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
                    {dict.app.pages.supplierOpeningBalances.currency_2} *
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
                    {dict.app.pages.supplierOpeningBalances.amount_2} *
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
                  {dict.app.pages.supplierOpeningBalances.reference_2}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder="OB-SUPP-001"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
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
        isOpen={postingBalanceId !== null}
        onClose={() => setPostingBalanceId(null)}
        onConfirm={(payload) => {
          if (!postingBalanceId) return;
          router.post(`/supplier-opening-balances/${postingBalanceId}/post`, payload, {
            preserveScroll: true,
            onSuccess: () => setPostingBalanceId(null),
          });
        }}
        confirmCode="POST_SUPPLIER_OPENING_BALANCE"
        message={dict.app.pages.supplierOpeningBalances.confirmPostOpeningBalance}
        locale={locale}
      />
    </AppLayout>
  );
}
