import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type Branch = {
  id: string;
  code: string;
  name: TranslatedName;
};

type Warehouse = {
  id: string;
  code: string;
  name: TranslatedName;
  branch?: Branch | null;
};

type UnitOfMeasure = {
  id: string;
  code: string;
  name: TranslatedName;
};

type Product = {
  id: string;
  code: string;
  name: TranslatedName;
  unit_of_measure_id: string;
  unit_of_measure?: UnitOfMeasure | null;
};

type StockTransferLine = {
  id: string;
  line_no: number;
  product_id: string;
  unit_of_measure_id: string;
  quantity_e6: number;
  issued_quantity_e6: number;
  received_quantity_e6: number;
  issued_value_minor: number;
  notes?: string | null;
  product?: Product | null;
  unit_of_measure?: UnitOfMeasure | null;
};

type StockTransfer = {
  id: string;
  number?: string | null;
  transfer_date: string;
  source_warehouse_id: string;
  destination_warehouse_id: string;
  source_warehouse?: Warehouse | null;
  destination_warehouse?: Warehouse | null;
  status: string;
  reference?: string | null;
  reason?: string | null;
  lock_version: number;
  lines: StockTransferLine[];
};

type PaginatedData<T> = {
  data: T[];
  total: number;
};

type TransferLineForm = {
  product_id: string;
  unit_of_measure_id: string;
  quantity_input: string;
  notes: string;
};

type TransferForm = {
  transfer_date: string;
  source_warehouse_id: string;
  destination_warehouse_id: string;
  reference: string;
  reason: string;
  lock_version: number;
  lines: TransferLineForm[];
};

