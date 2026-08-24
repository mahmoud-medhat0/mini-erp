import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type SupplierOption = {
  id: string;
  code: string;
  name: string;
};

type CurrencyOption = {
  code: string;
  name: string;
  symbol: string;
};

type ProductOption = {
  id: string;
  code: string;
  name: string;
  unit_of_measure_id: string;
  unit_of_measure?: {
    id: string;
    code: string;
    name: string;
    symbol: string;
  } | null;
};

type PurchaseOrderLineItem = {
  id?: string;
  product_id: string;
  unit_of_measure_id: string;
  description: string;
  quantity: number; // Decimal input on UI
  unit_price: number; // Major unit input on UI
};

type PurchaseOrderRow = {
  id: string;
  number?: string | null;
  supplier_id: string;
  order_date: string;
  expected_receipt_date?: string | null;
  currency: string;
  fx_rate_e6: number;
  status: 'draft' | 'submitted' | 'confirmed' | 'cancelled';
  reference?: string | null;
  notes?: string | null;
  subtotal_minor: number;
  total_minor: number;
  lock_version: number;
  created_at: string;
  supplier?: SupplierOption | null;
  lines: Array<{
    id: string;
    line_no: number;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    unit_price_minor: number;
    line_total_minor: number;
    product?: ProductOption | null;
    unit_of_measure?: {
      code: string;
      name: string;
    } | null;
  }>;
};

type PurchaseOrdersProps = SharedPageProps & {
  purchaseOrders: {
    data: PurchaseOrderRow[];
    links: any[];
  };
  suppliers: SupplierOption[];
  currencies: CurrencyOption[];
  products: ProductOption[];
  filters: {
    search?: string;
    status?: string;
    supplier_id?: string;
  };
};

