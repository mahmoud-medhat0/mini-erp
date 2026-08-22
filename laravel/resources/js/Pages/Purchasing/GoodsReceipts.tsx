import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
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
  }>;
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
  }>;
};

type GoodsReceiptsProps = SharedPageProps & {
  goodsReceipts: {
    data: GoodsReceiptRow[];
    links: any[];
  };
  confirmedPurchaseOrders: PurchaseOrderOption[];
  filters: {
    search?: string;
    status?: string;
  };
};

export default function GoodsReceiptsIndex({ locale, goodsReceipts, confirmedPurchaseOrders, filters }: GoodsReceiptsProps) {
  const dict = getDictionary(locale);
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingReceipt, setEditingReceipt] = useState<GoodsReceiptRow | null>(null);

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<GoodsReceiptLineItem[]>([]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    purchase_order_id: confirmedPurchaseOrders[0]?.id || '',
    receipt_date: todayStr,
    reference: '',
    notes: '',
    lock_version: 1,
  });

  const handlePurchaseOrderSelect = (purchaseOrderId: string) => {
    setData('purchase_order_id', purchaseOrderId);
    const selectedPo = confirmedPurchaseOrders.find((po) => po.id === purchaseOrderId);
    if (selectedPo && selectedPo.lines) {
      setLineItems(
        selectedPo.lines.map((l) => ({
          purchase_order_line_id: l.id,
          product_name: l.product?.name || '',
          uom_name: l.unitOfMeasure?.name || 'PCS',
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
          uom_name: l.unitOfMeasure?.name || 'PCS',
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
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/purchasing/goods-receipts', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (receiptId: string, action: 'confirm' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'confirm') confirmMsg = dict.app.pages.purchasingGoodsReceipts.confirmThisGoodsReceipt;
    if (action === 'cancel') confirmMsg = dict.app.pages.purchasingGoodsReceipts.cancelThisGoodsReceipt;

    if (confirm(confirmMsg)) {
      router.post(`/purchasing/goods-receipts/${receiptId}/${action}`);
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

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.purchasingGoodsReceipts.searchNumberReferenceOrSupplier}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/purchasing/goods-receipts', { ...filters, search: val }, { preserveState: true });
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
              value={filters.status || ''}
              onChange={(e) => router.get('/purchasing/goods-receipts', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.purchasingGoodsReceipts.allStatuses}</option>
              <option value="draft">{dict.app.pages.purchasingGoodsReceipts.draft}</option>
              <option value="confirmed">{dict.app.pages.purchasingGoodsReceipts.confirmed}</option>
              <option value="cancelled">{dict.app.pages.purchasingGoodsReceipts.cancelled}</option>
            </select>
          </div>
        </div>

        {goodsReceipts.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.purchasingGoodsReceipts.noGoodsReceiptsFound}
            description={dict.app.pages.purchasingGoodsReceipts.confirmAPurchaseOrderFirstThen}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingGoodsReceipts.goodsReceipt}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingGoodsReceipts.purchaseOrder}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingGoodsReceipts.supplier}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingGoodsReceipts.receiptDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingGoodsReceipts.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.purchasingGoodsReceipts.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {goodsReceipts.data.map((receipt) => (
                  <tr key={receipt.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {receipt.number || dict.app.pages.purchasingGoodsReceipts.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>{receipt.purchaseOrder?.number || '-'}</td>
                    <td className={`${tableClasses.td} font-medium`}>{receipt.purchaseOrder?.supplier?.name || '-'}</td>
                    <td className={tableClasses.td}>{receipt.receipt_date}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(receipt.status)}>
                        {getStatusLabel(receipt.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {receipt.status === 'draft' ? (
                        <>
                          {can('purchasing.edit') ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(receipt)}
                              className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                            >
                              {dict.app.pages.purchasingGoodsReceipts.edit}
                            </button>
                          ) : null}
                          {can('purchasing.approve') ? (
                            <button
                              type="button"
                              onClick={() => handleAction(receipt.id, 'confirm')}
                              className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                            >
                              {dict.app.pages.purchasingGoodsReceipts.confirm}
                            </button>
                          ) : null}
                          {can('purchasing.cancel') ? (
                            <button
                              type="button"
                              onClick={() => handleAction(receipt.id, 'cancel')}
                              className="text-xs font-semibold text-red-600 hover:text-red-800"
                            >
                              {dict.app.pages.purchasingGoodsReceipts.cancel}
                            </button>
                          ) : null}
                        </>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
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
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingGoodsReceipts.confirmedPurchaseOrder} *
                  </label>
                  <select
                    disabled={Boolean(editingReceipt)}
                    value={data.purchase_order_id}
                    onChange={(e) => handlePurchaseOrderSelect(e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.purchasingGoodsReceipts.selectPurchaseOrder}</option>
                    {confirmedPurchaseOrders.map((po) => (
                      <option key={po.id} value={po.id}>
                        {po.number} - {po.supplier?.name}
                      </option>
                    ))}
                  </select>
                  {errors.purchase_order_id ? <p className="mt-1 text-[10px] text-red-500">{errors.purchase_order_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingGoodsReceipts.receiptDate_2} *
                  </label>
                  <input
                    type="date"
                    value={data.receipt_date}
                    onChange={(e) => setData('receipt_date', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                  {errors.receipt_date ? <p className="mt-1 text-[10px] text-red-500">{errors.receipt_date}</p> : null}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.purchasingGoodsReceipts.reference}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder="e.g. VENDOR-DELIV-99"
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
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.purchasingGoodsReceipts.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing
                    ? dict.app.pages.purchasingGoodsReceipts.saving
                    : dict.app.pages.purchasingGoodsReceipts.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
