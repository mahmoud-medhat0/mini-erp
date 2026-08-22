import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type CustomerOption = {
  id: string;
  code: string;
  name: string;
};

type ProductOption = {
  id: string;
  code: string;
  name: { en?: string; ar?: string } | string;
  type: 'service' | 'non_stock' | 'stock';
  unit_of_measure_id: string;
  unitOfMeasure?: {
    id: string;
    code: string;
    name: string;
  } | null;
};

type InvoiceLineForm = {
  product_id: string;
  unit_of_measure_id: string;
  sales_order_line_id?: string | null;
  delivery_note_line_id?: string | null;
  description: string;
  quantity: number; // Decimal UI input
  unit_price: number; // Decimal UI input
};

type CustomerInvoiceRow = {
  id: string;
  number?: string | null;
  customer_id: string;
  sales_order_id?: string | null;
  delivery_note_id?: string | null;
  customer?: { id: string; name: string } | null;
  invoice_date: string;
  due_date?: string | null;
  currency: string;
  subtotal_minor: number;
  total_minor: number;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  reference?: string | null;
  description?: string | null;
  lock_version: number;
  lines?: Array<{
    id: string;
    product_id: string;
    unit_of_measure_id: string;
    sales_order_line_id?: string | null;
    delivery_note_line_id?: string | null;
    description: string;
    quantity_e6: number;
    unit_price_minor: number;
    line_total_minor: number;
    product?: ProductOption | null;
    unitOfMeasure?: { id: string; code: string; name: string } | null;
  }>;
};

type CustomerInvoicesProps = SharedPageProps & {
  customerInvoices: {
    data: CustomerInvoiceRow[];
    links: any[];
  };
  activeCustomers: CustomerOption[];
  eligibleProducts: ProductOption[];
  confirmedSalesOrders: any[];
  confirmedDeliveryNotes: any[];
  filters: {
    search?: string;
    status?: string;
  };
};

