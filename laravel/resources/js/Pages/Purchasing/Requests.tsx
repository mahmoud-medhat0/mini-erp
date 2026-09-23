import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type SupplierOption = { id: string; code: string; name: TranslatedName };
type CurrencyOption = { code: string; name: TranslatedName; symbol: string };
type ProductOption = { id: string; code: string; name: TranslatedName; unit_of_measure_id: string };

type RequestLine = {
  id?: string;
  product_id: string;
  unit_of_measure_id: string;
  description: string;
  quantity: number;
  estimated_unit_price: number;
};

type PurchaseRequestRow = {
  id: string;
  number?: string | null;
  supplier_id?: string | null;
  requested_date: string;
  needed_by_date?: string | null;
  currency: string;
  status: string;
  reference?: string | null;
  notes?: string | null;
  total_minor: number;
  lock_version: number;
  supplier?: SupplierOption | null;
  lines: Array<{
    id: string;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    estimated_unit_price_minor: number;
    product?: ProductOption | null;
  }>;
};

type Props = SharedPageProps & {
  suppliers: SupplierOption[];
  products: ProductOption[];
  currencies: CurrencyOption[];
  statuses: string[];
  filters: { search?: string; status?: string; supplier_id?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'approved' || value === 'converted') return 'ok';
  if (value === 'submitted') return 'info';
  if (value === 'rejected') return 'danger';
  return 'muted';
}

