import { useMemo, type ReactElement } from 'react';

import { formatMoney, getLocalizedName } from '../lib/accountingHelpers';
import ServerDataTable from './ServerDataTable';
import { StatusBadge } from './Primitives';

type TableLabels = Partial<Record<
  | 'orderNumber'
  | 'customer'
  | 'supplier'
  | 'date'
  | 'status'
  | 'currency'
  | 'qty'
  | 'totalAmount'
  | 'deliveryNumber'
  | 'salesOrderNumber'
  | 'warehouse'
  | 'deliveredQty'
  | 'receiptNumber'
  | 'purchaseOrderNumber'
  | 'receivedQty'
  | 'invoiceNumber'
  | 'billNumber'
  | 'dueDate'
  | 'total'
  | 'journal'
  | 'arEntry'
  | 'apEntry'
  | 'type'
  | 'source'
  | 'product'
  | 'qtyDelta'
  | 'valueDelta'
  | 'postBalance'
  | 'branch'
  | 'notAssigned'
  | 'draft'
  | 'submitted'
  | 'confirmed'
  | 'approved'
  | 'posted'
  | 'cancelled'
  | 'receipt'
  | 'issue'
  | 'reversal'
  | 'scrap'
  | 'transferOut'
  | 'transferIn'
  | 'adjustment',
  string
>>;

type ReportTableProps = {
  filters: object & { search?: string };
  labels: TableLabels;
  locale: string;
  notAvailable?: string;
};

type ReportSlots = Record<string, (data: any, row: any) => ReactElement>;

const filterPayload = (filters: object): Record<string, string> => Object.fromEntries(
  Object.entries(filters).filter(([key]) => key !== 'search'),
);

const quantity = (value: number, locale: string): string => (Number(value || 0) / 1_000_000).toLocaleString(
  locale === 'ar' ? 'ar-EG' : 'en-US',
  { minimumFractionDigits: 2, maximumFractionDigits: 6 },
);

const statusTone = (status: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
  if (['posted', 'confirmed', 'paid', 'completed'].includes(status)) return 'ok';
  if (['cancelled', 'voided', 'rejected'].includes(status)) return 'danger';
  if (['submitted', 'approved', 'partially_paid'].includes(status)) return 'warning';
  if (status === 'processing') return 'info';

  return 'muted';
};

const statusLabel = (status: string, labels: TableLabels): string => ({
  draft: labels.draft,
  submitted: labels.submitted,
  confirmed: labels.confirmed,
  approved: labels.approved,
  posted: labels.posted,
  cancelled: labels.cancelled,
}[status] || status);

function StatusCell({ status, labels }: { status: string; labels: TableLabels }) {
  return <StatusBadge tone={statusTone(status)}>{statusLabel(status, labels)}</StatusBadge>;
}

export function SalesOrdersDataTable({ filters, labels, locale }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'order_number', name: 'number', title: labels.orderNumber },
    { data: 'customer_name', name: 'customer_name', title: labels.customer, orderable: false },
    { data: 'order_date', name: 'order_date', title: labels.date },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'currency', name: 'currency', title: labels.currency },
    { data: 'ordered_quantity_e6', name: 'ordered_quantity_e6', title: labels.qty, searchable: false },
    { data: 'total_minor', name: 'total_minor', title: labels.totalAmount, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    customer_name: (_data, row) => <span>{row.customer_code} - {row.customer_name}</span>,
    status: (data) => <StatusCell status={String(data)} labels={labels} />,
    ordered_quantity_e6: (data) => <span className="font-mono">{quantity(Number(data), locale)}</span>,
    total_minor: (data, row) => <span className="font-mono font-semibold">{formatMoney(Number(data), row.currency)}</span>,
  }), [labels, locale]);

  return <ServerDataTable ajaxUrl="/reports/sales-orders/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[2, 'desc'], [0, 'desc']]} slots={slots} tableId="sales-orders-data-table" />;
}

export function PurchaseOrdersDataTable({ filters, labels, locale }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'order_number', name: 'number', title: labels.orderNumber },
    { data: 'supplier_name', name: 'supplier_name', title: labels.supplier, orderable: false },
    { data: 'order_date', name: 'order_date', title: labels.date },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'currency', name: 'currency', title: labels.currency },
    { data: 'ordered_quantity_e6', name: 'ordered_quantity_e6', title: labels.qty, searchable: false },
    { data: 'total_minor', name: 'total_minor', title: labels.totalAmount, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    supplier_name: (_data, row) => <span>{row.supplier_code} - {row.supplier_name}</span>,
    status: (data) => <StatusCell status={String(data)} labels={labels} />,
    ordered_quantity_e6: (data) => <span className="font-mono">{quantity(Number(data), locale)}</span>,
    total_minor: (data, row) => <span className="font-mono font-semibold">{formatMoney(Number(data), row.currency)}</span>,
  }), [labels, locale]);

  return <ServerDataTable ajaxUrl="/reports/purchase-orders/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[2, 'desc'], [0, 'desc']]} slots={slots} tableId="purchase-orders-data-table" />;
}

