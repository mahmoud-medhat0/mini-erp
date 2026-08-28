import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyRow, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Branch = { id: string; code: string; name: TranslatedName };
type Warehouse = { id: string; code: string; name: TranslatedName; branch?: Branch | null };
type UnitOfMeasure = { id: string; code: string; name: TranslatedName };
type Product = { id: string; code: string; name: TranslatedName; unit_of_measure_id: string; unit_of_measure?: UnitOfMeasure | null };
type StockCountLine = {
  id: string;
  line_no: number;
  product_id: string;
  expected_quantity_e6: number;
  counted_quantity_e6: number;
  variance_quantity_e6: number;
  unit_cost_minor?: number | null;
  notes?: string | null;
  product?: Product | null;
  unit_of_measure?: UnitOfMeasure | null;
};
type StockCount = {
  id: string;
  number?: string | null;
  count_date: string;
  warehouse_id: string;
  warehouse?: Warehouse | null;
  currency: string;
  status: string;
  reference?: string | null;
  notes?: string | null;
  lock_version: number;
  lines: StockCountLine[];
};
type PaginatedData<T> = { data: T[]; total: number };
type CountLineForm = { product_id: string; expected_input: string; counted_input: string; unit_cost_minor: string; notes: string };
type CountForm = {
  count_date: string;
  warehouse_id: string;
  currency: string;
  reference: string;
  notes: string;
  lock_version: number;
  lines: CountLineForm[];
};
type Props = SharedPageProps & {
  stockCounts: PaginatedData<StockCount>;
  warehouses: Warehouse[];
  products: Product[];
  currencies: CurrencyRow[];
  statuses: string[];
  filters: { search?: string; status?: string; warehouse_id?: string };
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function formatDate(value?: string | null): string {
  if (!value) return '';
  return String(value).includes('T') ? String(value).slice(0, 10) : String(value);
}

function formatQuantityE6(quantityE6: number): string {
  const sign = quantityE6 < 0 ? '-' : '';
  const absolute = Math.abs(Math.trunc(quantityE6));
  const whole = Math.floor(absolute / 1000000).toLocaleString();
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${sign}${whole}${fraction ? `.${fraction}` : ''}`;
}

function parseQuantityToE6(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (!/^\d+(\.\d{0,6})?$/.test(normalized)) return 0;

  const [wholeRaw, fractionRaw = ''] = normalized.split('.');
  const whole = Number(wholeRaw || '0');
  const fraction = Number(fractionRaw.padEnd(6, '0').slice(0, 6) || '0');

  if (!Number.isSafeInteger(whole) || !Number.isSafeInteger(fraction)) return 0;

  return whole * 1000000 + fraction;
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'posted') return 'ok';
  if (value === 'cancelled') return 'danger';
  if (value === 'approved') return 'warning';
  if (value === 'submitted') return 'info';

  return 'muted';
}

export default function StockCountsIndex({ locale, stockCounts, warehouses, products, currencies, statuses, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockCounts;
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCountStock = can('inventory.count');
  const canApproveInventory = can('inventory.approve');
  const canPostInventory = can('inventory.post') && can('view_financials');
  const defaultCurrency = currencies[0]?.code || '';
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<StockCount | null>(null);

  const form = useForm<CountForm>({
    count_date: today(),
    warehouse_id: warehouses[0]?.id || '',
    currency: defaultCurrency,
    reference: '',
    notes: '',
    lock_version: 1,
    lines: [{ product_id: '', expected_input: '', counted_input: '0', unit_cost_minor: '', notes: '' }],
  });

  const warehouseOptions = useMemo(() => warehouses.map((warehouse) => ({
    value: warehouse.id,
    label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
    sublabel: warehouse.branch ? `${warehouse.branch.code} - ${getLocalizedName(warehouse.branch.name, locale)}` : undefined,
  })), [warehouses, locale]);

  const productOptions = useMemo(() => products.map((product) => ({
    value: product.id,
    label: `${product.code} - ${getLocalizedName(product.name, locale)}`,
    sublabel: product.unit_of_measure?.code,
  })), [products, locale]);

  const currencyOptions = useMemo(() => currencies.map((currency) => ({
    value: currency.code,
    label: `${currency.code} - ${getLocalizedName(currency.name, locale)}`,
    badge: currency.symbol || currency.code,
  })), [currencies, locale]);

  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const activeFilterCount = [search, status, warehouseId].filter(Boolean).length;

  function labelForStatus(value: string): string {
    return pageDict.statuses[value as keyof typeof pageDict.statuses] || value;
  }

  function applyFilters() {
    router.get('/inventory/stock-counts', { search, status, warehouse_id: warehouseId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setWarehouseId('');
    router.get('/inventory/stock-counts', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      count_date: today(),
      warehouse_id: warehouses[0]?.id || '',
      currency: defaultCurrency,
      reference: '',
      notes: '',
      lock_version: 1,
      lines: [{ product_id: '', expected_input: '', counted_input: '0', unit_cost_minor: '', notes: '' }],
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEdit(count: StockCount) {
    setEditing(count);
    form.setData({
      count_date: formatDate(count.count_date),
      warehouse_id: count.warehouse_id,
      currency: count.currency,
      reference: count.reference || '',
      notes: count.notes || '',
      lock_version: count.lock_version,
      lines: count.lines.map((line) => ({
        product_id: line.product_id,
        expected_input: formatQuantityE6(line.expected_quantity_e6),
        counted_input: formatQuantityE6(line.counted_quantity_e6),
        unit_cost_minor: line.unit_cost_minor ? String(line.unit_cost_minor) : '',
        notes: line.notes || '',
      })),
    });
    form.clearErrors();
    setShowForm(true);
  }

  function setLine(index: number, patch: Partial<CountLineForm>) {
    form.setData('lines', form.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  function addLine() {
    form.setData('lines', [...form.data.lines, { product_id: '', expected_input: '', counted_input: '0', unit_cost_minor: '', notes: '' }]);
  }

  function removeLine(index: number) {
    form.setData('lines', form.data.lines.filter((_, lineIndex) => lineIndex !== index));
  }

  function submitForm(event: React.FormEvent) {
    event.preventDefault();
    const payload = {
      ...form.data,
      lines: form.data.lines.map((line) => ({
        product_id: line.product_id,
        expected_quantity_e6: line.expected_input === '' ? null : parseQuantityToE6(line.expected_input),
        counted_quantity_e6: parseQuantityToE6(line.counted_input),
        unit_cost_minor: line.unit_cost_minor === '' ? null : Number(line.unit_cost_minor),
        notes: line.notes,
      })),
    };

    if (editing) {
      router.put(`/inventory/stock-counts/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/inventory/stock-counts', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function transition(count: StockCount, action: 'submit' | 'approve' | 'post' | 'cancel') {
    const message = pageDict.confirmations[action as keyof typeof pageDict.confirmations];
    if (message && !confirm(message)) return;

    router.post(`/inventory/stock-counts/${count.id}/${action}`, {}, { preserveScroll: true });
  }

  const isStockCountActionable = (count: StockCount) => ['draft', 'submitted', 'approved'].includes(count.status);

  const hasAvailableStockCountAction = (count: StockCount) => (
    count.status === 'draft'
      ? canCountStock || canApproveInventory
      : count.status === 'submitted'
        ? canApproveInventory || canCountStock
        : count.status === 'approved'
          ? canPostInventory || canCountStock
          : false
  );

  const getStockCountActionState = (count: StockCount) => {
    if (hasAvailableStockCountAction(count)) return null;

    return isStockCountActionable(count) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="stock-counts.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canCountStock ? <Button onClick={openCreate}>{pageDict.createCount}</Button> : null}
      />

      <Card className="mb-5 p-4">
        <div className="grid gap-3 md:grid-cols-[1.4fr_1fr_1fr_auto_auto]">
          <input className="erp-input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect value={status} onChange={(value) => setStatus(value || '')} options={statusOptions} placeholder={pageDict.allStatuses} />
          <SearchableSelect value={warehouseId} onChange={(value) => setWarehouseId(value || '')} options={warehouseOptions} placeholder={pageDict.allWarehouses} />
          <Button onClick={applyFilters}>{pageDict.filter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilters}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-4">
          <form onSubmit={submitForm} className="space-y-4">
            <div className="grid gap-3 md:grid-cols-5">
              <DatePicker label={pageDict.date} value={form.data.count_date} onChange={(value) => form.setData('count_date', value || '')} />
              <SearchableSelect value={form.data.warehouse_id} onChange={(value) => form.setData('warehouse_id', value || '')} options={warehouseOptions} placeholder={pageDict.warehouse} />
              <SearchableSelect value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} options={currencyOptions} placeholder={pageDict.currency} />
              <input className="erp-input" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} placeholder={pageDict.reference} />
              <input className="erp-input" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} placeholder={pageDict.notes} />
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.lines}</h2>
                <Button variant="secondary" onClick={addLine}>{pageDict.addLine}</Button>
              </div>
              {form.data.lines.map((line, index) => (
                <div key={index} className="grid gap-3 rounded-md border border-[var(--border)] bg-[var(--background)] p-3 md:grid-cols-[1.3fr_0.8fr_0.8fr_0.8fr_1fr_auto]">
                  <SearchableSelect value={line.product_id} onChange={(value) => setLine(index, { product_id: value || '' })} options={productOptions} placeholder={pageDict.product} />
                  <input className="erp-input" value={line.expected_input} onChange={(event) => setLine(index, { expected_input: event.target.value })} placeholder={pageDict.expected} />
                  <input className="erp-input" value={line.counted_input} onChange={(event) => setLine(index, { counted_input: event.target.value })} placeholder={pageDict.counted} />
                  <input className="erp-input" value={line.unit_cost_minor} onChange={(event) => setLine(index, { unit_cost_minor: event.target.value })} placeholder={pageDict.unitCostMinor} />
                  <input className="erp-input" value={line.notes} onChange={(event) => setLine(index, { notes: event.target.value })} placeholder={pageDict.lineNotes} />
                  <Button variant="secondary" onClick={() => removeLine(index)} disabled={form.data.lines.length === 1}>{pageDict.removeLine}</Button>
                </div>
              ))}
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
              <Button type="submit" disabled={form.processing}>{form.processing ? pageDict.saving : pageDict.saveCount}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {stockCounts.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.number}</th>
                <th className={tableClasses.th}>{pageDict.date}</th>
                <th className={tableClasses.th}>{pageDict.warehouse}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.lines}</th>
                <th className={tableClasses.th}>{pageDict.varianceLines}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {stockCounts.data.map((count) => {
                const actionState = getStockCountActionState(count);

                return (
                  <tr key={count.id}>
                    <td className={tableClasses.td}>{count.number || pageDict.draftNumber}</td>
                    <td className={tableClasses.td}>{formatDate(count.count_date)}</td>
                    <td className={tableClasses.td}>{count.warehouse ? `${count.warehouse.code} - ${getLocalizedName(count.warehouse.name, locale)}` : accDict.notAvailable}</td>
                    <td className={tableClasses.td}><StatusBadge tone={statusTone(count.status)}>{labelForStatus(count.status)}</StatusBadge></td>
                    <td className={tableClasses.td}>{count.lines.length}</td>
                    <td className={tableClasses.td}>{count.lines.filter((line) => Number(line.variance_quantity_e6) !== 0).length}</td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        {count.status === 'draft' && canCountStock ? (
                          <button type="button" onClick={() => openEdit(count)} title={pageDict.edit} aria-label={pageDict.edit} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.edit}</button>
                        ) : null}
                        {count.status === 'draft' && canCountStock ? (
                          <button type="button" onClick={() => transition(count, 'submit')} title={pageDict.submit} aria-label={pageDict.submit} className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40">{pageDict.submit}</button>
                        ) : null}
                        {['draft', 'submitted'].includes(count.status) && canApproveInventory ? (
                          <button type="button" onClick={() => transition(count, 'approve')} title={pageDict.approve} aria-label={pageDict.approve} className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40">{pageDict.approve}</button>
                        ) : null}
                        {count.status === 'approved' && canPostInventory ? (
                          <button type="button" onClick={() => transition(count, 'post')} title={pageDict.post} aria-label={pageDict.post} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.post}</button>
                        ) : null}
                        {isStockCountActionable(count) && canCountStock ? (
                          <button type="button" onClick={() => transition(count, 'cancel')} title={pageDict.cancelCount} aria-label={pageDict.cancelCount} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{pageDict.cancelCount}</button>
                        ) : null}
                        {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