type StockTransfersProps = SharedPageProps & {
  transfers: PaginatedData<StockTransfer>;
  warehouses: Warehouse[];
  products: Product[];
  statuses: string[];
  filters: {
    search?: string;
    status?: string;
    warehouse_id?: string;
  };
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

function lineRemaining(line: StockTransferLine): number {
  return Math.max(0, Number(line.issued_quantity_e6 || 0) - Number(line.received_quantity_e6 || 0));
}

export default function StockTransfersIndex({
  locale,
  transfers,
  warehouses,
  products,
  statuses,
  filters,
}: StockTransfersProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockTransfers;
  const accDict = dict.app.accounting;
  const can = useCan();
  const canTransferStock = can('inventory.transfer');
  const canApproveInventory = can('inventory.approve');
  const canIssueInventory = can('inventory.post');
  const canReceiveInventory = can('inventory.receive');

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
  const [showTransferForm, setShowTransferForm] = useState(false);
  const [editingTransfer, setEditingTransfer] = useState<StockTransfer | null>(null);
  const [receivingTransfer, setReceivingTransfer] = useState<StockTransfer | null>(null);
  const [receiptDate, setReceiptDate] = useState(today());
  const [receiveQuantities, setReceiveQuantities] = useState<Record<string, string>>({});

  const transferForm = useForm<TransferForm>({
    transfer_date: today(),
    source_warehouse_id: warehouses[0]?.id || '',
    destination_warehouse_id: warehouses[1]?.id || '',
    reference: '',
    reason: '',
    lock_version: 1,
    lines: [{ product_id: '', unit_of_measure_id: '', quantity_input: '1', notes: '' }],
  });

  const warehouseOptions = useMemo(
    () => warehouses.map((warehouse) => ({
      value: warehouse.id,
      label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
      sublabel: warehouse.branch ? `${warehouse.branch.code} - ${getLocalizedName(warehouse.branch.name, locale)}` : undefined,
    })),
    [warehouses, locale],
  );

  const productOptions = useMemo(
    () => products.map((product) => ({
      value: product.id,
      label: `${product.code} - ${getLocalizedName(product.name, locale)}`,
      sublabel: product.unit_of_measure?.code,
    })),
    [products, locale],
  );

  const statusOptions = statuses.map((item) => ({
    value: item,
    label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item,
  }));

  function statusLabel(value: string): string {
    return pageDict.statuses[value as keyof typeof pageDict.statuses] || value;
  }

  function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
    if (value === 'received') return 'ok';
    if (value === 'cancelled') return 'danger';
    if (value === 'issued' || value === 'partially_received') return 'warning';
    if (value === 'approved' || value === 'submitted') return 'info';

    return 'muted';
  }
  const activeFilterCount = [search, status, warehouseId].filter(Boolean).length;

  function applyFilters() {
    router.get('/inventory/transfers', { search, status, warehouse_id: warehouseId }, { preserveState: true, preserveScroll: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setWarehouseId('');
    router.get('/inventory/transfers', {}, { preserveState: true, preserveScroll: true });
  }

  function openCreateTransfer() {
    setEditingTransfer(null);
    transferForm.setData({
      transfer_date: today(),
      source_warehouse_id: warehouses[0]?.id || '',
      destination_warehouse_id: warehouses[1]?.id || '',
      reference: '',
      reason: '',
      lock_version: 1,
      lines: [{ product_id: '', unit_of_measure_id: '', quantity_input: '1', notes: '' }],
    });
    transferForm.clearErrors();
    setShowTransferForm(true);
  }

  function openEditTransfer(transfer: StockTransfer) {
    setEditingTransfer(transfer);
    transferForm.setData({
      transfer_date: formatDate(transfer.transfer_date),
      source_warehouse_id: transfer.source_warehouse_id,
      destination_warehouse_id: transfer.destination_warehouse_id,
      reference: transfer.reference || '',
      reason: transfer.reason || '',
      lock_version: transfer.lock_version,
      lines: transfer.lines.map((line) => ({
        product_id: line.product_id,
        unit_of_measure_id: line.unit_of_measure_id,
        quantity_input: formatQuantityE6(line.quantity_e6),
        notes: line.notes || '',
      })),
    });
    transferForm.clearErrors();
    setShowTransferForm(true);
  }

  function setLine(index: number, patch: Partial<TransferLineForm>) {
    const next = transferForm.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line));
    transferForm.setData('lines', next);
  }

  function addLine() {
    transferForm.setData('lines', [
      ...transferForm.data.lines,
      { product_id: '', unit_of_measure_id: '', quantity_input: '1', notes: '' },
    ]);
  }

  function removeLine(index: number) {
    transferForm.setData('lines', transferForm.data.lines.filter((_, lineIndex) => lineIndex !== index));
  }

  function handleProductSelect(index: number, productId: string) {
    const product = products.find((item) => item.id === productId);
    setLine(index, {
      product_id: productId,
      unit_of_measure_id: product?.unit_of_measure_id || '',
    });
  }

  function submitTransfer(event: React.FormEvent) {
    event.preventDefault();
    const payload = {
      ...transferForm.data,
      lines: transferForm.data.lines.map((line) => ({
        product_id: line.product_id,
        unit_of_measure_id: line.unit_of_measure_id,
        quantity_e6: parseQuantityToE6(line.quantity_input),
        notes: line.notes,
      })),
    };

    if (editingTransfer) {
      router.put(`/inventory/transfers/${editingTransfer.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => setShowTransferForm(false),
      });
      return;
    }

    router.post('/inventory/transfers', payload, {
      preserveScroll: true,
      onSuccess: () => setShowTransferForm(false),
    });
  }

  function postAction(transfer: StockTransfer, action: 'submit' | 'approve' | 'issue' | 'cancel', message: string) {
    if (!confirm(message)) return;
    const payload = action === 'issue' ? { confirm_action: 'ISSUE_STOCK_TRANSFER' } : {};
    router.post(`/inventory/transfers/${transfer.id}/${action}`, payload, { preserveScroll: true });
  }

  function openReceivePanel(transfer: StockTransfer) {
    const quantities: Record<string, string> = {};
    transfer.lines.forEach((line) => {
      const remaining = lineRemaining(line);
      if (remaining > 0) {
        quantities[line.id] = formatQuantityE6(remaining);
      }
    });
    setReceivingTransfer(transfer);
    setReceiptDate(today());
    setReceiveQuantities(quantities);
  }

  function receiveRemaining(transfer: StockTransfer) {
    if (!confirm(pageDict.confirmReceive)) return;

    router.post(`/inventory/transfers/${transfer.id}/receive`, {
      confirm_action: 'RECEIVE_STOCK_TRANSFER',
      receipt_date: today(),
      lines: [],
    }, { preserveScroll: true });
  }

  function receiveSelected(event: React.FormEvent) {
    event.preventDefault();
    if (!receivingTransfer) return;

    const lines = receivingTransfer.lines
      .map((line) => ({
        stock_transfer_line_id: line.id,
        quantity_e6: parseQuantityToE6(receiveQuantities[line.id] || '0'),
      }))
      .filter((line) => line.quantity_e6 > 0);

    router.post(`/inventory/transfers/${receivingTransfer.id}/receive`, {
      confirm_action: 'RECEIVE_STOCK_TRANSFER',
      receipt_date: receiptDate,
      lines,
    }, {
      preserveScroll: true,
      onSuccess: () => setReceivingTransfer(null),
    });
  }

  const isStockTransferActionable = (transfer: StockTransfer) => (
    ['draft', 'submitted', 'approved', 'issued', 'partially_received'].includes(transfer.status)
  );

  const hasAvailableStockTransferAction = (transfer: StockTransfer) => (
    transfer.status === 'draft'
      ? canTransferStock || canApproveInventory
      : transfer.status === 'submitted'
        ? canApproveInventory || canTransferStock
        : transfer.status === 'approved'
          ? canIssueInventory || canTransferStock
          : ['issued', 'partially_received'].includes(transfer.status)
            ? canReceiveInventory
            : false
  );

  const getStockTransferActionState = (transfer: StockTransfer) => {
    if (hasAvailableStockTransferAction(transfer)) return null;

    return isStockTransferActionable(transfer) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="stock-transfers.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canTransferStock ? (
            <Button onClick={openCreateTransfer}>
              <svg className="me-2 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              {pageDict.createTransfer}
            </Button>
          ) : null
        }
      />

      <div className="space-y-5">
        <Card className="p-4">
          <div className="grid gap-3 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto] lg:items-end">
            <div>
              <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.search}</label>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="h-[42px] w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
              />
            </div>
            <SearchableSelect
              label={pageDict.status}
              options={statusOptions}
              value={status}
              onChange={(value) => setStatus(value || '')}
              placeholder={pageDict.allStatuses}
            />
            <SearchableSelect
              label={pageDict.source}
              options={warehouseOptions}
              value={warehouseId}
              onChange={(value) => setWarehouseId(value || '')}
              placeholder={pageDict.allWarehouses}
            />
            <Button onClick={applyFilters}>
              <svg className="me-2 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18M6 10h12M10 16h4" />
              </svg>
              {pageDict.filter}
            </Button>
            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilters}</Button>
          </div>
        </Card>

        {showTransferForm ? (
          <Card className="p-5">
            <form onSubmit={submitTransfer} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {editingTransfer ? pageDict.editTransfer : pageDict.createTransfer}
                </h2>
                <Button variant="secondary" onClick={() => setShowTransferForm(false)}>{pageDict.cancel}</Button>
              </div>

              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <DatePicker
                  label={pageDict.date}
                  value={transferForm.data.transfer_date}
                  onChange={(value) => transferForm.setData('transfer_date', value || '')}
                  required
                />
                <SearchableSelect
                  label={pageDict.source}
                  options={warehouseOptions}
                  value={transferForm.data.source_warehouse_id}
                  onChange={(value) => transferForm.setData('source_warehouse_id', value || '')}
                  isClearable={false}
                  error={transferForm.errors.source_warehouse_id}
                />
                <SearchableSelect
                  label={pageDict.destination}
                  options={warehouseOptions}
                  value={transferForm.data.destination_warehouse_id}
                  onChange={(value) => transferForm.setData('destination_warehouse_id', value || '')}
                  isClearable={false}
                  error={transferForm.errors.destination_warehouse_id}
                />
                <div>
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.reference}</label>
                  <input
                    value={transferForm.data.reference}
                    onChange={(event) => transferForm.setData('reference', event.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                  />
                </div>
                <div className="md:col-span-2 xl:col-span-4">
                  <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.reason}</label>
                  <textarea
                    value={transferForm.data.reason}
                    onChange={(event) => transferForm.setData('reason', event.target.value)}
                    className="min-h-20 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2.5 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                  />
                </div>
              </div>

              <div className="space-y-3">
                <div className="flex items-center justify-between gap-3">
                  <h3 className="m-0 text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.lines}</h3>
                  <Button variant="secondary" onClick={addLine}>{pageDict.addLine}</Button>
                </div>
                {transferForm.data.lines.map((line, index) => (
                  <div key={index} className="grid gap-3 rounded-md border border-[var(--border)] bg-[var(--background)] p-3 md:grid-cols-[minmax(0,1fr)_160px_120px_auto] md:items-end">
                    <SearchableSelect
                      label={pageDict.product}
                      options={productOptions}
                      value={line.product_id}
                      onChange={(value) => handleProductSelect(index, value || '')}
                      isClearable={false}
                    />
                    <div>
                      <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.quantity}</label>
                      <input
                        value={line.quantity_input}
                        onChange={(event) => setLine(index, { quantity_input: event.target.value })}
                        inputMode="decimal"
                        className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2.5 text-sm font-mono text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                      />
                    </div>
                    <div>
                      <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.unit}</label>
                      <span className="flex h-[42px] items-center rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 text-xs font-bold text-[var(--text-secondary)]">
                        {products.find((product) => product.id === line.product_id)?.unit_of_measure?.code || accDict.notAvailable}
                      </span>
                    </div>
                    <Button variant="secondary" onClick={() => removeLine(index)} disabled={transferForm.data.lines.length === 1}>
                      {pageDict.removeLine}
                    </Button>
                  </div>
                ))}
              </div>

              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setShowTransferForm(false)}>{pageDict.cancel}</Button>
                <Button type="submit" disabled={transferForm.processing}>
                  {transferForm.processing ? pageDict.saving : pageDict.saveTransfer}
                </Button>
              </div>
            </form>
          </Card>
        ) : null}

        {receivingTransfer ? (
          <Card className="p-5">
            <form onSubmit={receiveSelected} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {pageDict.receive} {receivingTransfer.number || pageDict.draftNumber}
                </h2>
                <Button variant="secondary" onClick={() => setReceivingTransfer(null)}>{pageDict.cancel}</Button>
              </div>

              <DatePicker
                label={pageDict.receiptDate}
                value={receiptDate}
                onChange={(value) => setReceiptDate(value || '')}
                required
              />

              <div className={tableClasses.wrap}>
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{pageDict.product}</th>
                      <th className={`${tableClasses.th} text-end`}>{pageDict.issued}</th>
                      <th className={`${tableClasses.th} text-end`}>{pageDict.received}</th>
                      <th className={`${tableClasses.th} text-end`}>{pageDict.remaining}</th>
                      <th className={`${tableClasses.th} text-end`}>{pageDict.receive}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {receivingTransfer.lines.map((line) => (
                      <tr key={line.id}>
                        <td className={tableClasses.td}>
                          {line.product?.code} - {getLocalizedName(line.product?.name, locale)}
                        </td>
                        <td className={`${tableClasses.td} text-end font-mono`}>{formatQuantityE6(line.issued_quantity_e6)}</td>
                        <td className={`${tableClasses.td} text-end font-mono`}>{formatQuantityE6(line.received_quantity_e6)}</td>
                        <td className={`${tableClasses.td} text-end font-mono font-bold`}>{formatQuantityE6(lineRemaining(line))}</td>
                        <td className={`${tableClasses.td} text-end`}>
                          <input
                            value={receiveQuantities[line.id] || ''}
                            onChange={(event) => setReceiveQuantities((current) => ({ ...current, [line.id]: event.target.value }))}
                            inputMode="decimal"
                            className="w-36 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-end text-sm font-mono text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setReceivingTransfer(null)}>{pageDict.cancel}</Button>
                <Button type="submit">{pageDict.receiveSelected}</Button>
              </div>
            </form>
          </Card>
        ) : null}

        {transfers.data.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.number}</th>
                  <th className={tableClasses.th}>{pageDict.date}</th>
                  <th className={tableClasses.th}>{pageDict.source}</th>
                  <th className={tableClasses.th}>{pageDict.destination}</th>
                  <th className={tableClasses.th}>{pageDict.status}</th>
                  <th className={tableClasses.th}>{pageDict.lines}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.actions}</th>
                </tr>
              </thead>
              <tbody>
                {transfers.data.map((transfer) => {
                  const actionState = getStockTransferActionState(transfer);

                  return (
                    <tr key={transfer.id} className="hover:bg-[var(--background)]">
                      <td className={tableClasses.td}>
                        <div className="flex min-w-40 flex-col gap-1">
                          <span className="font-mono text-xs font-extrabold">{transfer.number || pageDict.draftNumber}</span>
                          {transfer.reference ? <span className="text-xs text-[var(--text-secondary)]">{transfer.reference}</span> : null}
                        </div>
                      </td>
                      <td className={tableClasses.td}>{formatDate(transfer.transfer_date)}</td>
                      <td className={tableClasses.td}>
                        <span className="font-mono text-xs font-bold">{transfer.source_warehouse?.code || accDict.notAvailable}</span>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="font-mono text-xs font-bold">{transfer.destination_warehouse?.code || accDict.notAvailable}</span>
                      </td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={statusTone(transfer.status)}>{statusLabel(transfer.status)}</StatusBadge>
                      </td>
                      <td className={tableClasses.td}>
                        {transfer.lines.length === 0 ? (
                          <span className="text-xs text-[var(--text-muted)]">{pageDict.noLines}</span>
                        ) : (
                          <div className="flex min-w-72 flex-col gap-1">
                            {transfer.lines.map((line) => (
                              <div key={line.id} className="flex items-center justify-between gap-3 text-xs">
                                <span className="font-semibold text-[var(--text-primary)]">
                                  {line.product?.code} - {getLocalizedName(line.product?.name, locale)}
                                </span>
                                <span className="font-mono text-[var(--text-secondary)]">
                                  {formatQuantityE6(line.quantity_e6)} {line.unit_of_measure?.code || accDict.notAvailable}
                                </span>
                              </div>
                            ))}
                          </div>
                        )}
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          {canTransferStock && transfer.status === 'draft' ? (
                            <button type="button" onClick={() => openEditTransfer(transfer)} title={pageDict.editTransfer} aria-label={pageDict.editTransfer} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.editTransfer}</button>
                          ) : null}
                          {canTransferStock && transfer.status === 'draft' ? (
                            <button type="button" onClick={() => postAction(transfer, 'submit', pageDict.confirmSubmit)} title={pageDict.submit} aria-label={pageDict.submit} className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40">{pageDict.submit}</button>
                          ) : null}
                          {canApproveInventory && ['draft', 'submitted'].includes(transfer.status) ? (
                            <button type="button" onClick={() => postAction(transfer, 'approve', pageDict.confirmApprove)} title={pageDict.approve} aria-label={pageDict.approve} className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40">{pageDict.approve}</button>
                          ) : null}
                          {canIssueInventory && transfer.status === 'approved' ? (
                            <button type="button" onClick={() => postAction(transfer, 'issue', pageDict.confirmIssue)} title={pageDict.issue} aria-label={pageDict.issue} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.issue}</button>
                          ) : null}
                          {canReceiveInventory && ['issued', 'partially_received'].includes(transfer.status) ? (
                            <button type="button" onClick={() => openReceivePanel(transfer)} title={pageDict.receive} aria-label={pageDict.receive} className="inline-flex h-8 items-center rounded-md border border-cyan-200 px-2.5 text-xs font-semibold text-cyan-700 transition-colors hover:bg-cyan-50 dark:border-cyan-900/60 dark:text-cyan-300 dark:hover:bg-cyan-950/40">{pageDict.receive}</button>
                          ) : null}
                          {canReceiveInventory && ['issued', 'partially_received'].includes(transfer.status) ? (
                            <button type="button" onClick={() => receiveRemaining(transfer)} title={pageDict.receiveRemaining} aria-label={pageDict.receiveRemaining} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.receiveRemaining}</button>
                          ) : null}
                          {canTransferStock && ['draft', 'submitted', 'approved'].includes(transfer.status) ? (
                            <button type="button" onClick={() => postAction(transfer, 'cancel', pageDict.confirmCancel)} title={pageDict.cancelTransfer} aria-label={pageDict.cancelTransfer} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{pageDict.cancelTransfer}</button>
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
      </div>
    </AppLayout>
  );
}
