import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type SupplierOption = {
  id: string;
  code: string;
  name: string | Record<string, string>;
};

type GoodsReceiptLineOption = {
  id: string;
  product_id: string;
  unit_of_measure_id: string;
  quantity_e6: number;
  product?: {
    id: string;
    code: string;
    name: string | Record<string, string>;
    type?: string;
  } | null;
  unitOfMeasure?: {
    id: string;
    code: string;
    name: string | Record<string, string>;
  } | null;
  purchaseOrderLine?: {
    id: string;
    unit_price_minor: number;
    line_total_minor: number;
  } | null;
};

type GoodsReceiptOption = {
  id: string;
  number?: string | null;
  receipt_date: string;
  warehouse?: {
    id: string;
    code: string;
    name: string | Record<string, string>;
    branch?: { id: string; code: string; name: string | Record<string, string> } | null;
  } | null;
  purchaseOrder?: {
    id: string;
    number?: string | null;
    currency: string;
    supplier?: SupplierOption | null;
  } | null;
  lines: GoodsReceiptLineOption[];
};

type LandedCostLineRow = {
  id: string;
  goods_receipt_line_id: string;
  product_id: string;
  unit_of_measure_id: string;
  quantity_e6_snapshot: number;
  receipt_value_minor_snapshot: number;
  allocated_cost_minor: number;
  capitalized_amount_minor: number;
  expensed_amount_minor: number;
  product?: { code: string; name: string | Record<string, string> } | null;
  unitOfMeasure?: { code: string; name: string | Record<string, string> } | null;
};

type LandedCostRow = {
  id: string;
  number?: string | null;
  goods_receipt_id: string;
  supplier_id: string;
  allocation_date: string;
  due_date?: string | null;
  currency: string;
  allocation_method: 'by_value' | 'by_quantity' | 'manual';
  cost_amount_minor: number;
  tax_amount_minor: number;
  total_amount_minor: number;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  reference?: string | null;
  description?: string | null;
  lock_version: number;
  supplier?: SupplierOption | null;
  goodsReceipt?: GoodsReceiptOption | null;
  lines?: LandedCostLineRow[];
};

type LineForm = {
  goods_receipt_line_id: string;
  selected: boolean;
  allocated_cost_minor: number;
};

type AllocationMethod = 'by_value' | 'by_quantity' | 'manual';

type LandedCostsProps = SharedPageProps & {
  landedCosts: {
    data: LandedCostRow[];
    links: PaginationLink[];
  };
  activeSuppliers: SupplierOption[];
  confirmedGoodsReceipts: GoodsReceiptOption[];
  statuses: string[];
  allocationMethods: AllocationMethod[];
  filters: {
    search?: string;
    status?: string;
  };
};

const amountToMinor = (value: string | number): number => Math.round(Number(value || 0) * 100);
const minorToAmount = (value: number): string => (Number(value || 0) / 100).toFixed(2);
const quantity = (value: number): string => (Number(value || 0) / 1000000).toLocaleString(undefined, { maximumFractionDigits: 6 });

function toAllocationMethod(value: string | null): AllocationMethod {
  if (value === 'by_quantity' || value === 'manual') {
    return value;
  }

  return 'by_value';
}

