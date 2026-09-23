import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type PartnerOption = { id: string; code: string; name: TranslatedName };
type CashAccountOption = { id: string; code: string; currency: string };
type BankAccountOption = { id: string; code: string; currency: string };
type CurrencyOption = { code: string; name: TranslatedName; symbol: string };

type PartnerLoan = {
  id: string;
  currency: string;
  principal_minor: number;
  remaining_balance_minor: number;
  disbursement_date: string;
  status: string;
  partner?: PartnerOption | null;
};

type Props = SharedPageProps & {
  partners: PartnerOption[];
  cashAccounts: CashAccountOption[];
  bankAccounts: BankAccountOption[];
  currencies: CurrencyOption[];
  settlementMethods: string[];
  filters: { status?: string; partner_id?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'active') return 'info';
  if (value === 'completed') return 'ok';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

export default function PartnerLoans({ locale, partners, cashAccounts, bankAccounts, currencies, settlementMethods, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.partnerLoans;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [repayTarget, setRepayTarget] = useState<PartnerLoan | null>(null);
  const [tableReloadToken, setTableReloadToken] = useState(0);

  const form = useForm({
    partner_id: partners[0]?.id || '',
    currency: currencies[0]?.code || '',
    principal_amount: '',
    disbursement_date: todayStr,
    disbursement_method: 'cash',
    cash_account_id: cashAccounts[0]?.id || '',
    bank_account_id: '',
    notes: '',
  });

  const repayForm = useForm({
    repayment_date: todayStr,
    amount: '',
    repayment_method: 'cash',
    cash_account_id: cashAccounts[0]?.id || '',
    bank_account_id: '',
    notes: '',
  });

  const partnerOptions = useMemo(() => partners.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [partners, activeLocale]);
  const cashAccountOptions = useMemo(() => cashAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [cashAccounts]);
  const bankAccountOptions = useMemo(() => bankAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [bankAccounts]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [currencies, activeLocale]);
  const statusOptions = ['active', 'completed', 'cancelled'].map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    form.reset();
    form.setData({
      partner_id: partners[0]?.id || '',
      currency: currencies[0]?.code || '',
      principal_amount: '',
      disbursement_date: todayStr,
      disbursement_method: 'cash',
      cash_account_id: cashAccounts[0]?.id || '',
      bank_account_id: '',
      notes: '',
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      partner_id: data.partner_id,
      currency: data.currency,
      principal_minor: Math.round(Number(data.principal_amount || 0) * 100),
      disbursement_date: data.disbursement_date,
      disbursement_method: data.disbursement_method,
      cash_account_id: data.disbursement_method === 'cash' ? data.cash_account_id : null,
      bank_account_id: data.disbursement_method === 'bank' ? data.bank_account_id : null,
      notes: data.notes || null,
    }));

    form.post('/partners/loans', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function openRepay(loan: PartnerLoan) {
    repayForm.reset();
    repayForm.setData({
      repayment_date: todayStr,
      amount: '',
      repayment_method: 'cash',
      cash_account_id: cashAccounts[0]?.id || '',
      bank_account_id: '',
      notes: '',
    });
    repayForm.clearErrors();
    setRepayTarget(loan);
  }

  function submitRepay(event: FormEvent) {
    event.preventDefault();
    if (!repayTarget) return;
    repayForm.transform((data) => ({
      repayment_date: data.repayment_date,
      amount_minor: Math.round(Number(data.amount || 0) * 100),
      repayment_method: data.repayment_method,
      cash_account_id: data.repayment_method === 'cash' ? data.cash_account_id : null,
      bank_account_id: data.repayment_method === 'bank' ? data.bank_account_id : null,
      notes: data.notes || null,
    }));

    repayForm.post(`/partners/loans/${repayTarget.id}/repay`, {
      preserveScroll: true,
      onSuccess: () => {
        setRepayTarget(null);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function cancelLoan(loan: PartnerLoan) {
    if (!confirm(pageDict.confirmCancel)) return;
    router.post(`/partners/loans/${loan.id}/cancel`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'partner', name: 'partner', title: pageDict.partner, orderable: false, searchable: false },
    { data: 'principal_minor', name: 'partner_loan.principal_minor', title: pageDict.principal, className: 'text-end' },
    { data: 'remaining_balance_minor', name: 'partner_loan.remaining_balance_minor', title: pageDict.remaining, className: 'text-end' },
    { data: 'status', name: 'partner_loan.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    partner: (_v: unknown, _t: unknown, row: PartnerLoan) => <span className="font-medium">{row.partner ? `${row.partner.code} - ${namePart(row.partner.name, activeLocale)}` : ''}</span>,
    'partner_loan.principal_minor': (value: number, _t: unknown, row: PartnerLoan) => <span>{formatMoney(value, row.currency)}</span>,
    'partner_loan.remaining_balance_minor': (value: number, _t: unknown, row: PartnerLoan) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
    'partner_loan.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    actions: (_v: unknown, _t: unknown, row: PartnerLoan) => (
      <div className="flex flex-wrap justify-end gap-2">
        {row.status === 'active' && can('partners.post') ? <Button variant="secondary" onClick={() => openRepay(row)}>{pageDict.repay}</Button> : null}
        {row.status === 'active' && row.remaining_balance_minor === row.principal_minor && can('partners.edit') ? <Button variant="danger" onClick={() => cancelLoan(row)}>{pageDict.cancel}</Button> : null}
      </div>
    ),
  }), [activeLocale, can, pageDict]);

  const tableFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44"><SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} /></div>
    </div>
  );

  return (
    <AppLayout active="partners.loans.index">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} actions={can('partners.create') ? <Button onClick={openCreate} disabled={partners.length === 0}>{pageDict.create}</Button> : null} />

      {partners.length === 0 ? (
        <Card className="mb-5 border-amber-300 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
          <p className="m-0 text-sm font-semibold text-amber-800 dark:text-amber-300">{pageDict.noPartnersWarning}</p>
        </Card>
      ) : null}

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{pageDict.createTitle}</h2>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.close}</Button>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <SearchableSelect label={pageDict.partner} value={form.data.partner_id || null} onChange={(value) => form.setData('partner_id', value || '')} options={partnerOptions} isClearable={false} required error={form.errors.partner_id} />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.principal}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={form.data.principal_amount} onChange={(event) => form.setData('principal_amount', event.target.value)} required />
                {form.errors['principal_minor' as keyof typeof form.errors] ? <p className="mt-1 text-xs text-rose-600">{form.errors['principal_minor' as keyof typeof form.errors]}</p> : null}
              </label>
              <DatePicker label={pageDict.disbursementDate} value={form.data.disbursement_date} onChange={(value) => form.setData('disbursement_date', value || '')} required />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <SearchableSelect label={pageDict.currency} value={form.data.currency || null} onChange={(value) => form.setData('currency', value || '')} options={currencyOptions} isClearable={false} required />
              <SearchableSelect
                label={pageDict.disbursementMethod}
                value={form.data.disbursement_method}
                onChange={(value) => form.setData('disbursement_method', value || 'cash')}
                options={settlementMethods.map((item) => ({ value: item, label: pageDict.settlementMethods[item as keyof typeof pageDict.settlementMethods] || item }))}
                isClearable={false}
                required
              />
              {form.data.disbursement_method === 'cash' ? (
                <SearchableSelect label={pageDict.cashAccount} value={form.data.cash_account_id || null} onChange={(value) => form.setData('cash_account_id', value || '')} options={cashAccountOptions} isClearable={false} required error={form.errors.cash_account_id} />
              ) : (
                <SearchableSelect label={pageDict.bankAccount} value={form.data.bank_account_id || null} onChange={(value) => form.setData('bank_account_id', value || '')} options={bankAccountOptions} isClearable={false} required error={form.errors.bank_account_id} />
              )}
            </div>
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.notes}
              <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
            </label>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancelAction}</Button>
              <Button type="submit" disabled={form.processing}>{pageDict.disburse}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/partners/loans/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="partner-loans-data-table"
          toolbar={toolbar}
        />
      </Card>

      {repayTarget ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{pageDict.repayTitle}</h3>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{formatMoney(repayTarget.remaining_balance_minor, repayTarget.currency)}</p>
            <form onSubmit={submitRepay} className="mt-4 space-y-4">
              <DatePicker label={pageDict.repaymentDate} value={repayForm.data.repayment_date} onChange={(value) => repayForm.setData('repayment_date', value || '')} required />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.amount}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={repayForm.data.amount} onChange={(event) => repayForm.setData('amount', event.target.value)} required />
                {repayForm.errors['amount_minor' as keyof typeof repayForm.errors] ? <p className="mt-1 text-xs text-rose-600">{repayForm.errors['amount_minor' as keyof typeof repayForm.errors]}</p> : null}
              </label>
              <SearchableSelect
                label={pageDict.repaymentMethod}
                value={repayForm.data.repayment_method}
                onChange={(value) => repayForm.setData('repayment_method', value || 'cash')}
                options={settlementMethods.map((item) => ({ value: item, label: pageDict.settlementMethods[item as keyof typeof pageDict.settlementMethods] || item }))}
                isClearable={false}
                required
              />
              {repayForm.data.repayment_method === 'cash' ? (
                <SearchableSelect label={pageDict.cashAccount} value={repayForm.data.cash_account_id || null} onChange={(value) => repayForm.setData('cash_account_id', value || '')} options={cashAccountOptions} isClearable={false} required error={repayForm.errors.cash_account_id} />
              ) : (
                <SearchableSelect label={pageDict.bankAccount} value={repayForm.data.bank_account_id || null} onChange={(value) => repayForm.setData('bank_account_id', value || '')} options={bankAccountOptions} isClearable={false} required error={repayForm.errors.bank_account_id} />
              )}
              <div className="flex justify-end gap-2.5 pt-2">
                <Button type="button" variant="secondary" onClick={() => setRepayTarget(null)}>{pageDict.cancelAction}</Button>
                <Button type="submit" disabled={repayForm.processing}>{pageDict.confirmRepay}</Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
