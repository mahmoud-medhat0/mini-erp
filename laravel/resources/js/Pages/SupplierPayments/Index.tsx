import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type CashBankDestinationType = 'cash' | 'bank';

type SupplierPaymentProps = SharedPageProps & {
  suppliers: Array<{ id: string; code: string; name: string }>;
  cashAccounts: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
};

function toCashBankDestinationType(value: string): CashBankDestinationType {
  return value === 'bank' ? 'bank' : 'cash';
}

export default function SupplierPaymentsIndex({
  locale,
  suppliers = [],
  cashAccounts = [],
  bankAccounts = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
}: SupplierPaymentProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreatePayment = can('suppliers.payments');
  const canPostPayment = can('suppliers.payments') && can('view_financials');

  const [showModal, setShowModal] = useState(false);
  const [destinationType, setDestinationType] = useState<CashBankDestinationType>('cash');
  const [postingPaymentId, setPostingPaymentId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    supplier_id: '',
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    payment_date: new Date().toISOString().split('T')[0],
    reference: '',
    description: 'Supplier Payment',
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

    post('/supplier-payments', {
      preserveScroll: true,
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const handlePost = (id: string) => {
    setPostingPaymentId(id);
  };

  const supplierSelectOptions = suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${getLocalizedName(s.name, locale)}` }));
  const cashSelectOptions = cashAccounts.map((c) => ({ value: c.id, label: `${c.code} - ${getLocalizedName(c.name, locale)}` }));
  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${getLocalizedName(b.name, locale)}` }));
  const sourceTypeOptions = [
    { value: 'cash', label: dict.app.pages.supplierPayments.cashAccount },
    { value: 'bank', label: dict.app.pages.supplierPayments.bankAccount },
  ];
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));
  const currencyOptions = currencies.map((c) => ({ value: c.code, label: `${c.code} (${getLocalizedName(c.name, locale)})` }));

  // ── DataTables columns ────────────────────────────────────────────────────
  const columns = useMemo(() => [
    { data: 'number', name: 'number', title: dict.app.pages.supplierPayments.paymentNo, className: 'font-mono font-bold text-xs', width: '130px' },
    { data: 'supplier_name', name: 'supplier_name', title: dict.app.pages.supplierPayments.supplier },
    { data: 'payment_date', name: 'payment_date', title: dict.app.pages.supplierPayments.date, width: '110px' },
    { data: 'source', name: 'source', title: dict.app.pages.supplierPayments.sourceAccount, orderable: false, searchable: false },
    { data: 'amount_minor', name: 'amount_minor', title: dict.app.pages.supplierPayments.totalAmount, width: '120px' },
    { data: 'unapplied_minor', name: 'unapplied_minor', title: dict.app.pages.supplierPayments.unapplied, width: '120px' },
    { data: 'status', name: 'status', title: dict.app.pages.supplierPayments.status, searchable: false, width: '100px' },
    { data: 'actions', name: 'actions', title: dict.app.pages.supplierPayments.actions, orderable: false, searchable: false, width: '90px', className: 'text-end' },
  ], [dict]);

  // ── DataTables slots ──────────────────────────────────────────────────────
  const slots = useMemo<DataTableSlots>(() => ({
    supplier_name: (d: any, _type: any, row: any) => (
      <span className="font-semibold">
        {row?.supplier_code ? `${row.supplier_code} - ${getLocalizedName(d, locale)}` : getLocalizedName(d, locale) || accDict.notAvailable}
      </span>
    ),
    payment_date: (d: any) => <span className="font-mono text-xs">{d}</span>,
    source: (_d: any, _type: any, row: any) => (
      <span>
        {row?.cash_account_name
          ? `${dict.app.pages.supplierPayments.cashAccount}: ${getLocalizedName(row.cash_account_name, locale)}`
          : row?.bank_account_name
          ? `${dict.app.pages.supplierPayments.bankAccount}: ${getLocalizedName(row.bank_account_name, locale)}`
          : accDict.notAvailable}
      </span>
    ),
    amount_minor: (d: any, _type: any, row: any) => <span className="font-mono font-bold text-xs">{formatMoney(d, row?.currency)}</span>,
    unapplied_minor: (d: any, _type: any, row: any) => (
      <span className={`font-mono text-xs font-bold ${Number(d) > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
        {formatMoney(d, row?.currency)}
      </span>
    ),
    status: (d: any) => (
      <StatusBadge tone={d === 'posted' ? 'ok' : 'warning'}>
        {d === 'posted' ? dict.app.pages.supplierPayments.posted : dict.app.pages.supplierPayments.draft}
      </StatusBadge>
    ),
    actions: (_d: any, _type: any, row: any) => (
      <div className="flex items-center justify-end gap-2">
        {row?.status === 'draft' ? (
          canPostPayment ? (
            <button
              type="button"
              onClick={() => handlePost(row?.id)}
              title={dict.app.pages.supplierPayments.confirmPostPayment}
              aria-label={dict.app.pages.supplierPayments.confirmPostPayment}
              className="text-xs font-bold text-emerald-600 hover:underline cursor-pointer"
            >
              {dict.app.pages.supplierPayments.post}
            </button>
          ) : (
            <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
          )
        ) : (
          <Link
            href={`/payable-allocations?payment_id=${row.id}`}
            title={dict.app.pages.supplierPayments.allocate}
            aria-label={dict.app.pages.supplierPayments.allocate}
            className="text-xs font-bold text-[var(--primary)] hover:underline"
          >
            {dict.app.pages.supplierPayments.allocate}
          </Link>
        )}
      </div>
    ),
  } as Record<string, (data: any, type: any, row: any) => ReactElement>), [dict, accDict, locale, canPostPayment]);

  const tableFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  const statusOptions = [
    { value: 'draft', label: dict.app.pages.supplierPayments.draft },
    { value: 'posted', label: dict.app.pages.supplierPayments.posted },
  ];

  const toolbar = (
    <div className="flex items-center gap-2">
      <div className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-[color-mix(in_srgb,var(--primary)_8%,transparent)] text-xs font-bold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] whitespace-nowrap shrink-0">
        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        <span>{dict.common.datatable.filterStatus}</span>
      </div>
      <SearchableSelect
        options={[{ value: '', label: locale === 'ar' ? 'جميع الحالات' : 'All Statuses' }, ...statusOptions]}
        value={statusFilter}
        onChange={(v) => setStatusFilter(v || '')}
        className="w-44"
        isSearchable={false}
      />
    </div>
  );

  return (
    <AppLayout active="supplier-payments.index">
      <Head title={dict.app.pages.supplierPayments.supplierPaymentsMiniErp} />

      <PageHeader
        title={dict.app.pages.supplierPayments.supplierPayments}
        description={dict.app.pages.supplierPayments.recordAndPostSupplierCashBank}
        actions={
          canCreatePayment ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              title={dict.app.pages.supplierPayments.newSupplierPayment}
              aria-label={dict.app.pages.supplierPayments.newSupplierPayment}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.supplierPayments.newSupplierPayment}
            </button>
          ) : null
        }
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/supplier-payments/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'desc']]}
          pageLength={25}
          slots={slots}
          tableId="supplier-payments-data-table"
          toolbar={toolbar}
        />
      </Card>

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
                  <SearchableSelect
                    options={sourceTypeOptions}
                    value={destinationType}
                    onChange={(val) => setDestinationType(toCashBankDestinationType(val || 'cash'))}
                    isClearable={false}
                  />
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
                    onChange={(val) => setData('currency', val || '')}
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
                  title={dict.app.pages.supplierPayments.cancel}
                  aria-label={dict.app.pages.supplierPayments.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.supplierPayments.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={dict.app.pages.supplierPayments.saveDraft}
                  aria-label={dict.app.pages.supplierPayments.saveDraft}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.supplierPayments.saving : dict.app.pages.supplierPayments.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      <SensitiveActionModal
        isOpen={postingPaymentId !== null}
        onClose={() => setPostingPaymentId(null)}
        onConfirm={(payload) => {
          if (!postingPaymentId) return;
          router.post(`/supplier-payments/${postingPaymentId}/post`, payload, {
            preserveScroll: true,
            onSuccess: () => setPostingPaymentId(null),
          });
        }}
        confirmCode="POST_SUPPLIER_PAYMENT"
        message={dict.app.pages.supplierPayments.confirmPostPayment}
        locale={locale}
      />
    </AppLayout>
  );
}