export function DeliveryNotesDataTable({ filters, labels, locale }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'delivery_number', name: 'number', title: labels.deliveryNumber },
    { data: 'sales_order_number', name: 'sales_order_number', title: labels.salesOrderNumber, orderable: false },
    { data: 'customer_name', name: 'customer_name', title: labels.customer, orderable: false },
    { data: 'warehouse_name', name: 'warehouse_name', title: labels.warehouse, orderable: false },
    { data: 'delivery_date', name: 'delivery_date', title: labels.date },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'delivered_quantity_e6', name: 'delivered_quantity_e6', title: labels.deliveredQty, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    customer_name: (_data, row) => <span>{row.customer_code} - {row.customer_name}</span>,
    warehouse_name: (data, row) => <span>{row.warehouse_code} - {getLocalizedName(data, locale)}</span>,
    status: (data) => <StatusCell status={String(data)} labels={labels} />,
    delivered_quantity_e6: (data) => <span className="font-mono font-semibold">{quantity(Number(data), locale)}</span>,
  }), [labels, locale]);

  return <ServerDataTable ajaxUrl="/reports/delivery-notes/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[4, 'desc'], [0, 'desc']]} slots={slots} tableId="delivery-notes-data-table" />;
}

export function GoodsReceiptsDataTable({ filters, labels, locale }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'receipt_number', name: 'number', title: labels.receiptNumber },
    { data: 'purchase_order_number', name: 'purchase_order_number', title: labels.purchaseOrderNumber, orderable: false },
    { data: 'supplier_name', name: 'supplier_name', title: labels.supplier, orderable: false },
    { data: 'warehouse_name', name: 'warehouse_name', title: labels.warehouse, orderable: false },
    { data: 'receipt_date', name: 'receipt_date', title: labels.date },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'received_quantity_e6', name: 'received_quantity_e6', title: labels.receivedQty, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    supplier_name: (_data, row) => <span>{row.supplier_code} - {row.supplier_name}</span>,
    warehouse_name: (data, row) => <span>{row.warehouse_code} - {getLocalizedName(data, locale)}</span>,
    status: (data) => <StatusCell status={String(data)} labels={labels} />,
    received_quantity_e6: (data) => <span className="font-mono font-semibold">{quantity(Number(data), locale)}</span>,
  }), [labels, locale]);

  return <ServerDataTable ajaxUrl="/reports/goods-receipts/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[4, 'desc'], [0, 'desc']]} slots={slots} tableId="goods-receipts-data-table" />;
}

export function CustomerInvoicesDataTable({ filters, labels, locale, notAvailable = '—' }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'invoice_number', name: 'number', title: labels.invoiceNumber },
    { data: 'customer_name', name: 'customer_name', title: labels.customer, orderable: false },
    { data: 'invoice_date', name: 'invoice_date', title: labels.date },
    { data: 'due_date', name: 'due_date', title: labels.dueDate },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'total_minor', name: 'total_minor', title: labels.total, searchable: false },
    { data: 'journal_entry_number', name: 'journal_entry_number', title: labels.journal, orderable: false, searchable: false },
    { data: 'receivable_entry_id', name: 'receivable_entry_id', title: labels.arEntry, orderable: false, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    customer_name: (_data, row) => <span>{row.customer_code} - {row.customer_name}</span>,
    status: (data) => <StatusCell status={String(data)} labels={labels} />,
    total_minor: (data, row) => <span className="font-mono font-semibold">{formatMoney(Number(data), row.currency)}</span>,
    journal_entry_number: (data) => data ? <span className="font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">{String(data)}</span> : <span className="text-xs text-[var(--text-muted)]">{notAvailable}</span>,
    receivable_entry_id: (data) => data ? <span className="font-mono text-xs font-semibold text-blue-600 dark:text-blue-400">AR-{String(data).slice(0, 8)}</span> : <span className="text-xs text-[var(--text-muted)]">{notAvailable}</span>,
  }), [labels, notAvailable]);

  return <ServerDataTable ajaxUrl="/reports/customer-invoices/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[2, 'desc'], [0, 'desc']]} slots={slots} tableId="customer-invoices-data-table" />;
}

