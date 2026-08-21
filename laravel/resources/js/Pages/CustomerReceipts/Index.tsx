import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { CurrencyOption, SharedPageProps } from '../../Types';

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

type CustomerReceiptProps = SharedPageProps & {
  receipts: {
    data: CustomerReceiptRow[];
    links: any[];
  };
  customers: Array<{ id: string; code: string; name: string }>;
  cashAccounts: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
};

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
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showModal, setShowModal] = useState(false);
  const [destinationType, setDestinationType] = useState<'cash' | 'bank'>('cash');

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    customer_id: '',
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    receipt_date: new Date().toISOString().split('T')[0],
    reference: '',
    description: 'Customer Receipt',
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

    post('/customer-receipts', {
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const handlePost = (id: string) => {
    if (confirm(isAr ? 'هل أنت تأكد من ترحيل سند القبض؟' : 'Are you sure you want to post this customer receipt?')) {
      post(`/customer-receipts/${id}/post`);
    }
  };

  const customerSelectOptions = customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }));
  const cashSelectOptions = cashAccounts.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }));
  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }));
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));
  const currencyOptions = currencies.map((c) => ({ value: c.code, label: `${c.code} (${c.name})` }));

  return (
    <AppLayout active="customer-receipts.index">
      <Head title={isAr ? 'سندات القبض - Mini ERP' : 'Customer Receipts - Mini ERP'} />

      <PageHeader
        title={isAr ? 'سندات القبض من العملاء' : 'Customer Receipts'}
        description={isAr ? 'إنشاء وتتبع سندات القبض النقدية والبنكية من العملاء وترحيلها لخصم المستحقات.' : 'Record and post customer cash/bank receipts.'}
        actions={
          <button
            type="button"
            onClick={() => {
              reset();
              setShowModal(true);
            }}
            className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
          >
            {isAr ? '+ سند قبض جديد' : '+ New Customer Receipt'}
          </button>
        }
      />

      {receipts.data.length === 0 ? (
        <EmptyState
          title={isAr ? 'لا يوجد سندات قبض' : 'No Customer Receipts Found'}
          description={isAr ? 'قم بإضافة اول سند قبض بالضغط على الزر اعلاه.' : 'Get started by creating your first customer receipt.'}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{isAr ? 'رقم السند' : 'Receipt No.'}</th>
                <th className={tableClasses.th}>{isAr ? 'العميل' : 'Customer'}</th>
                <th className={tableClasses.th}>{isAr ? 'التاريخ' : 'Date'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحساب المستلم' : 'Destination'}</th>
                <th className={tableClasses.th}>{isAr ? 'المبلغ الإجمالي' : 'Total Amount'}</th>
                <th className={tableClasses.th}>{isAr ? 'غير مسوى (متبقي)' : 'Unapplied'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {receipts.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.number}</td>
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.customer ? `${row.customer.code} - ${row.customer.name}` : '—'}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.receipt_date}</td>
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
                      {row.status === 'posted' ? (isAr ? 'رحل' : 'Posted') : (isAr ? 'مسودة' : 'Draft')}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    {row.status === 'draft' ? (
                      <button
                        type="button"
                        onClick={() => handlePost(row.id)}
                        className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
                      >
                        {isAr ? 'ترحيل' : 'Post'}
                      </button>
                    ) : (
                      <a
                        href={`/receivable-allocations?receipt_id=${row.id}`}
                        className="text-xs font-bold text-[var(--primary)] hover:underline"
                      >
                        {isAr ? 'تسوية' : 'Allocate'}
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
              {isAr ? 'إنشاء سند قبض جديد' : 'Create New Customer Receipt'}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'اختر العميل' : 'Customer'} *
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
                    {isAr ? 'جهة الاستلام' : 'Destination Type'}
                  </label>
                  <select
                    value={destinationType}
                    onChange={(e) => setDestinationType(e.target.value as any)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
                  >
                    <option value="cash">{isAr ? 'خزينة نقدية' : 'Cash Account'}</option>
                    <option value="bank">{isAr ? 'حساب بنكي' : 'Bank Account'}</option>
                  </select>
                </div>
                <div>
                  {destinationType === 'cash' ? (
                    <div>
                      <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                        {isAr ? 'اختر الخزينة' : 'Cash Account'} *
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
                        {isAr ? 'اختر الحساب البنكي' : 'Bank Account'} *
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
                    label={isAr ? 'تاريخ السند' : 'Receipt Date'}
                    value={data.receipt_date}
                    onChange={(val) => setData('receipt_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'الفترة المالية' : 'Financial Period'} *
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
                    {isAr ? 'العملة' : 'Currency'} *
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
                    {isAr ? 'المبلغ' : 'Amount'} *
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
                  {isAr ? 'المرجع المستندي / البيان' : 'Reference / Description'}
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
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? (isAr ? 'جاري الحفظ...' : 'Saving...') : (isAr ? 'حفظ مسودة' : 'Save Draft')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
