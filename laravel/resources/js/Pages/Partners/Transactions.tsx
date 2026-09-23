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

type PartnerTransaction = {
  id: string;
  number?: string | null;
  transaction_type: string;
  transaction_date: string;
  currency: string;
  amount_minor: number;
  status: string;
  reference: string | null;
  partner?: PartnerOption | null;
};

type Props = SharedPageProps & {
  partners: PartnerOption[];
  cashAccounts: CashAccountOption[];
  bankAccounts: BankAccountOption[];
  currencies: CurrencyOption[];
  transactionTypes: string[];
  settlementMethods: string[];
  filters: { status?: string; partner_id?: string; transaction_type?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'posted') return 'ok';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

export default function PartnerTransactions({ locale, partners, cashAccounts, bankAccounts, currencies, transactionTypes, settlementMethods, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.partnerTransactions;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [postTarget, setPostTarget] = useState<PartnerTransaction | null>(null);
  const [tableReloadToken, setTableReloadToken] = useState(0);

  const form = useForm({
    partner_id: partners[0]?.id || '',
    transaction_type: transactionTypes[0] || 'contribution',
    transaction_date: todayStr,
    currency: currencies[0]?.code || '',
    amount: '',
    reference: '',
    notes: '',
  });

  const postForm = useForm({
    settlement_method: 'cash',
    cash_account_id: cashAccounts[0]?.id || '',
    bank_account_id: '',
  });

  const partnerOptions = useMemo(() => partners.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [partners, activeLocale]);
  const cashAccountOptions = useMemo(() => cashAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [cashAccounts]);
  const bankAccountOptions = useMemo(() => bankAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [bankAccounts]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [currencies, activeLocale]);
  const transactionTypeOptions = transactionTypes.map((item) => ({ value: item, label: pageDict.transactionTypes[item as keyof typeof pageDict.transactionTypes] || item }));
  const statusOptions = ['draft', 'posted', 'cancelled'].map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    form.reset();
    form.setData({
      partner_id: partners[0]?.id || '',
      transaction_type: transactionTypes[0] || 'contribution',
      transaction_date: todayStr,
      currency: currencies[0]?.code || '',
      amount: '',
      reference: '',
      notes: '',
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      partner_id: data.partner_id,
      transaction_type: data.transaction_type,
      transaction_date: data.transaction_date,
      currency: data.currency,
      amount_minor: Math.round(Number(data.amount || 0) * 100),
      reference: data.reference || null,
      notes: data.notes || null,
    }));

    form.post('/partners/transactions', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function openPost(row: PartnerTransaction) {
    postForm.reset();
    postForm.setData({ settlement_method: 'cash', cash_account_id: cashAccounts[0]?.id || '', bank_account_id: '' });
    postForm.clearErrors();
    setPostTarget(row);
  }

  function submitPost(event: FormEvent) {
    event.preventDefault();
    if (!postTarget) return;
    postForm.transform((data) => ({
      settlement_method: data.settlement_method,
      cash_account_id: data.settlement_method === 'cash' ? data.cash_account_id : null,
      bank_account_id: data.settlement_method === 'bank' ? data.bank_account_id : null,
    }));

    postForm.post(`/partners/transactions/${postTarget.id}/post`, {
      preserveScroll: true,
      onSuccess: () => {
        setPostTarget(null);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function cancelTransaction(row: PartnerTransaction) {
    if (!confirm(pageDict.confirmCancel)) return;
    router.post(`/partners/transactions/${row.id}/cancel`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'number', name: 'partner_transaction.number', title: pageDict.number },
    { data: 'partner', name: 'partner', title: pageDict.partner, orderable: false, searchable: false },
    { data: 'transaction_type', name: 'partner_transaction.transaction_type', title: pageDict.transactionType },
    { data: 'amount_minor', name: 'partner_transaction.amount_minor', title: pageDict.amount, className: 'text-end' },
    { data: 'status', name: 'partner_transaction.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    'partner_transaction.number': (value: string | null) => <span className="font-mono text-sm font-bold text-blue-600">{value || pageDict.draftLabel}</span>,
    partner: (_v: unknown, _t: unknown, row: PartnerTransaction) => <span className="font-medium">{row.partner ? `${row.partner.code} - ${namePart(row.partner.name, activeLocale)}` : ''}</span>,
    'partner_transaction.transaction_type': (value: string) => pageDict.transactionTypes[value as keyof typeof pageDict.transactionTypes] || value,
    'partner_transaction.amount_minor': (value: number, _t: unknown, row: PartnerTransaction) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
    'partner_transaction.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    actions: (_v: unknown, _t: unknown, row: PartnerTransaction) => (
      <div className="flex flex-wrap justify-end gap-2">
        {row.status === 'draft' && can('partners.post') ? <Button variant="secondary" onClick={() => openPost(row)}>{pageDict.post}</Button> : null}
        {row.status === 'draft' && can('partners.edit') ? <Button variant="danger" onClick={() => cancelTransaction(row)}>{pageDict.cancel}</Button> : null}
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
    <AppLayout active="partners.transactions.index">
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
              <SearchableSelect label={pageDict.transactionType} value={form.data.transaction_type} onChange={(value) => form.setData('transaction_type', value || 'contribution')} options={transactionTypeOptions} isClearable={false} required />
              <DatePicker label={pageDict.transactionDate} value={form.data.transaction_date} onChange={(value) => form.setData('transaction_date', value || '')} required />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.amount}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} required />
                {form.errors['amount_minor' as keyof typeof form.errors] ? <p className="mt-1 text-xs text-rose-600">{form.errors['amount_minor' as keyof typeof form.errors]}</p> : null}
              </label>
              <SearchableSelect label={pageDict.currency} value={form.data.currency || null} onChange={(value) => form.setData('currency', value || '')} options={currencyOptions} isClearable={false} required />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.reference}
                <input className="input mt-1" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
              </label>
            </div>
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.notes}
              <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
            </label>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancelAction}</Button>
              <Button type="submit" disabled={form.processing}>{pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/partners/transactions/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="partner-transactions-data-table"
          toolbar={toolbar}
        />
      </Card>

      {postTarget ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{pageDict.postTitle}</h3>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{pageDict.postHint}</p>
            <form onSubmit={submitPost} className="mt-4 space-y-4">
              <SearchableSelect
                label={pageDict.settlementMethod}
                value={postForm.data.settlement_method}
                onChange={(value) => postForm.setData('settlement_method', value || 'cash')}
                options={settlementMethods.map((item) => ({ value: item, label: pageDict.settlementMethods[item as keyof typeof pageDict.settlementMethods] || item }))}
                isClearable={false}
                required
              />
              {postForm.data.settlement_method === 'cash' ? (
                <SearchableSelect label={pageDict.cashAccount} value={postForm.data.cash_account_id || null} onChange={(value) => postForm.setData('cash_account_id', value || '')} options={cashAccountOptions} isClearable={false} required error={postForm.errors.cash_account_id} />
              ) : (
                <SearchableSelect label={pageDict.bankAccount} value={postForm.data.bank_account_id || null} onChange={(value) => postForm.setData('bank_account_id', value || '')} options={bankAccountOptions} isClearable={false} required error={postForm.errors.bank_account_id} />
              )}
              <div className="flex justify-end gap-2.5 pt-2">
                <Button type="button" variant="secondary" onClick={() => setPostTarget(null)}>{pageDict.cancelAction}</Button>
                <Button type="submit" disabled={postForm.processing}>{pageDict.confirmPost}</Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
