import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, Modal, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type CustomerOption = { id: string; code: string; name: TranslatedName };
type CurrencyOption = { code: string; name: TranslatedName; symbol: string };
type ProductOption = { id: string; code: string; name: TranslatedName; unit_of_measure_id: string };

type QuotationLine = {
  id?: string;
  product_id: string;
  unit_of_measure_id: string;
  description: string;
  quantity: number;
  unit_price: number;
};

type Quotation = {
  id: string;
  number?: string | null;
  customer_id: string;
  quotation_date: string;
  valid_until?: string | null;
  currency: string;
  status: string;
  reference?: string | null;
  notes?: string | null;
  total_minor: number;
  lock_version: number;
  customer?: CustomerOption | null;
  lines: Array<{
    id: string;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    unit_price_minor: number;
  }>;
};

type Props = SharedPageProps & {
  customers: CustomerOption[];
  products: ProductOption[];
  currencies: CurrencyOption[];
  statuses: string[];
  filters: { search?: string; status?: string; customer_id?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'accepted' || value === 'converted') return 'ok';
  if (value === 'submitted') return 'info';
  if (value === 'rejected' || value === 'expired') return 'danger';
  return 'muted';
}

export default function Quotations({ locale, customers, products, currencies, statuses, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.salesQuotations;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Quotation | null>(null);
  const [convertTarget, setConvertTarget] = useState<Quotation | null>(null);
  const [tableReloadToken, setTableReloadToken] = useState(0);
  const [lines, setLines] = useState<QuotationLine[]>([
    { product_id: products[0]?.id || '', unit_of_measure_id: products[0]?.unit_of_measure_id || '', description: '', quantity: 1, unit_price: 10 },
  ]);

  const form = useForm({
    customer_id: customers[0]?.id || '',
    quotation_date: todayStr,
    valid_until: '',
    currency: currencies[0]?.code || '',
    reference: '',
    notes: '',
    lock_version: 1,
  });

  const convertForm = useForm({ order_date: todayStr, expected_delivery_date: '' });

  const customerOptions = useMemo(() => customers.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [customers, activeLocale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [currencies, activeLocale]);
  const productOptions = useMemo(() => products.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [products, activeLocale]);
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    setEditing(null);
    form.reset();
    form.setData({ customer_id: customers[0]?.id || '', quotation_date: todayStr, valid_until: '', currency: currencies[0]?.code || '', reference: '', notes: '', lock_version: 1 });
    setLines([{ product_id: products[0]?.id || '', unit_of_measure_id: products[0]?.unit_of_measure_id || '', description: '', quantity: 1, unit_price: 10 }]);
    setShowModal(true);
  }

  function openEdit(quotation: Quotation) {
    setEditing(quotation);
    form.setData({
      customer_id: quotation.customer_id,
      quotation_date: quotation.quotation_date,
      valid_until: quotation.valid_until || '',
      currency: quotation.currency,
      reference: quotation.reference || '',
      notes: quotation.notes || '',
      lock_version: quotation.lock_version,
    });
    setLines(quotation.lines.map((line) => ({
      id: line.id,
      product_id: line.product_id,
      unit_of_measure_id: line.unit_of_measure_id,
      description: line.description || '',
      quantity: line.quantity_e6 / 1_000_000,
      unit_price: line.unit_price_minor / 100,
    })));
    setShowModal(true);
  }

  function handleProductChange(index: number, productId: string) {
    const product = products.find((item) => item.id === productId);
    setLines((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], product_id: productId, unit_of_measure_id: product?.unit_of_measure_id || '' };
      return next;
    });
  }

  function addLine() {
    setLines((prev) => [...prev, { product_id: products[0]?.id || '', unit_of_measure_id: products[0]?.unit_of_measure_id || '', description: '', quantity: 1, unit_price: 10 }]);
  }

  function removeLine(index: number) {
    if (lines.length <= 1) return;
    setLines((prev) => prev.filter((_, i) => i !== index));
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      lines: lines.map((line) => ({
        product_id: line.product_id,
        unit_of_measure_id: line.unit_of_measure_id,
        description: line.description,
        quantity_e6: Math.round(Number(line.quantity) * 1_000_000),
        unit_price_minor: Math.round(Number(line.unit_price) * 100),
      })),
    }));

    const onSuccess = () => {
      setShowModal(false);
      setTableReloadToken((value) => value + 1);
    };
    if (editing) {
      form.put(`/sales/quotations/${editing.id}`, { preserveScroll: true, onSuccess });
    } else {
      form.post('/sales/quotations', { preserveScroll: true, onSuccess });
    }
  }

  function runAction(quotation: Quotation, action: 'submit' | 'accept' | 'reject' | 'cancel') {
    if (!confirm(pageDict.confirmAction)) return;
    router.post(`/sales/quotations/${quotation.id}/${action}`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  function submitConvert(event: FormEvent) {
    event.preventDefault();
    if (!convertTarget) return;
    convertForm.post(`/sales/quotations/${convertTarget.id}/convert`, {
      preserveScroll: true,
      onSuccess: () => {
        setConvertTarget(null);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  const columns = useMemo(() => [
    { data: 'number', name: 'sales_quotation.number', title: pageDict.number },
    { data: 'customer', name: 'customer', title: pageDict.customer, orderable: false, searchable: false },
    { data: 'quotation_date', name: 'sales_quotation.quotation_date', title: pageDict.date },
    { data: 'total_minor', name: 'sales_quotation.total_minor', title: pageDict.total, className: 'text-end' },
    { data: 'status', name: 'sales_quotation.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    'sales_quotation.number': (value: string | null) => <span className="font-mono text-sm font-bold text-blue-600">{value || pageDict.draftLabel}</span>,
    customer: (_v: unknown, _t: unknown, row: Quotation) => <span className="font-medium">{namePart(row.customer?.name || null, activeLocale)}</span>,
    'sales_quotation.total_minor': (value: number, _t: unknown, row: Quotation) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
    'sales_quotation.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    actions: (_v: unknown, _t: unknown, row: Quotation) => (
      <div className="flex flex-wrap justify-end gap-2">
        {row.status === 'draft' && can('sales.edit') ? <Button variant="secondary" onClick={() => openEdit(row)}>{pageDict.edit}</Button> : null}
        {row.status === 'draft' && can('sales.submit') ? <Button variant="secondary" onClick={() => runAction(row, 'submit')}>{pageDict.submit}</Button> : null}
        {row.status === 'submitted' && can('sales.approve') ? <Button variant="secondary" onClick={() => runAction(row, 'accept')}>{pageDict.accept}</Button> : null}
        {row.status === 'submitted' && can('sales.approve') ? <Button variant="danger" onClick={() => runAction(row, 'reject')}>{pageDict.reject}</Button> : null}
        {(row.status === 'draft' || row.status === 'submitted') && can('sales.cancel') ? <Button variant="danger" onClick={() => runAction(row, 'cancel')}>{pageDict.cancel}</Button> : null}
        {row.status === 'accepted' && can('sales.create') ? <Button onClick={() => { convertForm.setData({ order_date: todayStr, expected_delivery_date: '' }); setConvertTarget(row); }}>{pageDict.convert}</Button> : null}
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
    <AppLayout active="sales-quotations.index">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} actions={can('sales.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null} />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/sales/quotations/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[2, 'desc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="sales-quotations-data-table"
          toolbar={toolbar}
        />
      </Card>

      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title={editing ? pageDict.editTitle : pageDict.createTitle}
        closeLabel={pageDict.cancelAction}
        size="3xl"
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.cancelAction}</Button>
            <Button type="submit" form="sales-quotation-form" disabled={form.processing}>{editing ? pageDict.update : pageDict.save}</Button>
          </>
        }
      >
            <form id="sales-quotation-form" onSubmit={submitForm} className="space-y-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SearchableSelect label={pageDict.customer} value={form.data.customer_id || null} onChange={(value) => form.setData('customer_id', value || '')} options={customerOptions} isClearable={false} required error={form.errors.customer_id} />
                <DatePicker label={pageDict.date} value={form.data.quotation_date} onChange={(value) => form.setData('quotation_date', value || '')} required />
                <DatePicker label={pageDict.validUntil} value={form.data.valid_until} onChange={(value) => form.setData('valid_until', value || '')} />
              </div>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <SearchableSelect label={pageDict.currency} value={form.data.currency || null} onChange={(value) => form.setData('currency', value || '')} options={currencyOptions} isClearable={false} required />
                <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                  {pageDict.reference}
                  <input className="input mt-1" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
                </label>
              </div>

              <div className="border-t border-[var(--border)] pt-4">
                <div className="mb-3 flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{pageDict.lines}</h4>
                  <button type="button" onClick={addLine} title={pageDict.addLine} aria-label={pageDict.addLine} className="text-xs font-semibold text-blue-600 hover:text-blue-800">{pageDict.addLine}</button>
                </div>
                <div className="space-y-3">
                  {lines.map((line, index) => (
                    <div key={index} className="flex flex-col items-start gap-2 rounded-xl border border-[var(--border)] bg-[var(--background)]/50 p-3 sm:flex-row sm:items-center">
                      <div className="w-full flex-1"><SearchableSelect label={pageDict.product} value={line.product_id || null} onChange={(value) => handleProductChange(index, value || '')} options={productOptions} isClearable={false} required /></div>
                      <div className="w-full sm:w-28">
                        <label className="mb-1 block text-[10px] font-semibold text-[var(--text-muted)]">{pageDict.quantity}</label>
                        <input type="number" step="0.000001" min="0.000001" value={line.quantity} onChange={(event) => setLines((prev) => { const next = [...prev]; next[index] = { ...next[index], quantity: parseFloat(event.target.value) || 0 }; return next; })} className="input" required />
                      </div>
                      <div className="w-full sm:w-28">
                        <label className="mb-1 block text-[10px] font-semibold text-[var(--text-muted)]">{pageDict.unitPrice}</label>
                        <input type="number" step="0.01" min="0.01" value={line.unit_price} onChange={(event) => setLines((prev) => { const next = [...prev]; next[index] = { ...next[index], unit_price: parseFloat(event.target.value) || 0 }; return next; })} className="input" required />
                      </div>
                      <button type="button" onClick={() => removeLine(index)} disabled={lines.length <= 1} title={pageDict.removeLine} aria-label={pageDict.removeLine} className="text-xs font-semibold text-rose-600 disabled:opacity-30">{pageDict.removeLine}</button>
                    </div>
                  ))}
                </div>
              </div>

            </form>
      </Modal>

      <Modal
        isOpen={Boolean(convertTarget)}
        onClose={() => setConvertTarget(null)}
        title={pageDict.convertTitle}
        closeLabel={pageDict.cancelAction}
        size="md"
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setConvertTarget(null)}>{pageDict.cancelAction}</Button>
            <Button type="submit" form="sales-quotation-convert-form" disabled={convertForm.processing}>{pageDict.confirmConvert}</Button>
          </>
        }
      >
            <form id="sales-quotation-convert-form" onSubmit={submitConvert} className="space-y-4">
              <DatePicker label={pageDict.orderDate} value={convertForm.data.order_date} onChange={(value) => convertForm.setData('order_date', value || '')} />
              <DatePicker label={pageDict.expectedDeliveryDate} value={convertForm.data.expected_delivery_date} onChange={(value) => convertForm.setData('expected_delivery_date', value || '')} />
            </form>
      </Modal>
    </AppLayout>
  );
}
