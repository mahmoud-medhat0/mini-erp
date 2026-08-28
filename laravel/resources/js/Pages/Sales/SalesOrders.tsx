import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type CustomerOption = {
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

type SalesOrderLineItem = {
  id?: string;
  product_id: string;
  unit_of_measure_id: string;
  description: string;
  quantity: number; // Decimal input on UI
  unit_price: number; // Major unit input on UI
};

type SalesOrderRow = {
  id: string;
  number?: string | null;
  customer_id: string;
  order_date: string;
  expected_delivery_date?: string | null;
  currency: string;
  fx_rate_e6: number;
  status: 'draft' | 'submitted' | 'confirmed' | 'cancelled';
  reference?: string | null;
  notes?: string | null;
  subtotal_minor: number;
  total_minor: number;
  lock_version: number;
  created_at: string;
  customer?: CustomerOption | null;
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

type SalesOrdersProps = SharedPageProps & {
  salesOrders: {
    data: SalesOrderRow[];
    links: PaginationLink[];
  };
  customers: CustomerOption[];
  currencies: CurrencyOption[];
  products: ProductOption[];
  filters: {
    search?: string;
    status?: string;
    customer_id?: string;
  };
};

export default function SalesOrdersIndex({ locale, salesOrders, customers, currencies, products, filters }: SalesOrdersProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.salesSalesOrders;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingOrder, setEditingOrder] = useState<SalesOrderRow | null>(null);

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<SalesOrderLineItem[]>([
    {
      product_id: products[0]?.id || '',
      unit_of_measure_id: products[0]?.unit_of_measure_id || '',
      description: '',
      quantity: 1,
      unit_price: 10,
    },
  ]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    customer_id: customers[0]?.id || '',
    order_date: todayStr,
    expected_delivery_date: '',
    currency: currencies[0]?.code || '',
    fx_rate_e6: 1000000,
    reference: '',
    notes: '',
    lock_version: 1,
  });
  const statusFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'draft', label: pageDict.draft },
    { value: 'submitted', label: pageDict.submitted },
    { value: 'confirmed', label: pageDict.confirmed },
    { value: 'cancelled', label: pageDict.cancelled },
  ], [pageDict.allStatuses, pageDict.draft, pageDict.submitted, pageDict.confirmed, pageDict.cancelled]);
  const customerOptions = useMemo(() => customers.map((customer) => ({
    value: customer.id,
    label: customer.name,
    sublabel: customer.code,
  })), [customers]);
  const currencyOptions = useMemo(() => currencies.map((currency) => ({
    value: currency.code,
    label: `${currency.code} - ${currency.name}`,
    sublabel: currency.symbol,
  })), [currencies]);
  const productOptions = useMemo(() => products.map((product) => ({
    value: product.id,
    label: product.name,
    sublabel: product.code,
  })), [products]);
  const canEditSalesOrders = can('sales.edit');
  const canSubmitSalesOrders = can('sales.submit');
  const canConfirmSalesOrders = can('sales.approve');
  const canCancelSalesOrders = can('sales.cancel');
  const salesOrderSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;

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
      customer_id: customers[0]?.id || '',
      order_date: todayStr,
      expected_delivery_date: '',
      currency: currencies[0]?.code || '',
      fx_rate_e6: 1000000,
      reference: '',
      notes: '',
      lock_version: 1,
    });
    setShowModal(true);
  };

  const openEditModal = (order: SalesOrderRow) => {
    setEditingOrder(order);
    setData({
      customer_id: order.customer_id,
      order_date: order.order_date,
      expected_delivery_date: order.expected_delivery_date || '',
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
      router.put(`/sales/orders/${editingOrder.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/orders', payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (orderId: string, action: 'submit' | 'confirm' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.salesSalesOrders.submitThisSalesOrder;
    if (action === 'confirm') confirmMsg = dict.app.pages.salesSalesOrders.confirmThisSalesOrderAndAllocate;
    if (action === 'cancel') confirmMsg = dict.app.pages.salesSalesOrders.cancelThisSalesOrder;

    if (confirm(confirmMsg)) {
      router.post(`/sales/orders/${orderId}/${action}`, {}, { preserveScroll: true });
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
        return dict.app.pages.salesSalesOrders.draft;
      case 'submitted':
        return dict.app.pages.salesSalesOrders.submitted;
      case 'confirmed':
        return dict.app.pages.salesSalesOrders.confirmed;
      case 'cancelled':
        return dict.app.pages.salesSalesOrders.cancelled;
      default:
        return status;
    }
  };

  const isSalesOrderActionable = (order: SalesOrderRow) => order.status === 'draft' || order.status === 'submitted';

  const hasAvailableSalesOrderAction = (order: SalesOrderRow) => (
    order.status === 'draft'
      ? canEditSalesOrders || canSubmitSalesOrders || canConfirmSalesOrders || canCancelSalesOrders
      : order.status === 'submitted'
        ? canConfirmSalesOrders || canCancelSalesOrders
        : false
  );

  const getSalesOrderActionState = (order: SalesOrderRow) => {
    if (hasAvailableSalesOrderAction(order)) return null;

    return isSalesOrderActionable(order) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  const calculatePreviewSubtotal = () => {
    return lineItems.reduce((acc, item) => {
      const q = Number(item.quantity) || 0;
      const p = Number(item.unit_price) || 0;
      return acc + q * p;
    }, 0);
  };

  return (
    <AppLayout active="sales-orders.index">
      <Head title={dict.app.pages.salesSalesOrders.salesOrders} />

      <PageHeader
        title={dict.app.pages.salesSalesOrders.salesOrders_2}
        description={dict.app.pages.salesSalesOrders.manageCustomerSalesOrdersAndCommitments}
        actions={
          can('sales.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={pageDict.createSalesOrder}
              aria-label={pageDict.createSalesOrder}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.salesSalesOrders.createSalesOrder}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.salesSalesOrders.searchNumberReferenceOrCustomer}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/orders', { ...filters, search: val }, { preserveState: true, preserveScroll: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <SearchableSelect
              options={statusFilterOptions}
              value={filters.status || null}
              onChange={(value) => router.get('/sales/orders', { ...filters, status: value || '' }, { preserveState: true, preserveScroll: true })}
              label={dict.app.pages.salesSalesOrders.status}
            />
          </div>
        </div>

        {salesOrders.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.salesSalesOrders.noSalesOrdersFound}
            description={dict.app.pages.salesSalesOrders.getStartedByCreatingYourFirst}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesOrders.order}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesOrders.customer}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesOrders.date}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesOrders.totalAmount}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesOrders.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesSalesOrders.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {salesOrders.data.map((order) => {
                  const actionState = getSalesOrderActionState(order);

                  return (
                    <tr key={order.id}>
                      <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                        {order.number || dict.app.pages.salesSalesOrders.draft_2}
                      </td>
                      <td className={`${tableClasses.td} font-medium`}>{order.customer?.name || accDict.notAvailable}</td>
                      <td className={tableClasses.td}>{order.order_date}</td>
                      <td className={`${tableClasses.td} text-end font-semibold accounting-amount`}>
                        {formatMoney(order.total_minor, order.currency)}
                      </td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={getStatusTone(order.status)}>
                          {getStatusLabel(order.status)}
                        </StatusBadge>
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          {order.status === 'draft' && canEditSalesOrders ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(order)}
                              title={dict.app.pages.salesSalesOrders.edit}
                              aria-label={dict.app.pages.salesSalesOrders.edit}
                              className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                            >
                              {dict.app.pages.salesSalesOrders.edit}
                            </button>
                          ) : null}

                          {order.status === 'draft' && canSubmitSalesOrders ? (
                            <button
                              type="button"
                              onClick={() => handleAction(order.id, 'submit')}
                              title={dict.app.pages.salesSalesOrders.submit}
                              aria-label={dict.app.pages.salesSalesOrders.submit}
                              className="inline-flex h-8 items-center rounded-md border border-violet-200 px-2.5 text-xs font-semibold text-violet-700 transition-colors hover:bg-violet-50 dark:border-violet-900/60 dark:text-violet-300 dark:hover:bg-violet-950/40"
                            >
                              {dict.app.pages.salesSalesOrders.submit}
                            </button>
                          ) : null}

                          {isSalesOrderActionable(order) && canConfirmSalesOrders ? (
                            <button
                              type="button"
                              onClick={() => handleAction(order.id, 'confirm')}
                              title={dict.app.pages.salesSalesOrders.confirm}
                              aria-label={dict.app.pages.salesSalesOrders.confirm}
                              className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                            >
                              {dict.app.pages.salesSalesOrders.confirm}
                            </button>
                          ) : null}

                          {isSalesOrderActionable(order) && canCancelSalesOrders ? (
                            <button
                              type="button"
                              onClick={() => handleAction(order.id, 'cancel')}
                              title={dict.app.pages.salesSalesOrders.cancel}
                              aria-label={dict.app.pages.salesSalesOrders.cancel}
                              className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                            >
                              {dict.app.pages.salesSalesOrders.cancel}
                            </button>
                          ) : null}

                          {actionState ? (
                            <StatusBadge tone="muted">{actionState}</StatusBadge>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                })}
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
                ? dict.app.pages.salesSalesOrders.editSalesOrder
                : dict.app.pages.salesSalesOrders.createSalesOrder_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <SearchableSelect
                  label={dict.app.pages.salesSalesOrders.customer_2}
                  value={data.customer_id || null}
                  onChange={(value) => setData('customer_id', value || '')}
                  options={customerOptions}
                  placeholder={dict.app.pages.salesSalesOrders.selectCustomer}
                  isClearable={false}
                  required
                  error={errors.customer_id}
                />

                <DatePicker
                  label={dict.app.pages.salesSalesOrders.orderDate}
                  value={data.order_date}
                  onChange={(value) => setData('order_date', value || '')}
                  required
                  error={errors.order_date}
                />

                <SearchableSelect
                  label={dict.app.pages.salesSalesOrders.currency}
                  value={data.currency || null}
                  onChange={(value) => setData('currency', value || '')}
                  options={currencyOptions}
                  isClearable={false}
                  required
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <DatePicker
                  label={dict.app.pages.salesSalesOrders.expectedDeliveryDate}
                  value={data.expected_delivery_date}
                  onChange={(value) => setData('expected_delivery_date', value || '')}
                />

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.salesSalesOrders.reference}
                  </label>
                  <input
                    type="text"
                    value={data.reference}
                    onChange={(e) => setData('reference', e.target.value)}
                    placeholder={dict.app.pages.salesSalesOrders.referencePlaceholder}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                </div>
              </div>

              {/* Order Lines Section */}
              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">
                    {dict.app.pages.salesSalesOrders.orderLines}
                  </h4>
                  <button
                    type="button"
                    onClick={addLineItem}
                    title={pageDict.addLine}
                    aria-label={pageDict.addLine}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800"
                  >
                    <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{dict.app.pages.salesSalesOrders.addLine}</span>
                  </button>
                </div>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => {
                    const lineProd = products.find((p) => p.id === item.product_id);
                    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);

                    return (
                      <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                        <div className="flex-1 w-full sm:w-auto">
                          <SearchableSelect
                            label={dict.app.pages.salesSalesOrders.productService}
                            value={item.product_id || null}
                            onChange={(value) => handleProductChange(idx, value || '')}
                            options={productOptions}
                            isClearable={false}
                            required
                          />
                        </div>

                        <div className="w-full sm:w-24">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.salesSalesOrders.uom}
                          </label>
                          <input
                            type="text"
                            disabled
                            value={lineProd?.unit_of_measure?.name || dict.app.pages.salesSalesOrders.noUom}
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-muted)] font-medium"
                          />
                        </div>

                        <div className="w-full sm:w-28">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                            {dict.app.pages.salesSalesOrders.quantity}
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
                            {dict.app.pages.salesSalesOrders.unitPrice}
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
                            {dict.app.pages.salesSalesOrders.lineTotal}
                          </label>
                          <div className="py-1.5 px-2 font-mono text-xs font-bold text-[var(--text-primary)]">
                            {lineTotal.toFixed(2)} {data.currency}
                          </div>
                        </div>

                        {lineItems.length > 1 ? (
                          <button
                            type="button"
                            onClick={() => removeLineItem(idx)}
                            title={pageDict.removeLine}
                            aria-label={pageDict.removeLine}
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
                      {dict.app.pages.salesSalesOrders.orderTotal}
                    </span>
                    <span className="text-base font-extrabold text-blue-600 font-mono">
                      {calculatePreviewSubtotal().toFixed(2)} {data.currency}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.salesSalesOrders.notes}
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
                  {dict.app.pages.salesSalesOrders.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={salesOrderSubmitLabel}
                  aria-label={salesOrderSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {salesOrderSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
