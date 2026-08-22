import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type SupplierPaymentRow = {
  id: string;
  number: string;
  supplier_id: string;
  supplier?: { id: string; code: string; name: string };
  cash_account_id?: string | null;
  cash_account?: { id: string; name: string };
  bank_account_id?: string | null;
  bank_account?: { id: string; name: string };
  payment_date: string;
  reference?: string | null;
  currency: string;
  amount_minor: number;
  allocated_minor: number;
  unapplied_minor: number;
  status: 'draft' | 'posted';
  posted_at?: string | null;
  created_at: string;
};

type SupplierPaymentProps = SharedPageProps & {
  payments: {
    data: SupplierPaymentRow[];
    links: any[];
  };
  suppliers: Array<{ id: string; code: string; name: string }>;
  cashAccounts: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
};

export default function SupplierPaymentsIndex({
  locale,
  payments,
  suppliers = [],
  cashAccounts = [],
  bankAccounts = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
}: SupplierPaymentProps) {
  const dict = getDictionary(locale);
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [destinationType, setDestinationType] = useState<'cash' | 'bank'>('cash');

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    supplier_id: '',
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    payment_date: new Date().toISOString().split('T')[0],
    reference: '',
    description: 'Supplier Payment',
    cash_account_id: '',
    bank_account_id: '',
    currency: 'EGP',
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
      cash_account_id: destinationType === 'cash' ? data.cash_account_id : null,
      bank_account_id: destinationType === 'bank' ? data.bank_account_id : null,
      amount_minor: minorVal,
    }));

    post('/supplier-payments', {
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const handlePost = (id: string) => {
    if (confirm(dict.app.pages.supplierPayments.areYouSureYouWantTo)) {
      post(`/supplier-payments/${id}/post`);
    }
  };

  const supplierSelectOptions = suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` }));
  const cashSelectOptions = cashAccounts.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }));
  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }));
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));
  const currencyOptions = currencies.map((c) => ({ value: c.code, label: `${c.code} (${c.name})` }));

  return (
    <AppLayout active="supplier-payments.index">
      <Head title={dict.app.pages.supplierPayments.supplierPaymentsMiniErp} />

      <PageHeader
        title={dict.app.pages.supplierPayments.supplierPayments}
        description={dict.app.pages.supplierPayments.recordAndPostSupplierCashBank}
        actions={
          can('suppliers.payments') ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.supplierPayments.newSupplierPayment}
            </button>
          ) : null
        }
      />

      {payments.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.supplierPayments.noSupplierPaymentsFound}
          description={dict.app.pages.supplierPayments.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.paymentNo}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.supplier}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.date}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.sourceAccount}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.totalAmount}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.unapplied}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.supplierPayments.actions}</th>
              </tr>
            </thead>
            <tbody>
              {payments.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.number}</td>
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.supplier ? `${row.supplier.code} - ${row.supplier.name}` : '—'}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.payment_date}</td>
                  <td className={tableClasses.td}>
                    {row.cash_account ? `خزينة: ${row.cash_account.name}` : row.bank_account ? `بنك: ${row.bank_account.name}` : '—'}
                  </td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs font-bold ${row.unapplied_minor > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
                    {formatMoney(row.unapplied_minor, row.currency)}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={row.status === 'posted' ? 'ok' : 'warning'}>
                      {row.status === 'posted' ? dict.app.pages.supplierPayments.posted : dict.app.pages.supplierPayments.draft}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    {row.status === 'draft' ? (
                      can('suppliers.payments') ? (
                        <button
                          type="button"
                          onClick={() => handlePost(row.id)}
                          className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
                        >
                          {dict.app.pages.supplierPayments.post}
                        </button>
                      ) : null
                    ) : (
                      <a
                        href={`/payable-allocations?payment_id=${row.id}`}
                        className="text-xs font-bold text-[var(--primary)] hover:underline"
                      >
                        {dict.app.pages.supplierPayments.allocate}
                      </a>
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
              {dict.app.pages.supplierPayments.createNewSupplierPayment}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.supplierPayments.supplier_2} *
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
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierPayments.sourceType}
                  </label>
                  <select
                    value={destinationType}
                    onChange={(e) => setDestinationType(e.target.value as any)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
                  >
                    <option value="cash">{dict.app.pages.supplierPayments.cashAccount}</option>
                    <option value="bank">{dict.app.pages.supplierPayments.bankAccount}</option>
                  </select>
                </div>
                <div>
                  {destinationType === 'cash' ? (
                    <div>
                      <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                        {dict.app.pages.supplierPayments.cashAccount_2} *
                      </label>
                      <SearchableSelect
                        options={cashSelectOptions}
                        value={data.cash_account_id}
                        onChange={(val) => setData('cash_account_id', val || '')}
                        isClearable={false}
                      />
                    </div>
                  ) : (
                    <div>
                      <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                        {dict.app.pages.supplierPayments.bankAccount_2} *
                      </label>
                      <SearchableSelect
                        options={bankSelectOptions}
                        value={data.bank_account_id}
                        onChange={(val) => setData('bank_account_id', val || '')}
                        isClearable={false}
                      />
                    </div>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <DatePicker
                    label={dict.app.pages.supplierPayments.paymentDate}
                    value={data.payment_date}
                    onChange={(val) => setData('payment_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierPayments.financialPeriod} *
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
                    {dict.app.pages.supplierPayments.currency} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={data.currency}
                    onChange={(val) => setData('currency', val || 'EGP')}
                    isClearable={false}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.supplierPayments.amount} *
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
                  {dict.app.pages.supplierPayments.referenceDescription}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder="REF-PAY-001"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.supplierPayments.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.supplierPayments.saving : dict.app.pages.supplierPayments.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