export default function CustomerInvoicesIndex({
  locale,
  customerInvoices,
  activeCustomers,
  eligibleProducts,
  confirmedSalesOrders,
  confirmedDeliveryNotes,
  filters,
}: CustomerInvoicesProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingInvoice, setEditingInvoice] = useState<CustomerInvoiceRow | null>(null);
  const [sourceMode, setSourceMode] = useState<'manual' | 'sales_order' | 'delivery_note'>('manual');

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<InvoiceLineForm[]>([]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    customer_id: activeCustomers[0]?.id || '',
    sales_order_id: '',
    delivery_note_id: '',
    invoice_date: todayStr,
    due_date: todayStr,
    currency: 'USD',
    fx_rate_e6: 1000000,
    reference: '',
    description: '',
    lock_version: 1,
  });

  const getProductName = (prod: any) => {
    if (!prod) return '';
    if (typeof prod.name === 'string') return prod.name;
    return isAr ? prod.name?.ar || prod.name?.en : prod.name?.en || prod.name?.ar;
  };

  const handleSourceModeChange = (mode: 'manual' | 'sales_order' | 'delivery_note') => {
    setSourceMode(mode);
    setLineItems([]);
    setData('sales_order_id', '');
    setData('delivery_note_id', '');
  };

  const handleSalesOrderSelect = (soId: string) => {
    setData('sales_order_id', soId);
    const selectedSo = confirmedSalesOrders.find((so) => so.id === soId);
    if (selectedSo) {
      if (selectedSo.customer_id) setData('customer_id', selectedSo.customer_id);
      if (selectedSo.currency) setData('currency', selectedSo.currency);

      const nonStockLines = (selectedSo.lines || []).filter((l: any) => l.product?.type !== 'stock');
      setLineItems(
        nonStockLines.map((l: any) => ({
          sales_order_line_id: l.id,
          product_id: l.product_id,
          unit_of_measure_id: l.unit_of_measure_id,
          description: l.description || getProductName(l.product),
          quantity: l.quantity_e6 / 1000000,
          unit_price: l.unit_price_minor / 100,
        }))
      );
    }
  };

  const handleDeliveryNoteSelect = (dnId: string) => {
    setData('delivery_note_id', dnId);
    const selectedDn = confirmedDeliveryNotes.find((dn) => dn.id === dnId);
    if (selectedDn && selectedDn.salesOrder) {
      if (selectedDn.salesOrder.customer_id) setData('customer_id', selectedDn.salesOrder.customer_id);
      if (selectedDn.salesOrder.currency) setData('currency', selectedDn.salesOrder.currency);

      const nonStockLines = (selectedDn.lines || []).filter((l: any) => l.product?.type !== 'stock');
      setLineItems(
        nonStockLines.map((l: any) => ({
          delivery_note_line_id: l.id,
          product_id: l.product_id,
          unit_of_measure_id: l.unit_of_measure_id,
          description: l.description || getProductName(l.product),
          quantity: l.quantity_e6 / 1000000,
          unit_price: 0,
        }))
      );
    }
  };

  const addManualLine = () => {
    const defaultProduct = eligibleProducts[0];
    if (!defaultProduct) return;
    setLineItems((prev) => [
      ...prev,
      {
        product_id: defaultProduct.id,
        unit_of_measure_id: defaultProduct.unit_of_measure_id,
        description: getProductName(defaultProduct),
        quantity: 1,
        unit_price: 10,
      },
    ]);
  };

  const removeLine = (index: number) => {
    setLineItems((prev) => prev.filter((_, i) => i !== index));
  };

  const openCreateModal = () => {
    reset();
    setEditingInvoice(null);
    setSourceMode('manual');
    const defaultProduct = eligibleProducts[0];
    if (defaultProduct) {
      setLineItems([
        {
          product_id: defaultProduct.id,
          unit_of_measure_id: defaultProduct.unit_of_measure_id,
          description: getProductName(defaultProduct),
          quantity: 1,
          unit_price: 10,
        },
      ]);
    } else {
      setLineItems([]);
    }
    setShowModal(true);
  };

  const openEditModal = (inv: CustomerInvoiceRow) => {
    setEditingInvoice(inv);
    setData({
      customer_id: inv.customer_id,
      sales_order_id: inv.sales_order_id || '',
      delivery_note_id: inv.delivery_note_id || '',
      invoice_date: inv.invoice_date,
      due_date: inv.due_date || inv.invoice_date,
      currency: inv.currency,
      fx_rate_e6: 1000000,
      reference: inv.reference || '',
      description: inv.description || '',
      lock_version: inv.lock_version,
    });

    if (inv.lines) {
      setLineItems(
        inv.lines.map((l) => ({
          product_id: l.product_id,
          unit_of_measure_id: l.unitOfMeasure?.id || '',
          sales_order_line_id: (l as any).sales_order_line_id,
          delivery_note_line_id: (l as any).delivery_note_line_id,
          description: l.description || getProductName(l.product),
          quantity: l.quantity_e6 / 1000000,
          unit_price: l.unit_price_minor / 100,
        }))
      );
    }
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingInvoice(null);
    reset();
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      product_id: item.product_id,
      unit_of_measure_id: item.unit_of_measure_id,
      sales_order_line_id: item.sales_order_line_id || null,
      delivery_note_line_id: item.delivery_note_line_id || null,
      description: item.description,
      quantity_e6: Math.round(Number(item.quantity) * 1000000),
      unit_price_minor: Math.round(Number(item.unit_price) * 100),
    }));

    const payload = {
      ...data,
      lines: formattedLines,
    };

    if (editingInvoice) {
      router.put(`/sales/invoices/${editingInvoice.id}`, payload, {
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/invoices', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (invId: string, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.salesCustomerInvoices.submitThisInvoice;
    if (action === 'approve') confirmMsg = dict.app.pages.salesCustomerInvoices.approveThisInvoice;
    if (action === 'post') confirmMsg = dict.app.pages.salesCustomerInvoices.postThisInvoiceToArGl;
    if (action === 'cancel') confirmMsg = dict.app.pages.salesCustomerInvoices.cancelThisInvoice;

    if (confirm(confirmMsg)) {
      router.post(`/sales/invoices/${invId}/${action}`);
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
        return dict.app.pages.salesCustomerInvoices.draft;
      case 'submitted':
        return dict.app.pages.salesCustomerInvoices.submitted;
      case 'approved':
        return dict.app.pages.salesCustomerInvoices.approved;
      case 'posted':
        return dict.app.pages.salesCustomerInvoices.posted;
      case 'cancelled':
        return dict.app.pages.salesCustomerInvoices.cancelled;
      default:
        return status;
    }
  };

  const previewTotalMinor = lineItems.reduce((acc, item) => {
    const qtyE6 = Math.round(Number(item.quantity || 0) * 1000000);
    const priceMinor = Math.round(Number(item.unit_price || 0) * 100);
    return acc + Math.floor((qtyE6 * priceMinor) / 1000000);
  }, 0);

  return (
    <AppLayout active="customer-invoices.index">
      <Head title={dict.app.pages.salesCustomerInvoices.customerInvoices} />

      <PageHeader
        title={dict.app.pages.salesCustomerInvoices.customerInvoices_2}
        description={dict.app.pages.salesCustomerInvoices.manageCustomerSalesInvoicesAndPost}
        actions={
          can('sales.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.salesCustomerInvoices.createCustomerInvoice}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.salesCustomerInvoices.searchNumberReferenceOrCustomer}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/invoices', { ...filters, search: val }, { preserveState: true });
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
              onChange={(e) => router.get('/sales/invoices', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.salesCustomerInvoices.allStatuses}</option>
              <option value="draft">{dict.app.pages.salesCustomerInvoices.draft}</option>
              <option value="submitted">{dict.app.pages.salesCustomerInvoices.submitted}</option>
              <option value="approved">{dict.app.pages.salesCustomerInvoices.approved}</option>
              <option value="posted">{dict.app.pages.salesCustomerInvoices.posted}</option>
              <option value="cancelled">{dict.app.pages.salesCustomerInvoices.cancelled}</option>
            </select>
          </div>
        </div>

        {customerInvoices.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.salesCustomerInvoices.noCustomerInvoicesFound}
            description={dict.app.pages.salesCustomerInvoices.createAManualServiceInvoiceOr}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerInvoices.invoice}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerInvoices.customer}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerInvoices.invoiceDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerInvoices.totalAmount}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerInvoices.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesCustomerInvoices.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {customerInvoices.data.map((inv) => (
                  <tr key={inv.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {inv.number || dict.app.pages.salesCustomerInvoices.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-medium`}>{inv.customer?.name || '-'}</td>
                    <td className={tableClasses.td}>{inv.invoice_date}</td>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      {formatMoney(inv.total_minor, inv.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(inv.status)}>
                        {getStatusLabel(inv.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {inv.status === 'draft' ? (
                        <>
                          {can('sales.edit') ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(inv)}
                              className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                            >
                              {dict.app.pages.salesCustomerInvoices.edit}
                            </button>
                          ) : null}
                          {can('sales.submit') ? (
                            <button
                              type="button"
                              onClick={() => handleAction(inv.id, 'submit')}
                              className="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                            >
                              {dict.app.pages.salesCustomerInvoices.submit}
                            </button>
                          ) : null}
                        </>
                      ) : null}

                      {['draft', 'submitted'].includes(inv.status) && can('sales.approve') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(inv.id, 'approve')}
                          className="text-xs font-semibold text-amber-600 hover:text-amber-800"
                        >
                          {dict.app.pages.salesCustomerInvoices.approve}
                        </button>
                      ) : null}

                      {inv.status === 'approved' && can('sales.post') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(inv.id, 'post')}
                          className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                        >
                          {dict.app.pages.salesCustomerInvoices.postToArGl}
                        </button>
                      ) : null}

                      {inv.status !== 'posted' && inv.status !== 'cancelled' && can('sales.cancel') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(inv.id, 'cancel')}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {dict.app.pages.salesCustomerInvoices.cancel}
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

      {/* Create / Edit Modal */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-4xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingInvoice
                ? dict.app.pages.salesCustomerInvoices.editCustomerInvoice
                : dict.app.pages.salesCustomerInvoices.createCustomerInvoice_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Source Mode Toggle */}
              {!editingInvoice ? (
                <div className="flex items-center gap-2 p-1 rounded-xl bg-[var(--background)] border border-[var(--border)] max-w-md mb-4">
                  <button
                    type="button"
                    onClick={() => handleSourceModeChange('manual')}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'manual' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesCustomerInvoices.manualService}
                  </button>
                  <button
                    type="button"
                    onClick={() => handleSourceModeChange('sales_order')}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'sales_order' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesCustomerInvoices.fromSalesOrder}
                  </button>
                  <button
                    type="button"
                    onClick={() => handleSourceModeChange('delivery_note')}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'delivery_note' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesCustomerInvoices.fromDeliveryNote}
                  </button>
                </div>
              ) : null}

              {/* Source selection dropdowns */}
              {sourceMode === 'sales_order' && !editingInvoice ? (
                <div className="mb-4">
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.salesCustomerInvoices.selectConfirmedSalesOrder}
                  </label>
                  <select
                    value={data.sales_order_id}
                    onChange={(e) => handleSalesOrderSelect(e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">{dict.app.pages.salesCustomerInvoices.selectSalesOrder}</option>
                    {confirmedSalesOrders.map((so) => (
                      <option key={so.id} value={so.id}>
                        {so.number} - {so.customer?.name} ({so.currency})
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}

              {sourceMode === 'delivery_note' && !editingInvoice ? (
                <div className="mb-4">
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.salesCustomerInvoices.selectConfirmedDeliveryNote}
                  </label>
                  <select
                    value={data.delivery_note_id}
                    onChange={(e) => handleDeliveryNoteSelect(e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">{dict.app.pages.salesCustomerInvoices.selectDeliveryNote}</option>
                    {confirmedDeliveryNotes.map((dn) => (
                      <option key={dn.id} value={dn.id}>
                        {dn.number} - {dn.salesOrder?.customer?.name}
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.salesCustomerInvoices.customer_2} *
                  </label>
                  <select
                    disabled={Boolean(editingInvoice) || sourceMode !== 'manual'}
                    value={data.customer_id}
                    onChange={(e) => setData('customer_id', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.salesCustomerInvoices.selectCustomer}</option>
                    {activeCustomers.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.code})
                      </option>
                    ))}
                  </select>
                  {errors.customer_id ? <p className="mt-1 text-[10px] text-red-500">{errors.customer_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.salesCustomerInvoices.invoiceDate_2} *
                  </label>
                  <input
                    type="date"
                    value={data.invoice_date}
                    onChange={(e) => setData('invoice_date', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                  {errors.invoice_date ? <p className="mt-1 text-[10px] text-red-500">{errors.invoice_date}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {dict.app.pages.salesCustomerInvoices.currency} *
                  </label>
                  <input
                    type="text"
                    disabled={Boolean(editingInvoice) || sourceMode !== 'manual'}
                    value={data.currency}
                    onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono uppercase focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  />
                </div>
              </div>

              {/* Invoice Lines */}
              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">
                    {dict.app.pages.salesCustomerInvoices.invoiceLinesServiceNonStockOnly}
                  </h4>
                  {sourceMode === 'manual' ? (
                    <button
                      type="button"
                      onClick={addManualLine}
                      className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                    >
                      + {dict.app.pages.salesCustomerInvoices.addLine}
                    </button>
                  ) : null}
                </div>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => (
                    <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                      <div className="flex-1 w-full sm:w-auto">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.salesCustomerInvoices.productService}
                        </label>
                        <select
                          disabled={sourceMode !== 'manual'}
                          value={item.product_id}
                          onChange={(e) => {
                            const pId = e.target.value;
                            const prod = eligibleProducts.find((p) => p.id === pId);
                            setLineItems((prev) => {
                              const next = [...prev];
                              next[idx] = {
                                ...next[idx],
                                product_id: pId,
                                unit_of_measure_id: prod?.unit_of_measure_id || next[idx].unit_of_measure_id,
                                description: prod ? getProductName(prod) : next[idx].description,
                              };
                              return next;
                            });
                          }}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                        >
                          {eligibleProducts.map((p) => (
                            <option key={p.id} value={p.id}>
                              {p.code} - {getProductName(p)} ({p.type})
                            </option>
                          ))}
                        </select>
                      </div>

                      <div className="w-full sm:w-28">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.salesCustomerInvoices.qty}
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

                      <div className="w-full sm:w-32">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.salesCustomerInvoices.unitPrice} ({data.currency})
                        </label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
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

                      {sourceMode === 'manual' && lineItems.length > 1 ? (
                        <button
                          type="button"
                          onClick={() => removeLine(idx)}
                          className="mt-4 sm:mt-0 text-red-500 hover:text-red-700 text-xs font-bold"
                        >
                          ✕
                        </button>
                      ) : null}
                    </div>
                  ))}
                </div>

                <div className="mt-4 flex justify-end">
                  <div className="text-end">
                    <span className="text-xs font-semibold text-[var(--text-secondary)] me-2">
                      {dict.app.pages.salesCustomerInvoices.estimatedInvoiceTotal}
                    </span>
                    <span className="text-sm font-bold font-mono text-blue-600">
                      {formatMoney(previewTotalMinor, data.currency)}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.salesCustomerInvoices.descriptionNotes}
                </label>
                <textarea
                  rows={2}
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.salesCustomerInvoices.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing
                    ? dict.app.pages.salesCustomerInvoices.saving
                    : dict.app.pages.salesCustomerInvoices.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
