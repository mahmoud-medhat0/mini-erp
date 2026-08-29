import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

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
  warehouse_id?: string | null;
  salesOrder?: {
    customer_id: string;
    customer?: { id: string; name: string } | null;
  } | null;
  lines: DeliveryNoteLineOption[];
};

type WarehouseOption = {
  id: string;
  code: string;
  name: { en?: string; ar?: string } | string;
  is_default?: boolean;
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
  warehouse_id: string;
  customer_invoice_id?: string | null;
  customer?: { id: string; name: string } | null;
  deliveryNote?: { id: string; number?: string | null } | null;
  warehouse?: WarehouseOption | null;
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
    links: PaginationLink[];
  };
  activeCustomers: CustomerOption[];
  confirmedDeliveryNotes: DeliveryNoteOption[];
  postedCustomerInvoices: PostedInvoiceOption[];
  warehouses: WarehouseOption[];
  taxCodes?: any[];
  filters: {
    search?: string;
    status?: string;
    customer_id?: string;
    warehouse_id?: string;
  };
};

const formatQuantity = (qtyE6: number) => String(parseFloat(((qtyE6 || 0) / 1000000).toFixed(6)));

function toDisposition(value: string): Disposition {
  if (value === 'restock_manual_value' || value === 'scrap_no_restock') {
    return value;
  }

  return 'restock_original_cost';
}