export default function PurchaseOrdersIndex({ locale, purchaseOrders, suppliers, currencies, products, filters }: PurchaseOrdersProps) {
  const dict = getDictionary(locale);
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingOrder, setEditingOrder] = useState<PurchaseOrderRow | null>(null);

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<PurchaseOrderLineItem[]>([
    {
      product_id: products[0]?.id || '',
      unit_of_measure_id: products[0]?.unit_of_measure_id || '',
      description: '',
      quantity: 1,
      unit_price: 10,
    },
  ]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    supplier_id: suppliers[0]?.id || '',
    order_date: todayStr,
    expected_receipt_date: '',
    currency: currencies[0]?.code || 'USD',
    fx_rate_e6: 1000000,
    reference: '',
    notes: '',
    lock_version: 1,
  });

  const openCreateModal = () => {
    reset();
    setEditingOrder(null);
    const defaultProduct = products[0];
    setLineItems([
      {
        product_id: defaultProduct?.id || '',
        unit_of_measure_id: defaultProduct?.unit_of_measure_id || '',
        description: '',
        quantity: 1,
        unit_price: 10,
      },
    ]);
    setData({
      supplier_id: suppliers[0]?.id || '',
      order_date: todayStr,
      expected_receipt_date: '',
      currency: currencies[0]?.code || 'USD',
      fx_rate_e6: 1000000,
      reference: '',
      notes: '',
      lock_version: 1,
    });
    setShowModal(true);
  };

  const openEditModal = (order: PurchaseOrderRow) => {
    setEditingOrder(order);
    setData({
      supplier_id: order.supplier_id,
      order_date: order.order_date,
      expected_receipt_date: order.expected_receipt_date || '',
      currency: order.currency,
      fx_rate_e6: order.fx_rate_e6,
      reference: order.reference || '',
      notes: order.notes || '',
      lock_version: order.lock_version,
    });

    if (order.lines && order.lines.length > 0) {
      setLineItems(
        order.lines.map((l) => ({
          id: l.id,
          product_id: l.product_id,
          unit_of_measure_id: l.unit_of_measure_id,
          description: l.description || '',
          quantity: l.quantity_e6 / 1000000,
          unit_price: l.unit_price_minor / 100,
        }))
      );
    }
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingOrder(null);
    reset();
  };

  const handleProductChange = (index: number, productId: string) => {
    const selectedProd = products.find((p) => p.id === productId);
    setLineItems((prev) => {
      const next = [...prev];
      next[index] = {
        ...next[index],
        product_id: productId,
        unit_of_measure_id: selectedProd?.unit_of_measure_id || '',
      };
      return next;
    });
  };

  const addLineItem = () => {
    const defaultProduct = products[0];
    setLineItems((prev) => [
      ...prev,
      {
        product_id: defaultProduct?.id || '',
        unit_of_measure_id: defaultProduct?.unit_of_measure_id || '',
        description: '',
        quantity: 1,
        unit_price: 10,
      },
    ]);
  };

  const removeLineItem = (index: number) => {
    if (lineItems.length <= 1) return;
    setLineItems((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      product_id: item.product_id,
      unit_of_measure_id: item.unit_of_measure_id,
      description: item.description,
      quantity_e6: Math.round(Number(item.quantity) * 1000000),
      unit_price_minor: Math.round(Number(item.unit_price) * 100),
    }));

    const payload = {
      ...data,
      lines: formattedLines,
    };

    if (editingOrder) {
      router.put(`/purchasing/orders/${editingOrder.id}`, payload, {
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/purchasing/orders', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (orderId: string, action: 'submit' | 'confirm' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.purchasingPurchaseOrders.submitThisPurchaseOrder;
    if (action === 'confirm') confirmMsg = dict.app.pages.purchasingPurchaseOrders.confirmThisPurchaseOrderAndAllocate;
    if (action === 'cancel') confirmMsg = dict.app.pages.purchasingPurchaseOrders.cancelThisPurchaseOrder;

    if (confirm(confirmMsg)) {
      router.post(`/purchasing/orders/${orderId}/${action}`);
    }
  };

  const getStatusTone = (status: string): 'muted' | 'info' | 'ok' | 'danger' => {
    switch (status) {
      case 'draft':
        return 'muted';
      case 'submitted':
        return 'info';
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
        return dict.app.pages.purchasingPurchaseOrders.draft;
      case 'submitted':
        return dict.app.pages.purchasingPurchaseOrders.submitted;
      case 'confirmed':
        return dict.app.pages.purchasingPurchaseOrders.confirmed;
      case 'cancelled':
        return dict.app.pages.purchasingPurchaseOrders.cancelled;
      default:
        return status;
    }
  };

  const calculatePreviewSubtotal = () => {
    return lineItems.reduce((acc, item) => {
      const q = Number(item.quantity) || 0;
      const p = Number(item.unit_price) || 0;
      return acc + q * p;
    }, 0);
  };

  return (
    <AppLayout active="purchase-orders.index">
      <Head title={dict.app.pages.purchasingPurchaseOrders.purchaseOrders} />

      <PageHeader
        title={dict.app.pages.purchasingPurchaseOrders.purchaseOrders_2}
        description={dict.app.pages.purchasingPurchaseOrders.manageSupplierPurchaseOrdersAndCommitments}
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
            <span>{dict.app.pages.purchasingPurchaseOrders.createPurchaseOrder}</span>
          </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.purchasingPurchaseOrders.searchNumberReferenceOrSupplier}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/purchasing/orders', { ...filters, search: val }, { preserveState: true });
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
              onChange={(e) => router.get('/purchasing/orders', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.purchasingPurchaseOrders.allStatuses}</option>
              <option value="draft">{dict.app.pages.purchasingPurchaseOrders.draft}</option>
              <option value="submitted">{dict.app.pages.purchasingPurchaseOrders.submitted}</option>
              <option value="confirmed">{dict.app.pages.purchasingPurchaseOrders.confirmed}</option>
              <option value="cancelled">{dict.app.pages.purchasingPurchaseOrders.cancelled}</option>
            </select>
          </div>
        </div>

        {purchaseOrders.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.purchasingPurchaseOrders.noPurchaseOrdersFound}
            description={dict.app.pages.purchasingPurchaseOrders.getStartedByCreatingYourFirst}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseOrders.order}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseOrders.supplier}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseOrders.date}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseOrders.totalAmount}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingPurchaseOrders.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.purchasingPurchaseOrders.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {purchaseOrders.data.map((order) => (
                  <tr key={order.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {order.number || dict.app.pages.purchasingPurchaseOrders.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-medium`}>{order.supplier?.name || '-'}</td>
                    <td className={tableClasses.td}>{order.order_date}</td>
                    <td className={`${tableClasses.td} text-end font-semibold accounting-amount`}>
                      {formatMoney(order.total_minor, order.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(order.status)}>
                        {getStatusLabel(order.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {order.status === 'draft' ? (
                        <>
                          {can('purchasing.edit') ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(order)}
                              className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                            >
                              {dict.app.pages.purchasingPurchaseOrders.edit}
                            </button>
                          ) : null}
                          {can('purchasing.submit') ? (
                            <button
                              type="button"
                              onClick={() => handleAction(order.id, 'submit')}
                              className="text-xs font-semibold text-purple-600 hover:text-purple-800"
                            >
                              {dict.app.pages.purchasingPurchaseOrders.submit}
                            </button>
                          ) : null}
                        </>
                      ) : null}

                      {order.status === 'draft' || order.status === 'submitted' ? (
                        <>
                          {can('purchasing.approve') ? (
                            <button
                              type="button"
                              onClick={() => handleAction(order.id, 'confirm')}
                              className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                            >
                              {dict.app.pages.purchasingPurchaseOrders.confirm}
                            </button>
                          ) : null}
                          {can('purchasing.cancel') ? (
                            <button
                              type="button"
                              onClick={() => handleAction(order.id, 'cancel')}
                              className="text-xs font-semibold text-red-600 hover:text-red-800"
                            >
                              {dict.app.pages.purchasingPurchaseOrders.cancel}
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
              {editingOrder
                ? dict.app.pages.purchasingPurchaseOrders.editPurchaseOrder
                : dict.app.pages.purchasingPurchaseOrders.createPurchaseOrder_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingPurchaseOrders.supplier_2} *
                  </label>
                  <select
                    value={data.supplier_id}
                    onChange={(e) => setData('supplier_id', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">{dict.app.pages.purchasingPurchaseOrders.selectSupplier}</option>
                    {suppliers.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} ({s.code})
                      </option>
                    ))}
                  </select>
                  {errors.supplier_id ? <p className="mt-1 text-[10px] text-red-500">{errors.supplier_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingPurchaseOrders.orderDate} *
                  </label>
                  <input
                    type="date"
                    value={data.order_date}
                    onChange={(e) => setData('order_date', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                  {errors.order_date ? <p className="mt-1 text-[10px] text-red-500">{errors.order_date}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingPurchaseOrders.currency} *
                  </label>
                  <select
                    value={data.currency}
                    onChange={(e) => setData('currency', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    {currencies.map((curr) => (
                      <option key={curr.code} value={curr.code}>
                        {curr.code} - {curr.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingPurchaseOrders.expectedReceiptDate}
                  </label>
                  <input
                    type="date"
                    value={data.expected_receipt_date}
                    onChange={(e) => setData('expected_receipt_date', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.purchasingPurchaseOrders.reference}
                  </label>
                  <input
                    type="text"
                    value={data.reference}
                    onChange={(e) => setData('reference', e.target.value)}
                    placeholder="e.g. RFQ-SUPP-99"
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                </div>
              </div>

              {/* Order Lines Section */}
              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">
                    {dict.app.pages.purchasingPurchaseOrders.orderLines}
                  </h4>
                  <button
                    type="button"
                    onClick={addLineItem}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800"
                  >
                    <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{dict.app.pages.purchasingPurchaseOrders.addLine}</span>
                  </button>
                </div>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => {
                    const lineProd = products.find((p) => p.id === item.product_id);
                    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);

                    return (
                      <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                        <div className="flex-1 w-full sm:w-auto">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.purchasingPurchaseOrders.productService}
                          </label>
                          <select
                            value={item.product_id}
                            onChange={(e) => handleProductChange(idx, e.target.value)}
                            required
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                          >
                            {products.map((p) => (
                              <option key={p.id} value={p.id}>
                                {p.name} ({p.code})
                              </option>
                            ))}
                          </select>
                        </div>

                        <div className="w-full sm:w-24">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.purchasingPurchaseOrders.uom}
                          </label>
                          <input
                            type="text"
                            disabled
                            value={lineProd?.unit_of_measure?.name || 'PCS'}
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-muted)] font-medium"
                          />
                        </div>

                        <div className="w-full sm:w-28">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.purchasingPurchaseOrders.quantity}
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

                        <div className="w-full sm:w-28">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.purchasingPurchaseOrders.unitPrice}
                          </label>
                          <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={item.unit_price}
                            onChange={(e) => {
                              const val = parseFloat(e.target.value) || 0;
                              setLineItems((prev) => {
                                const next = [...prev];
                                next[idx] = { ...next[idx], unit_price: val };
                                return next;
                              });
                            }}
                            required
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                          />
                        </div>

                        <div className="w-full sm:w-32">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.purchasingPurchaseOrders.lineTotal}
                          </label>
                          <div className="py-1.5 px-2 font-mono text-xs font-bold text-[var(--text-primary)]">
                            {lineTotal.toFixed(2)} {data.currency}
                          </div>
                        </div>

                        {lineItems.length > 1 ? (
                          <button
                            type="button"
                            onClick={() => removeLineItem(idx)}
                            className="mt-4 sm:mt-0 p-1.5 text-red-500 hover:text-red-700 transition-colors"
                          >
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        ) : null}
                      </div>
                    );
                  })}
                </div>

                <div className="mt-4 flex justify-end">
                  <div className="text-end">
                    <span className="text-xs font-semibold text-[var(--text-secondary)] me-2">
                      {dict.app.pages.purchasingPurchaseOrders.orderTotal}
                    </span>
                    <span className="text-base font-extrabold text-blue-600 font-mono">
                      {calculatePreviewSubtotal().toFixed(2)} {data.currency}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.purchasingPurchaseOrders.notes}
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
                  {dict.app.pages.purchasingPurchaseOrders.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing
                    ? dict.app.pages.purchasingPurchaseOrders.saving
                    : dict.app.pages.purchasingPurchaseOrders.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
