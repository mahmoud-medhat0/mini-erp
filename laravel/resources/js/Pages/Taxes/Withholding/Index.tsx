import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../../Components/AppLayout';
import DatePicker from '../../../Components/DatePicker';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../../Components/ServerDataTable';
import { formatMoney } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import { useCan } from '../../../lib/permissions';
import type { SharedPageProps } from '../../../Types';

type TranslatedName = Record<string, string> | string | null;
type TaxCodeOption = { id: string; code: string; name: TranslatedName };
type SupplierOption = { id: string; code: string; name: TranslatedName };
type CurrencyOption = { code: string; name: TranslatedName; symbol: string };

type WithholdingEntry = {
  id: string;
  number?: string | null;
  entry_date: string;
  currency: string;
  base_amount_minor: number;
  rate_bps: number;
  withheld_amount_minor: number;
  status: string;
  taxCode?: TaxCodeOption | null;
  supplier?: SupplierOption | null;
};

type Props = SharedPageProps & {
  taxCodes: TaxCodeOption[];
  suppliers: SupplierOption[];
  currencies: CurrencyOption[];
  filters: { status?: string; supplier_id?: string };
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

export default function WithholdingTaxIndex({ locale, taxCodes, suppliers, currencies, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.taxes.withholding;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [tableReloadToken, setTableReloadToken] = useState(0);

  const form = useForm({
    tax_code_id: taxCodes[0]?.id || '',
    supplier_id: suppliers[0]?.id || '',
    reference: '',
    entry_date: todayStr,
    currency: currencies[0]?.code || '',
    base_amount: '',
    notes: '',
  });

  const taxCodeOptions = useMemo(() => taxCodes.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [taxCodes, activeLocale]);
  const supplierOptions = useMemo(() => suppliers.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [suppliers, activeLocale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [currencies, activeLocale]);
  const statusOptions = ['draft', 'posted', 'cancelled'].map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    form.reset();
    form.setData({
      tax_code_id: taxCodes[0]?.id || '',
      supplier_id: suppliers[0]?.id || '',
      reference: '',
      entry_date: todayStr,
      currency: currencies[0]?.code || '',
      base_amount: '',
      notes: '',
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      tax_code_id: data.tax_code_id,
      supplier_id: data.supplier_id,
      reference: data.reference || null,
      entry_date: data.entry_date,
      currency: data.currency,
      base_amount_minor: Math.round(Number(data.base_amount || 0) * 100),
      notes: data.notes || null,
    }));

    form.post('/taxes/withholding', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function postEntry(entry: WithholdingEntry) {
    if (!confirm(pageDict.confirmPost)) return;
    router.post(`/taxes/withholding/${entry.id}/post`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  function cancelEntry(entry: WithholdingEntry) {
    if (!confirm(pageDict.confirmCancel)) return;
    router.post(`/taxes/withholding/${entry.id}/cancel`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'number', name: 'withholding_tax_entry.number', title: pageDict.number },
    { data: 'supplier', name: 'supplier', title: pageDict.supplier, orderable: false, searchable: false },
    { data: 'tax_code', name: 'tax_code', title: pageDict.taxCode, orderable: false, searchable: false },
    { data: 'base_amount_minor', name: 'withholding_tax_entry.base_amount_minor', title: pageDict.baseAmount, className: 'text-end' },
    { data: 'withheld_amount_minor', name: 'withholding_tax_entry.withheld_amount_minor', title: pageDict.withheldAmount, className: 'text-end' },
    { data: 'status', name: 'withholding_tax_entry.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    'withholding_tax_entry.number': (value: string | null) => <span className="font-mono text-sm font-bold text-blue-600">{value || pageDict.draftLabel}</span>,
    supplier: (_v: unknown, _t: unknown, row: WithholdingEntry) => <span className="font-medium">{row.supplier ? `${row.supplier.code} - ${namePart(row.supplier.name, activeLocale)}` : ''}</span>,
    tax_code: (_v: unknown, _t: unknown, row: WithholdingEntry) => <span>{row.taxCode ? `${row.taxCode.code} (${(row.rate_bps / 100).toFixed(2)}%)` : ''}</span>,
    'withholding_tax_entry.base_amount_minor': (value: number, _t: unknown, row: WithholdingEntry) => formatMoney(value, row.currency),
    'withholding_tax_entry.withheld_amount_minor': (value: number, _t: unknown, row: WithholdingEntry) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
    'withholding_tax_entry.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    actions: (_v: unknown, _t: unknown, row: WithholdingEntry) => (
      <div className="flex flex-wrap justify-end gap-2">
        {row.status === 'draft' && can('taxes.file') ? <Button variant="secondary" onClick={() => postEntry(row)}>{pageDict.post}</Button> : null}
        {row.status === 'draft' && can('taxes.edit') ? <Button variant="danger" onClick={() => cancelEntry(row)}>{pageDict.cancel}</Button> : null}
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
    <AppLayout active="taxes.withholding.index">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} actions={can('taxes.edit') ? <Button onClick={openCreate} disabled={taxCodes.length === 0}>{pageDict.create}</Button> : null} />

      {taxCodes.length === 0 ? (
        <Card className="mb-5 border-amber-300 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/30">
          <p className="m-0 text-sm font-semibold text-amber-800 dark:text-amber-300">{pageDict.noTaxCodesWarning}</p>
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
              <SearchableSelect label={pageDict.taxCode} value={form.data.tax_code_id || null} onChange={(value) => form.setData('tax_code_id', value || '')} options={taxCodeOptions} isClearable={false} required error={form.errors.tax_code_id} />
              <SearchableSelect label={pageDict.supplier} value={form.data.supplier_id || null} onChange={(value) => form.setData('supplier_id', value || '')} options={supplierOptions} isClearable={false} required error={form.errors.supplier_id} />
              <DatePicker label={pageDict.entryDate} value={form.data.entry_date} onChange={(value) => form.setData('entry_date', value || '')} required />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.baseAmount}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={form.data.base_amount} onChange={(event) => form.setData('base_amount', event.target.value)} required />
                {form.errors['base_amount_minor' as keyof typeof form.errors] ? <p className="mt-1 text-xs text-rose-600">{form.errors['base_amount_minor' as keyof typeof form.errors]}</p> : null}
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
          ajaxUrl="/taxes/withholding/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="withholding-tax-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
