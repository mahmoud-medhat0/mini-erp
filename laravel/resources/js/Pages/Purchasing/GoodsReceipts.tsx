import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type SupplierOption = {
  id: string;
  code: string;
  name: string;
};

type PurchaseOrderOption = {
  id: string;
  number?: string | null;
  supplier?: SupplierOption | null;
  lines: Array<{
    id: string;
    line_no: number;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    product?: {
      id: string;
      code: string;
      name: string;
    } | null;
    unitOfMeasure?: {
      id: string;
      code: string;
      name: string;
    } | null;
    unit_of_measure?: {
      id: string;
      code: string;
      name: string;
    } | null;
  }>;
};

type WarehouseOption = {
  id: string;
  code: string;
  name: { en?: string; ar?: string } | string;
  is_default?: boolean;
};

type GoodsReceiptLineItem = {
  id?: string;
  purchase_order_line_id: string;
  product_name: string;
  uom_name: string;
  description: string;
  quantity: number; // Decimal input on UI
};

type GoodsReceiptRow = {
  id: string;
  number?: string | null;
  purchase_order_id: string;
  warehouse_id: string;
  receipt_date: string;
  status: 'draft' | 'confirmed' | 'cancelled';
  reference?: string | null;
  notes?: string | null;
  lock_version: number;
  created_at: string;
  purchaseOrder?: {
    id: string;
    number?: string | null;
    supplier?: SupplierOption | null;
  } | null;
  purchase_order?: {
    id: string;
    number?: string | null;
    supplier?: SupplierOption | null;
  } | null;
  warehouse?: WarehouseOption | null;
  lines: Array<{
    id: string;
    line_no: number;
    purchase_order_line_id: string;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    product?: {
      code: string;
      name: string;
    } | null;
    unitOfMeasure?: {
      code: string;
      name: string;
    } | null;
    unit_of_measure?: {
      code: string;
      name: string;
    } | null;
  }>;
};

type GoodsReceiptsProps = SharedPageProps & {
  goodsReceipts?: GoodsReceiptRow[];
  confirmedPurchaseOrders: PurchaseOrderOption[];
  warehouses: WarehouseOption[];
  filters: {
    search?: string;
    status?: string;
    warehouse_id?: string;
  };
};

