import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type CustomerOption = {
  id: string;
  code: string;
  name: string;
};

type ProductName = { en?: string; ar?: string } | string;

type DeliveryNoteLineOption = {
  id: string;
  product_id: string;
  quantity_e6: number;
  description?: string | null;
  product?: { code: string; name: ProductName } | null;
  unitOfMeasure?: { id: string; code: string; name: string } | null;
};

type DeliveryNoteOption = {
  id: string;
  number?: string | null;
  salesOrder?: {
    customer_id: string;
    customer?: { id: string; name: string } | null;
  } | null;
  lines: DeliveryNoteLineOption[];
};

type PostedInvoiceOption = {
  id: string;
  number?: string | null;
  customer_id: string;
  currency: string;
  lines: Array<{
    id: string;
    product_id: string;
    description?: string | null;
    quantity_e6: number;
    unit_price_minor: number;
  }>;
};

type ReturnableInvoiceLine = {
  id: string;
  description?: string | null;
  original_quantity_e6: number;
  returned_quantity_e6: number;
  credited_quantity_e6: number;
  max_returnable_quantity_e6: number;
  unit_price_minor: number;
};

type Disposition = 'restock_original_cost' | 'restock_manual_value' | 'scrap_no_restock';

type ReturnLineForm = {
  delivery_note_line_id: string;
  customer_invoice_line_id?: string | null;
  product_id: string;
  description: string;
  uom_name: string;
  max_quantity: number;
  quantity: number;
  disposition: Disposition;
  manual_value: number;
};

type SalesReturnRow = {
  id: string;
  number?: string | null;
  customer_id: string;
  delivery_note_id?: string | null;
  customer_invoice_id?: string | null;
  customer?: { id: string; name: string } | null;
  deliveryNote?: { id: string; number?: string | null } | null;
  customerInvoice?: { id: string; number?: string | null } | null;
  return_date: string;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  currency: string;
  reason?: string | null;
  notes?: string | null;
  lock_version: number;
  lines?: Array<{
    id: string;
    delivery_note_line_id: string;
    customer_invoice_line_id?: string | null;
    product_id: string;
    description?: string | null;
    quantity_e6: number;
    disposition: Disposition;
    manual_restock_value_minor?: number | null;
    product?: { code: string; name: ProductName } | null;
    unitOfMeasure?: { id: string; code: string; name: string } | null;
  }>;
};

type SalesReturnsProps = SharedPageProps & {
  salesReturns: {
    data: SalesReturnRow[];
    links: any[];
  };
  activeCustomers: CustomerOption[];
  confirmedDeliveryNotes: DeliveryNoteOption[];
  postedCustomerInvoices: PostedInvoiceOption[];
  filters: {
    search?: string;
    status?: string;
    customer_id?: string;
  };
};

const formatQuantity = (qtyE6: number) => String(parseFloat(((qtyE6 || 0) / 1000000).toFixed(6)));