export default function PurchaseRequests({ locale, suppliers, products, currencies, statuses, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.purchaseRequests;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<PurchaseRequestRow | null>(null);
  const [convertTarget, setConvertTarget] = useState<PurchaseRequestRow | null>(null);
  const [tableReloadToken, setTableReloadToken] = useState(0);
  const [lines, setLines] = useState<RequestLine[]>([
    { product_id: products[0]?.id || '', unit_of_measure_id: products[0]?.unit_of_measure_id || '', description: '', quantity: 1, estimated_unit_price: 0 },
  ]);

  const form = useForm({
    supplier_id: '',
    requested_date: todayStr,
    needed_by_date: '',
    currency: currencies[0]?.code || '',
    reference: '',
    notes: '',
    lock_version: 1,
  });

  const convertForm = useForm({
    supplier_id: '',
    order_date: todayStr,
    expected_receipt_date: '',
    prices: {} as Record<string, number>,
  });

  const supplierOptions = useMemo(() => suppliers.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [suppliers, activeLocale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [currencies, activeLocale]);
  const productOptions = useMemo(() => products.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [products, activeLocale]);
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    setEditing(null);
    form.reset();
    form.setData({ supplier_id: '', requested_date: todayStr, needed_by_date: '', currency: currencies[0]?.code || '', reference: '', notes: '', lock_version: 1 });
    setLines([{ product_id: products[0]?.id || '', unit_of_measure_id: products[0]?.unit_of_measure_id || '', description: '', quantity: 1, estimated_unit_price: 0 }]);
    setShowModal(true);
  }

  function openEdit(request: PurchaseRequestRow) {
    setEditing(request);
    form.setData({
      supplier_id: request.supplier_id || '',
      requested_date: request.requested_date,
      needed_by_date: request.needed_by_date || '',
      currency: request.currency,
      reference: request.reference || '',
      notes: request.notes || '',
      lock_version: request.lock_version,
    });
    setLines(request.lines.map((line) => ({
      id: line.id,
      product_id: line.product_id,
      unit_of_measure_id: line.unit_of_measure_id,
      description: line.description || '',
      quantity: line.quantity_e6 / 1_000_000,
      estimated_unit_price: line.estimated_unit_price_minor / 100,
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
    setLines((prev) => [...prev, { product_id: products[0]?.id || '', unit_of_measure_id: products[0]?.unit_of_measure_id || '', description: '', quantity: 1, estimated_unit_price: 0 }]);
  }

  function removeLine(index: number) {
    if (lines.length <= 1) return;
    setLines((prev) => prev.filter((_, i) => i !== index));
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      supplier_id: data.supplier_id || null,
      lines: lines.map((line) => ({
        product_id: line.product_id,
        unit_of_measure_id: line.unit_of_measure_id,
        description: line.description,
        quantity_e6: Math.round(Number(line.quantity) * 1_000_000),
        estimated_unit_price_minor: Math.round(Number(line.estimated_unit_price || 0) * 100),
      })),
    }));

    const onSuccess = () => {
      setShowModal(false);
      setTableReloadToken((value) => value + 1);
    };
    if (editing) {
      form.put(`/purchasing/requests/${editing.id}`, { preserveScroll: true, onSuccess });
    } else {
      form.post('/purchasing/requests', { preserveScroll: true, onSuccess });
    }
  }

  function runAction(request: PurchaseRequestRow, action: 'submit' | 'approve' | 'reject' | 'cancel') {
    if (!confirm(pageDict.confirmAction)) return;
    router.post(`/purchasing/requests/${request.id}/${action}`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  function openConvert(request: PurchaseRequestRow) {
    const prices: Record<string, number> = {};
    request.lines.forEach((line) => {
      prices[line.id] = line.estimated_unit_price_minor > 0 ? line.estimated_unit_price_minor / 100 : 0;
    });
    convertForm.setData({ supplier_id: request.supplier_id || '', order_date: todayStr, expected_receipt_date: '', prices });
    setConvertTarget(request);
  }

  function submitConvert(event: FormEvent) {
    event.preventDefault();
    if (!convertTarget) return;
    convertForm.transform((data) => {
      const unitPriceMinorByLine: Record<string, number> = {};
      Object.entries(data.prices).forEach(([lineId, majorPrice]) => {
        unitPriceMinorByLine[lineId] = Math.round(Number(majorPrice) * 100);
      });
      return {
        supplier_id: data.supplier_id || null,
        order_date: data.order_date,
        expected_receipt_date: data.expected_receipt_date || null,
        unit_price_minor_by_line: unitPriceMinorByLine,
      };
    });

    convertForm.post(`/purchasing/requests/${convertTarget.id}/convert`, {
      preserveScroll: true,
      onSuccess: () => {
        setConvertTarget(null);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  const columns = useMemo(() => [
    { data: 'number', name: 'purchase_request.number', title: pageDict.number },
    { data: 'supplier', name: 'supplier', title: pageDict.supplier, orderable: false, searchable: false },
    { data: 'requested_date', name: 'purchase_request.requested_date', title: pageDict.date },
    { data: 'total_minor', name: 'purchase_request.total_minor', title: pageDict.estimatedTotal, className: 'text-end' },
    { data: 'status', name: 'purchase_request.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    'purchase_request.number': (value: string | null) => <span className="font-mono text-sm font-bold text-blue-600">{value || pageDict.draftLabel}</span>,
    supplier: (_v: unknown, _t: unknown, row: PurchaseRequestRow) => <span className="font-medium">{row.supplier ? namePart(row.supplier.name, activeLocale) : pageDict.noSupplierYet}</span>,
    'purchase_request.total_minor': (value: number, _t: unknown, row: PurchaseRequestRow) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
    'purchase_request.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    actions: (_v: unknown, _t: unknown, row: PurchaseRequestRow) => (
      <div className="flex flex-wrap justify-end gap-2">
        {row.status === 'draft' && can('purchasing.edit') ? <Button variant="secondary" onClick={() => openEdit(row)}>{pageDict.edit}</Button> : null}
        {row.status === 'draft' && can('purchasing.submit') ? <Button variant="secondary" onClick={() => runAction(row, 'submit')}>{pageDict.submit}</Button> : null}
        {row.status === 'submitted' && can('purchasing.approve') ? <Button variant="secondary" onClick={() => runAction(row, 'approve')}>{pageDict.approve}</Button> : null}
        {row.status === 'submitted' && can('purchasing.approve') ? <Button variant="danger" onClick={() => runAction(row, 'reject')}>{pageDict.reject}</Button> : null}
        {(row.status === 'draft' || row.status === 'submitted') && can('purchasing.cancel') ? <Button variant="danger" onClick={() => runAction(row, 'cancel')}>{pageDict.cancel}</Button> : null}
        {row.status === 'approved' && can('purchasing.create') ? <Button onClick={() => openConvert(row)}>{pageDict.convert}</Button> : null}
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
    <AppLayout active="purchase-requests.index">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} actions={can('purchasing.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null} />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/purchasing/requests/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[2, 'desc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="purchase-requests-data-table"
          toolbar={toolbar}
        />
      </Card>

      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
          <div className="my-8 w-full max-w-3xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl">
            <h3 className="mb-4 text-base font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h3>
            <p className="mb-4 -mt-2 text-xs text-[var(--text-secondary)]">{pageDict.formHint}</p>
            <form onSubmit={submitForm} className="space-y-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SearchableSelect label={pageDict.preferredSupplier} value={form.data.supplier_id || null} onChange={(value) => form.setData('supplier_id', value || '')} options={supplierOptions} error={form.errors.supplier_id} />
                <DatePicker label={pageDict.date} value={form.data.requested_date} onChange={(value) => form.setData('requested_date', value || '')} required />
                <DatePicker label={pageDict.neededBy} value={form.data.needed_by_date} onChange={(value) => form.setData('needed_by_date', value || '')} />
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
                      <div className="w-full sm:w-32">
                        <label className="mb-1 block text-[10px] font-semibold text-[var(--text-muted)]">{pageDict.estimatedUnitPrice}</label>
                        <input type="number" step="0.01" min="0" value={line.estimated_unit_price} onChange={(event) => setLines((prev) => { const next = [...prev]; next[index] = { ...next[index], estimated_unit_price: parseFloat(event.target.value) || 0 }; return next; })} className="input" />
                      </div>
                      <button type="button" onClick={() => removeLine(index)} disabled={lines.length <= 1} title={pageDict.removeLine} aria-label={pageDict.removeLine} className="text-xs font-semibold text-rose-600 disabled:opacity-30">{pageDict.removeLine}</button>
                    </div>
                  ))}
                </div>
              </div>

              <div className="flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>{pageDict.cancelAction}</Button>
                <Button type="submit" disabled={form.processing}>{editing ? pageDict.update : pageDict.save}</Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {convertTarget ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
          <div className="my-8 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{pageDict.convertTitle}</h3>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{pageDict.convertHint}</p>
            <form onSubmit={submitConvert} className="mt-4 space-y-4">
              <SearchableSelect label={pageDict.supplier} value={convertForm.data.supplier_id || null} onChange={(value) => convertForm.setData('supplier_id', value || '')} options={supplierOptions} isClearable={false} required />
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <DatePicker label={pageDict.orderDate} value={convertForm.data.order_date} onChange={(value) => convertForm.setData('order_date', value || '')} />
                <DatePicker label={pageDict.expectedReceiptDate} value={convertForm.data.expected_receipt_date} onChange={(value) => convertForm.setData('expected_receipt_date', value || '')} />
              </div>

              <div className="space-y-2">
                <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{pageDict.finalPrices}</h4>
                {convertTarget.lines.map((line) => (
                  <div key={line.id} className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border)] p-2.5">
                    <span className="text-xs">{line.product ? namePart(line.product.name, activeLocale) : line.product_id}</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0.01"
                      value={convertForm.data.prices[line.id] ?? 0}
                      onChange={(event) => convertForm.setData('prices', { ...convertForm.data.prices, [line.id]: parseFloat(event.target.value) || 0 })}
                      className="input w-28"
                      required
                    />
                  </div>
                ))}
              </div>

              <div className="flex justify-end gap-2.5 pt-2">
                <Button type="button" variant="secondary" onClick={() => setConvertTarget(null)}>{pageDict.cancelAction}</Button>
                <Button type="submit" disabled={convertForm.processing}>{pageDict.confirmConvert}</Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
