import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type OptionRow = { id: string; code: string; name: TranslatedName };
type AccountOption = { id: string; code: string; name: TranslatedName; currency: string };
type CurrencyOption = { code: string; name: TranslatedName; symbol: string };

type TemplateFormData = {
  code: string;
  name: { en: string; ar: string };
  frequency: string;
  interval_count: number;
  start_date: string;
  end_date: string;
  lock_version: number;
  template_payload: TemplatePayload;
};

type TemplatePayload = {
  branch_id?: string | null;
  settlement_method: 'payable' | 'cash' | 'bank';
  supplier_id?: string | null;
  cash_account_id?: string | null;
  bank_account_id?: string | null;
  payee_name?: string | null;
  currency: string;
  reference?: string | null;
  description?: string | null;
  expense_category_id: string;
  unit_amount_minor: number;
};

type TemplateRow = {
  id: string;
  code: string;
  name: TranslatedName;
  frequency: string;
  interval_count: number;
  start_date: string;
  end_date: string | null;
  next_run_date: string;
  last_generated_date: string | null;
  status: string;
  generated_count: number;
  last_error: string | null;
  lock_version: number;
  template_payload: TemplatePayload;
};

type Props = SharedPageProps & {
  branches: OptionRow[];
  expenseCategories: OptionRow[];
  suppliers: OptionRow[];
  cashAccounts: AccountOption[];
  bankAccounts: AccountOption[];
  currencies: CurrencyOption[];
  frequencies: string[];
  filters: { status?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'active') return 'info';
  if (value === 'completed') return 'ok';
  if (value === 'paused') return 'warning';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

const emptyPayload: TemplatePayload = {
  branch_id: null,
  settlement_method: 'cash',
  supplier_id: null,
  cash_account_id: '',
  bank_account_id: '',
  payee_name: '',
  currency: '',
  reference: '',
  description: '',
  expense_category_id: '',
  unit_amount_minor: 0,
};

export default function RecurringTemplates({ locale, expenseCategories, suppliers, cashAccounts, bankAccounts, currencies, frequencies, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.recurringTemplates;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<TemplateRow | null>(null);
  const [amountDisplay, setAmountDisplay] = useState('');
  const [tableReloadToken, setTableReloadToken] = useState(0);

  const form = useForm<TemplateFormData>({
    code: '',
    name: { en: '', ar: '' },
    frequency: frequencies[0] || 'monthly',
    interval_count: 1,
    start_date: todayStr,
    end_date: '',
    lock_version: 1,
    template_payload: { ...emptyPayload, currency: currencies[0]?.code || '', cash_account_id: cashAccounts[0]?.id || '' },
  });

  const categoryOptions = useMemo(() => expenseCategories.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [expenseCategories, activeLocale]);
  const supplierOptions = useMemo(() => suppliers.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [suppliers, activeLocale]);
  const cashAccountOptions = useMemo(() => cashAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [cashAccounts]);
  const bankAccountOptions = useMemo(() => bankAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [bankAccounts]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [currencies, activeLocale]);
  const frequencyOptions = frequencies.map((item) => ({ value: item, label: pageDict.frequencies[item as keyof typeof pageDict.frequencies] || item }));
  const settlementMethodOptions = ['cash', 'bank', 'payable'].map((item) => ({ value: item, label: pageDict.settlementMethods[item as keyof typeof pageDict.settlementMethods] || item }));
  const statusOptions = ['active', 'paused', 'completed', 'cancelled'].map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    setEditing(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      frequency: frequencies[0] || 'monthly',
      interval_count: 1,
      start_date: todayStr,
      end_date: '',
      lock_version: 1,
      template_payload: { ...emptyPayload, currency: currencies[0]?.code || '', cash_account_id: cashAccounts[0]?.id || '' },
    });
    setAmountDisplay('');
    form.clearErrors();
    setShowModal(true);
  }

  function openEdit(row: TemplateRow) {
    setEditing(row);
    form.setData({
      code: row.code,
      name: { en: namePart(row.name, 'en'), ar: namePart(row.name, 'ar') },
      frequency: row.frequency,
      interval_count: row.interval_count,
      start_date: row.start_date,
      end_date: row.end_date || '',
      lock_version: row.lock_version,
      template_payload: { ...row.template_payload },
    });
    setAmountDisplay(String((row.template_payload.unit_amount_minor || 0) / 100));
    form.clearErrors();
    setShowModal(true);
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      code: data.code,
      name: data.name,
      frequency: data.frequency,
      interval_count: data.interval_count,
      start_date: data.start_date,
      end_date: data.end_date || null,
      lock_version: data.lock_version,
      template_payload: {
        ...data.template_payload,
        supplier_id: data.template_payload.settlement_method === 'payable' ? data.template_payload.supplier_id || null : null,
        cash_account_id: data.template_payload.settlement_method === 'cash' ? data.template_payload.cash_account_id || null : null,
        bank_account_id: data.template_payload.settlement_method === 'bank' ? data.template_payload.bank_account_id || null : null,
        unit_amount_minor: Math.round(Number(amountDisplay || 0) * 100),
      },
    }));

    const onSuccess = () => {
      setShowModal(false);
      setTableReloadToken((value) => value + 1);
    };

    if (editing) {
      form.put(`/recurring/templates/${editing.id}`, { preserveScroll: true, onSuccess });
      return;
    }

    form.post('/recurring/templates', { preserveScroll: true, onSuccess });
  }

  function pauseTemplate(row: TemplateRow) {
    router.post(`/recurring/templates/${row.id}/pause`, {}, { preserveScroll: true, onSuccess: () => setTableReloadToken((value) => value + 1) });
  }

  function resumeTemplate(row: TemplateRow) {
    router.post(`/recurring/templates/${row.id}/resume`, {}, { preserveScroll: true, onSuccess: () => setTableReloadToken((value) => value + 1) });
  }

  function cancelTemplate(row: TemplateRow) {
    if (!confirm(pageDict.confirmCancel)) return;
    router.post(`/recurring/templates/${row.id}/cancel`, {}, { preserveScroll: true, onSuccess: () => setTableReloadToken((value) => value + 1) });
  }

  function deleteTemplate(row: TemplateRow) {
    if (!confirm(pageDict.confirmDelete)) return;
    router.delete(`/recurring/templates/${row.id}`, { preserveScroll: true, onSuccess: () => setTableReloadToken((value) => value + 1) });
  }

  const columns = useMemo(() => [
    { data: 'code', name: 'recurring_template.code', title: pageDict.code },
    { data: 'name_text', name: 'name_text', title: pageDict.nameEn },
    { data: 'frequency', name: 'recurring_template.frequency', title: pageDict.frequency },
    { data: 'next_run_date', name: 'recurring_template.next_run_date', title: pageDict.nextRunDate },
    { data: 'generated_count', name: 'recurring_template.generated_count', title: pageDict.generatedCount, className: 'text-end' },
    { data: 'status', name: 'recurring_template.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    name_text: (_v: unknown, _t: unknown, row: TemplateRow) => namePart(row.name, activeLocale),
    'recurring_template.frequency': (value: string, _t: unknown, row: TemplateRow) => (
      <span>{pageDict.frequencies[value as keyof typeof pageDict.frequencies] || value}{row.interval_count > 1 ? ` (x${row.interval_count})` : ''}</span>
    ),
    'recurring_template.status': (value: string, _t: unknown, row: TemplateRow) => (
      <div>
        <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>
        {value === 'paused' && row.last_error ? <p className="mt-1 max-w-xs text-xs text-rose-600">{row.last_error}</p> : null}
      </div>
    ),
    actions: (_v: unknown, _t: unknown, row: TemplateRow) => (
      <div className="flex flex-wrap justify-end gap-2">
        {(row.status === 'active' || row.status === 'paused') && can('recurring.edit') ? <Button variant="secondary" onClick={() => openEdit(row)}>{pageDict.edit}</Button> : null}
        {row.status === 'active' && can('recurring.edit') ? <Button variant="secondary" onClick={() => pauseTemplate(row)}>{pageDict.pause}</Button> : null}
        {row.status === 'paused' && can('recurring.edit') ? <Button variant="secondary" onClick={() => resumeTemplate(row)}>{pageDict.resume}</Button> : null}
        {(row.status === 'active' || row.status === 'paused') && can('recurring.edit') ? <Button variant="danger" onClick={() => cancelTemplate(row)}>{pageDict.cancel}</Button> : null}
        {row.generated_count === 0 && can('recurring.delete') ? <Button variant="danger" onClick={() => deleteTemplate(row)}>{pageDict.delete}</Button> : null}
      </div>
    ),
  }), [activeLocale, can, pageDict]);

  const tableFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44"><SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} /></div>
    </div>
  );

  const payload = form.data.template_payload;

  return (
    <AppLayout active="recurring.templates.index">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} actions={can('recurring.create') ? <Button onClick={openCreate} disabled={expenseCategories.length === 0}>{pageDict.create}</Button> : null} />

      {expenseCategories.length === 0 ? (
        <Card className="mb-5 border-amber-300 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
          <p className="m-0 text-sm font-semibold text-amber-800 dark:text-amber-300">{pageDict.noCategoriesWarning}</p>
        </Card>
      ) : null}

      {showModal ? (
        <Card className="mb-5 p-5">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h2>
              <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.close}</Button>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.code}
                <input className="input mt-1" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} disabled={!!editing} />
                {form.errors.code ? <p className="mt-1 text-xs text-rose-600">{form.errors.code}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameEn}
                <input className="input mt-1" value={form.data.name.en} onChange={(event) => form.setData('name', { ...form.data.name, en: event.target.value })} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.nameAr}
                <input className="input mt-1" value={form.data.name.ar} onChange={(event) => form.setData('name', { ...form.data.name, ar: event.target.value })} />
              </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-4">
              <SearchableSelect label={pageDict.frequency} value={form.data.frequency} onChange={(value) => form.setData('frequency', value || 'monthly')} options={frequencyOptions} isClearable={false} required />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.intervalCount}
                <input className="input mt-1" type="number" min="1" value={form.data.interval_count} onChange={(event) => form.setData('interval_count', Number(event.target.value || 1))} required />
              </label>
              <DatePicker label={pageDict.startDate} value={form.data.start_date} onChange={(value) => form.setData('start_date', value || '')} required />
              <DatePicker label={pageDict.endDate} value={form.data.end_date} onChange={(value) => form.setData('end_date', value || '')} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <SearchableSelect label={pageDict.expenseCategory} value={payload.expense_category_id || null} onChange={(value) => form.setData('template_payload', { ...payload, expense_category_id: value || '' })} options={categoryOptions} isClearable={false} required error={form.errors['template_payload.expense_category_id' as keyof typeof form.errors]} />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.amount}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={amountDisplay} onChange={(event) => setAmountDisplay(event.target.value)} required />
                {form.errors['template_payload.unit_amount_minor' as keyof typeof form.errors] ? <p className="mt-1 text-xs text-rose-600">{form.errors['template_payload.unit_amount_minor' as keyof typeof form.errors]}</p> : null}
              </label>
              <SearchableSelect label={pageDict.currency} value={payload.currency || null} onChange={(value) => form.setData('template_payload', { ...payload, currency: value || '' })} options={currencyOptions} isClearable={false} required />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <SearchableSelect
                label={pageDict.settlementMethod}
                value={payload.settlement_method}
                onChange={(value) => form.setData('template_payload', { ...payload, settlement_method: (value as TemplatePayload['settlement_method']) || 'cash' })}
                options={settlementMethodOptions}
                isClearable={false}
                required
              />
              {payload.settlement_method === 'payable' ? (
                <SearchableSelect label={pageDict.supplier} value={payload.supplier_id || null} onChange={(value) => form.setData('template_payload', { ...payload, supplier_id: value || '' })} options={supplierOptions} isClearable={false} required error={form.errors['template_payload.supplier_id' as keyof typeof form.errors]} />
              ) : null}
              {payload.settlement_method === 'cash' ? (
                <SearchableSelect label={pageDict.cashAccount} value={payload.cash_account_id || null} onChange={(value) => form.setData('template_payload', { ...payload, cash_account_id: value || '' })} options={cashAccountOptions} isClearable={false} required error={form.errors['template_payload.cash_account_id' as keyof typeof form.errors]} />
              ) : null}
              {payload.settlement_method === 'bank' ? (
                <SearchableSelect label={pageDict.bankAccount} value={payload.bank_account_id || null} onChange={(value) => form.setData('template_payload', { ...payload, bank_account_id: value || '' })} options={bankAccountOptions} isClearable={false} required error={form.errors['template_payload.bank_account_id' as keyof typeof form.errors]} />
              ) : null}
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.reference}
                <input className="input mt-1" value={payload.reference || ''} onChange={(event) => form.setData('template_payload', { ...payload, reference: event.target.value })} />
              </label>
            </div>

            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.descriptionLabel}
              <textarea className="input mt-1 min-h-20" value={payload.description || ''} onChange={(event) => form.setData('template_payload', { ...payload, description: event.target.value })} />
            </label>

            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.cancelAction}</Button>
              <Button type="submit" disabled={form.processing}>{form.processing ? pageDict.saving : pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/recurring/templates/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="recurring-templates-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
