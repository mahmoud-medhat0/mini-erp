import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, PaginationLink, SharedPageProps } from '../../Types';

type CustomerReceiptRow = {
  id: string;
  number: string;
  customer_id: string;
  customer?: { id: string; code: string; name: string };
  cash_account_id?: string | null;
  cash_account?: { id: string; name: string };
  bank_account_id?: string | null;
  bank_account?: { id: string; name: string };
  receipt_date: string;
  reference?: string | null;
  currency: string;
  amount_minor: number;
  allocated_minor: number;
  unapplied_minor: number;
  status: 'draft' | 'posted';
  posted_at?: string | null;
  created_at: string;
};

type CashBankDestinationType = 'cash' | 'bank';

type CustomerReceiptProps = SharedPageProps & {
  receipts: {
    data: CustomerReceiptRow[];
    links: PaginationLink[];
  };
  customers: Array<{ id: string; code: string; name: string }>;
  cashAccounts: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
};

function toCashBankDestinationType(value: string): CashBankDestinationType {
  return value === 'bank' ? 'bank' : 'cash';
}

export default function CustomerReceiptsIndex({
  locale,
  receipts,
  customers = [],
  cashAccounts = [],
  bankAccounts = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
}: CustomerReceiptProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreateReceipt = can('customers.receipts');
  const canPostReceipt = can('customers.receipts') && can('view_financials');

  const [showModal, setShowModal] = useState(false);
  const [destinationType, setDestinationType] = useState<CashBankDestinationType>('cash');

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    customer_id: '',
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    receipt_date: new Date().toISOString().split('T')[0],
    reference: '',
    description: 'Customer Receipt',
    cash_account_id: '',
    bank_account_id: '',
    currency: currencies[0]?.code || '',
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

    post('/customer-receipts', {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const handlePost = (id: string) => {
    if (confirm(dict.app.pages.customerReceipts.confirmPostReceipt)) {
      router.post(`/customer-receipts/${id}/post`, { confirm_action: 'POST_CUSTOMER_RECEIPT' }, { preserveScroll: true });
    }
  };

  const customerSelectOptions = customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }));
  const cashSelectOptions = cashAccounts.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }));
  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }));
  const destinationTypeOptions = [
    { value: 'cash', label: dict.app.pages.customerReceipts.cashAccount },
    { value: 'bank', label: dict.app.pages.customerReceipts.bankAccount },
  ];
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));
  const currencyOptions = currencies.map((c) => ({ value: c.code, label: `${c.code} (${c.name})` }));

  return (
    <AppLayout active="customer-receipts.index">
      <Head title={dict.app.pages.customerReceipts.customerReceiptsMiniErp} />

      <PageHeader
        title={dict.app.pages.customerReceipts.customerReceipts}
        description={dict.app.pages.customerReceipts.recordAndPostCustomerCashBank}
        actions={
          canCreateReceipt ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              title={dict.app.pages.customerReceipts.newCustomerReceipt}
              aria-label={dict.app.pages.customerReceipts.newCustomerReceipt}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.customerReceipts.newCustomerReceipt}
            </button>
          ) : null
        }
      />

      {receipts.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.customerReceipts.noCustomerReceiptsFound}
          description={dict.app.pages.customerReceipts.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.receiptNo}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.customer}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.date}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.destination}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.totalAmount}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.unapplied}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.customerReceipts.actions}</th>
              </tr>
            </thead>
            <tbody>
              {receipts.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.number}</td>
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.customer ? `${row.customer.code} - ${row.customer.name}` : accDict.notAvailable}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.receipt_date}</td>
                  <td className={tableClasses.td}>
                    {row.cash_account
                      ? `${dict.app.pages.customerReceipts.cashAccount}: ${row.cash_account.name}`
                      : row.bank_account
                      ? `${dict.app.pages.customerReceipts.bankAccount}: ${row.bank_account.name}`
                      : accDict.notAvailable}
                  </td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs font-bold ${row.unapplied_minor > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
                    {formatMoney(row.unapplied_minor, row.currency)}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={row.status === 'posted' ? 'ok' : 'warning'}>
                      {row.status === 'posted' ? dict.app.pages.customerReceipts.posted : dict.app.pages.customerReceipts.draft}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center gap-2">
                      {row.status === 'draft' ? (
                        canPostReceipt ? (
                          <button
                            type="button"
                            onClick={() => handlePost(row.id)}
                            title={dict.app.pages.customerReceipts.confirmPostReceipt}
                            aria-label={dict.app.pages.customerReceipts.confirmPostReceipt}
                            className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
                          >
                            {dict.app.pages.customerReceipts.post}
                          </button>
                        ) : (
                          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
                        )
                      ) : (
                        <Link
                          href={`/receivable-allocations?receipt_id=${row.id}`}
                          title={dict.app.pages.customerReceipts.allocate}
                          aria-label={dict.app.pages.customerReceipts.allocate}
                          className="text-xs font-bold text-[var(--primary)] hover:underline"
                        >
                          {dict.app.pages.customerReceipts.allocate}
                        </Link>
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
              {dict.app.pages.customerReceipts.createNewCustomerReceipt}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.customerReceipts.customer_2} *
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
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.customerReceipts.destinationType}
                  </label>
                  <SearchableSelect
                    options={destinationTypeOptions}
                    value={destinationType}
                    onChange={(val) => setDestinationType(toCashBankDestinationType(val || 'cash'))}
                    isClearable={false}
                  />
                </div>
                <div>
                  {destinationType === 'cash' ? (
                    <div>
                      <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                        {dict.app.pages.customerReceipts.cashAccount_2} *
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
                        {dict.app.pages.customerReceipts.bankAccount_2} *
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
                    label={dict.app.pages.customerReceipts.receiptDate}
                    value={data.receipt_date}
                    onChange={(val) => setData('receipt_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.customerReceipts.financialPeriod} *
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
                    {dict.app.pages.customerReceipts.currency} *
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
                    {dict.app.pages.customerReceipts.amount} *
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
                  {dict.app.pages.customerReceipts.referenceDescription}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder="REF-REC-001"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  title={dict.app.pages.customerReceipts.cancel}
                  aria-label={dict.app.pages.customerReceipts.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.customerReceipts.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={dict.app.pages.customerReceipts.saveDraft}
                  aria-label={dict.app.pages.customerReceipts.saveDraft}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.customerReceipts.saving : dict.app.pages.customerReceipts.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
