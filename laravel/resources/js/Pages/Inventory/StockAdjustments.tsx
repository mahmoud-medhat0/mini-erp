import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyRow, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type Branch = { id: string; code: string; name: TranslatedName };
type Warehouse = { id: string; code: string; name: TranslatedName; branch?: Branch | null };
type UnitOfMeasure = { id: string; code: string; name: TranslatedName };
type Product = { id: string; code: string; name: TranslatedName; unit_of_measure_id: string; unit_of_measure?: UnitOfMeasure | null };

type StockAdjustmentLine = {
  id: string;
  line_no: number;
  product_id: string;
  quantity_delta_e6: number;
  unit_cost_minor?: number | null;
  value_delta_minor?: number | null;
  reason?: string | null;
  product?: Product | null;
  unit_of_measure?: UnitOfMeasure | null;
};

type StockAdjustment = {
  id: string;
  number?: string | null;
  adjustment_date: string;
  warehouse_id: string;
  warehouse?: Warehouse | null;
  currency: string;
  status: string;
  reference?: string | null;
  reason?: string | null;
  total_value_delta_minor: number;
  lock_version: number;
  lines: StockAdjustmentLine[];
};

type PaginatedData<T> = { data: T[]; total: number };
type AdjustmentLineForm = { product_id: string; quantity_input: string; unit_cost_minor: string; reason: string };
type AdjustmentForm = {
  adjustment_date: string;
  warehouse_id: string;
  currency: string;
  reference: string;
  reason: string;
  lock_version: number;
  lines: AdjustmentLineForm[];
};

type Props = SharedPageProps & {
  adjustments: PaginatedData<StockAdjustment>;
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

function parseSignedQuantityToE6(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (!/^-?\d+(\.\d{0,6})?$/.test(normalized)) return 0;

  const sign = normalized.startsWith('-') ? -1 : 1;
  const clean = normalized.replace('-', '');
  const [wholeRaw, fractionRaw = ''] = clean.split('.');
  const whole = Number(wholeRaw || '0');
  const fraction = Number(fractionRaw.padEnd(6, '0').slice(0, 6) || '0');

  if (!Number.isSafeInteger(whole) || !Number.isSafeInteger(fraction)) return 0;

  return sign * (whole * 1000000 + fraction);
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'posted') return 'ok';
  if (value === 'cancelled') return 'danger';
  if (value === 'approved') return 'warning';
  if (value === 'submitted') return 'info';

  return 'muted';
}