export default function GoodsReceiptsIndex({ locale, confirmedPurchaseOrders, warehouses, filters }: GoodsReceiptsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.purchasingGoodsReceipts;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingReceipt, setEditingReceipt] = useState<GoodsReceiptRow | null>(null);
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [warehouseFilter, setWarehouseFilter] = useState(filters.warehouse_id || '');
  const [reloadToken, setReloadToken] = useState(0);

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<GoodsReceiptLineItem[]>([]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    purchase_order_id: confirmedPurchaseOrders[0]?.id || '',
    warehouse_id: warehouses[0]?.id || '',
    receipt_date: todayStr,
    reference: '',
    notes: '',
    lock_version: 1,
  });
  const warehouseOptions = useMemo(() => warehouses.map((warehouse) => ({
    value: warehouse.id,
    label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
    badge: warehouse.is_default ? pageDict.defaultWarehouse : undefined,
  })), [warehouses, locale, pageDict.defaultWarehouse]);
  const warehouseFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allWarehouses },
    ...warehouseOptions,
  ], [pageDict.allWarehouses, warehouseOptions]);
  const statusFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'draft', label: pageDict.draft },
    { value: 'confirmed', label: pageDict.confirmed },
    { value: 'cancelled', label: pageDict.cancelled },
  ], [pageDict.allStatuses, pageDict.draft, pageDict.confirmed, pageDict.cancelled]);
  const purchaseOrderOptions = useMemo(() => confirmedPurchaseOrders.map((purchaseOrder) => ({
    value: purchaseOrder.id,
    label: purchaseOrder.number || accDict.notAvailable,
    sublabel: purchaseOrder.supplier?.name || accDict.notAvailable,
  })), [confirmedPurchaseOrders, accDict.notAvailable]);
  const canEditGoodsReceipts = can('purchasing.edit');
  const canConfirmGoodsReceipts = can('purchasing.approve');
  const canCancelGoodsReceipts = can('purchasing.cancel');
  const goodsReceiptSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;

  const handlePurchaseOrderSelect = (purchaseOrderId: string) => {
    setData('purchase_order_id', purchaseOrderId);
    const selectedPo = confirmedPurchaseOrders.find((po) => po.id === purchaseOrderId);
    if (selectedPo && selectedPo.lines) {
      setLineItems(
        selectedPo.lines.map((l) => ({
          purchase_order_line_id: l.id,
          product_name: l.product?.name || '',
          uom_name: (l.unit_of_measure || l.unitOfMeasure)?.name || dict.app.pages.purchasingGoodsReceipts.noUom,
          description: l.description || '',
          quantity: l.quantity_e6 / 1000000,
        }))
      );
    } else {
      setLineItems([]);
    }
  };

  const openCreateModal = () => {
    reset();
    setEditingReceipt(null);
    const defaultPo = confirmedPurchaseOrders[0];
    if (defaultPo) {
      handlePurchaseOrderSelect(defaultPo.id);
    } else {
      setLineItems([]);
    }
    setShowModal(true);
  };

  const openEditModal = (receipt: GoodsReceiptRow) => {
    setEditingReceipt(receipt);
    setData({
      purchase_order_id: receipt.purchase_order_id,
      warehouse_id: receipt.warehouse_id || warehouses[0]?.id || '',
      receipt_date: receipt.receipt_date,
      reference: receipt.reference || '',
      notes: receipt.notes || '',
      lock_version: receipt.lock_version,
    });

    if (receipt.lines && receipt.lines.length > 0) {
      setLineItems(
        receipt.lines.map((l) => ({
          id: l.id,
          purchase_order_line_id: l.purchase_order_line_id,
          product_name: l.product?.name || '',
          uom_name: (l.unit_of_measure || l.unitOfMeasure)?.name || dict.app.pages.purchasingGoodsReceipts.noUom,
          description: l.description || '',
          quantity: l.quantity_e6 / 1000000,
        }))
      );
    }
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingReceipt(null);
    reset();
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      purchase_order_line_id: item.purchase_order_line_id,
      description: item.description,
      quantity_e6: Math.round(Number(item.quantity) * 1000000),
    }));

    const payload = {
      ...data,
      lines: formattedLines,
    };

    if (editingReceipt) {
      router.put(`/purchasing/goods-receipts/${editingReceipt.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => {
          closeModal();
          setReloadToken((value) => value + 1);
        },
      });
    } else {
      router.post('/purchasing/goods-receipts', payload, {
        preserveScroll: true,
        onSuccess: () => {
          closeModal();
          setReloadToken((value) => value + 1);
        },
      });
    }
  };

  const handleAction = (receiptId: string, action: 'confirm' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'confirm') confirmMsg = dict.app.pages.purchasingGoodsReceipts.confirmThisGoodsReceipt;
    if (action === 'cancel') confirmMsg = dict.app.pages.purchasingGoodsReceipts.cancelThisGoodsReceipt;

    if (confirm(confirmMsg)) {
      router.post(`/purchasing/goods-receipts/${receiptId}/${action}`, {}, {
        preserveScroll: true,
        onSuccess: () => setReloadToken((value) => value + 1),
      });
    }
  };

  const getStatusTone = (status: string): 'muted' | 'ok' | 'danger' => {
    switch (status) {
      case 'draft':
        return 'muted';
      case 'confirmed':
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
        return dict.app.pages.purchasingGoodsReceipts.draft;
      case 'confirmed':
        return dict.app.pages.purchasingGoodsReceipts.confirmed;
      case 'cancelled':
        return dict.app.pages.purchasingGoodsReceipts.cancelled;
      default:
        return status;
    }
  };

  const hasAvailableGoodsReceiptAction = (receipt: GoodsReceiptRow) => (
    receipt.status === 'draft' && (canEditGoodsReceipts || canConfirmGoodsReceipts || canCancelGoodsReceipts)
  );

  const getGoodsReceiptActionState = (receipt: GoodsReceiptRow) => {
    if (hasAvailableGoodsReceiptAction(receipt)) return null;

    return receipt.status === 'draft' ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  const columns = useMemo(() => [
    { data: 'number', name: 'number', title: pageDict.goodsReceipt },
    { data: 'purchase_order_number', name: 'purchase_order_number', title: pageDict.purchaseOrder, orderable: false, searchable: false },
    { data: 'supplier_name', name: 'supplier_name', title: pageDict.supplier, orderable: false, searchable: false },
    { data: 'warehouse_name', name: 'warehouse_name', title: pageDict.warehouse, orderable: false, searchable: false },
    { data: 'receipt_date', name: 'receipt_date', title: pageDict.receiptDate },
    { data: 'status', name: 'status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    number: (value: any) => <span className="font-mono font-bold text-blue-600">{value || pageDict.draft_2}</span>,
    purchase_order_number: (_value: any, _type: any, receipt: GoodsReceiptRow) => {
      const purchaseOrder = receipt.purchase_order || receipt.purchaseOrder;
      return <span className="font-mono">{purchaseOrder?.number || accDict.notAvailable}</span>;
    },
    supplier_name: (_value: any, _type: any, receipt: GoodsReceiptRow) => {
      const purchaseOrder = receipt.purchase_order || receipt.purchaseOrder;
      return <span className="font-medium">{getLocalizedName(purchaseOrder?.supplier?.name, locale) || accDict.notAvailable}</span>;
    },
    warehouse_name: (_value: any, _type: any, receipt: GoodsReceiptRow) => (
      <span>{receipt.warehouse ? `${receipt.warehouse.code} - ${getLocalizedName(receipt.warehouse.name, locale)}` : accDict.notAvailable}</span>
    ),
    receipt_date: (value: any) => <span className="font-mono text-xs">{value}</span>,
    status: (value: any) => <StatusBadge tone={getStatusTone(value)}>{getStatusLabel(value)}</StatusBadge>,
    actions: (_value: any, _type: any, receipt: GoodsReceiptRow) => {
      const actionState = getGoodsReceiptActionState(receipt);
      return (
        <div className="flex flex-wrap items-center justify-end gap-2">
          {receipt.status === 'draft' && canEditGoodsReceipts ? (
            <button type="button" onClick={() => openEditModal(receipt)} title={pageDict.edit} aria-label={pageDict.edit} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.edit}</button>
          ) : null}
          {receipt.status === 'draft' && canConfirmGoodsReceipts ? (
            <button type="button" onClick={() => handleAction(receipt.id, 'confirm')} title={pageDict.confirm} aria-label={pageDict.confirm} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.confirm}</button>
          ) : null}
          {receipt.status === 'draft' && canCancelGoodsReceipts ? (
            <button type="button" onClick={() => handleAction(receipt.id, 'cancel')} title={pageDict.cancel} aria-label={pageDict.cancel} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{pageDict.cancel}</button>
          ) : null}
          {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
        </div>
      );
    },
  }), [accDict.notAvailable, canCancelGoodsReceipts, canConfirmGoodsReceipts, canEditGoodsReceipts, locale, pageDict]);

  const tableFilters = useMemo(() => ({ status: statusFilter, warehouse_id: warehouseFilter }), [statusFilter, warehouseFilter]);
  const toolbar = (
    <div className="flex flex-wrap items-end gap-3">
      <SearchableSelect options={warehouseFilterOptions} value={warehouseFilter || null} onChange={(value) => setWarehouseFilter(value || '')} label={pageDict.warehouse} />
      <SearchableSelect options={statusFilterOptions} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} label={pageDict.status} />
    </div>
  );

  return (
    <AppLayout active="goods-receipts.index">
      <Head title={dict.app.pages.purchasingGoodsReceipts.goodsReceipts} />

      <PageHeader
        title={dict.app.pages.purchasingGoodsReceipts.goodsReceipts_2}
        description={dict.app.pages.purchasingGoodsReceipts.manageSupplierPurchaseGoodsReceipts}
        actions={
          can('purchasing.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              disabled={confirmedPurchaseOrders.length === 0}
              title={pageDict.createGoodsReceipt}
              aria-label={pageDict.createGoodsReceipt}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 disabled:opacity-50 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.purchasingGoodsReceipts.createGoodsReceipt}</span>
            </button>
          ) : null
        }
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/purchasing/goods-receipts/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[4, 'desc']]}
          pageLength={25}
          reloadToken={reloadToken}
          slots={slots}
          tableId="purchasing-goods-receipts-data-table"
          toolbar={toolbar}
        />
      </Card>

      {/* Create / Edit Modal */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-3xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingReceipt
                ? dict.app.pages.purchasingGoodsReceipts.editGoodsReceipt
                : dict.app.pages.purchasingGoodsReceipts.createGoodsReceipt_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <SearchableSelect
                  label={dict.app.pages.purchasingGoodsReceipts.confirmedPurchaseOrder}
                  value={data.purchase_order_id || null}
                  onChange={(value) => handlePurchaseOrderSelect(value || '')}
                  options={purchaseOrderOptions}
                  placeholder={dict.app.pages.purchasingGoodsReceipts.selectPurchaseOrder}
                  disabled={Boolean(editingReceipt)}
                  isClearable={false}
                  required
                  error={errors.purchase_order_id}
                />

                <SearchableSelect
                  label={dict.app.pages.purchasingGoodsReceipts.warehouse}
                  value={data.warehouse_id || null}
                  onChange={(value) => setData('warehouse_id', value || '')}
                  options={warehouseOptions}
                  placeholder={dict.app.pages.purchasingGoodsReceipts.selectWarehouse}
                  isClearable={false}
                  required
                  error={errors.warehouse_id}
                />

                <DatePicker
                  label={dict.app.pages.purchasingGoodsReceipts.receiptDate_2}
                  value={data.receipt_date}
                  onChange={(value) => setData('receipt_date', value || '')}
                  required
                  error={errors.receipt_date}
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.purchasingGoodsReceipts.reference}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder={dict.app.pages.purchasingGoodsReceipts.referencePlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                />
              </div>

              {/* Goods Receipt Lines */}
              <div className="pt-4 border-t border-[var(--border)]">
                <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)] mb-3">
                  {dict.app.pages.purchasingGoodsReceipts.receiptLines}
                </h4>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => (
                    <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                      <div className="flex-1 w-full sm:w-auto">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.purchasingGoodsReceipts.product}
                        </label>
                        <input
                          type="text"
                          disabled
                          value={item.product_name}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-primary)] font-medium"
                        />
                      </div>

                      <div className="w-full sm:w-24">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.purchasingGoodsReceipts.uom}
                        </label>
                        <input
                          type="text"
                          disabled
                          value={item.uom_name}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-muted)] font-medium"
                        />
                      </div>

                      <div className="w-full sm:w-32">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.purchasingGoodsReceipts.receivedQty}
                        </label>
                        <input
                          type="number"
                          step="0.000001"
                          min="0.000001"
                          value={item.quantity}
                          onChange={(e) => {
                            const val = parseFloat(e.target.value) || 0;
                            setLineItems((prev) => {
                              const next = [...prev];
                              next[idx] = { ...next[idx], quantity: val };
                              return next;
                            });
                          }}
                          required
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.purchasingGoodsReceipts.notes}
                </label>
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
                  title={pageDict.cancel_2}
                  aria-label={pageDict.cancel_2}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.purchasingGoodsReceipts.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={goodsReceiptSubmitLabel}
                  aria-label={goodsReceiptSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {goodsReceiptSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