export default function LandedCostsIndex({
  locale,
  landedCosts,
  activeSuppliers,
  confirmedGoodsReceipts,
  statuses,
  allocationMethods,
  filters,
}: LandedCostsProps) {
  const dict = getDictionary(locale);
  const t = dict.app.pages.purchasingLandedCosts;
  const can = useCan();

  const today = new Date().toISOString().split('T')[0];
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<LandedCostRow | null>(null);
  const [lineForms, setLineForms] = useState<LineForm[]>([]);
  const [searchFilter, setSearchFilter] = useState(filters.search || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || '');

  const { data, setData, processing, errors, reset } = useForm<{
    goods_receipt_id: string;
    supplier_id: string;
    allocation_date: string;
    due_date: string;
    currency: string;
    allocation_method: 'by_value' | 'by_quantity' | 'manual';
    cost_amount_minor: number;
    tax_amount_minor: number;
    reference: string;
    description: string;
    lock_version?: number;
  }>({
    goods_receipt_id: confirmedGoodsReceipts[0]?.id || '',
    supplier_id: activeSuppliers[0]?.id || '',
    allocation_date: today,
    due_date: today,
    currency: confirmedGoodsReceipts[0]?.purchaseOrder?.currency || '',
    allocation_method: 'by_value',
    cost_amount_minor: 0,
    tax_amount_minor: 0,
    reference: '',
    description: '',
  });

  const selectedReceipt = useMemo(
    () => confirmedGoodsReceipts.find((receipt) => receipt.id === data.goods_receipt_id) || null,
    [confirmedGoodsReceipts, data.goods_receipt_id],
  );

  const eligibleLines = useMemo(
    () => (selectedReceipt?.lines || []).filter((line) => line.product?.type === 'stock'),
    [selectedReceipt],
  );

  const selectedLines = useMemo(
    () => eligibleLines.filter((line) => lineForms.find((item) => item.goods_receipt_line_id === line.id)?.selected),
    [eligibleLines, lineForms],
  );

  const totalCost = data.cost_amount_minor + data.tax_amount_minor;
  const postedCount = landedCosts.data.filter((row) => row.status === 'posted').length;
  const draftPipeline = landedCosts.data.filter((row) => ['draft', 'submitted', 'approved'].includes(row.status)).length;
  const totalVisibleMinor = landedCosts.data.reduce((sum, row) => sum + Number(row.total_amount_minor || 0), 0);

  const goodsReceiptOptions = useMemo(() => confirmedGoodsReceipts.map((receipt) => ({
    value: receipt.id,
    label: receipt.number || receipt.id.slice(0, 8),
    sublabel: receipt.purchaseOrder?.supplier ? getLocalizedName(receipt.purchaseOrder.supplier.name, locale) : t.selectSupplier,
  })), [confirmedGoodsReceipts, locale, t.selectSupplier]);

  const supplierOptions = useMemo(() => activeSuppliers.map((supplier) => ({
    value: supplier.id,
    label: `${supplier.code} - ${getLocalizedName(supplier.name, locale)}`,
  })), [activeSuppliers, locale]);

  const allocationMethodOptions = useMemo(() => allocationMethods.map((method) => ({
    value: method,
    label: (t as Record<string, string>)[method],
  })), [allocationMethods, t]);

  const statusFilterOptions = useMemo(() => [
    { value: '', label: t.allStatuses },
    ...statuses.map((status) => ({
      value: status,
      label: (t as Record<string, string>)[status],
    })),
  ], [statuses, t]);

  function initializeLines(receipt: GoodsReceiptOption | null, row?: LandedCostRow | null) {
    if (row?.lines?.length) {
      setLineForms(row.lines.map((line) => ({
        goods_receipt_line_id: line.goods_receipt_line_id,
        selected: true,
        allocated_cost_minor: line.allocated_cost_minor,
      })));
      return;
    }

    setLineForms((receipt?.lines || [])
      .filter((line) => line.product?.type === 'stock')
      .map((line) => ({
        goods_receipt_line_id: line.id,
        selected: true,
        allocated_cost_minor: 0,
      })));
  }

  function openCreate() {
    const receipt = confirmedGoodsReceipts[0] || null;
    setEditing(null);
    reset();
    setData({
      goods_receipt_id: receipt?.id || '',
      supplier_id: activeSuppliers[0]?.id || receipt?.purchaseOrder?.supplier?.id || '',
      allocation_date: today,
      due_date: today,
      currency: receipt?.purchaseOrder?.currency || '',
      allocation_method: 'by_value',
      cost_amount_minor: 0,
      tax_amount_minor: 0,
      reference: '',
      description: '',
    });
    initializeLines(receipt);
    setShowForm(true);
  }

  function openEdit(row: LandedCostRow) {
    const receipt = confirmedGoodsReceipts.find((item) => item.id === row.goods_receipt_id) || row.goodsReceipt || null;
    setEditing(row);
    setData({
      goods_receipt_id: row.goods_receipt_id,
      supplier_id: row.supplier_id,
      allocation_date: row.allocation_date,
      due_date: row.due_date || row.allocation_date,
      currency: row.currency,
      allocation_method: row.allocation_method,
      cost_amount_minor: row.cost_amount_minor,
      tax_amount_minor: row.tax_amount_minor,
      reference: row.reference || '',
      description: row.description || '',
      lock_version: row.lock_version,
    });
    initializeLines(receipt, row);
    setShowForm(true);
  }

  function closeForm() {
    setShowForm(false);
    setEditing(null);
    reset();
    setLineForms([]);
  }

  function handleReceiptChange(receiptId: string) {
    const receipt = confirmedGoodsReceipts.find((item) => item.id === receiptId) || null;
    setData('goods_receipt_id', receiptId);
    setData('currency', receipt?.purchaseOrder?.currency || data.currency);
    initializeLines(receipt);
  }

  function updateLine(lineId: string, patch: Partial<LineForm>) {
    setLineForms((items) => items.map((item) => (item.goods_receipt_line_id === lineId ? { ...item, ...patch } : item)));
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const payload = {
      ...data,
      lines: lineForms
        .filter((line) => line.selected)
        .map((line) => ({
          goods_receipt_line_id: line.goods_receipt_line_id,
          allocated_cost_minor: data.allocation_method === 'manual' ? line.allocated_cost_minor : 0,
        })),
    };

    if (editing) {
      router.put(`/purchasing/landed-costs/${editing.id}`, payload, { preserveScroll: true, onSuccess: closeForm });
      return;
    }

    router.post('/purchasing/landed-costs', payload, { preserveScroll: true, onSuccess: closeForm });
  }

  function runAction(row: LandedCostRow, action: 'submit' | 'approve' | 'post' | 'cancel') {
    const confirmMessage = {
      submit: t.submitConfirm,
      approve: t.approveConfirm,
      post: t.postConfirm,
      cancel: t.cancelConfirm,
    }[action];

    if (confirm(confirmMessage)) {
      router.post(`/purchasing/landed-costs/${row.id}/${action}`, {}, { preserveScroll: true });
    }
  }

  function applyFilters(e: FormEvent) {
    e.preventDefault();
    router.get('/purchasing/landed-costs', { search: searchFilter, status: statusFilter }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function statusTone(status: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
    if (status === 'posted') return 'ok';
    if (status === 'approved' || status === 'submitted') return 'info';
    if (status === 'cancelled') return 'danger';
    return 'muted';
  }

  function rowLabel(row: LandedCostRow) {
    return row.number || row.reference || row.id.slice(0, 8);
  }

  return (
    <AppLayout active="landed-costs.index">
      <Head title={t.title} />

      <PageHeader
        title={t.title}
        description={t.description}
        actions={can('purchasing.landed_costs') ? (
          <button
            type="button"
            onClick={() => (showForm ? closeForm() : openCreate())}
            title={showForm ? t.closeForm : t.create}
            aria-label={showForm ? t.closeForm : t.create}
            className="rounded-md bg-[var(--primary)] px-4 py-2 text-sm font-bold text-white shadow-sm"
          >
            {showForm ? t.closeForm : t.create}
          </button>
        ) : null}
      />

      <div className="grid gap-4 md:grid-cols-3">
        <MetricCard label={t.totalAmount} value={formatMoney(totalVisibleMinor, selectedReceipt?.purchaseOrder?.currency || t.noCurrency)} tone="blue" />
        <MetricCard label={t.posted} value={postedCount} tone="emerald" />
        <MetricCard label={t.draft} value={draftPipeline} tone="amber" />
      </div>

      {showForm ? (
        <Card className="mt-5 p-4">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-3 lg:grid-cols-4">
              <div className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <SearchableSelect
                  label={t.goodsReceipt}
                  value={data.goods_receipt_id || null}
                  onChange={(value) => handleReceiptChange(value || '')}
                  options={goodsReceiptOptions}
                  placeholder={t.selectGoodsReceipt}
                  disabled={Boolean(editing)}
                  isClearable={false}
                  required
                />
                {errors.goods_receipt_id ? <span className="text-red-500">{errors.goods_receipt_id}</span> : null}
              </div>

              <div className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <SearchableSelect
                  label={t.supplier}
                  value={data.supplier_id || null}
                  onChange={(value) => setData('supplier_id', value || '')}
                  options={supplierOptions}
                  placeholder={t.selectSupplier}
                  isClearable={false}
                  required
                />
                {errors.supplier_id ? <span className="text-red-500">{errors.supplier_id}</span> : null}
              </div>

              <DatePicker
                label={t.allocationDate}
                value={data.allocation_date}
                onChange={(value) => setData('allocation_date', value || '')}
              />

              <DatePicker
                label={t.dueDate}
                value={data.due_date}
                onChange={(value) => setData('due_date', value || '')}
              />
            </div>

            <div className="grid gap-3 lg:grid-cols-5">
              <SearchableSelect
                label={t.method}
                value={data.allocation_method}
                onChange={(value) => setData('allocation_method', toAllocationMethod(value))}
                options={allocationMethodOptions}
                isClearable={false}
                required
              />

              <label className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <span>{t.currency}</span>
                <input
                  value={data.currency}
                  onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                  className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]"
                  maxLength={3}
                />
              </label>

              <label className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <span>{t.costAmount}</span>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={minorToAmount(data.cost_amount_minor)}
                  onChange={(e) => setData('cost_amount_minor', amountToMinor(e.target.value))}
                  className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]"
                />
              </label>

              <label className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <span>{t.taxAmount}</span>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={minorToAmount(data.tax_amount_minor)}
                  onChange={(e) => setData('tax_amount_minor', amountToMinor(e.target.value))}
                  className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]"
                />
              </label>

              <div className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <span>{t.totalAmount}</span>
                <div className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]">
                  {formatMoney(totalCost, data.currency || t.noCurrency)}
                </div>
              </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
              <label className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <span>{t.reference}</span>
                <input
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]"
                />
              </label>

              <label className="space-y-1 text-xs font-bold text-[var(--text-secondary)]">
                <span>{t.descriptionField}</span>
                <input
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  className="w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]"
                />
              </label>
            </div>

            <div className="rounded-md border border-[var(--border)]">
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border)] px-4 py-3">
                <div>
                  <h2 className="text-sm font-bold text-[var(--text-primary)]">{t.allocationLines}</h2>
                  <p className="mt-1 text-xs text-[var(--text-secondary)]">
                    {data.allocation_method === 'manual' ? t.manualHint : t.selectLinesHint}
                  </p>
                </div>
                <StatusBadge tone="info">{data.allocation_method === 'manual' ? t.manual : t.automaticSplit}</StatusBadge>
              </div>

              {eligibleLines.length === 0 ? (
                <div className="p-4 text-sm text-[var(--text-secondary)]">{t.noReceiptLines}</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className={tableClasses.table}>
                    <thead>
                      <tr>
                        <th className={tableClasses.th}></th>
                        <th className={tableClasses.th}>{t.product}</th>
                        <th className={tableClasses.th}>{t.quantity}</th>
                        <th className={tableClasses.th}>{t.receiptValue}</th>
                        <th className={tableClasses.th}>{t.allocated}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {eligibleLines.map((line) => {
                        const lineForm = lineForms.find((item) => item.goods_receipt_line_id === line.id);
                        const receiptValue = Math.round((line.quantity_e6 * Number(line.purchaseOrderLine?.unit_price_minor || 0)) / 1000000);
                        return (
                          <tr key={line.id}>
                            <td className={tableClasses.td}>
                              <input
                                type="checkbox"
                                checked={Boolean(lineForm?.selected)}
                                onChange={(e) => updateLine(line.id, { selected: e.target.checked })}
                              />
                            </td>
                            <td className={tableClasses.td}>
                              <span className="font-semibold">{line.product?.code}</span>
                              <span className="ms-2 text-[var(--text-secondary)]">{getLocalizedName(line.product?.name, locale)}</span>
                            </td>
                            <td className={tableClasses.td}>{quantity(line.quantity_e6)} {line.unitOfMeasure?.code}</td>
                            <td className={tableClasses.td}>{formatMoney(receiptValue, data.currency || t.noCurrency)}</td>
                            <td className={tableClasses.td}>
                              {data.allocation_method === 'manual' ? (
                                <input
                                  type="number"
                                  min="0"
                                  step="0.01"
                                  value={minorToAmount(lineForm?.allocated_cost_minor || 0)}
                                  onChange={(e) => updateLine(line.id, { allocated_cost_minor: amountToMinor(e.target.value) })}
                                  className="w-32 rounded-md border border-[var(--border)] bg-[var(--background)] px-2 py-1 text-sm text-[var(--text-primary)]"
                                />
                              ) : (
                                <span className="text-[var(--text-secondary)]">{t.automaticSplit}</span>
                              )}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2">
              <button type="button" onClick={closeForm} title={t.cancel} aria-label={t.cancel} className="rounded-md border border-[var(--border)] px-4 py-2 text-sm font-bold text-[var(--text-primary)]">
                {t.cancel}
              </button>
              <button type="submit" disabled={processing || selectedLines.length === 0} title={processing ? t.processing : editing ? t.saveChanges : t.saveDraft} aria-label={processing ? t.processing : editing ? t.saveChanges : t.saveDraft} className="rounded-md bg-[var(--primary)] px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                {processing ? t.processing : editing ? t.saveChanges : t.saveDraft}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="mt-5 p-4">
        <form onSubmit={applyFilters} className="mb-4 grid gap-3 md:grid-cols-[1fr_220px_auto]">
          <input
            value={searchFilter}
            onChange={(e) => setSearchFilter(e.target.value)}
            placeholder={t.searchPlaceholder}
            className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]"
          />
          <SearchableSelect
            options={statusFilterOptions}
            value={statusFilter || null}
            onChange={(value) => setStatusFilter(value || '')}
            placeholder={t.allStatuses}
          />
          <button type="submit" title={t.filter} aria-label={t.filter} className="rounded-md border border-[var(--border)] px-4 py-2 text-sm font-bold text-[var(--text-primary)]">
            {t.filter}
          </button>
        </form>

        {landedCosts.data.length === 0 ? (
          <EmptyState title={t.emptyTitle} description={t.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{t.number}</th>
                  <th className={tableClasses.th}>{t.supplier}</th>
                  <th className={tableClasses.th}>{t.goodsReceipt}</th>
                  <th className={tableClasses.th}>{t.allocationDate}</th>
                  <th className={tableClasses.th}>{t.method}</th>
                  <th className={tableClasses.th}>{t.totalAmount}</th>
                  <th className={tableClasses.th}>{t.status}</th>
                  <th className={tableClasses.th}>{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {landedCosts.data.map((row) => (
                  <tr key={row.id}>
                    <td className={tableClasses.td}>
                      <div className="font-bold">{rowLabel(row)}</div>
                      {row.reference ? <div className="text-xs text-[var(--text-secondary)]">{row.reference}</div> : null}
                    </td>
                    <td className={tableClasses.td}>{getLocalizedName(row.supplier?.name, locale)}</td>
                    <td className={tableClasses.td}>
                      <div>{row.goodsReceipt?.number || row.goods_receipt_id.slice(0, 8)}</div>
                      <div className="text-xs text-[var(--text-secondary)]">{getLocalizedName(row.goodsReceipt?.warehouse?.name, locale)}</div>
                    </td>
                    <td className={tableClasses.td}>{row.allocation_date}</td>
                    <td className={tableClasses.td}>{(t as Record<string, string>)[row.allocation_method]}</td>
                    <td className={tableClasses.td}><AccountingAmount amountMinor={row.total_amount_minor} currency={row.currency} /></td>
                    <td className={tableClasses.td}><StatusBadge tone={statusTone(row.status)}>{(t as Record<string, string>)[row.status]}</StatusBadge></td>
                    <td className={tableClasses.td}>
                      <div className="flex flex-wrap gap-2">
                        {row.status === 'draft' ? (
                          <button type="button" onClick={() => openEdit(row)} title={`${t.edit} ${rowLabel(row)}`} aria-label={`${t.edit} ${rowLabel(row)}`} className="rounded-md border border-[var(--border)] px-3 py-1 text-xs font-bold">
                            {t.edit}
                          </button>
                        ) : null}
                        {row.status === 'draft' ? (
                          <button type="button" onClick={() => runAction(row, 'submit')} title={`${t.submit} ${rowLabel(row)}`} aria-label={`${t.submit} ${rowLabel(row)}`} className="rounded-md border border-[var(--border)] px-3 py-1 text-xs font-bold">
                            {t.submit}
                          </button>
                        ) : null}
                        {row.status === 'submitted' && can('purchasing.approve') ? (
                          <button type="button" onClick={() => runAction(row, 'approve')} title={`${t.approve} ${rowLabel(row)}`} aria-label={`${t.approve} ${rowLabel(row)}`} className="rounded-md border border-[var(--border)] px-3 py-1 text-xs font-bold">
                            {t.approve}
                          </button>
                        ) : null}
                        {row.status === 'approved' && can('purchasing.post') && can('view_financials') ? (
                          <button type="button" onClick={() => runAction(row, 'post')} title={`${t.post} ${rowLabel(row)}`} aria-label={`${t.post} ${rowLabel(row)}`} className="rounded-md bg-[var(--primary)] px-3 py-1 text-xs font-bold text-white">
                            {t.post}
                          </button>
                        ) : null}
                        {['draft', 'submitted', 'approved'].includes(row.status) ? (
                          <button type="button" onClick={() => runAction(row, 'cancel')} title={`${t.cancel} ${rowLabel(row)}`} aria-label={`${t.cancel} ${rowLabel(row)}`} className="rounded-md border border-red-500/40 px-3 py-1 text-xs font-bold text-red-600">
                            {t.cancel}
                          </button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </AppLayout>
  );
}
