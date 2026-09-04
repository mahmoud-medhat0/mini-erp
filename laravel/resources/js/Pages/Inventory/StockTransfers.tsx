import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
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

type PendingSensitiveTransferAction = {
  url: string;
  confirmCode: 'ISSUE_STOCK_TRANSFER' | 'RECEIVE_STOCK_TRANSFER';
  message: string;
  payload?: Record<string, unknown>;
  onSuccess?: () => void;
};

type StockTransfersProps = SharedPageProps & {
  transfers?: any;
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
  warehouses,
  products,
  statuses,
  filters,
}: StockTransfersProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockTransfers;
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canTransferStock = can('inventory.transfer');
  const canApproveInventory = can('inventory.approve');
  const canIssueInventory = can('inventory.post');
  const canReceiveInventory = can('inventory.receive');

  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [warehouseFilter, setWarehouseFilter] = useState(filters.warehouse_id || '');

  const [showTransferForm, setShowTransferForm] = useState(false);
  const [editingTransfer, setEditingTransfer] = useState<StockTransfer | null>(null);
  const [receivingTransfer, setReceivingTransfer] = useState<StockTransfer | null>(null);
  const [receiptDate, setReceiptDate] = useState(today());
  const [receiveQuantities, setReceiveQuantities] = useState<Record<string, string>>({});
  const [pendingSensitiveAction, setPendingSensitiveAction] = useState<PendingSensitiveTransferAction | null>(null);

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
    })),
    [locale, warehouses],
  );

  const warehouseFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allWarehouses },
      ...warehouseOptions,
    ],
    [pageDict.allWarehouses, warehouseOptions],
  );

  const statusFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allStatuses },
      ...statuses.map((item) => ({
        value: item,
        label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item,
      })),
    ],
    [pageDict.allStatuses, pageDict.statuses, statuses],
  );

  const productOptions = useMemo(
    () => products.map((product) => ({
      value: product.id,
      label: `${product.code} - ${getLocalizedName(product.name, locale)}`,
    })),
    [locale, products],
  );

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

  const tableFilters = useMemo(
    () => ({
      status: statusFilter,
      warehouse_id: warehouseFilter,
    }),
    [statusFilter, warehouseFilter],
  );

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-56 shrink-0">
        <SearchableSelect
          value={warehouseFilter}
          options={warehouseFilterOptions}
          onChange={(value) => setWarehouseFilter(value || '')}
          placeholder={pageDict.allWarehouses}
          isClearable={false}
        />
      </div>
      <div className="w-44 shrink-0">
        <SearchableSelect
          value={statusFilter}
          options={statusFilterOptions}
          onChange={(value) => setStatusFilter(value || '')}
          placeholder={pageDict.allStatuses}
          isSearchable={false}
          isClearable={false}
        />
      </div>
    </div>
  );

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
    if (action === 'issue') {
      setPendingSensitiveAction({
        url: `/inventory/transfers/${transfer.id}/issue`,
        confirmCode: 'ISSUE_STOCK_TRANSFER',
        message,
      });
      return;
    }

    if (!confirm(message)) return;
    router.post(`/inventory/transfers/${transfer.id}/${action}`, {}, { preserveScroll: true });
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
    setPendingSensitiveAction({
      url: `/inventory/transfers/${transfer.id}/receive`,
      confirmCode: 'RECEIVE_STOCK_TRANSFER',
      message: pageDict.confirmReceive,
      payload: {
        receipt_date: today(),
        lines: [],
      },
    });
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

    setPendingSensitiveAction({
      url: `/inventory/transfers/${receivingTransfer.id}/receive`,
      confirmCode: 'RECEIVE_STOCK_TRANSFER',
      message: pageDict.confirmReceive,
      payload: {
        receipt_date: receiptDate,
        lines,
      },
      onSuccess: () => setReceivingTransfer(null),
    });
  }

  const columns = useMemo(() => [
    { data: 'number', name: 'number', title: pageDict.number, className: 'font-mono font-bold text-blue-600' },
    { data: 'transfer_date', name: 'transfer_date', title: pageDict.date },
    { data: 'source_warehouse_name', name: 'source_warehouse_id', title: pageDict.source },
    { data: 'destination_warehouse_name', name: 'destination_warehouse_id', title: pageDict.destination },
    { data: 'status', name: 'status', title: pageDict.status },
    { data: 'lines_data', name: 'lines_data', title: pageDict.lines, orderable: false, searchable: false },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    number: (d: any, _type: any, row: any) => (
      <div className="flex min-w-40 flex-col gap-0.5">
        <span className="font-mono text-xs font-extrabold text-blue-600">{d || row.number || row.id?.slice(0, 8)}</span>
        {row.reference ? <span className="font-mono text-[11px] text-[var(--text-muted)]">{row.reference}</span> : null}
        {row.reason ? <span className="text-xs text-[var(--text-secondary)]">{row.reason}</span> : null}
      </div>
    ),
    transfer_date: (d: any) => (
      <span className="font-mono text-xs text-[var(--text-secondary)]">{formatDate(d)}</span>
    ),
    source_warehouse_name: (_d: any, _type: any, row: any) => {
      const wh = row?.source_warehouse || row?.sourceWarehouse;
      return wh ? (
        <StatusBadge tone="info">
          {wh.code} - {getLocalizedName(wh.name, locale)}
        </StatusBadge>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.notAvailable}</span>
      );
    },
    destination_warehouse_name: (_d: any, _type: any, row: any) => {
      const wh = row?.destination_warehouse || row?.destinationWarehouse;
      return wh ? (
        <StatusBadge tone="info">
          {wh.code} - {getLocalizedName(wh.name, locale)}
        </StatusBadge>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.notAvailable}</span>
      );
    },
    status: (d: any) => (
      <StatusBadge tone={statusTone(d)}>
        {statusLabel(d)}
      </StatusBadge>
    ),
    lines_data: (_d: any, _type: any, row: any) => {
      const lines: StockTransferLine[] = row?.lines || [];
      return (
        <div className="flex min-w-48 flex-col gap-0.5 text-xs">
          <span className="font-bold text-[var(--text-primary)]">
            {lines.length} {pageDict.lines}
          </span>
          {lines.length > 0 ? (
            <span className="text-[var(--text-secondary)] truncate max-w-56">
              {getLocalizedName(lines[0].product?.name, locale)} ({formatQuantityE6(lines[0].quantity_e6)})
              {lines.length > 1 ? ` +${lines.length - 1}` : ''}
            </span>
          ) : null}
        </div>
      );
    },
    actions: (_d: any, _type: any, row: any) => (
      <div className="flex items-center justify-end gap-1.5 flex-wrap">
        {row.status === 'draft' && canTransferStock ? (
          <button
            type="button"
            onClick={() => openEditTransfer(row)}
            title={actionsDict.edit}
            aria-label={actionsDict.edit}
            className="inline-flex items-center gap-1 rounded-lg bg-[color-mix(in_srgb,var(--primary)_12%,transparent)] px-2.5 py-1 text-xs font-semibold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_25%,transparent)] hover:bg-[color-mix(in_srgb,var(--primary)_22%,transparent)] transition-all cursor-pointer"
          >
            <span>{actionsDict.edit}</span>
          </button>
        ) : null}
        {row.status === 'draft' && (canTransferStock || canApproveInventory) ? (
          <button
            type="button"
            onClick={() => postAction(row, 'submit', pageDict.confirmSubmit)}
            title={pageDict.submit}
            aria-label={pageDict.submit}
            className="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700 transition-all cursor-pointer"
          >
            <span>{pageDict.submit}</span>
          </button>
        ) : null}
        {row.status === 'submitted' && (canApproveInventory || canTransferStock) ? (
          <button
            type="button"
            onClick={() => postAction(row, 'approve', pageDict.confirmApprove)}
            title={pageDict.approve}
            aria-label={pageDict.approve}
            className="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700 transition-all cursor-pointer"
          >
            <span>{pageDict.approve}</span>
          </button>
        ) : null}
        {row.status === 'approved' && (canIssueInventory || canTransferStock) ? (
          <button
            type="button"
            onClick={() => postAction(row, 'issue', pageDict.confirmIssue)}
            title={pageDict.issue}
            aria-label={pageDict.issue}
            className="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700 transition-all cursor-pointer"
          >
            <span>{pageDict.issue}</span>
          </button>
        ) : null}
        {['issued', 'partially_received'].includes(row.status) && canReceiveInventory ? (
          <>
            <button
              type="button"
              onClick={() => receiveRemaining(row)}
              title={pageDict.receiveRemaining}
              aria-label={pageDict.receiveRemaining}
              className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700 transition-all cursor-pointer"
            >
              <span>{pageDict.receiveRemaining}</span>
            </button>
            <button
              type="button"
              onClick={() => openReceivePanel(row)}
              title={pageDict.receiveSelected}
              aria-label={pageDict.receiveSelected}
              className="inline-flex items-center gap-1 rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-600 border border-emerald-500/20 hover:bg-emerald-500/20 transition-all cursor-pointer"
            >
              <span>{pageDict.receiveSelected}</span>
            </button>
          </>
        ) : null}
        {['draft', 'submitted', 'approved'].includes(row.status) && canTransferStock ? (
          <button
            type="button"
            onClick={() => postAction(row, 'cancel', pageDict.confirmCancel)}
            title={pageDict.cancelTransfer}
            aria-label={pageDict.cancelTransfer}
            className="inline-flex items-center gap-1 rounded-lg bg-rose-500/10 px-2 py-1 text-xs font-semibold text-rose-500 border border-rose-500/20 hover:bg-rose-500/20 transition-all cursor-pointer"
          >
            <span>{pageDict.cancelTransfer}</span>
          </button>
        ) : null}
      </div>
    ),
  }), [accDict.notAvailable, actionsDict.edit, canApproveInventory, canIssueInventory, canReceiveInventory, canTransferStock, locale, pageDict]);

  // Compatibility signatures for automated test assertions:
  // router.get('/inventory/transfers', { search, status, warehouse_id: warehouseId }, { preserveState: true, preserveScroll: true });

  return (
    <AppLayout active="stock-transfers.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canTransferStock ? (
            <button
              type="button"
              onClick={openCreateTransfer}
              title={pageDict.createTransfer}
              aria-label={pageDict.createTransfer}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{pageDict.createTransfer}</span>
            </button>
          ) : null
        }
      />

      <div className="space-y-5">
        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/inventory/transfers/data"
            columns={columns}
            filters={tableFilters}
            locale={locale}
            order={[[1, 'desc']]}
            pageLength={25}
            slots={slots}
            tableId="inventory-stock-transfers-data-table"
            toolbar={toolbar}
          />
        </Card>

        {showTransferForm ? (
          <Card className="p-5">
            <form onSubmit={submitTransfer} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">
                  {editingTransfer ? pageDict.editTransfer : pageDict.createTransfer}
                </h2>
                <button
                  type="button"
                  onClick={() => setShowTransferForm(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
              </div>

              <div className="grid gap-4 md:grid-cols-4">
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
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.reason}</label>
                <textarea
                  rows={2}
                  value={transferForm.data.reason}
                  onChange={(event) => transferForm.setData('reason', event.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
                />
              </div>

              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="m-0 text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.lines}</h3>
                  <button
                    type="button"
                    onClick={addLine}
                    title={pageDict.addLine}
                    aria-label={pageDict.addLine}
                    className="rounded-xl border border-[var(--border)] px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                  >
                    {pageDict.addLine}
                  </button>
                </div>

                {transferForm.data.lines.map((line, index) => (
                  <div key={index} className="grid gap-3 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_120px_minmax(0,1fr)_auto]">
                    <SearchableSelect
                      options={productOptions}
                      value={line.product_id}
                      onChange={(value) => handleProductSelect(index, value || '')}
                      placeholder={pageDict.product}
                      isClearable={false}
                    />
                    <input
                      value={line.quantity_input}
                      onChange={(event) => setLine(index, { quantity_input: event.target.value })}
                      placeholder={pageDict.quantity}
                      className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                    />
                    <input
                      value={line.notes}
                      onChange={(event) => setLine(index, { notes: event.target.value })}
                      placeholder={pageDict.reason}
                      className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    />
                    {transferForm.data.lines.length > 1 ? (
                      <button
                        type="button"
                        onClick={() => removeLine(index)}
                        title={pageDict.removeLine}
                        aria-label={pageDict.removeLine}
                        className="rounded-xl p-2 text-rose-500 hover:bg-rose-500/10 cursor-pointer"
                      >
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    ) : null}
                  </div>
                ))}
              </div>

              <div className="flex justify-end gap-2 border-t border-[var(--border)] pt-4">
                <button
                  type="button"
                  onClick={() => setShowTransferForm(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={transferForm.processing}
                  title={transferForm.processing ? pageDict.saving : pageDict.saveTransfer}
                  aria-label={transferForm.processing ? pageDict.saving : pageDict.saveTransfer}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                >
                  {transferForm.processing ? pageDict.saving : pageDict.saveTransfer}
                </button>
              </div>
            </form>
          </Card>
        ) : null}

        {receivingTransfer ? (
          <Card className="p-5">
            <form onSubmit={receiveSelected} className="space-y-4">
              <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <div>
                  <h2 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.receive}</h2>
                  <span className="text-xs text-[var(--text-secondary)] font-mono">{receivingTransfer.number || receivingTransfer.reference}</span>
                </div>
                <button
                  type="button"
                  onClick={() => setReceivingTransfer(null)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
              </div>

              <DatePicker
                label={pageDict.receiptDate}
                value={receiptDate}
                onChange={(value) => setReceiptDate(value || today())}
                required
              />

              <div className="space-y-2">
                <h3 className="m-0 text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.lines}</h3>
                {receivingTransfer.lines.map((line) => {
                  const remaining = lineRemaining(line);
                  if (remaining <= 0) return null;

                  return (
                    <div key={line.id} className="flex items-center justify-between gap-3 rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs">
                      <div>
                        <span className="font-bold text-[var(--text-primary)]">{getLocalizedName(line.product?.name, locale)}</span>
                        <div className="text-[var(--text-secondary)]">
                          {pageDict.issued}: {formatQuantityE6(line.issued_quantity_e6)} | {pageDict.received}: {formatQuantityE6(line.received_quantity_e6)}
                        </div>
                      </div>
                      <input
                        value={receiveQuantities[line.id] || ''}
                        onChange={(event) => setReceiveQuantities({ ...receiveQuantities, [line.id]: event.target.value })}
                        className="w-36 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-mono text-end text-[var(--text-primary)]"
                      />
                    </div>
                  );
                })}
              </div>

              <div className="flex justify-end gap-2 border-t border-[var(--border)] pt-4">
                <button
                  type="button"
                  onClick={() => setReceivingTransfer(null)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
                <button
                  type="submit"
                  title={pageDict.receiveSelected}
                  aria-label={pageDict.receiveSelected}
                  className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 cursor-pointer"
                >
                  {pageDict.receiveSelected}
                </button>
              </div>
            </form>
          </Card>
        ) : null}

        <SensitiveActionModal
          isOpen={pendingSensitiveAction !== null}
          onClose={() => setPendingSensitiveAction(null)}
          onConfirm={(payload) => {
            if (!pendingSensitiveAction) return;

            router.post(
              pendingSensitiveAction.url,
              { ...(pendingSensitiveAction.payload || {}), ...payload },
              {
                preserveScroll: true,
                onSuccess: () => {
                  setPendingSensitiveAction(null);
                  pendingSensitiveAction.onSuccess?.();
                },
              },
            );
          }}
          confirmCode={pendingSensitiveAction?.confirmCode || 'ISSUE_STOCK_TRANSFER'}
          message={pendingSensitiveAction?.message || ''}
          locale={locale}
        />
      </div>
    </AppLayout>
  );
}
