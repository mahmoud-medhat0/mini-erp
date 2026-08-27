import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type SupplierOption = {
  id: string;
  code: string;
  name: string;
};

type ProductName = { en?: string; ar?: string } | string;

type GoodsReceiptLineOption = {
  id: string;
  product_id: string;
  quantity_e6: number;
  description?: string | null;
  product?: { code: string; name: ProductName } | null;
  unitOfMeasure?: { id: string; code: string; name: string } | null;
};

type GoodsReceiptOption = {
  id: string;
  number?: string | null;
  warehouse_id?: string | null;
  purchaseOrder?: {
    id: string;
    supplier_id?: string;
    currency?: string;
    supplier?: { id: string; name: string } | null;
  } | null;
  lines: GoodsReceiptLineOption[];
};

type WarehouseOption = {
  id: string;
  code: string;
  name: { en?: string; ar?: string } | string;
  is_default?: boolean;
};

type ReturnLineForm = {
  goods_receipt_line_id: string;
  product_id: string;
  description: string;
  uom_name: string;
  max_quantity: number;
  quantity: number;
};

type PurchaseReturnRow = {
  id: string;
  number?: string | null;
  supplier_id: string;
  goods_receipt_id?: string | null;
  warehouse_id: string;
  supplier_bill_id?: string | null;
  supplier?: { id: string; name: string } | null;
  goodsReceipt?: { id: string; number?: string | null } | null;
  warehouse?: WarehouseOption | null;
  return_date: string;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  currency: string;
  reason?: string | null;
  notes?: string | null;
  lock_version: number;
  lines?: Array<{
    id: string;
    goods_receipt_line_id: string;
    product_id: string;
    description?: string | null;
    quantity_e6: number;
    product?: { code: string; name: ProductName } | null;
    unitOfMeasure?: { id: string; code: string; name: string } | null;
  }>;
};

type PurchaseReturnsProps = SharedPageProps & {
  purchaseReturns: {
    data: PurchaseReturnRow[];
    links: any[];
  };
  activeSuppliers: SupplierOption[];
  confirmedGoodsReceipts: GoodsReceiptOption[];
  warehouses: WarehouseOption[];
  taxCodes?: Array<{ id: string; code: string; name: Record<string, string> | string; calculation_mode: string }>;
  filters: {
    search?: string;
    status?: string;
    supplier_id?: string;
    warehouse_id?: string;
  };
};

const formatQuantity = (qtyE6: number) => String(parseFloat(((qtyE6 || 0) / 1000000).toFixed(6)));