export function SupplierBillsDataTable({ filters, labels, locale, notAvailable = '—' }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'bill_number', name: 'number', title: labels.billNumber },
    { data: 'supplier_name', name: 'supplier_name', title: labels.supplier, orderable: false },
    { data: 'bill_date', name: 'bill_date', title: labels.date },
    { data: 'due_date', name: 'due_date', title: labels.dueDate },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'total_minor', name: 'total_minor', title: labels.total, searchable: false },
    { data: 'journal_entry_number', name: 'journal_entry_number', title: labels.journal, orderable: false, searchable: false },
    { data: 'payable_entry_id', name: 'payable_entry_id', title: labels.apEntry, orderable: false, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    supplier_name: (_data, row) => <span>{row.supplier_code} - {row.supplier_name}</span>,
    status: (data) => <StatusCell status={String(data)} labels={labels} />,
    total_minor: (data, row) => <span className="font-mono font-semibold">{formatMoney(Number(data), row.currency)}</span>,
    journal_entry_number: (data) => data ? <span className="font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">{String(data)}</span> : <span className="text-xs text-[var(--text-muted)]">{notAvailable}</span>,
    payable_entry_id: (data) => data ? <span className="font-mono text-xs font-semibold text-purple-600 dark:text-purple-400">AP-{String(data).slice(0, 8)}</span> : <span className="text-xs text-[var(--text-muted)]">{notAvailable}</span>,
  }), [labels, notAvailable]);

  return <ServerDataTable ajaxUrl="/reports/supplier-bills/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[2, 'desc'], [0, 'desc']]} slots={slots} tableId="supplier-bills-data-table" />;
}

const movementTone = (movement: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
  if (movement === 'receipt' || movement === 'transfer_in') return 'ok';
  if (movement === 'issue' || movement === 'transfer_out' || movement === 'scrap') return 'warning';
  if (movement === 'adjustment') return 'info';

  return 'muted';
};

export function StockMovementsDataTable({ filters, labels, locale, notAvailable = '—' }: ReportTableProps) {
  const columns = useMemo(() => [
    { data: 'movement_date', name: 'movement_date', title: labels.date },
    { data: 'movement_type', name: 'movement_type', title: labels.type },
    { data: 'warehouse_name', name: 'warehouse_name', title: labels.warehouse, orderable: false },
    { data: 'source_type', name: 'source_type', title: labels.source },
    { data: 'product_name', name: 'product_name', title: labels.product, orderable: false },
    { data: 'quantity_delta_e6', name: 'quantity_delta_e6', title: labels.qtyDelta, searchable: false },
    { data: 'value_delta_minor', name: 'value_delta_minor', title: labels.valueDelta, searchable: false },
    { data: 'balance_quantity_e6', name: 'balance_quantity_e6', title: labels.postBalance, searchable: false },
    { data: 'journal_entry_number', name: 'journal_entry_number', title: labels.journal, orderable: false, searchable: false },
  ], [labels]);
  const slots = useMemo<ReportSlots>(() => ({
    movement_type: (data) => {
      const movement = String(data);
      const text = ({
        receipt: labels.receipt,
        issue: labels.issue,
        reversal: labels.reversal,
        scrap: labels.scrap,
        transfer_out: labels.transferOut,
        transfer_in: labels.transferIn,
        adjustment: labels.adjustment,
      }[movement] || movement);

      return <StatusBadge tone={movementTone(movement)}>{text}</StatusBadge>;
    },
    warehouse_name: (data, row) => (
      <div className="flex min-w-48 flex-col gap-1">
        <span className="font-mono text-xs font-bold">{row.warehouse_code || labels.notAssigned}</span>
        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(data, locale) || labels.notAssigned}</span>
        <span className="text-[10px] font-semibold text-[var(--text-muted)]">
          {row.branch_code ? `${labels.branch}: ${row.branch_code} - ${getLocalizedName(row.branch_name, locale)}` : labels.notAssigned}
        </span>
      </div>
    ),
    source_type: (data) => <span className="font-mono text-xs text-[var(--text-secondary)]">{String(data)}</span>,
    product_name: (data, row) => <span><strong>{row.product_code}</strong><span className="ms-2 text-xs text-[var(--text-secondary)]">{getLocalizedName(data, locale)}</span></span>,
    quantity_delta_e6: (data, row) => {
      const value = Number(data);
      return <span className={`font-mono font-bold ${value >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{value >= 0 ? '+' : ''}{quantity(value, locale)} {row.uom_code}</span>;
    },
    value_delta_minor: (data, row) => {
      const value = Number(data);
      return <span className={`font-mono font-bold ${value >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{value >= 0 ? '+' : ''}{formatMoney(value, row.currency)}</span>;
    },
    balance_quantity_e6: (data, row) => <span className="font-mono">{quantity(Number(data), locale)} {row.uom_code}<span className="ms-2 text-[var(--text-secondary)]">{formatMoney(Number(row.balance_valuation_amount_minor), row.currency)}</span></span>,
    journal_entry_number: (data) => data ? <span className="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">{String(data)}</span> : <span className="text-xs text-[var(--text-muted)]">{notAvailable}</span>,
  }), [labels, locale, notAvailable]);

  return <ServerDataTable ajaxUrl="/reports/stock-movements/data" columns={columns} filters={filterPayload(filters)} initialSearch={filters.search} locale={locale} order={[[0, 'desc']]} slots={slots} tableId="stock-movements-data-table" />;
}