export default function SalesReturnsIndex({
  locale,
  salesReturns,
  activeCustomers,
  confirmedDeliveryNotes,
  postedCustomerInvoices,
  warehouses,
  filters,
}: SalesReturnsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.salesSalesReturns;
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
    warehouse_id: warehouses[0]?.id || '',
    customer_invoice_id: '',
    return_date: todayStr,
    reason: '',
    notes: '',
    lock_version: 1,
  });
  const formErrors = errors as Record<string, string | undefined>;

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

  const warehouseOptions = useMemo(() => warehouses.map((warehouse) => ({
    value: warehouse.id,
    label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
    badge: warehouse.is_default ? pageDict.defaultWarehouse : undefined,
  })), [warehouses, locale, pageDict.defaultWarehouse]);

  const warehouseFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allWarehouses },
    ...warehouseOptions,
  ], [warehouseOptions, pageDict.allWarehouses]);

  const statusFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'draft', label: pageDict.draft },
    { value: 'submitted', label: pageDict.submitted },
    { value: 'approved', label: pageDict.approved },
    { value: 'posted', label: pageDict.posted },
    { value: 'cancelled', label: pageDict.cancelled },
  ], [pageDict.allStatuses, pageDict.draft, pageDict.submitted, pageDict.approved, pageDict.posted, pageDict.cancelled]);

  const customerOptions = useMemo(() => activeCustomers.map((customer) => ({
    value: customer.id,
    label: customer.name,
    sublabel: customer.code,
  })), [activeCustomers]);

  const deliveryNoteOptions = useMemo(() => customerDeliveryNotes.map((deliveryNote) => ({
    value: deliveryNote.id,
    label: deliveryNote.number || pageDict.draft_2,
    sublabel: deliveryNote.salesOrder?.customer?.name || accDict.notAvailable,
  })), [customerDeliveryNotes, pageDict.draft_2, accDict.notAvailable]);

  const postedInvoiceOptions = useMemo(() => customerInvoices.map((invoice) => ({
    value: invoice.id,
    label: invoice.number || pageDict.draft_2,
    sublabel: invoice.currency,
  })), [customerInvoices, pageDict.draft_2]);

  const deliveryNoteLineOptions = useMemo(() => (selectedDn?.lines || []).map((line) => ({
    value: line.id,
    label: getProductName(line.product),
    sublabel: formatQuantity(line.quantity_e6),
  })), [selectedDn, locale]);

  const dispositionOptions = useMemo(() => [
    { value: 'restock_original_cost', label: pageDict.restockOriginalCost },
    { value: 'restock_manual_value', label: pageDict.restockManualValue },
    { value: 'scrap_no_restock', label: pageDict.scrapNoRestock },
  ], [pageDict.restockOriginalCost, pageDict.restockManualValue, pageDict.scrapNoRestock]);
  const canManageSalesReturns = can('sales.returns');
  const canPostSalesReturns = canManageSalesReturns && can('view_financials');
  const salesReturnLoadLinesLabel = fetchingLines ? pageDict.loading : pageDict.loadLines;
  const salesReturnSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;

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
    setData('warehouse_id', dn?.warehouse_id || warehouses[0]?.id || '');
    if (dn && sourceMode === 'delivery_note') {
      setLineItems(
        dn.lines.map((l) => ({
          delivery_note_line_id: l.id,
          product_id: l.product_id,
          description: l.description || getProductName(l.product),
          uom_name: l.unitOfMeasure?.name || accDict.notAvailable,
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
          uom_name: accDict.notAvailable,
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

  const updateLineItem = <K extends keyof ReturnLineForm>(index: number, field: K, value: ReturnLineForm[K]) => {
    setLineItems((prev) => {
      const next = [...prev];
      const item = { ...next[index], [field]: value };
      if (field === 'delivery_note_line_id' && selectedDn) {
        const dnLine = selectedDn.lines.find((l) => l.id === String(value ?? ''));
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
      warehouse_id: ret.warehouse_id || warehouses[0]?.id || '',
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
        uom_name: l.unitOfMeasure?.name || accDict.notAvailable,
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
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/returns', payload, {
        preserveScroll: true,
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
      const payload = action === 'post' ? { confirm_action: 'POST_SALES_RETURN' } : {};
      router.post(`/sales/returns/${retId}/${action}`, payload, { preserveScroll: true });
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

  const isSalesReturnActionable = (ret: SalesReturnRow) => ['draft', 'submitted', 'approved'].includes(ret.status);

  const hasAvailableSalesReturnAction = (ret: SalesReturnRow) => (
    ret.status === 'draft'
      ? canManageSalesReturns
      : ret.status === 'submitted'
        ? canManageSalesReturns
        : ret.status === 'approved'
          ? canManageSalesReturns || canPostSalesReturns
          : false
  );

  const getSalesReturnActionState = (ret: SalesReturnRow) => {
    if (hasAvailableSalesReturnAction(ret)) return null;

    return isSalesReturnActionable(ret) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="sales-returns.index">
      <Head title={dict.app.pages.salesSalesReturns.salesReturns} />

      <PageHeader
        title={dict.app.pages.salesSalesReturns.salesReturns_2}
        description={dict.app.pages.salesSalesReturns.manageCustomerSalesReturnsAndRestock}
        actions={
          canManageSalesReturns ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={dict.app.pages.salesSalesReturns.createSalesReturn}
              aria-label={dict.app.pages.salesSalesReturns.createSalesReturn}
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
                  router.get('/sales/returns', { ...filters, search: val }, { preserveState: true, preserveScroll: true });
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
              options={warehouseFilterOptions}
              value={filters.warehouse_id || null}
              onChange={(value) => router.get('/sales/returns', { ...filters, warehouse_id: value || '' }, { preserveState: true, preserveScroll: true })}
              label={dict.app.pages.salesSalesReturns.warehouse}
            />

            <SearchableSelect
              options={statusFilterOptions}
              value={filters.status || null}
              onChange={(value) => router.get('/sales/returns', { ...filters, status: value || '' }, { preserveState: true, preserveScroll: true })}
              label={dict.app.pages.salesSalesReturns.status}
            />
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
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.warehouse}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.returnDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesSalesReturns.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesSalesReturns.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {salesReturns.data.map((ret) => {
                  const actionState = getSalesReturnActionState(ret);

                  return (
                    <tr key={ret.id}>
                      <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                        {ret.number || dict.app.pages.salesSalesReturns.draft_2}
                      </td>
                      <td className={`${tableClasses.td} font-medium`}>{ret.customer?.name || accDict.notAvailable}</td>
                      <td className={`${tableClasses.td} font-mono`}>{ret.deliveryNote?.number || accDict.notAvailable}</td>
                      <td className={`${tableClasses.td} font-mono`}>{ret.customerInvoice?.number || accDict.notAvailable}</td>
                      <td className={tableClasses.td}>{ret.warehouse ? `${ret.warehouse.code} - ${getLocalizedName(ret.warehouse.name, locale)}` : accDict.notAvailable}</td>
                      <td className={tableClasses.td}>{ret.return_date}</td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={getStatusTone(ret.status)}>
                          {getStatusLabel(ret.status)}
                        </StatusBadge>
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          {ret.status === 'draft' && canManageSalesReturns ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(ret)}
                              title={dict.app.pages.salesSalesReturns.edit}
                              aria-label={dict.app.pages.salesSalesReturns.edit}
                              className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                            >
                              {dict.app.pages.salesSalesReturns.edit}
                            </button>
                          ) : null}

                          {ret.status === 'draft' && canManageSalesReturns ? (
                            <button
                              type="button"
                              onClick={() => handleAction(ret.id, 'submit')}
                              title={dict.app.pages.salesSalesReturns.submit}
                              aria-label={dict.app.pages.salesSalesReturns.submit}
                              className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40"
                            >
                              {dict.app.pages.salesSalesReturns.submit}
                            </button>
                          ) : null}

                          {['draft', 'submitted'].includes(ret.status) && canManageSalesReturns ? (
                            <button
                              type="button"
                              onClick={() => handleAction(ret.id, 'approve')}
                              title={dict.app.pages.salesSalesReturns.approve}
                              aria-label={dict.app.pages.salesSalesReturns.approve}
                              className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                            >
                              {dict.app.pages.salesSalesReturns.approve}
                            </button>
                          ) : null}

                          {ret.status === 'approved' && canPostSalesReturns ? (
                            <button
                              type="button"
                              onClick={() => handleAction(ret.id, 'post')}
                              title={dict.app.pages.salesSalesReturns.post}
                              aria-label={dict.app.pages.salesSalesReturns.post}
                              className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                            >
                              {dict.app.pages.salesSalesReturns.post}
                            </button>
                          ) : null}

                          {isSalesReturnActionable(ret) && canManageSalesReturns ? (
                            <button
                              type="button"
                              onClick={() => handleAction(ret.id, 'cancel')}
                              title={dict.app.pages.salesSalesReturns.cancel}
                              aria-label={dict.app.pages.salesSalesReturns.cancel}
                              className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                            >
                              {dict.app.pages.salesSalesReturns.cancel}
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
                    title={dict.app.pages.salesSalesReturns.fromDeliveryNote}
                    aria-label={dict.app.pages.salesSalesReturns.fromDeliveryNote}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'delivery_note' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesSalesReturns.fromDeliveryNote}
                  </button>
                  <button
                    type="button"
                    onClick={() => handleSourceModeChange('invoice')}
                    title={dict.app.pages.salesSalesReturns.createFromInvoice}
                    aria-label={dict.app.pages.salesSalesReturns.createFromInvoice}
                    className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all ${
                      sourceMode === 'invoice' ? 'bg-blue-600 text-white shadow-xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
                    }`}
                  >
                    {dict.app.pages.salesSalesReturns.createFromInvoice}
                  </button>
                </div>
              ) : null}

              <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <SearchableSelect
                  label={dict.app.pages.salesSalesReturns.customer_2}
                  disabled={Boolean(editingReturn)}
                  value={data.customer_id || null}
                  onChange={(value) => handleCustomerSelect(value || '')}
                  options={customerOptions}
                  placeholder={dict.app.pages.salesSalesReturns.selectCustomer}
                  isClearable={false}
                  required
                  error={errors.customer_id}
                />

                <SearchableSelect
                  label={dict.app.pages.salesSalesReturns.warehouse}
                  value={data.warehouse_id || null}
                  onChange={(value) => setData('warehouse_id', value || '')}
                  options={warehouseOptions}
                  placeholder={dict.app.pages.salesSalesReturns.selectWarehouse}
                  isClearable={false}
                  required
                  error={errors.warehouse_id}
                />

                <SearchableSelect
                  label={dict.app.pages.salesSalesReturns.confirmedDeliveryNote}
                  disabled={Boolean(editingReturn)}
                  value={data.delivery_note_id || null}
                  onChange={(value) => {
                    handleDeliveryNoteSelect(value || '');
                    if (sourceMode === 'invoice') setLineItems([]);
                  }}
                  options={deliveryNoteOptions}
                  placeholder={dict.app.pages.salesSalesReturns.selectDeliveryNote}
                  isClearable={false}
                  required
                  error={errors.delivery_note_id}
                />

                <DatePicker
                  label={dict.app.pages.salesSalesReturns.returnDate_2}
                  value={data.return_date}
                  onChange={(value) => setData('return_date', value || '')}
                  required
                  error={errors.return_date}
                />
              </div>

              {sourceMode === 'invoice' && !editingReturn ? (
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesSalesReturns.postedInvoice}</label>
                  <div className="flex items-center gap-2">
                    <SearchableSelect
                      value={data.customer_invoice_id || null}
                      onChange={(value) => setData('customer_invoice_id', value || '')}
                      disabled={!data.customer_id}
                      options={postedInvoiceOptions}
                      placeholder={dict.app.pages.salesSalesReturns.selectPostedInvoice}
                      isClearable={false}
                    />
                    <button
                      type="button"
                      onClick={fetchReturnableLines}
                      disabled={!data.customer_invoice_id || !data.delivery_note_id || fetchingLines}
                      title={salesReturnLoadLinesLabel}
                      aria-label={salesReturnLoadLinesLabel}
                      className="shrink-0 rounded-xl border border-[var(--border)] px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-[var(--background)] disabled:opacity-50"
                    >
                      {salesReturnLoadLinesLabel}
                    </button>
                  </div>
                </div>
              ) : null}

              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{dict.app.pages.salesSalesReturns.returnLines}</h4>
                </div>
                {formErrors.lines ? (
                  <p className="text-xs text-red-500 mb-2 font-medium">{formErrors.lines}</p>
                ) : null}

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
                              value={item.description}
                              className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-primary)] font-medium"
                            />
                          </div>

                          {sourceMode === 'invoice' ? (
                            <div className="w-full sm:w-56">
                              <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesSalesReturns.dnLine} *</label>
                              <SearchableSelect
                                value={item.delivery_note_line_id || null}
                                onChange={(value) => updateLineItem(idx, 'delivery_note_line_id', value || '')}
                                options={deliveryNoteLineOptions}
                                placeholder={dict.app.pages.salesSalesReturns.selectDnLine}
                                isClearable={false}
                                required={Number(item.quantity) > 0}
                              />
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
                            <SearchableSelect
                              label={dict.app.pages.salesSalesReturns.disposition}
                              value={item.disposition}
                              onChange={(value) => updateLineItem(idx, 'disposition', toDisposition(value || ''))}
                              options={dispositionOptions}
                              isClearable={false}
                              required
                            />
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
                {errors.reason ? (
                  <p className="text-xs text-red-500 mt-1 font-medium">{errors.reason}</p>
                ) : null}
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
                  title={dict.app.pages.salesSalesReturns.cancel_2}
                  aria-label={dict.app.pages.salesSalesReturns.cancel_2}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.salesSalesReturns.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={salesReturnSubmitLabel}
                  aria-label={salesReturnSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {salesReturnSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