export default function SalesReturnsIndex({
  locale,
  salesReturns,
  activeCustomers,
  confirmedDeliveryNotes,
  postedCustomerInvoices,
  filters,
}: SalesReturnsProps) {
  const dict = getDictionary(locale);
  const can = useCan();
  
  const [showModal, setShowModal] = useState(false);
  const [editingReturn, setEditingReturn] = useState<SalesReturnRow | null>(null);
  const [sourceMode, setSourceMode] = useState<'delivery_note' | 'invoice'>('delivery_note');
  const [lineItems, setLineItems] = useState<ReturnLineForm[]>([]);
  const [fetchingLines, setFetchingLines] = useState(false);

  const todayStr = new Date().toISOString().split('T')[0];

  const { data, setData, post, put, processing, errors, reset } = useForm({
    customer_id: '',
    delivery_note_id: '',
    customer_invoice_id: '',
    return_date: todayStr,
    reason: '',
    notes: '',
    lock_version: 1,
  });

  const getProductName = (prod?: { code: string; name: ProductName } | null): string => {
    if (!prod) return '';
    if (typeof prod.name === 'string') return prod.name;
    return locale === 'ar' ? prod.name?.ar || prod.name?.en || '' : prod.name?.en || prod.name?.ar || '';
  };

  const customerDeliveryNotes = confirmedDeliveryNotes.filter(
    (dn) => !data.customer_id || dn.salesOrder?.customer_id === data.customer_id
  );

  const selectedDn = customerDeliveryNotes.find((dn) => dn.id === data.delivery_note_id);

  const customerInvoices = postedCustomerInvoices.filter((inv) => inv.customer_id === data.customer_id);

  const resetLines = () => setLineItems([]);

  const handleSourceModeChange = (mode: 'delivery_note' | 'invoice') => {
    setSourceMode(mode);
    setData('customer_invoice_id', '');
    setLineItems([]);
  };

  const handleCustomerSelect = (customerId: string) => {
    setData('customer_id', customerId);
    setData('delivery_note_id', '');
    setData('customer_invoice_id', '');
    setLineItems([]);
  };

  const handleDeliveryNoteSelect = (dnId: string) => {
    setData('delivery_note_id', dnId);
    if (!dnId) {
      setLineItems([]);
      return;
    }
    const dn = confirmedDeliveryNotes.find((n) => n.id === dnId);
    if (dn && sourceMode === 'delivery_note') {
      setLineItems(
        dn.lines.map((l) => ({
          delivery_note_line_id: l.id,
          product_id: l.product_id,
          description: l.description || getProductName(l.product),
          uom_name: l.unitOfMeasure?.name || '-',
          max_quantity: l.quantity_e6 / 1000000,
          quantity: l.quantity_e6 / 1000000,
          disposition: 'restock_original_cost' as Disposition,
          manual_value: 0,
        }))
      );
    }
  };

  const fetchReturnableLines = async () => {
    if (!data.customer_invoice_id || !selectedDn) return;
    setFetchingLines(true);
    try {
      const response = await fetch(`/sales/returns/returnable-lines/${data.customer_invoice_id}`, {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error('failed');
      const payload: { lines: ReturnableInvoiceLine[] } = await response.json();
      setLineItems(
        payload.lines.map((l) => ({
          delivery_note_line_id: '',
          customer_invoice_line_id: l.id,
          product_id: '',
          description: l.description || '',
          uom_name: '-',
          max_quantity: l.max_returnable_quantity_e6 / 1000000,
          quantity: 0,
          disposition: 'restock_original_cost' as Disposition,
          manual_value: 0,
        }))
      );
    } catch {
      setLineItems([]);
    } finally {
      setFetchingLines(false);
    }
  };

  const updateLineItem = (index: number, field: keyof ReturnLineForm, value: any) => {
    setLineItems((prev) => {
      const next = [...prev];
      const item = { ...next[index], [field]: value };
      if (field === 'delivery_note_line_id' && selectedDn) {
        const dnLine = selectedDn.lines.find((l) => l.id === value);
        if (dnLine) item.product_id = dnLine.product_id;
      }
      next[index] = item;
      return next;
    });
  };

  const openCreateModal = () => {
    reset();
    setEditingReturn(null);
    setSourceMode('delivery_note');
    setLineItems([]);
    setShowModal(true);
  };

  const openEditModal = (ret: SalesReturnRow) => {
    setEditingReturn(ret);
    setSourceMode('delivery_note');
    setData({
      customer_id: ret.customer_id,
      delivery_note_id: ret.delivery_note_id || '',
      customer_invoice_id: ret.customer_invoice_id || '',
      return_date: ret.return_date,
      reason: ret.reason || '',
      notes: ret.notes || '',
      lock_version: ret.lock_version,
    });
    setLineItems(
      (ret.lines || []).map((l) => ({
        delivery_note_line_id: l.delivery_note_line_id,
        customer_invoice_line_id: l.customer_invoice_line_id || null,
        product_id: l.product_id,
        description: l.description || getProductName(l.product),
        uom_name: l.unitOfMeasure?.name || '-',
        max_quantity: l.quantity_e6 / 1000000,
        quantity: l.quantity_e6 / 1000000,
        disposition: l.disposition,
        manual_value: (l.manual_restock_value_minor || 0) / 100,
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
      .filter((item) => item.delivery_note_line_id && Number(item.quantity) > 0)
      .map((item) => ({
        delivery_note_line_id: item.delivery_note_line_id,
        customer_invoice_line_id: item.customer_invoice_line_id || null,
        product_id: item.product_id,
        quantity_e6: Math.round(Number(item.quantity) * 1000000),
        disposition: item.disposition,
        manual_restock_value_minor:
          item.disposition === 'restock_manual_value' ? Math.round(Number(item.manual_value) * 100) : null,
      }));

    if (formattedLines.length === 0) return;

    const payload = {
      ...data,
      customer_invoice_id: data.customer_invoice_id || null,
      lines: formattedLines,
    };

    if (editingReturn) {
      router.put(`/sales/returns/${editingReturn.id}`, payload, {
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/returns', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (retId: string, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.salesSalesReturns.submitThisSalesReturn;
    if (action === 'approve') confirmMsg = dict.app.pages.salesSalesReturns.approveThisSalesReturn;
    if (action === 'post') confirmMsg = dict.app.pages.salesSalesReturns.postThisSalesReturnToInventoryGl;
    if (action === 'cancel') confirmMsg = dict.app.pages.salesSalesReturns.cancelThisSalesReturn;

    if (confirm(confirmMsg)) {
      router.post(`/sales/returns/${retId}/${action}`);
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
        return dict.app.pages.salesSalesReturns.draft;
      case 'submitted':
        return dict.app.pages.salesSalesReturns.submitted;
      case 'approved':
        return dict.app.pages.salesSalesReturns.approved;
      case 'posted':
        return dict.app.pages.salesSalesReturns.posted;
      case 'cancelled':
        return dict.app.pages.salesSalesReturns.cancelled;
      default:
        return status;
    }
  };

  const getDispositionLabel = (disposition: string) => {
    switch (disposition) {
      case 'restock_original_cost':
        return dict.app.pages.salesSalesReturns.restockOriginalCost;
      case 'restock_manual_value':
        return dict.app.pages.salesSalesReturns.restockManualValue;
      case 'scrap_no_restock':
        return dict.app.pages.salesSalesReturns.scrapNoRestock;
      default:
        return disposition;
    }
  };

  return (
    <AppLayout active="sales-returns.index">
      <Head title={dict.app.pages.salesSalesReturns.salesReturns} />

      <PageHeader
        title={dict.app.pages.salesSalesReturns.salesReturns_2}
        description={dict.app.pages.salesSalesReturns.manageCustomerSalesReturnsAndRestock}
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
              <span>{dict.app.pages.salesSalesReturns.createSalesReturn}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.salesSalesReturns.searchNumberReasonOrCustomer}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/returns', { ...filters, search: val }, { preserveState: true });
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
              onChange={(e) => router.get('/sales/returns', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.salesSalesReturns.allStatuses}</option>
              <option value="draft">{dict.app.pages.salesSalesReturns.draft}</option>
              <option value="submitted">{dict.app.pages.salesSalesReturns.submitted}</option>
              <option value="approved">{dict.app.pages.salesSalesReturns.approved}</option>
              <option value="posted">{dict.app.pages.salesSalesReturns.posted}</option>
              <option value="cancelled">{dict.app.pages.salesSalesReturns.cancelled}</option>
            </select>
          </div>
        </div>

        {salesReturns.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.salesSalesReturns.noSalesReturnsFound}
            description={dict.app.pages.salesSalesReturns.createAReturnFromADeliveryNote}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.returnNumber}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.customer}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.deliveryNote}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.invoice}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.returnDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesSalesReturns.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {salesReturns.data.map((ret) => (
                  <tr key={ret.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {ret.number || dict.app.pages.salesSalesReturns.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-medium`}>{ret.customer?.name || '-'}</td>
                    <td className={`${tableClasses.td} font-mono`}>{ret.deliveryNote?.number || '-'}</td>
                    <td className={`${tableClasses.td} font-mono`}>{ret.customerInvoice?.number || '-'}</td>
                    <td className={tableClasses.td}>{ret.return_date}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(ret.status)}>
                        {getStatusLabel(ret.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {ret.status === 'draft' && can('sales.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(ret)}
                          className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                          {dict.app.pages.salesSalesReturns.edit}
                        </button>
                      ) : null}

                      {ret.status === 'draft' && can('sales.submit') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'submit')}
                          className="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                        >
                          {dict.app.pages.salesSalesReturns.submit}
                        </button>
                      ) : null}

                      {['draft', 'submitted'].includes(ret.status) && can('sales.approve') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'approve')}
                          className="text-xs font-semibold text-amber-600 hover:text-amber-800"
                        >
                          {dict.app.pages.salesSalesReturns.approve}
                        </button>
                      ) : null}

                      {ret.status === 'approved' && can('sales.post') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'post')}
                          className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                        >
                          {dict.app.pages.salesSalesReturns.post}
                        </button>
                      ) : null}

                      {['draft', 'submitted', 'approved'].includes(ret.status) && can('sales.cancel') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(ret.id, 'cancel')}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {dict.app.pages.salesSalesReturns.cancel}
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
          <div className="w-full max-w-4xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingReturn ? dict.app.pages.salesSalesReturns.editSalesReturn : dict.app.pages.salesSalesReturns.createSalesReturn_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              {!editingReturn ? (
                <div className="flex items-center gap-2 p-1 rounded-xl bg-[var(--background)] border border-[var(--border)] max-w-md mb-4">
                  <button
                    type="button"
                    onClick={() => handleSourceModeChange('delivery_note')}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'delivery_note' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesSalesReturns.fromDeliveryNote}
                  </button>
                  <button
                    type="button"
                    onClick={() => handleSourceModeChange('invoice')}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'invoice' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesSalesReturns.createFromInvoice}
                  </button>
                </div>
              ) : null}

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.customer_2} *</label>
                  <select
                    disabled={Boolean(editingReturn)}
                    value={data.customer_id}
                    onChange={(e) => handleCustomerSelect(e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.salesSalesReturns.selectCustomer}</option>
                    {activeCustomers.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.code})
                      </option>
                    ))}
                  </select>
                  {errors.customer_id ? <p className="mt-1 text-[10px] text-red-500">{errors.customer_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.confirmedDeliveryNote} *</label>
                  <select
                    disabled={Boolean(editingReturn)}
                    value={data.delivery_note_id}
                    onChange={(e) => {
                      handleDeliveryNoteSelect(e.target.value);
                      if (sourceMode === 'invoice') setLineItems([]);
                    }}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.salesSalesReturns.selectDeliveryNote}</option>
                    {customerDeliveryNotes.map((dn) => (
                      <option key={dn.id} value={dn.id}>
                        {dn.number || dict.app.pages.salesSalesReturns.draft_2} - {dn.salesOrder?.customer?.name}
                      </option>
                    ))}
                  </select>
                  {errors.delivery_note_id ? <p className="mt-1 text-[10px] text-red-500">{errors.delivery_note_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.returnDate_2} *</label>
                  <input
                    type="date"
                    value={data.return_date}
                    onChange={(e) => setData('return_date', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                  {errors.return_date ? <p className="mt-1 text-[10px] text-red-500">{errors.return_date}</p> : null}
                </div>
              </div>

              {sourceMode === 'invoice' && !editingReturn ? (
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.postedInvoice}</label>
                  <div className="flex items-center gap-2">
                    <select
                      value={data.customer_invoice_id}
                      onChange={(e) => setData('customer_invoice_id', e.target.value)}
                      disabled={!data.customer_id}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                    >
                      <option value="">{dict.app.pages.salesSalesReturns.selectPostedInvoice}</option>
                      {customerInvoices.map((inv) => (
                        <option key={inv.id} value={inv.id}>
                          {inv.number || dict.app.pages.salesSalesReturns.draft_2} - {inv.currency}
                        </option>
                      ))}
                    </select>
                    <button
                      type="button"
                      onClick={fetchReturnableLines}
                      disabled={!data.customer_invoice_id || !data.delivery_note_id || fetchingLines}
                      className="shrink-0 rounded-xl border border-[var(--border)] px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-[var(--background)] disabled:opacity-50"
                    >
                      {fetchingLines ? dict.app.pages.salesSalesReturns.loading : dict.app.pages.salesSalesReturns.loadLines}
                    </button>
                  </div>
                </div>
              ) : null}

              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{dict.app.pages.salesSalesReturns.returnLines}</h4>
                </div>

                {lineItems.length === 0 ? (
                  <p className="text-xs text-[var(--text-muted)]">
                    {sourceMode === 'invoice'
                      ? editingReturn
                        ? dict.app.pages.salesSalesReturns.noLinesLoadedHint
                        : dict.app.pages.salesSalesReturns.selectAnInvoiceThenLoadIts
                        : dict.app.pages.salesSalesReturns.selectADeliveryNoteToLoad}
                  </p>
                ) : (
                  <div className="space-y-3">
                    {lineItems.map((item, idx) => (
                      <div key={idx} className="flex flex-col gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                        <div className="flex flex-col sm:flex-row items-start sm:items-end gap-2 w-full">
                          <div className="flex-1 w-full min-w-0">
                            <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesSalesReturns.description}</label>
                            <input
                              type="text"
                              disabled
                              value={item.description || '-'}
                              className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-primary)] font-medium"
                            />
                          </div>

                          {sourceMode === 'invoice' ? (
                            <div className="w-full sm:w-56">
                              <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesSalesReturns.dnLine} *</label>
                              <select
                                value={item.delivery_note_line_id}
                                onChange={(e) => updateLineItem(idx, 'delivery_note_line_id', e.target.value)}
                                required={Number(item.quantity) > 0}
                                className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                              >
                                <option value="">{dict.app.pages.salesSalesReturns.selectDnLine}</option>
                                {(selectedDn?.lines || []).map((l) => (
                                  <option key={l.id} value={l.id}>
                                    {getProductName(l.product)} ({formatQuantity(l.quantity_e6)})
                                  </option>
                                ))}
                              </select>
                            </div>
                          ) : null}

                          <div className="w-full sm:w-28">
                            <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                              {dict.app.pages.salesSalesReturns.returnQty} {item.max_quantity > 0 ? `(${dict.app.pages.salesSalesReturns.max} ${formatQuantity(Math.round(item.max_quantity * 1000000))})` : ''}
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

                          <div className="w-full sm:w-44">
                            <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesSalesReturns.disposition}</label>
                            <select
                              value={item.disposition}
                              onChange={(e) => updateLineItem(idx, 'disposition', e.target.value)}
                              className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                            >
                              <option value="restock_original_cost">{getDispositionLabel('restock_original_cost')}</option>
                              <option value="restock_manual_value">{getDispositionLabel('restock_manual_value')}</option>
                              <option value="scrap_no_restock">{getDispositionLabel('scrap_no_restock')}</option>
                            </select>
                          </div>

                          {item.disposition === 'restock_manual_value' ? (
                            <div className="w-full sm:w-32">
                              <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesSalesReturns.restockValue}</label>
                              <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={item.manual_value}
                                onChange={(e) => updateLineItem(idx, 'manual_value', parseFloat(e.target.value) || 0)}
                                required={Number(item.quantity) > 0}
                                className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                              />
                            </div>
                          ) : null}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.reason}</label>
                <textarea
                  rows={2}
                  value={data.reason}
                  onChange={(e) => setData('reason', e.target.value)}
                  maxLength={255}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.notes}</label>
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
                  {dict.app.pages.salesSalesReturns.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing ? dict.app.pages.salesSalesReturns.saving : dict.app.pages.salesSalesReturns.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