export default function PurchaseReturnsIndex({
  locale,
  purchaseReturns,
  activeSuppliers,
  confirmedGoodsReceipts,
  warehouses,
  taxCodes = [],
  filters,
}: PurchaseReturnsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  
  const [showModal, setShowModal] = useState(false);
  const [editingReturn, setEditingReturn] = useState<PurchaseReturnRow | null>(null);
  const [lineItems, setLineItems] = useState<ReturnLineForm[]>([]);

  const todayStr = new Date().toISOString().split('T')[0];

  const { data, setData, post, put, processing, errors, reset } = useForm({
    supplier_id: '',
    goods_receipt_id: '',
    warehouse_id: warehouses[0]?.id || '',
    return_date: todayStr,
    currency: '',
    reason: '',
    notes: '',
    lock_version: 1,
  });

  const getProductName = (prod?: { code: string; name: ProductName } | null): string => {
    if (!prod) return '';
    if (typeof prod.name === 'string') return prod.name;
    return locale === 'ar' ? prod.name?.ar || prod.name?.en || '' : prod.name?.en || prod.name?.ar || '';
  };

  const supplierGoodsReceipts = confirmedGoodsReceipts.filter(
    (gr) => !data.supplier_id || gr.purchaseOrder?.supplier_id === data.supplier_id
  );

  const selectedGr = confirmedGoodsReceipts.find((gr) => gr.id === data.goods_receipt_id);

  const handleSupplierSelect = (supplierId: string) => {
    setData('supplier_id', supplierId);
    setData('goods_receipt_id', '');
    setData('currency', '');
    setLineItems([]);
  };

  const handleGoodsReceiptSelect = (grId: string) => {
    const gr = confirmedGoodsReceipts.find((g) => g.id === grId);
    setData('goods_receipt_id', grId);
    setData('warehouse_id', gr?.warehouse_id || warehouses[0]?.id || '');
    if (gr?.purchaseOrder?.currency) {
      setData('currency', gr.purchaseOrder.currency);
    }
    if (!grId || !gr) {
      setLineItems([]);
      return;
    }
    if (!editingReturn) {
      setLineItems(
        gr.lines.map((l) => ({
          goods_receipt_line_id: l.id,
          product_id: l.product_id,
          description: l.description || getProductName(l.product),
          uom_name: l.unitOfMeasure?.name || accDict.notAvailable,
          max_quantity: l.quantity_e6 / 1000000,
          quantity: l.quantity_e6 / 1000000,
        }))
      );
    }
  };

  const updateLineItem = <K extends keyof ReturnLineForm>(index: number, field: K, value: ReturnLineForm[K]) => {
    setLineItems((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], [field]: value };
      return next;
    });
  };

  const openCreateModal = () => {
    reset();
    setEditingReturn(null);
    setLineItems([]);
    setShowModal(true);
  };

  const openEditModal = (ret: PurchaseReturnRow) => {
    setEditingReturn(ret);
    setData({
      supplier_id: ret.supplier_id,
      goods_receipt_id: ret.goods_receipt_id || '',
      warehouse_id: ret.warehouse_id || warehouses[0]?.id || '',
      return_date: ret.return_date,
      currency: ret.currency,
      reason: ret.reason || '',
      notes: ret.notes || '',
      lock_version: ret.lock_version,
    });
    setLineItems(
      (ret.lines || []).map((l) => ({
        goods_receipt_line_id: l.goods_receipt_line_id,
        product_id: l.product_id,
        description: l.description || getProductName(l.product),
        uom_name: l.unitOfMeasure?.name || accDict.notAvailable,
        max_quantity: l.quantity_e6 / 1000000,
        quantity: l.quantity_e6 / 1000000,
      }))
    );
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingReturn(null);
    reset();
    setLineItems([]);
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems
      .filter((item) => item.goods_receipt_line_id && Number(item.quantity) > 0)
      .map((item) => ({
        goods_receipt_line_id: item.goods_receipt_line_id,
        product_id: item.product_id,
        quantity_e6: Math.round(Number(item.quantity) * 1000000),
      }));

    if (formattedLines.length === 0) return;

    const payload = {
      ...data,
      lines: formattedLines,
    };

    if (editingReturn) {
      router.put(`/purchasing/returns/${editingReturn.id}`, payload, {
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/purchasing/returns', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (retId: string, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.purchasingPurchaseReturns.submitThisPurchaseReturn;
    if (action === 'approve') confirmMsg = dict.app.pages.purchasingPurchaseReturns.approveThisPurchaseReturn;
    if (action === 'post') confirmMsg = dict.app.pages.purchasingPurchaseReturns.postThisPurchaseReturnToInventoryGl;
    if (action === 'cancel') confirmMsg = dict.app.pages.purchasingPurchaseReturns.cancelThisPurchaseReturn;

    if (confirm(confirmMsg)) {
      router.post(`/purchasing/returns/${retId}/${action}`);
    }
  };

  const getStatusTone = (status: string): 'muted' | 'info' | 'warning' | 'ok' | 'danger' => {
    switch (status) {
      case 'draft':
        return 'muted';
      case 'submitted':
        return 'info';
      case 'approved':
        return 'warning';
      case 'posted':
        return 'ok';
      case 'cancelled':
        return 'danger';
      default:
        return 'muted';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'draft':
        return dict.app.pages.purchasingPurchaseReturns.draft;
      case 'submitted':
        return dict.app.pages.purchasingPurchaseReturns.submitted;
      case 'approved':
        return dict.app.pages.purchasingPurchaseReturns.approved;
      case 'posted':
        return dict.app.pages.purchasingPurchaseReturns.posted;
      case 'cancelled':
        return dict.app.pages.purchasingPurchaseReturns.cancelled;
      default:
        return status;
    }
  };

  return (
    <AppLayout active="purchase-returns.index">
      <Head title={dict.app.pages.purchasingPurchaseReturns.purchaseReturns} />

      <PageHeader
        title={dict.app.pages.purchasingPurchaseReturns.purchaseReturns_2}
        description={dict.app.pages.purchasingPurchaseReturns.manageSupplierPurchaseReturnsToStock}
        actions={
          can('purchasing.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.purchasingPurchaseReturns.createPurchaseReturn}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.purchasingPurchaseReturns.searchNumberReasonOrSupplier}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/purchasing/returns', { ...filters, search: val }, { preserveState: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <select
              value={filters.warehouse_id || ''}
              onChange={(e) => router.get('/purchasing/returns', { ...filters, warehouse_id: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.purchasingPurchaseReturns.allWarehouses}</option>
              {warehouses.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.code} - {getLocalizedName(warehouse.name, locale)}
                </option>
              ))}
            </select>

            <select
              value={filters.status || ''}
              onChange={(e) => router.get('/purchasing/returns', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.purchasingPurchaseReturns.allStatuses}</option>
              <option value="draft">{dict.app.pages.purchasingPurchaseReturns.draft}</option>
              <option value="submitted">{dict.app.pages.purchasingPurchaseReturns.submitted}</option>
              <option value="approved">{dict.app.pages.purchasingPurchaseReturns.approved}</option>
              <option value="posted">{dict.app.pages.purchasingPurchaseReturns.posted}</option>
              <option value="cancelled">{dict.app.pages.purchasingPurchaseReturns.cancelled}</option>
            </select>
          </div>
        </div>

        {purchaseReturns.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.purchasingPurchaseReturns.noPurchaseReturnsFound}
            description={dict.app.pages.purchasingPurchaseReturns.createAReturnFromAConfirmedGoodsReceipt}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseReturns.returnNumber}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseReturns.supplier}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseReturns.goodsReceipt}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseReturns.warehouse}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseReturns.returnDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseReturns.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.purchasingPurchaseReturns.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {purchaseReturns.data.map((ret) => (
                  <tr key={ret.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {ret.number || dict.app.pages.purchasingPurchaseReturns.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-medium`}>{ret.supplier?.name || accDict.notAvailable}</td>
                    <td className={`${tableClasses.td} font-mono`}>{ret.goodsReceipt?.number || accDict.notAvailable}</td>
                    <td className={tableClasses.td}>{ret.warehouse ? `${ret.warehouse.code} - ${getLocalizedName(ret.warehouse.name, locale)}` : accDict.notAvailable}</td>
                    <td className={tableClasses.td}>{ret.return_date}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(ret.status)}>
                        {getStatusLabel(ret.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {ret.status === 'draft' && can('purchasing.returns') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(ret)}
                          className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                          {dict.app.pages.purchasingPurchaseReturns.edit}
                        </button>
                      ) : null}

                      {ret.status === 'draft' && can('purchasing.returns') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'submit')}
                          className="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                        >
                          {dict.app.pages.purchasingPurchaseReturns.submit}
                        </button>
                      ) : null}

                      {['draft', 'submitted'].includes(ret.status) && can('purchasing.returns') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'approve')}
                          className="text-xs font-semibold text-amber-600 hover:text-amber-800"
                        >
                          {dict.app.pages.purchasingPurchaseReturns.approve}
                        </button>
                      ) : null}

                      {ret.status === 'approved' && can('purchasing.returns') && can('view_financials') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'post')}
                          className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                        >
                          {dict.app.pages.purchasingPurchaseReturns.post}
                        </button>
                      ) : null}

                      {['draft', 'submitted', 'approved'].includes(ret.status) && can('purchasing.returns') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'cancel')}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {dict.app.pages.purchasingPurchaseReturns.cancel}
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-3xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingReturn ? dict.app.pages.purchasingPurchaseReturns.editPurchaseReturn : dict.app.pages.purchasingPurchaseReturns.createPurchaseReturn_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.supplier_2} *</label>
                  <select
                    disabled={Boolean(editingReturn)}
                    value={data.supplier_id}
                    onChange={(e) => handleSupplierSelect(e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.purchasingPurchaseReturns.selectSupplier}</option>
                    {activeSuppliers.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} ({s.code})
                      </option>
                    ))}
                  </select>
                  {errors.supplier_id ? <p className="mt-1 text-[10px] text-red-500">{errors.supplier_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.confirmedGoodsReceipt} *</label>
                  <select
                    disabled={Boolean(editingReturn)}
                    value={data.goods_receipt_id}
                    onChange={(e) => handleGoodsReceiptSelect(e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.purchasingPurchaseReturns.selectGoodsReceipt}</option>
                    {supplierGoodsReceipts.map((gr) => (
                      <option key={gr.id} value={gr.id}>
                        {gr.number || dict.app.pages.purchasingPurchaseReturns.draft_2} - {gr.purchaseOrder?.supplier?.name}
                      </option>
                    ))}
                  </select>
                  {errors.goods_receipt_id ? <p className="mt-1 text-[10px] text-red-500">{errors.goods_receipt_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.returnDate_2} *</label>
                  <input
                    type="date"
                    value={data.return_date}
                    onChange={(e) => setData('return_date', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                  {errors.return_date ? <p className="mt-1 text-[10px] text-red-500">{errors.return_date}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.warehouse} *</label>
                  <select
                    value={data.warehouse_id}
                    onChange={(e) => setData('warehouse_id', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">{dict.app.pages.purchasingPurchaseReturns.selectWarehouse}</option>
                    {warehouses.map((warehouse) => (
                      <option key={warehouse.id} value={warehouse.id}>
                        {warehouse.code} - {getLocalizedName(warehouse.name, locale)}
                        {warehouse.is_default ? ` (${dict.app.pages.purchasingPurchaseReturns.defaultWarehouse})` : ''}
                      </option>
                    ))}
                  </select>
                  {errors.warehouse_id ? <p className="mt-1 text-[10px] text-red-500">{errors.warehouse_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.currency}</label>
                  <input
                    type="text"
                    disabled
                    value={data.currency}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-xs font-mono uppercase text-[var(--text-muted)] disabled:opacity-50"
                  />
                </div>
              </div>

              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{dict.app.pages.purchasingPurchaseReturns.returnLines}</h4>
                </div>

                {lineItems.length === 0 ? (
                  <p className="text-xs text-[var(--text-muted)]">{dict.app.pages.purchasingPurchaseReturns.selectAGoodsReceiptToLoadItsLines}</p>
                ) : (
                  <div className="space-y-3">
                    {lineItems.map((item, idx) => (
                      <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-end gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                        <div className="flex-1 w-full min-w-0">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.purchasingPurchaseReturns.description}</label>
                          <input
                            type="text"
                            disabled
                            value={item.description}
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-primary)] font-medium"
                          />
                        </div>

                        <div className="w-full sm:w-32">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.purchasingPurchaseReturns.returnQty} ({dict.app.pages.purchasingPurchaseReturns.max} {formatQuantity(Math.round(item.max_quantity * 1000000))})
                          </label>
                          <input
                            type="number"
                            step="0.000001"
                            min="0"
                            max={item.max_quantity > 0 ? item.max_quantity : undefined}
                            value={item.quantity}
                            onChange={(e) => updateLineItem(idx, 'quantity', parseFloat(e.target.value) || 0)}
                            required
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.reason}</label>
                <textarea
                  rows={2}
                  value={data.reason}
                  onChange={(e) => setData('reason', e.target.value)}
                  maxLength={255}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingPurchaseReturns.notes}</label>
                <textarea
                  rows={2}
                  value={data.notes}
                  onChange={(e) => setData('notes', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.purchasingPurchaseReturns.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing ? dict.app.pages.purchasingPurchaseReturns.saving : dict.app.pages.purchasingPurchaseReturns.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