export default function StockAdjustmentsIndex({ locale, adjustments, warehouses, products, currencies, statuses, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockAdjustments;
  const accDict = dict.app.accounting;
  const can = useCan();
  const canAdjustStock = can('inventory.adjust');
  const canApproveInventory = can('inventory.approve');
  const canPostInventory = can('inventory.post') && can('view_financials');
  const defaultCurrency = currencies[0]?.code || '';
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<StockAdjustment | null>(null);

  const form = useForm<AdjustmentForm>({
    adjustment_date: today(),
    warehouse_id: warehouses[0]?.id || '',
    currency: defaultCurrency,
    reference: '',
    reason: '',
    lock_version: 1,
    lines: [{ product_id: '', quantity_input: '1', unit_cost_minor: '', reason: '' }],
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
    router.get('/inventory/adjustments', { search, status, warehouse_id: warehouseId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setWarehouseId('');
    router.get('/inventory/adjustments', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      adjustment_date: today(),
      warehouse_id: warehouses[0]?.id || '',
      currency: defaultCurrency,
      reference: '',
      reason: '',
      lock_version: 1,
      lines: [{ product_id: '', quantity_input: '1', unit_cost_minor: '', reason: '' }],
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEdit(adjustment: StockAdjustment) {
    setEditing(adjustment);
    form.setData({
      adjustment_date: formatDate(adjustment.adjustment_date),
      warehouse_id: adjustment.warehouse_id,
      currency: adjustment.currency,
      reference: adjustment.reference || '',
      reason: adjustment.reason || '',
      lock_version: adjustment.lock_version,
      lines: adjustment.lines.map((line) => ({
        product_id: line.product_id,
        quantity_input: formatQuantityE6(line.quantity_delta_e6),
        unit_cost_minor: line.unit_cost_minor ? String(line.unit_cost_minor) : '',
        reason: line.reason || '',
      })),
    });
    form.clearErrors();
    setShowForm(true);
  }

  function setLine(index: number, patch: Partial<AdjustmentLineForm>) {
    form.setData('lines', form.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  function addLine() {
    form.setData('lines', [...form.data.lines, { product_id: '', quantity_input: '1', unit_cost_minor: '', reason: '' }]);
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
        quantity_delta_e6: parseSignedQuantityToE6(line.quantity_input),
        unit_cost_minor: line.unit_cost_minor === '' ? null : Number(line.unit_cost_minor),
        reason: line.reason,
      })),
    };

    if (editing) {
      router.put(`/inventory/adjustments/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/inventory/adjustments', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function transition(adjustment: StockAdjustment, action: 'submit' | 'approve' | 'post' | 'cancel') {
    const message = pageDict.confirmations[action as keyof typeof pageDict.confirmations];
    if (message && !confirm(message)) return;

    router.post(`/inventory/adjustments/${adjustment.id}/${action}`, {}, { preserveScroll: true });
  }

  const isStockAdjustmentActionable = (adjustment: StockAdjustment) => ['draft', 'submitted', 'approved'].includes(adjustment.status);

  const hasAvailableStockAdjustmentAction = (adjustment: StockAdjustment) => (
    adjustment.status === 'draft'
      ? canAdjustStock || canApproveInventory
      : adjustment.status === 'submitted'
        ? canApproveInventory || canAdjustStock
        : adjustment.status === 'approved'
          ? canPostInventory || canAdjustStock
          : false
  );

  const getStockAdjustmentActionState = (adjustment: StockAdjustment) => {
    if (hasAvailableStockAdjustmentAction(adjustment)) return null;

    return isStockAdjustmentActionable(adjustment) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="stock-adjustments.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canAdjustStock ? <Button onClick={openCreate}>{pageDict.createAdjustment}</Button> : null}
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
              <DatePicker label={pageDict.date} value={form.data.adjustment_date} onChange={(value) => form.setData('adjustment_date', value || '')} />
              <SearchableSelect value={form.data.warehouse_id} onChange={(value) => form.setData('warehouse_id', value || '')} options={warehouseOptions} placeholder={pageDict.warehouse} />
              <SearchableSelect value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} options={currencyOptions} placeholder={pageDict.currency} />
              <input className="erp-input" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} placeholder={pageDict.reference} />
              <input className="erp-input" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} placeholder={pageDict.reason} />
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.lines}</h2>
                <Button variant="secondary" onClick={addLine}>{pageDict.addLine}</Button>
              </div>
              {form.data.lines.map((line, index) => (
                <div key={index} className="grid gap-3 rounded-md border border-[var(--border)] bg-[var(--background)] p-3 md:grid-cols-[1.4fr_0.8fr_0.8fr_1fr_auto]">
                  <SearchableSelect value={line.product_id} onChange={(value) => setLine(index, { product_id: value || '' })} options={productOptions} placeholder={pageDict.product} />
                  <input className="erp-input" value={line.quantity_input} onChange={(event) => setLine(index, { quantity_input: event.target.value })} placeholder={pageDict.quantityDelta} />
                  <input className="erp-input" value={line.unit_cost_minor} onChange={(event) => setLine(index, { unit_cost_minor: event.target.value })} placeholder={pageDict.unitCostMinor} />
                  <input className="erp-input" value={line.reason} onChange={(event) => setLine(index, { reason: event.target.value })} placeholder={pageDict.lineReason} />
                  <Button variant="secondary" onClick={() => removeLine(index)} disabled={form.data.lines.length === 1}>{pageDict.removeLine}</Button>
                </div>
              ))}
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
              <Button type="submit" disabled={form.processing}>{form.processing ? pageDict.saving : pageDict.saveAdjustment}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {adjustments.data.length === 0 ? (
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
                <th className={tableClasses.th}>{pageDict.totalValueDelta}</th>
                <th className={tableClasses.th}>{pageDict.lines}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {adjustments.data.map((adjustment) => {
                const actionState = getStockAdjustmentActionState(adjustment);

                return (
                  <tr key={adjustment.id}>
                    <td className={tableClasses.td}>{adjustment.number || pageDict.draftNumber}</td>
                    <td className={tableClasses.td}>{formatDate(adjustment.adjustment_date)}</td>
                    <td className={tableClasses.td}>{adjustment.warehouse ? `${adjustment.warehouse.code} - ${getLocalizedName(adjustment.warehouse.name, locale)}` : accDict.notAvailable}</td>
                    <td className={tableClasses.td}><StatusBadge tone={statusTone(adjustment.status)}>{labelForStatus(adjustment.status)}</StatusBadge></td>
                    <td className={`${tableClasses.td} accounting-amount`}>{formatMoney(adjustment.total_value_delta_minor, adjustment.currency)}</td>
                    <td className={tableClasses.td}>{adjustment.lines.length}</td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        {adjustment.status === 'draft' && canAdjustStock ? (
                          <button type="button" onClick={() => openEdit(adjustment)} title={pageDict.edit} aria-label={pageDict.edit} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.edit}</button>
                        ) : null}
                        {adjustment.status === 'draft' && canAdjustStock ? (
                          <button type="button" onClick={() => transition(adjustment, 'submit')} title={pageDict.submit} aria-label={pageDict.submit} className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40">{pageDict.submit}</button>
                        ) : null}
                        {['draft', 'submitted'].includes(adjustment.status) && canApproveInventory ? (
                          <button type="button" onClick={() => transition(adjustment, 'approve')} title={pageDict.approve} aria-label={pageDict.approve} className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40">{pageDict.approve}</button>
                        ) : null}
                        {adjustment.status === 'approved' && canPostInventory ? (
                          <button type="button" onClick={() => transition(adjustment, 'post')} title={pageDict.post} aria-label={pageDict.post} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.post}</button>
                        ) : null}
                        {isStockAdjustmentActionable(adjustment) && canAdjustStock ? (
                          <button type="button" onClick={() => transition(adjustment, 'cancel')} title={pageDict.cancelAdjustment} aria-label={pageDict.cancelAdjustment} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{pageDict.cancelAdjustment}</button>
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
